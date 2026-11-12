<?php
/**
 * Importazione prima nota tesseramenti (template Excel del direttivo).
 *
 * Incrocia le righe del template con i tesseramenti gia importati dal file
 * del portale Inter Club:
 *   - Categoria quota (+ Rinnovo) -> id_tipologia e quota dovuta
 *   - Importo versato             -> pagamenti_tesseramenti (+ prima_nota)
 * Con "Simula" tutto gira in una transazione che viene poi annullata.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/xlsx_reader.php';

start_secure_session();
require_login();

const PN_ALIAS = [
    'cognome'   => ['cognome'],
    'nome'      => ['nome'],
    'cf'        => ['codice fiscale', 'codice_fiscale', 'cf'],
    'tessera'   => ['numero tessera', 'numero_tessera', 'tessera'],
    'categoria' => ['categoria quota', 'categoria', 'tipo tessera'],
    'rinnovo'   => ['rinnovo (si/no)', 'rinnovo'],
    'data'      => ['data pagamento', 'data'],
    'tipo_pag'  => ['tipo pagamento'],
    'importo'   => ['importo versato (€)', 'importo versato', 'importo'],
    'metodo'    => ['metodo pagamento', 'metodo'],
    'conto'     => ['conto'],
    'rif'       => ['riferimento'],
    'note'      => ['note'],
];

function pn_norm(string $s): string
{
    return mb_strtolower(trim($s), 'UTF-8');
}

function pn_colonne(array $headers): array
{
    $map = [];
    foreach ($headers as $i => $h) {
        $n = pn_norm((string)$h);
        foreach (PN_ALIAS as $campo => $alias) {
            if (!isset($map[$campo]) && in_array($n, $alias, true)) {
                $map[$campo] = $i;
                break;
            }
        }
    }
    return $map;
}

/** Data da testo (Y-m-d, d/m/Y, d-m-Y) o da seriale Excel. */
function pn_data(string $v): ?string
{
    $v = trim($v);
    if ($v === '') return null;
    if (preg_match('/^\d{4,6}(\.\d+)?$/', $v)) {
        $n = (int)floor((float)$v);
        if ($n > 20000 && $n < 80000) {
            return gmdate('Y-m-d', ($n - 25569) * 86400);
        }
    }
    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s', 'd/m/Y H:i:s', 'd-m-Y H:i:s'] as $f) {
        $d = DateTime::createFromFormat('!' . $f, $v);
        if ($d && $d->format($f) === $v) return $d->format('Y-m-d');
    }
    return null;
}

function pn_importo(string $v): ?float
{
    $v = trim(str_replace(['€', ' '], '', $v));
    if ($v === '') return null;
    if (str_contains($v, ',')) {
        $v = str_replace(',', '.', str_replace('.', '', $v));
    }
    return is_numeric($v) ? round((float)$v, 2) : null;
}

/** Chiave di tipologie_tesseramento.tipo da categoria + rinnovo. */
function pn_chiave_tipo(string $categoria, bool $rinnovo): string
{
    $c = strtolower(trim(preg_replace('/\s+/', '_', $categoria)));
    if (str_ends_with($c, '_rinnovo')) {
        $c = substr($c, 0, -8);
        $rinnovo = true;
    }
    return $rinnovo ? $c . '_rinnovo' : $c;
}

