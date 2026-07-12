<?php
/**
 * Importazione Inter Club
 * Step 3: processa le righe e mostra il log risultato
 *
 * Tabelle usate (schema reale):
 *   importazioni        : id_importazione, nome_file, data_importazione, id_stagione, ...
 *   righe_importazione  : id_riga_import, id_importazione, numero_riga, payload_json, esito, messaggio_esito, id_socio, id_tesseramento
 *   soci                : campi anagrafici, comune_nascita (non luogo_nascita)
 *   tipologie_tesseramento: colonna 'tipo' (non 'codice')
 * ENUM esito: 'inserita','aggiornata','duplicato','errore'
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

// ── Crea record importazione ──────────────────────────────────────────────
// La tabella `importazioni` ha: id_importazione, nome_file, hash_file,
// data_importazione (DEFAULT NOW()), righe_totali, righe_inserite,
// righe_aggiornate, righe_scartate, note_esito, importato_da, id_stagione
// NOTA: id_stagione NON è nello schema base — usiamo solo nome_file e
// importato_da per ora; la stagione la teniamo in righe_importazione.
// Se esiste id_stagione nello schema aggiornato, aggiungerlo qui.
$stmt = $pdo->prepare(
    'INSERT INTO importazioni (nome_file, importato_da) VALUES (?, ?)'
);
$id_utente = $_SESSION['user_id'] ?? null;
$stmt->execute([$filename, $id_utente]);
$id_import = (int)$pdo->lastInsertId();

// ── Leggi righe dati ──────────────────────────────────────────────────────
$reader = new XlsxReader($file, $ext);
$rows   = $reader->getAllRows();

$log = [];
$cnt = ['inserita' => 0, 'aggiornata' => 0, 'duplicato' => 0, 'errore' => 0];

foreach ($rows as $r_idx => $row) {
    $riga_num = $r_idx + 2;
    $dati = [];
    foreach ($mapping as $col => $campo) {
        $dati[$campo] = trim($row[$col] ?? '');
    }

    // ── Normalizzazioni ───────────────────────────────────────────────────
    if (isset($dati['codice_fiscale'])) {
        $dati['codice_fiscale'] = strtoupper($dati['codice_fiscale']);
    }
    if (isset($dati['sesso'])) {
        $v = strtoupper(substr(trim($dati['sesso']), 0, 1));
        $dati['sesso'] = in_array($v, ['M', 'F']) ? $v : null;
    }
    // Date
    foreach (['data_nascita'] as $df) {
        if (!empty($dati[$df])) {
            $d = date_create_from_format('d/m/Y', $dati[$df])
              ?: date_create_from_format('Y-m-d', $dati[$df])
              ?: date_create_from_format('d-m-Y', $dati[$df]);
            $dati[$df] = $d ? $d->format('Y-m-d') : null;
        } else {
            $dati[$df] = null;
        }
    }
    // Date/ora attivazione
    if (!empty($dati['data_attivazione'])) {
        $d = date_create_from_format('d/m/Y H:i', $dati['data_attivazione'])
          ?: date_create_from_format('Y-m-d H:i:s', $dati['data_attivazione'])
          ?: date_create_from_format('d/m/Y', $dati['data_attivazione']);
        $dati['data_attivazione'] = $d ? $d->format('Y-m-d H:i:s') : null;
    } else {
        $dati['data_attivazione'] = null;
    }
    // Booleani
    foreach (['attivo_portale','conferma_anagrafica','tessera_fisica','socio_plus','attivo_scorsa'] as $bf) {
        if (isset($dati[$bf])) {
            $dati[$bf] = in_array(strtolower(trim($dati[$bf])), ['1','si','sì','yes','true','x']) ? 1 : 0;
        }
    }

    // Salta riga vuota
    $non_vuoti = array_filter($dati, fn($v) => $v !== '' && $v !== null);
    if (empty($non_vuoti)) continue;

    // ── Match socio (gerarchico) ──────────────────────────────────────────
    $id_socio     = null;
    $metodo_match = '';

    if (!empty($dati['codice_fiscale'])) {
        $s = $pdo->prepare('SELECT id_socio FROM soci WHERE codice_fiscale = ? AND attivo_record = 1 LIMIT 1');
        $s->execute([$dati['codice_fiscale']]);
        $id_socio = $s->fetchColumn() ?: null;
        if ($id_socio) $metodo_match = 'CF';
    }

    if (!$id_socio && !empty($dati['numero_tessera'])) {
        $s = $pdo->prepare(
            'SELECT t.id_socio FROM tesseramenti t WHERE t.numero_tessera = ? AND t.id_stagione = ? LIMIT 1'
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
            $id_socio     = $matches[0]['id_socio'];
            $metodo_match = 'nome+cognome';
        } elseif (count($matches) > 1) {
            $cnt['duplicato']++;
            $log[] = ['riga' => $riga_num, 'esito' => 'duplicato',
                'messaggio' => 'Più soci con stesso cognome+nome. Aggiungere CF o numero tessera.',
                'dati' => trim(($dati['cognome'] ?? '') . ' ' . ($dati['nome'] ?? ''))];
            _ins_riga($pdo, $id_import, $riga_num, 'duplicato', 'Match ambiguo: cognome+nome non univoco', $dati);
            continue;
        }
    }

    try {
        $pdo->beginTransaction();

        // ── Campi soci (colonne reali della tabella) ──────────────────────
        // NOTA: 'luogo_nascita' dal file → 'comune_nascita' nel DB
        //       'citta' dal file      → 'comune' nel DB
        //       'paese' dal file      → 'nazionalita' nel DB
        $map_soci = [
            'nome'           => 'nome',
            'cognome'        => 'cognome',
            'codice_fiscale' => 'codice_fiscale',
            'data_nascita'   => 'data_nascita',
            'luogo_nascita'  => 'comune_nascita',
            'paese'          => 'nazionalita',
            'sesso'          => 'sesso',
            'email'          => 'email',
            'telefono'       => 'telefono',
            'indirizzo'      => 'indirizzo',
            'numero_civico'  => 'numero_civico',
            'cap'            => 'cap',
            'citta'          => 'comune',
            'provincia'      => 'provincia',
        ];
        $dati_soci = [];
        foreach ($map_soci as $campo_import => $col_db) {
            if (isset($dati[$campo_import]) && $dati[$campo_import] !== '' && $dati[$campo_import] !== null) {
                $dati_soci[$col_db] = $dati[$campo_import];
            }
        }

        // ── Campi tesseramenti ────────────────────────────────────────────
        $dati_tess = [];
        $campi_tess_map = [
            'numero_tessera'      => 'numero_tessera',
            'tipologia_codice'    => '__tipologia__',
            'data_attivazione'    => 'data_ora_attivazione',
            'attivo_portale'      => 'attivo_portale',
            'conferma_anagrafica' => 'conferma_anagrafica',
            'tessera_fisica'      => 'tessera_fisica',
            'socio_plus'          => 'socio_plus',
            'attivo_scorsa'       => 'attivo_scorsa_stagione',
            'ruolo'               => 'ruolo_portale',
            'listino'             => 'listino_originale',
        ];
        foreach ($campi_tess_map as $campo_import => $col_db) {
            if (isset($dati[$campo_import]) && $dati[$campo_import] !== '' && $dati[$campo_import] !== null) {
                $dati_tess[$col_db] = $dati[$campo_import];
            }
        }

        // Risolvi tipologia: colonna 'tipo' in tipologie_tesseramento
        $id_tipologia = null;
        if (isset($dati_tess['__tipologia__'])) {
            $val_tipo = $dati_tess['__tipologia__'];
            unset($dati_tess['__tipologia__']);
            if ($val_tipo !== '') {
                $tp = $pdo->prepare(
                    'SELECT id_tipologia FROM tipologie_tesseramento WHERE UPPER(TRIM(tipo)) = UPPER(TRIM(?)) LIMIT 1'
                );
                $tp->execute([$val_tipo]);
                $id_tipologia = $tp->fetchColumn() ?: null;
                if ($id_tipologia) {
                    $dati_tess['id_tipologia'] = $id_tipologia;
                }
            }
        } else {
            unset($dati_tess['__tipologia__']);
        }

        if ($id_socio) {
            // UPDATE anagrafica
            if (!empty($dati_soci)) {
                $set = implode(', ', array_map(fn($c) => "`$c` = :$c", array_keys($dati_soci)));
                $upd = $pdo->prepare("UPDATE soci SET $set WHERE id_socio = :id_socio");
                $upd->execute(array_merge($dati_soci, ['id_socio' => $id_socio]));
            }
            $esito = 'aggiornata';
        } else {
            // INSERT nuovo socio
            if (empty($dati_soci['cognome']) || empty($dati_soci['nome'])) {
                $pdo->rollBack();
                $cnt['errore']++;
                $log[] = ['riga' => $riga_num, 'esito' => 'errore',
                    'messaggio' => 'Nuovo socio senza match: cognome e nome obbligatori.', 'dati' => ''];
                _ins_riga($pdo, $id_import, $riga_num, 'errore', 'Cognome/nome mancanti per inserimento', $dati);
                continue;
            }
            $dati_soci['attivo_record'] = 1;
            $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($dati_soci)));
            $phs  = implode(', ', array_map(fn($c) => ":$c", array_keys($dati_soci)));
            $ins  = $pdo->prepare("INSERT INTO soci ($cols) VALUES ($phs)");
            $ins->execute($dati_soci);
            $id_socio = (int)$pdo->lastInsertId();
            $esito = 'inserita';
        }

        // Tesseramento: upsert
        $id_tess = null;
        if ($id_socio) {
            $t_ex = $pdo->prepare(
                'SELECT id_tesseramento FROM tesseramenti WHERE id_socio = ? AND id_stagione = ? LIMIT 1'
            );
            $t_ex->execute([$id_socio, $id_stagione]);
            $id_tess = $t_ex->fetchColumn() ?: null;

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
                $dati_tess['id_importazione'] = $id_import;
                $dati_tess_f = array_filter($dati_tess, fn($v) => $v !== '' && $v !== null);
                $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($dati_tess_f)));
                $phs  = implode(', ', array_map(fn($c) => ":$c", array_keys($dati_tess_f)));
                $ins  = $pdo->prepare("INSERT INTO tesseramenti ($cols) VALUES ($phs)");
                $ins->execute($dati_tess_f);
                $id_tess = (int)$pdo->lastInsertId();
            }
        }

        $pdo->commit();
        $cnt[$esito]++;

        $nome_display = trim(($dati['cognome'] ?? '') . ' ' . ($dati['nome'] ?? ''));
        $log[] = [
            'riga'      => $riga_num,
            'esito'     => $esito,
            'messaggio' => ($esito === 'inserita' ? 'Nuovo socio inserito' : "Aggiornato (match: $metodo_match)"),
            'dati'      => $nome_display,
        ];
        _ins_riga($pdo, $id_import, $riga_num, $esito,
            ($esito === 'inserita' ? 'Nuovo socio' : "Aggiornato via $metodo_match"),
            $dati, $id_socio, $id_tess);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $cnt['errore']++;
        $log[] = ['riga' => $riga_num, 'esito' => 'errore',
            'messaggio' => 'Eccezione: ' . $e->getMessage(), 'dati' => ''];
        _ins_riga($pdo, $id_import, $riga_num, 'errore', $e->getMessage(), $dati);
    }
}

// Aggiorna contatori sull'importazione
$totale = count($rows);
$pdo->prepare(
    'UPDATE importazioni SET righe_totali=?, righe_inserite=?, righe_aggiornate=?, righe_scartate=? WHERE id_importazione=?'
)->execute([$totale, $cnt['inserita'], $cnt['aggiornata'], $cnt['duplicato'] + $cnt['errore'], $id_import]);

// Pulizia sessione e file temporaneo
unset($_SESSION['import_file'], $_SESSION['import_ext'],
      $_SESSION['import_mapping'], $_SESSION['import_filename'],
      $_SESSION['import_stagione']);
if (file_exists($file)) @unlink($file);

// ── Helper: inserisci riga nel log ────────────────────────────────────────
function _ins_riga(PDO $pdo, int $id_import, int $riga, string $esito, string $msg, array $dati, ?int $id_socio = null, ?int $id_tess = null): void {
    $stmt = $pdo->prepare(
        'INSERT INTO righe_importazione
             (id_importazione, numero_riga, esito, messaggio_esito, payload_json, id_socio, id_tesseramento)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $id_import,
        $riga,
        $esito,
        $msg,
        json_encode($dati, JSON_UNESCAPED_UNICODE),
        $id_socio,
        $id_tess,
    ]);
}

$page_title = 'Importazione — Risultati';
require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Importazione Inter Club <small style="font-size:.6em;font-weight:400">Step 3 di 3 &mdash; Risultati</small></h1>

<div class="cards-grid" style="margin-bottom:1.5rem">
    <div class="card">
        <span class="card-value"><?= $cnt['inserita'] ?></span>
        <span class="card-label">Nuovi soci inseriti</span>
    </div>
    <div class="card">
        <span class="card-value"><?= $cnt['aggiornata'] ?></span>
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
                    'inserita'   => 'color:var(--color-success)',
                    'aggiornata' => '',
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
