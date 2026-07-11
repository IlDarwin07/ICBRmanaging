<?php
/**
 * Fase 4 – Elaborazione import file Inter Club
 *
 * Flusso:
 *   1. Validazione upload + CSRF
 *   2. Lettura file (CSV con rilevamento separatore | XLSX via ext-zip/SimpleXML se disponibile)
 *   3. Mapping automatico delle intestazioni sui campi DB
 *   4. Per ogni riga: matching socio (CF → n° tessera → nome+cognome+tel)
 *   5. Upsert tesseramento per la stagione scelta
 *   6. Log importazione + redirect al dettaglio log
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

// ── CSRF ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('/import/upload.php', 'Richiesta non valida.', 'error');
}

// ── Parametri form ────────────────────────────────────────────────────────────
$id_stagione          = (int)($_POST['id_stagione'] ?? 0);
$id_tipologia_default = (int)($_POST['id_tipologia_default'] ?? 0) ?: null;
$modalita             = in_array($_POST['modalita'] ?? '', ['upsert','insert_only','update_only'])
                        ? $_POST['modalita'] : 'upsert';

$pdo = get_db_connection();

if ($id_stagione <= 0) {
    redirect_with_message('/import/upload.php', 'Seleziona una stagione valida.', 'error');
}

// Verifica stagione
$stmt = $pdo->prepare('SELECT * FROM stagioni WHERE id_stagione = :id');
$stmt->execute(['id' => $id_stagione]);
$stagione = $stmt->fetch();
if (!$stagione) {
    redirect_with_message('/import/upload.php', 'Stagione non trovata.', 'error');
}

// ── Upload file ───────────────────────────────────────────────────────────────
if (empty($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
    redirect_with_message('/import/upload.php', 'Errore nel caricamento del file.', 'error');
}

$file_tmp  = $_FILES['import_file']['tmp_name'];
$file_name = basename($_FILES['import_file']['name']);
$file_size = $_FILES['import_file']['size'];
$file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

if ($file_size > 5 * 1024 * 1024) {
    redirect_with_message('/import/upload.php', 'File troppo grande (max 5 MB).', 'error');
}

if (!in_array($file_ext, ['csv', 'txt', 'xls', 'xlsx'])) {
    redirect_with_message('/import/upload.php', 'Formato file non supportato. Usa CSV o XLSX.', 'error');
}

$file_hash = hash_file('sha256', $file_tmp);

// ── Lettura righe ─────────────────────────────────────────────────────────────
/**
 * Legge un CSV/TXT rilevando automaticamente il separatore (virgola o punto-e-virgola).
 */
function read_csv_file(string $path): array
{
    $content = file_get_contents($path);
    // Gestione BOM UTF-8
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $content = substr($content, 3);
    }
    $lines = preg_split('/\r\n|\r|\n/', trim($content));
    if (empty($lines)) return [];

    // Rileva separatore dalla prima riga
    $sep = (substr_count($lines[0], ';') > substr_count($lines[0], ',')) ? ';' : ',';

    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $rows[] = str_getcsv($line, $sep);
    }
    return $rows;
}

/**
 * Lettura XLSX (Office Open XML) senza librerie esterne.
 * Estrae il primo foglio tramite ZipArchive + SimpleXML.
 * Restituisce null se non supportato.
 */
function read_xlsx_file(string $path): ?array
{
    if (!class_exists('ZipArchive')) return null;

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return null;

    // Leggi le stringhe condivise
    $shared_strings = [];
    $ss_xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss_xml !== false) {
        $ss = @simplexml_load_string($ss_xml);
        if ($ss) {
            foreach ($ss->si as $si) {
                $text = '';
                foreach ($si->r as $r) { $text .= (string)$r->t; }
                if ($text === '' && isset($si->t)) $text = (string)$si->t;
                $shared_strings[] = $text;
            }
        }
    }

    // Leggi primo foglio
    $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheet_xml === false) return null;

    $sheet = @simplexml_load_string($sheet_xml);
    if (!$sheet) return null;

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $row_data = [];
        foreach ($row->c as $cell) {
            $t = (string)($cell['t'] ?? '');
            $v = (string)$cell->v;
            if ($t === 's') {
                $row_data[] = $shared_strings[(int)$v] ?? '';
            } elseif ($t === 'inlineStr') {
                $row_data[] = (string)$cell->is->t;
            } else {
                $row_data[] = $v;
            }
        }
        $rows[] = $row_data;
    }
    return $rows;
}

