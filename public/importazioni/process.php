<?php
/**
 * Fase 4 — Import XLSX Inter Club
 * Step 3: processa le righe e mostra il log risultato
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/xlsx_reader.php';

start_secure_session();
require_login();

if (empty($_SESSION['import_file']) || !file_exists($_SESSION['import_file'])
    || empty($_SESSION['import_mapping'])) {
    header('Location: /importazioni/upload.php');
    exit;
}

$file        = $_SESSION['import_file'];
$ext         = $_SESSION['import_ext'];
$filename    = $_SESSION['import_filename'] ?? basename($file);
$id_stagione = (int)$_SESSION['import_stagione'];
$mapping     = $_SESSION['import_mapping']; // [col_index => campo_db]

$pdo = get_db_connection();

// Crea record importazione
$stmt = $pdo->prepare(
    'INSERT INTO importazioni (id_stagione, nome_file, data_import) VALUES (?, ?, NOW())'
);
$stmt->execute([$id_stagione, $filename]);
$id_import = (int)$pdo->lastInsertId();

// Leggi tutte le righe dati (salta header)
$reader = new XlsxReader($file, $ext);
$rows   = $reader->getAllRows(); // salta riga 0 (intestazioni)

$log = []; // ['riga' => n, 'esito' => 'inserito|aggiornato|duplicato|errore', 'messaggio' => '...']

foreach ($rows as $r_idx => $row) {
    $riga_num = $r_idx + 2; // +2 perché row 1 = header
    $dati = [];
    foreach ($mapping as $col => $campo) {
        $dati[$campo] = trim($row[$col] ?? '');
    }

    // Normalizzazioni base
    if (isset($dati['codice_fiscale'])) {
        $dati['codice_fiscale'] = strtoupper($dati['codice_fiscale']);
    }
    if (isset($dati['sesso'])) {
        $dati['sesso'] = strtoupper(substr($dati['sesso'], 0, 1));
        if (!in_array($dati['sesso'], ['M', 'F'])) {
            $dati['sesso'] = null;
        }
    }
    // Normalizza date (accetta d/m/Y o Y-m-d)
    foreach (['data_nascita', 'data_iscrizione'] as $df) {
        if (!empty($dati[$df])) {
            $d = date_create_from_format('d/m/Y', $dati[$df])
              ?: date_create_from_format('Y-m-d', $dati[$df])
              ?: date_create_from_format('d-m-Y', $dati[$df]);
            $dati[$df] = $d ? $d->format('Y-m-d') : null;
        } else {
            $dati[$df] = null;
        }
    }
    foreach (['attivo_portale', 'conferma_anagrafica'] as $bf) {
        if (isset($dati[$bf])) {
            $dati[$bf] = in_array(strtolower($dati[$bf]), ['1', 'si', 'sì', 'yes', 'true']) ? 1 : 0;
        }
    }

    // Salta riga completamente vuota
    $valori_non_vuoti = array_filter($dati, fn($v) => $v !== '' && $v !== null);
    if (empty($valori_non_vuoti)) {
        continue;
    }

    // -----------------------------------------------------------------------
    // MATCHING gerarchico socio
    // 1. codice_fiscale  2. numero_tessera (via tesseramenti)  3. cognome+nome
    // -----------------------------------------------------------------------
    $id_socio = null;
    $metodo_match = '';

    if (!empty($dati['codice_fiscale'])) {
        $s = $pdo->prepare('SELECT id_socio FROM soci WHERE codice_fiscale = ? AND attivo_record = 1 LIMIT 1');
        $s->execute([$dati['codice_fiscale']]);
        $id_socio = $s->fetchColumn() ?: null;
        if ($id_socio) $metodo_match = 'CF';
    }

    if (!$id_socio && !empty($dati['numero_tessera'])) {
        $s = $pdo->prepare(
            'SELECT t.id_socio FROM tesseramenti t
             WHERE t.numero_tessera = ? AND t.id_stagione = ? LIMIT 1'
        );
        $s->execute([$dati['numero_tessera'], $id_stagione]);
        $id_socio = $s->fetchColumn() ?: null;
        if ($id_socio) $metodo_match = 'tessera';
    }

    if (!$id_socio && !empty($dati['cognome']) && !empty($dati['nome'])) {
        $s = $pdo->prepare(
            'SELECT id_socio FROM soci
             WHERE LOWER(TRIM(cognome)) = LOWER(TRIM(?))
               AND LOWER(TRIM(nome))    = LOWER(TRIM(?))
               AND attivo_record = 1
             LIMIT 2'
        );
        $s->execute([$dati['cognome'], $dati['nome']]);
        $matches = $s->fetchAll();
        if (count($matches) === 1) {
            $id_socio = $matches[0]['id_socio'];
            $metodo_match = 'nome+cognome';
        } elseif (count($matches) > 1) {
            // Ambiguo
            $log[] = [
                'riga'      => $riga_num,
                'esito'     => 'duplicato',
                'messaggio' => 'Più soci trovati con lo stesso cognome+nome. Aggiungere CF o numero tessera.',
                'dati'      => implode(' ', array_filter([$dati['cognome'] ?? '', $dati['nome'] ?? ''])),
            ];
            insert_log_riga($pdo, $id_import, $riga_num, 'duplicato', 'Match ambiguo: cognome+nome non univoco', $dati);
            continue;
        }
    }

    try {
        $pdo->beginTransaction();

        // Campi anagrafica
        $campi_soci = ['cognome','nome','codice_fiscale','data_nascita','luogo_nascita',
                       'paese','sesso','email','telefono','indirizzo','cap','citta','provincia'];
        $dati_soci = array_intersect_key($dati, array_flip($campi_soci));
        $dati_soci = array_filter($dati_soci, fn($v) => $v !== '' && $v !== null);

        // Campi tesseramento
        $campi_tess = ['numero_tessera','tipologia_codice','data_iscrizione',
                       'attivo_portale','conferma_anagrafica','note_tesseramento'];
        $dati_tess = array_intersect_key($dati, array_flip($campi_tess));

        if ($id_socio) {
            // UPDATE anagrafica (solo campi non vuoti)
            if (!empty($dati_soci)) {
                $set = implode(', ', array_map(fn($c) => "`$c` = :$c", array_keys($dati_soci)));
                $upd = $pdo->prepare("UPDATE soci SET $set WHERE id_socio = :id_socio");
                $upd->execute(array_merge($dati_soci, ['id_socio' => $id_socio]));
            }
            $esito = 'aggiornato';
        } else {
            // INSERT nuovo socio (richiede almeno cognome+nome)
            if (empty($dati_soci['cognome']) || empty($dati_soci['nome'])) {
                $pdo->rollBack();
                $log[] = [
                    'riga'      => $riga_num,
                    'esito'     => 'errore',
                    'messaggio' => 'Nuovo socio senza match: cognome e nome obbligatori.',
                    'dati'      => '',
                ];
                insert_log_riga($pdo, $id_import, $riga_num, 'errore', 'Cognome/nome mancanti per inserimento', $dati);
                continue;
            }
            $dati_soci['attivo_record'] = 1;
            $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($dati_soci)));
            $phs  = implode(', ', array_map(fn($c) => ":$c", array_keys($dati_soci)));
            $ins = $pdo->prepare("INSERT INTO soci ($cols) VALUES ($phs)");
            $ins->execute($dati_soci);
            $id_socio = (int)$pdo->lastInsertId();
            $esito = 'inserito';
        }

        // Tesseramento: upsert
        if (!empty($dati_tess) && $id_socio) {
            // Cerca tesseramento esistente per questo socio + stagione
            $t_ex = $pdo->prepare(
                'SELECT id_tesseramento FROM tesseramenti WHERE id_socio = ? AND id_stagione = ? LIMIT 1'
            );
            $t_ex->execute([$id_socio, $id_stagione]);
            $id_tess = $t_ex->fetchColumn();

            // Risolvi tipologia codice → id_tipologia
            $id_tipologia = null;
            if (!empty($dati_tess['tipologia_codice'])) {
                $tp = $pdo->prepare(
                    'SELECT id_tipologia FROM tipologie_tesseramento WHERE UPPER(TRIM(codice)) = UPPER(TRIM(?)) LIMIT 1'
                );
                $tp->execute([$dati_tess['tipologia_codice']]);
                $id_tipologia = $tp->fetchColumn() ?: null;
            }
            unset($dati_tess['tipologia_codice']);
            if ($id_tipologia) $dati_tess['id_tipologia'] = $id_tipologia;

            if ($id_tess) {
                $dati_tess_f = array_filter($dati_tess, fn($v) => $v !== '' && $v !== null);
                if (!empty($dati_tess_f)) {
                    $set = implode(', ', array_map(fn($c) => "`$c` = :$c", array_keys($dati_tess_f)));
                    $upd = $pdo->prepare("UPDATE tesseramenti SET $set WHERE id_tesseramento = :id");
                    $upd->execute(array_merge($dati_tess_f, ['id' => $id_tess]));
                }
            } else {
                $dati_tess['id_socio']    = $id_socio;
                $dati_tess['id_stagione'] = $id_stagione;
                $dati_tess_f = array_filter($dati_tess, fn($v) => $v !== '' && $v !== null);
                $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($dati_tess_f)));
                $phs  = implode(', ', array_map(fn($c) => ":$c", array_keys($dati_tess_f)));
                $ins = $pdo->prepare("INSERT INTO tesseramenti ($cols) VALUES ($phs)");
                $ins->execute($dati_tess_f);
            }
        }

        $pdo->commit();

        $nome_display = trim(($dati['cognome'] ?? '') . ' ' . ($dati['nome'] ?? ''));
        $log[] = [
            'riga'      => $riga_num,
            'esito'     => $esito,
            'messaggio' => ($esito === 'inserito' ? 'Nuovo socio inserito' : "Aggiornato (match: $metodo_match)"),
            'dati'      => $nome_display,
        ];
        insert_log_riga($pdo, $id_import, $riga_num, $esito,
            ($esito === 'inserito' ? 'Nuovo socio' : "Aggiornato via $metodo_match"), $dati);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $log[] = [
            'riga'      => $riga_num,
            'esito'     => 'errore',
            'messaggio' => 'Eccezione: ' . $e->getMessage(),
            'dati'      => '',
        ];
        insert_log_riga($pdo, $id_import, $riga_num, 'errore', $e->getMessage(), $dati);
    }
}

// Pulizia sessione import
unset($_SESSION['import_file'], $_SESSION['import_ext'],
      $_SESSION['import_mapping'], $_SESSION['import_filename'],
      $_SESSION['import_stagione']);
// Rimuovi file temporaneo
if (file_exists($file)) @unlink($file);

// Contatori
$cnt = ['inserito' => 0, 'aggiornato' => 0, 'duplicato' => 0, 'errore' => 0];
foreach ($log as $l) { $cnt[$l['esito']] = ($cnt[$l['esito']] ?? 0) + 1; }

function insert_log_riga(PDO $pdo, int $id_import, int $riga, string $esito, string $msg, array $dati): void {
    $stmt = $pdo->prepare(
        'INSERT INTO importazioni_righe (id_importazione, numero_riga, esito, messaggio, dati_json)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$id_import, $riga, $esito, $msg, json_encode($dati, JSON_UNESCAPED_UNICODE)]);
}

$page_title = 'Importazione — Risultati';
require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Importazione Inter Club <small style="font-size:.6em;font-weight:400">Step 3 di 3 — Risultati</small></h1>

<div class="cards-grid" style="margin-bottom:1.5rem">
    <div class="card">
        <span class="card-value"><?= $cnt['inserito'] ?></span>
        <span class="card-label">Nuovi soci inseriti</span>
    </div>
    <div class="card">
        <span class="card-value"><?= $cnt['aggiornato'] ?></span>
        <span class="card-label">Anagrafiche aggiornate</span>
    </div>
    <div class="card card-warning">
        <span class="card-value"><?= $cnt['duplicato'] ?></span>
        <span class="card-label">Ambigui / duplicati</span>
    </div>
    <div class="card card-warning">
        <span class="card-value"><?= $cnt['errore'] ?></span>
        <span class="card-label">Errori</span>
    </div>
</div>

<?php if (!empty($log)): ?>
<div style="overflow-x:auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Riga</th>
                <th>Esito</th>
                <th>Socio</th>
                <th>Dettaglio</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($log as $l):
                $cls = match($l['esito']) {
                    'inserito'   => 'color:var(--color-success)',
                    'aggiornato' => '',
                    'duplicato'  => 'color:var(--color-warning)',
                    'errore'     => 'color:var(--color-error)',
                    default      => '',
                };
            ?>
                <tr>
                    <td><?= (int)$l['riga'] ?></td>
                    <td style="<?= $cls ?>;font-weight:600"><?= h(ucfirst($l['esito'])) ?></td>
                    <td><?= h($l['dati']) ?></td>
                    <td><?= h($l['messaggio']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="form-actions" style="margin-top:1.5rem">
    <a href="/importazioni/upload.php" class="btn">Nuova importazione</a>
    <a href="/soci/list.php" class="btn btn-secondary">Vai all'anagrafica soci</a>
    <a href="/dashboard.php" class="btn btn-secondary">Dashboard</a>
</div>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