/** Cerca il tesseramento della stagione: CF, poi numero tessera, poi cognome+nome. */
function pn_find_tess(PDO $pdo, int $id_stagione, string $cf, string $tessera, string $cognome, string $nome): array
{
    $base = 'SELECT t.id_tesseramento, t.id_socio, t.id_tipologia, t.quota_tesseramento, t.listino_originale, s.cognome, s.nome
             FROM tesseramenti t JOIN soci s ON s.id_socio = t.id_socio
             WHERE t.id_stagione = ? AND s.attivo_record = 1 AND ';
    if ($cf !== '') {
        $st = $pdo->prepare($base . 's.codice_fiscale = ? LIMIT 1');
        $st->execute([$id_stagione, $cf]);
        if ($r = $st->fetch()) return [$r, 'CF', null];
    }
    if ($tessera !== '') {
        $st = $pdo->prepare($base . 't.numero_tessera = ? LIMIT 1');
        $st->execute([$id_stagione, $tessera]);
        if ($r = $st->fetch()) return [$r, 'tessera', null];
    }
    if ($cognome !== '' && $nome !== '') {
        $st = $pdo->prepare($base . 'LOWER(TRIM(s.cognome)) = LOWER(?) AND LOWER(TRIM(s.nome)) = LOWER(?) LIMIT 2');
        $st->execute([$id_stagione, $cognome, $nome]);
        $rows = $st->fetchAll();
        if (count($rows) === 1) return [$rows[0], 'nome+cognome', null];
        if (count($rows) > 1) return [null, '', 'Piu soci con stesso cognome e nome: serve il codice fiscale o il numero tessera.'];
    }
    return [null, '', 'Socio non trovato nella stagione scelta: importa prima il file del portale.'];
}

$pdo = get_db_connection();
$stagioni = $pdo->query('SELECT id_stagione, codice_stagione, attiva FROM stagioni ORDER BY attiva DESC, codice_stagione DESC')->fetchAll();