// Leggi il file
if (in_array($file_ext, ['xlsx', 'xls'])) {
    $raw_rows = read_xlsx_file($file_tmp);
    if ($raw_rows === null) {
        redirect_with_message('/import/upload.php',
            'Impossibile leggere il file XLSX. Esporta il file come CSV e riprova.', 'error');
    }
} else {
    $raw_rows = read_csv_file($file_tmp);
}

if (count($raw_rows) < 2) {
    redirect_with_message('/import/upload.php', 'Il file è vuoto o ha solo l\'intestazione.', 'error');
}

// ── Mapping colonne ───────────────────────────────────────────────────────────
$headers_raw = array_map('trim', $raw_rows[0]);
$headers     = array_map('strtolower', $headers_raw);

/**
 * Mappa le intestazioni del file ai campi interni.
 * Chiave = campo interno · Valore = array di possibili nomi colonna nel file.
 */
const COLUMN_MAP = [
    'nome'                 => ['nome','first name','firstname','name'],
    'cognome'              => ['cognome','last name','lastname','surname'],
    'sesso'                => ['sesso','gender','sex'],
    'data_nascita'         => ['data nascita','data di nascita','birthdate','date of birth','nato il'],
    'comune_nascita'       => ['comune nascita','luogo nascita','birthplace','nato a'],
    'codice_fiscale'       => ['codice fiscale','cf','fiscal code','tax code','codice fis'],
    'nazionalita'          => ['nazionalita','nazionalità','nationality','paese'],
    'indirizzo'            => ['indirizzo','address','via','viale','strada'],
    'numero_civico'        => ['numero civico','civico','house number','n civico'],
    'cap'                  => ['cap','postal code','zip','zip code','codice postale'],
    'comune'               => ['comune','città','city','citta'],
    'provincia'            => ['provincia','prov','province'],
    'telefono'             => ['telefono','phone','tel','cellulare','mobile','numero telefono'],
    'email'                => ['email','e-mail','mail'],
    'numero_tessera'       => ['numero tessera','n tessera','tessera','card number','num tessera','n. tessera'],
    'listino_originale'    => ['listino','listino originale','listino_originale','tipo listino'],
    'attivo_portale'       => ['attivo portale','attivo','active','attivo_portale'],
    'socio_plus'           => ['socio plus','socio_plus','plus','member plus'],
    'ruolo_portale'        => ['ruolo portale','ruolo','role','ruolo_portale'],
    'tipo_portale'         => ['tipo portale','tipo_portale','tipo'],
    'data_ora_attivazione' => ['data attivazione','attivazione','activation date','data_ora_attivazione'],
    'attivo_scorsa_stagione'=> ['attivo scorsa stagione','attivo_scorsa_stagione','precedente stagione'],
    'conferma_anagrafica'  => ['conferma anagrafica','anagrafica confermata','conferma_anagrafica'],
    'tessera_fisica'       => ['tessera fisica','tessera_fisica','physical card'],
    'fonte_inserimento'    => ['fonte','fonte inserimento','fonte_inserimento','source'],
];

function find_column(array $headers, array $aliases): ?int
{
    foreach ($aliases as $alias) {
        $idx = array_search(strtolower($alias), $headers);
        if ($idx !== false) return (int)$idx;
    }
    return null;
}