$log = [];
$cnt = ['categorie' => 0, 'pagamenti' => 0, 'movimenti' => 0, 'duplicati' => 0, 'errori' => 0, 'vuote' => 0];
$tot_importo = 0.0;
$errore = '';
$eseguito = false;
$simula = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $id_stagione = (int)($_POST['id_stagione'] ?? 0);
    $simula      = !empty($_POST['simula']);
    $registra_pn = !empty($_POST['registra_prima_nota']) && can('prima_nota.write');
    $f   = $_FILES['file'] ?? null;
    $ext = $f ? strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION)) : '';
    $headers = [];
    $rows = [];
    $col = [];

    if (!$id_stagione) {
        $errore = 'Seleziona la stagione.';
    } elseif (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        $errore = 'Nessun file caricato o caricamento non riuscito.';
    } elseif (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
        $errore = 'Formato non supportato: usa .xlsx, .xls o .csv.';
    } else {
        try {
            $reader  = new XlsxReader($f['tmp_name'], $ext);
            $headers = $reader->getHeaders();
            $rows    = $reader->getAllRows();
        } catch (Throwable $e) {
            $errore = 'Impossibile leggere il file: ' . $e->getMessage();
        }
    }

    if ($errore === '') {
        $col = pn_colonne($headers);
        $ha_id = isset($col['cf']) || isset($col['tessera']) || (isset($col['cognome']) && isset($col['nome']));
        if (!$ha_id || (!isset($col['categoria']) && !isset($col['importo']))) {
            $errore = 'Intestazioni non riconosciute: servono Codice fiscale, Numero tessera (o Cognome e Nome) e almeno Categoria quota oppure Importo versato. Usa il template della prima nota.';
        }
    }

    if ($errore === '') {
        $tip = [];
        foreach ($pdo->query('SELECT id_tipologia, tipo, quota_standard FROM tipologie_tesseramento') as $r) {
            $tip[strtolower($r['tipo'])] = [(int)$r['id_tipologia'], $r['quota_standard']];
        }
        $utente = current_user();
        $id_utente = $utente ? (int)$utente['id_utente'] : null;
        $id_import = null;

        $pdo->beginTransaction();
        try {
            if (!$simula) {
                $pdo->prepare('INSERT INTO importazioni (nome_file, importato_da, id_stagione) VALUES (?, ?, ?)')
                    ->execute(['Prima nota - ' . basename((string)$f['name']), $id_utente, $id_stagione]);
                $id_import = (int)$pdo->lastInsertId();
            }
            $metodi = ['contanti', 'bonifico', 'pos', 'altro'];
            $tipi   = ['acconto', 'saldo', 'integrazione', 'rimborso'];
            $conti  = ['cassa', 'banca', 'altro'];

            foreach ($rows as $r_idx => $row) {
                $riga_num = $r_idx + 2;
                $g = fn(string $k): string => isset($col[$k]) ? trim((string)($row[$col[$k]] ?? '')) : '';
                $cf = strtoupper($g('cf'));
                $tessera = $g('tessera');
                $cognome = $g('cognome');
                $nome = $g('nome');
                if ($cf === '' && $tessera === '' && $cognome === '' && $nome === '') continue;

                $categoria = $g('categoria');
                $importo_txt = $g('importo');
                $importo = pn_importo($importo_txt);
                if ($categoria === '' && $importo_txt === '') {
                    $cnt['vuote']++;
                    continue;
                }
                $etichetta = trim($cognome . ' ' . $nome) ?: ($cf ?: $tessera);
                $id_socio = null;
                $id_tess = null;

                try {
                    $pdo->exec('SAVEPOINT riga');
                    [$t, $via, $err] = pn_find_tess($pdo, $id_stagione, $cf, $tessera, $cognome, $nome);
                    if (!$t) throw new RuntimeException($err);
                    $id_socio = (int)$t['id_socio'];
                    $id_tess = (int)$t['id_tesseramento'];
                    $etichetta = trim($t['cognome'] . ' ' . $t['nome']);
                    $msg = [];
                    $cambiato = false;

                    if ($categoria !== '') {
                        $rin = strtoupper($g('rinnovo'));
                        if ($rin === '') {
                            $rin = str_contains(strtoupper((string)$t['listino_originale']), 'RINNOVO') ? 'SI' : 'NO';
                        }
                        $key = pn_chiave_tipo($categoria, in_array($rin, ['SI', 'SÌ', '1', 'X', 'TRUE'], true));
                        if (!isset($tip[$key])) throw new RuntimeException('Categoria non riconosciuta: ' . $key);
                        [$id_tip, $quota] = $tip[$key];
                        if ((int)$t['id_tipologia'] !== $id_tip || $t['quota_tesseramento'] === null) {
                            $pdo->prepare("UPDATE tesseramenti SET id_tipologia = ?, quota_tesseramento = ?, fonte_inserimento = IF(fonte_inserimento = 'portale', 'misto', fonte_inserimento) WHERE id_tesseramento = ?")
                                ->execute([$id_tip, $quota, $id_tess]);
                            $cnt['categorie']++;
                            $cambiato = true;
                            $msg[] = 'categoria ' . $key . ($quota !== null ? ' (quota ' . number_format((float)$quota, 2, ',', '.') . ')' : '');
                        }
                    }

                    $pagato = false;
                    if ($importo_txt !== '') {
                        if ($importo === null || $importo <= 0) throw new RuntimeException('Importo non valido: ' . $importo_txt);
                        $data = pn_data($g('data'));
                        if (!$data) throw new RuntimeException('Data pagamento mancante o non valida.');
                        $tipo = strtolower($g('tipo_pag')) ?: 'acconto';
                        $metodo = strtolower($g('metodo')) ?: 'contanti';
                        $conto = strtolower($g('conto')) ?: ($metodo === 'contanti' ? 'cassa' : 'banca');
                        if (!in_array($tipo, $tipi, true)) throw new RuntimeException('Tipo pagamento non valido: ' . $tipo);
                        if (!in_array($metodo, $metodi, true)) throw new RuntimeException('Metodo pagamento non valido: ' . $metodo);
                        if (!in_array($conto, $conti, true)) throw new RuntimeException('Conto non valido: ' . $conto);
                        $rif = $g('rif');
                        $note = $g('note');

                        $dup = $pdo->prepare("SELECT 1 FROM pagamenti_tesseramenti WHERE id_tesseramento = ? AND data_pagamento = ? AND tipo_pagamento = ? AND importo = ? AND metodo_pagamento = ? AND COALESCE(riferimento, '') = ? LIMIT 1");
                        $dup->execute([$id_tess, $data, $tipo, $importo, $metodo, $rif]);
                        if ($dup->fetchColumn()) {
                            $msg[] = 'pagamento gia presente';
                            $cnt['duplicati']++;
                            $esito = $cambiato ? 'aggiornata' : 'duplicato';
                        } else {
                            $pdo->prepare('INSERT INTO pagamenti_tesseramenti (id_tesseramento, data_pagamento, tipo_pagamento, importo, metodo_pagamento, riferimento, note, registrato_da) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                                ->execute([$id_tess, $data, $tipo, $importo, $metodo, $rif !== '' ? $rif : null, $note !== '' ? $note : null, $id_utente]);
                            $id_pag = (int)$pdo->lastInsertId();
                            $cnt['pagamenti']++;
                            $tot_importo += $tipo === 'rimborso' ? -$importo : $importo;
                            $pagato = true;
                            $msg[] = $tipo . ' ' . number_format($importo, 2, ',', '.') . ' (' . $metodo . ')';
                            if ($registra_pn) {
                                $pdo->prepare('INSERT INTO prima_nota (data_movimento, tipo_movimento, conto, categoria, causale, importo, id_socio, id_tesseramento, id_pagamento, riferimento_documento, note, registrato_da) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                                    ->execute([$data, $tipo === 'rimborso' ? 'uscita' : 'entrata', $conto, 'tesseramenti',
                                        'Quota tesseramento ' . $t['cognome'] . ' ' . $t['nome'] . ' - ' . $tipo,
                                        $importo, $id_socio, $id_tess, $id_pag, $rif !== '' ? $rif : null, $note !== '' ? $note : null, $id_utente]);
                                $cnt['movimenti']++;
                            }
                            $esito = 'inserita';
                        }
                    } else {
                        $esito = 'aggiornata';
                        if (!$cambiato) $msg[] = 'nessuna modifica';
                    }
                    if (!isset($esito)) $esito = 'aggiornata';
                    $esito_riga = $pagato ? 'inserita' : $esito;
                    $testo = implode('; ', $msg) . ' [match ' . $via . ']';
                    $log[] = ['riga' => $riga_num, 'esito' => $esito_riga, 'dati' => $etichetta, 'messaggio' => $testo];
                    if ($id_import) {
                        $pdo->prepare('INSERT INTO righe_importazione (id_importazione, numero_riga, esito, messaggio_esito, payload_json, id_socio, id_tesseramento) VALUES (?, ?, ?, ?, ?, ?, ?)')
                            ->execute([$id_import, $riga_num, $esito_riga, mb_substr($testo, 0, 255), json_encode($row, JSON_UNESCAPED_UNICODE), $id_socio, $id_tess]);
                    }
                    unset($esito);
                    $pdo->exec('RELEASE SAVEPOINT riga');
                } catch (Throwable $e) {
                    $pdo->exec('ROLLBACK TO SAVEPOINT riga');
                    $cnt['errori']++;
                    $log[] = ['riga' => $riga_num, 'esito' => 'errore', 'dati' => $etichetta, 'messaggio' => $e->getMessage()];
                    if ($id_import) {
                        $pdo->prepare('INSERT INTO righe_importazione (id_importazione, numero_riga, esito, messaggio_esito, payload_json) VALUES (?, ?, ?, ?, ?)')
                            ->execute([$id_import, $riga_num, 'errore', mb_substr($e->getMessage(), 0, 255), json_encode($row, JSON_UNESCAPED_UNICODE)]);
                    }
                    unset($esito);
                }
            }

            if ($id_import) {
                $pdo->prepare('UPDATE importazioni SET righe_totali = ?, righe_inserite = ?, righe_aggiornate = ?, righe_scartate = ? WHERE id_importazione = ?')
                    ->execute([count($rows), $cnt['pagamenti'], $cnt['categorie'], $cnt['duplicati'] + $cnt['errori'], $id_import]);
            }
            if ($simula) {
                $pdo->rollBack();
            } else {
                $pdo->commit();
            }
            $eseguito = true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errore = 'Importazione interrotta, nessuna modifica salvata: ' . $e->getMessage();
        }
    }
}