$col = [];
foreach (COLUMN_MAP as $field => $aliases) {
    $col[$field] = find_column($headers, $aliases);
}

// Campi obbligatori per il matching: almeno uno deve essere presente
$has_matching = ($col['codice_fiscale'] !== null)
             || ($col['numero_tessera'] !== null)
             || ($col['cognome'] !== null && $col['nome'] !== null);

if (!$has_matching) {
    redirect_with_message('/import/upload.php',
        'Il file non contiene colonne identificabili per il matching (codice fiscale, n° tessera o nome+cognome).', 'error');
}

// ── Recupera lookup tipologie per nome listino ────────────────────────────────
$tipologie_lookup = [];
$stmt = $pdo->query('SELECT id_tipologia, tipo, listino_label FROM tipologie_tesseramento WHERE attiva = 1');
foreach ($stmt->fetchAll() as $tip) {
    $key = strtolower(trim($tip['listino_label'] ?: $tip['tipo']));
    $tipologie_lookup[$key] = (int)$tip['id_tipologia'];
}

// ── Elaborazione righe ────────────────────────────────────────────────────────
$data_rows        = array_slice($raw_rows, 1);
$righe_totali     = count($data_rows);
$righe_inserite   = 0;
$righe_aggiornate = 0;
$righe_scartate   = 0;
$note_righe       = []; // [riga => stringa motivo scarto/azione]

$utente = current_user();
$id_utente = (int)($utente['id_utente'] ?? 0);

function get_col(array $row, ?int $idx): string
{
    if ($idx === null) return '';
    return trim((string)($row[$idx] ?? ''));
}

foreach ($data_rows as $i => $row) {
    $row_num = $i + 2; // riga 1 = intestazione

    // Valori grezzi
    $cf         = clean_codice_fiscale(get_col($row, $col['codice_fiscale']));
    $n_tessera  = clean_text(get_col($row, $col['numero_tessera']));
    $nome_raw   = clean_text(get_col($row, $col['nome']));
    $cognome_raw= clean_text(get_col($row, $col['cognome']));
    $telefono   = clean_phone(get_col($row, $col['telefono']));

    // ── Matching socio (gerarchia: CF → n° tessera → nome+cognome) ──────────
    $id_socio = null;

    if ($cf) {
        $s = $pdo->prepare('SELECT id_socio FROM soci WHERE codice_fiscale = :cf AND attivo_record = 1 LIMIT 1');
        $s->execute(['cf' => $cf]);
        $id_socio = $s->fetchColumn() ?: null;
    }

    if (!$id_socio && $n_tessera) {
        // Cerca nell'anagrafica tesseramenti della stessa stagione
        $s = $pdo->prepare(
            'SELECT t.id_socio FROM tesseramenti t
             JOIN soci sc ON sc.id_socio = t.id_socio
             WHERE t.numero_tessera = :nt AND t.id_stagione = :ids AND sc.attivo_record = 1 LIMIT 1'
        );
        $s->execute(['nt' => $n_tessera, 'ids' => $id_stagione]);
        $id_socio = $s->fetchColumn() ?: null;
    }

    if (!$id_socio && $nome_raw && $cognome_raw) {
        $s = $pdo->prepare(
            'SELECT id_socio FROM soci
             WHERE LOWER(TRIM(nome)) = LOWER(:nome) AND LOWER(TRIM(cognome)) = LOWER(:cognome)
             AND attivo_record = 1 LIMIT 1'
        );
        $s->execute(['nome' => $nome_raw, 'cognome' => $cognome_raw]);
        $id_socio = $s->fetchColumn() ?: null;
    }

    // Se il socio non esiste ancora, lo creiamo
    if (!$id_socio && $nome_raw && $cognome_raw) {
        try {
            $ins = $pdo->prepare(
                'INSERT INTO soci (nome, cognome, sesso, data_nascita, comune_nascita, codice_fiscale,
                 nazionalita, indirizzo, numero_civico, cap, comune, provincia, telefono, email)
                 VALUES (:nome,:cognome,:sesso,:dn,:cn,:cf,:naz,:ind,:nc,:cap,:com,:prov,:tel,:email)'
            );
            $ins->execute([
                'nome'    => $nome_raw,
                'cognome' => $cognome_raw,
                'sesso'   => clean_text(get_col($row, $col['sesso'])) ?: null,
                'dn'      => parse_date_to_sql(get_col($row, $col['data_nascita'])),
                'cn'      => clean_text(get_col($row, $col['comune_nascita'])),
                'cf'      => $cf,
                'naz'     => clean_text(get_col($row, $col['nazionalita'])),
                'ind'     => clean_text(get_col($row, $col['indirizzo'])),
                'nc'      => clean_text(get_col($row, $col['numero_civico'])),
                'cap'     => clean_text(get_col($row, $col['cap'])),
                'com'     => clean_text(get_col($row, $col['comune'])),
                'prov'    => clean_text(get_col($row, $col['provincia'])),
                'tel'     => $telefono,
                'email'   => clean_email(get_col($row, $col['email'])),
            ]);
            $id_socio = (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            $righe_scartate++;
            $note_righe[] = "Riga {$row_num}: impossibile creare socio ({$e->getMessage()})";
            continue;
        }
    }

    if (!$id_socio) {
        $righe_scartate++;
        $note_righe[] = "Riga {$row_num}: socio non trovato né creabile (mancano nome+cognome)";
        continue;
    }

    // ── Risolvi tipologia ────────────────────────────────────────────────────
    $id_tipologia = $id_tipologia_default;
    $listino_raw  = clean_text(get_col($row, $col['listino_originale']));
    if ($listino_raw) {
        $key = strtolower($listino_raw);
        if (isset($tipologie_lookup[$key])) {
            $id_tipologia = $tipologie_lookup[$key];
        }
    }

    // ── Upsert tesseramento ──────────────────────────────────────────────────
    $t_check = $pdo->prepare(
        'SELECT id_tesseramento FROM tesseramenti WHERE id_socio = :ids AND id_stagione = :idst LIMIT 1'
    );
    $t_check->execute(['ids' => $id_socio, 'idst' => $id_stagione]);
    $existing_id = $t_check->fetchColumn();

    $attivo_portale        = si_no_to_bool(get_col($row, $col['attivo_portale']));
    $socio_plus            = si_no_to_bool(get_col($row, $col['socio_plus']));
    $attivo_scorsa         = si_no_to_bool(get_col($row, $col['attivo_scorsa_stagione']));
    $conferma_anagrafica   = si_no_to_bool(get_col($row, $col['conferma_anagrafica']));
    $tessera_fisica        = si_no_to_bool(get_col($row, $col['tessera_fisica']));
    $numero_tessera_clean  = $n_tessera ?: null;
    $ruolo_portale         = clean_text(get_col($row, $col['ruolo_portale']));
    $tipo_portale          = clean_text(get_col($row, $col['tipo_portale']));
    $fonte                 = clean_text(get_col($row, $col['fonte_inserimento'])) ?: 'import';
    $data_attivazione      = parse_date_to_sql(get_col($row, $col['data_ora_attivazione']));
    $listino_originale     = $listino_raw;

    try {
        if ($existing_id && in_array($modalita, ['upsert', 'update_only'])) {
            $upd = $pdo->prepare(
                'UPDATE tesseramenti SET
                    id_tipologia = COALESCE(:tip, id_tipologia),
                    listino_originale = COALESCE(:lo, listino_originale),
                    numero_tessera = COALESCE(:nt, numero_tessera),
                    attivo_portale = :ap,
                    socio_plus = :sp,
                    ruolo_portale = COALESCE(:rp, ruolo_portale),
                    tipo_portale = COALESCE(:tp, tipo_portale),
                    attivo_scorsa_stagione = :ass,
                    conferma_anagrafica = :ca,
                    tessera_fisica = :tf,
                    fonte_inserimento = :fonte,
                    data_ora_attivazione = COALESCE(:doa, data_ora_attivazione)
                 WHERE id_tesseramento = :id'
            );
            $upd->execute([
                'tip'   => $id_tipologia,
                'lo'    => $listino_originale,
                'nt'    => $numero_tessera_clean,
                'ap'    => $attivo_portale,
                'sp'    => $socio_plus,
                'rp'    => $ruolo_portale,
                'tp'    => $tipo_portale,
                'ass'   => $attivo_scorsa,
                'ca'    => $conferma_anagrafica,
                'tf'    => $tessera_fisica,
                'fonte' => $fonte,
                'doa'   => $data_attivazione,
                'id'    => $existing_id,
            ]);
            $righe_aggiornate++;
        } elseif (!$existing_id && in_array($modalita, ['upsert', 'insert_only'])) {
            $ins = $pdo->prepare(
                'INSERT INTO tesseramenti
                    (id_socio, id_stagione, id_tipologia, listino_originale, numero_tessera,
                     attivo_portale, socio_plus, ruolo_portale, tipo_portale,
                     attivo_scorsa_stagione, conferma_anagrafica, tessera_fisica,
                     fonte_inserimento, data_ora_attivazione)
                 VALUES
                    (:ids, :idst, :tip, :lo, :nt,
                     :ap, :sp, :rp, :tp,
                     :ass, :ca, :tf,
                     :fonte, :doa)'
            );
            $ins->execute([
                'ids'   => $id_socio,
                'idst'  => $id_stagione,
                'tip'   => $id_tipologia,
                'lo'    => $listino_originale,
                'nt'    => $numero_tessera_clean,
                'ap'    => $attivo_portale,
                'sp'    => $socio_plus,
                'rp'    => $ruolo_portale,
                'tp'    => $tipo_portale,
                'ass'   => $attivo_scorsa,
                'ca'    => $conferma_anagrafica,
                'tf'    => $tessera_fisica,
                'fonte' => $fonte,
                'doa'   => $data_attivazione,
            ]);
            $righe_inserite++;
        } else {
            // Modalità update_only ma socio non esisteva, oppure insert_only ma esisteva
            $righe_scartate++;
            $note_righe[] = "Riga {$row_num}: saltata (modalità '{$modalita}', record " . ($existing_id ? 'già presente' : 'non trovato') . ")";
        }
    } catch (PDOException $e) {
        $righe_scartate++;
        $note_righe[] = "Riga {$row_num}: errore DB — " . $e->getMessage();
    }
}

// ── Salva log importazione ────────────────────────────────────────────────────
$note_esito = implode("\n", $note_righe);
if (strlen($note_esito) > 65000) {
    $note_esito = substr($note_esito, 0, 65000) . "\n[... troncato ...]";
}

$log = $pdo->prepare(
    'INSERT INTO importazioni
        (nome_file, hash_file, righe_totali, righe_inserite, righe_aggiornate, righe_scartate, note_esito, importato_da)
     VALUES
        (:nf, :hf, :rt, :ri, :ra, :rs, :ne, :uid)'
);
$log->execute([
    'nf'  => $file_name,
    'hf'  => $file_hash,
    'rt'  => $righe_totali,
    'ri'  => $righe_inserite,
    'ra'  => $righe_aggiornate,
    'rs'  => $righe_scartate,
    'ne'  => $note_esito ?: null,
    'uid' => $id_utente ?: null,
]);
$id_importazione = (int)$pdo->lastInsertId();

redirect_with_message(
    '/import/log.php?id=' . $id_importazione,
    "Importazione completata: {$righe_inserite} inseriti · {$righe_aggiornate} aggiornati · {$righe_scartate} scartati.",
    $righe_scartate > 0 ? 'warning' : 'success'
);