$page_title = 'Importa prima nota';
require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Importa prima nota tesseramenti</h1>
<p style="color:#555;max-width:760px">Carica il template Excel della prima nota <strong>dopo</strong> aver importato il file del portale. Ogni riga viene abbinata al socio (codice fiscale, numero tessera, poi cognome e nome): assegna la categoria della quota e registra i versamenti.</p>

<?php if ($errore): ?>
    <div style="background:#fdecea;border:1px solid #f5c6c6;color:#a12c2c;padding:.75rem 1rem;border-radius:.5rem;margin-bottom:1rem"><?= h($errore) ?></div>
<?php endif; ?>

<div class="admin-panel" style="max-width:720px;margin-bottom:1.5rem">
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <div style="margin-bottom:1rem">
            <label for="id_stagione" style="display:block;font-weight:600;margin-bottom:.35rem">Stagione *</label>
            <select name="id_stagione" id="id_stagione" required style="width:100%;padding:.45rem .6rem;border:1px solid #ccc;border-radius:.375rem">
                <?php foreach ($stagioni as $s): ?>
                    <option value="<?= (int)$s['id_stagione'] ?>" <?= !empty($s['attiva']) ? 'selected' : '' ?>><?= h($s['codice_stagione']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="margin-bottom:1rem">
            <label for="file" style="display:block;font-weight:600;margin-bottom:.35rem">Template prima nota (.xlsx, .xls, .csv) *</label>
            <input type="file" name="file" id="file" accept=".xlsx,.xls,.csv" required>
        </div>
        <div style="margin-bottom:1rem">
            <label style="display:block;margin-bottom:.35rem"><input type="checkbox" name="simula" value="1" <?= $simula ? 'checked' : '' ?>> Simula: mostra il risultato senza salvare nulla</label>
            <?php if (can('prima_nota.write')): ?>
                <label style="display:block"><input type="checkbox" name="registra_prima_nota" value="1" checked> Registra anche i movimenti in prima nota</label>
            <?php else: ?>
                <small style="color:#666">Il tuo account non puo creare movimenti di prima nota: verranno registrati solo i pagamenti.</small>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn">Avvia</button>
        <a href="<?= u('/importazioni/upload.php') ?>" style="margin-left:.75rem;color:#555;text-decoration:none">Import file portale</a>
    </form>
</div>

<?php if ($eseguito): ?>
    <?php if ($simula): ?>
        <div style="background:#fff7e0;border:1px solid #f0d88a;color:#7a5a00;padding:.75rem 1rem;border-radius:.5rem;margin-bottom:1rem"><strong>Simulazione:</strong> non e stato salvato nulla. Togli la spunta su Simula e ricarica lo stesso file per importare davvero.</div>
    <?php endif; ?>
    <div class="cards-grid" style="margin-bottom:1.5rem">
        <div class="card"><span class="card-value"><?= $cnt['categorie'] ?></span><span class="card-label">Categorie assegnate</span></div>
        <div class="card"><span class="card-value"><?= $cnt['pagamenti'] ?></span><span class="card-label">Pagamenti (&euro; <?= euro($tot_importo) ?>)</span></div>
        <div class="card"><span class="card-value"><?= $cnt['movimenti'] ?></span><span class="card-label">Movimenti prima nota</span></div>
        <div class="card card-warning"><span class="card-value"><?= $cnt['duplicati'] ?></span><span class="card-label">Gia presenti</span></div>
        <div class="card card-warning"><span class="card-value"><?= $cnt['errori'] ?></span><span class="card-label">Errori</span></div>
        <div class="card"><span class="card-value"><?= $cnt['vuote'] ?></span><span class="card-label">Righe senza dati</span></div>
    </div>
    <?php if ($log): ?>
    <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr><th>Riga</th><th>Esito</th><th>Socio</th><th>Dettaglio</th></tr></thead>
            <tbody>
            <?php foreach ($log as $l):
                $cls = match ($l['esito']) {
                    'inserita'  => 'color:var(--color-success)',
                    'duplicato' => 'color:var(--color-warning)',
                    'errore'    => 'color:var(--color-error)',
                    default     => '',
                }; ?>
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
<?php endif; ?>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
