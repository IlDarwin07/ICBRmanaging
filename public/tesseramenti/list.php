<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

// ---------------------------------------------------------------------
// Filtri
// ---------------------------------------------------------------------
$stagioni = $pdo->query('SELECT * FROM stagioni ORDER BY attiva DESC, codice_stagione DESC')->fetchAll();

// Default: stagione attiva (se esiste), altrimenti nessun filtro stagione
$id_stagione_filtro = (int)($_GET['id_stagione'] ?? -1); // -1 = non passato
if ($id_stagione_filtro === -1) {
    $id_stagione_filtro = 0; // 0 = tutte le stagioni
    foreach ($stagioni as $s) {
        if ($s['attiva']) { $id_stagione_filtro = (int)$s['id_stagione']; break; }
    }
}
// Nota: selezionando esplicitamente "Tutte le stagioni" (value="") il filtro
// resta 0 e la query NON ricade automaticamente sulla stagione attiva.

$q              = trim($_GET['q'] ?? '');
$stato_filtro   = $_GET['stato_pagamento'] ?? '';
$attivo_filtro  = $_GET['attivo_portale'] ?? '';
$tipologia_filtro = (int)($_GET['id_tipologia'] ?? 0);
$tessera_filtro = $_GET['tessera_fisica'] ?? '';
$export_csv     = ($_GET['export'] ?? '') === 'csv';

$STATI_VALIDI = ['da_pagare', 'acconto', 'saldato', 'eccedenza', 'quota_sconosciuta'];
if (!in_array($stato_filtro, $STATI_VALIDI, true)) {
    $stato_filtro = '';
}
if (!array_key_exists($tessera_filtro, TESSERA_FISICA_STATI)) {
    $tessera_filtro = '';
}

$tipologie = $pdo->query('SELECT id_tipologia, listino_label, tipo FROM tipologie_tesseramento ORDER BY tipo')->fetchAll();

// ---------------------------------------------------------------------
// Query: quota (snapshot -> fallback listino), versato netto, residuo
// ---------------------------------------------------------------------
$sql = 'SELECT t.id_tesseramento, t.numero_tessera, t.attivo_portale, t.socio_plus,
               t.fonte_inserimento, t.data_ora_attivazione, t.tessera_fisica,
               t.quota_tesseramento, tip.quota_standard,
               s.nome, s.cognome, s.telefono, s.email,
               st.codice_stagione,
               tip.listino_label,
               COALESCE(NULLIF(t.quota_tesseramento, 0), NULLIF(tip.quota_standard, 0)) AS quota_dovuta,
               COALESCE(pag.versato_netto, 0) AS versato_netto
        FROM tesseramenti t
        JOIN soci s ON s.id_socio = t.id_socio
        JOIN stagioni st ON st.id_stagione = t.id_stagione
        LEFT JOIN tipologie_tesseramento tip ON tip.id_tipologia = t.id_tipologia
        LEFT JOIN (
            SELECT id_tesseramento,
                   SUM(CASE WHEN tipo_pagamento = \'rimborso\' THEN -importo ELSE importo END) AS versato_netto
            FROM pagamenti_tesseramenti
            GROUP BY id_tesseramento
        ) pag ON pag.id_tesseramento = t.id_tesseramento
        WHERE 1=1';
$params = [];

if ($id_stagione_filtro > 0) {
    $sql .= ' AND t.id_stagione = :id_stagione';
    $params['id_stagione'] = $id_stagione_filtro;
}
if ($q !== '') {
    $sql .= ' AND (s.nome LIKE :q1 OR s.cognome LIKE :q2 OR s.telefono LIKE :q3 OR t.numero_tessera LIKE :q4)';
    $like = '%' . $q . '%';
    $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
}
if ($attivo_filtro !== '') {
    $sql .= ' AND t.attivo_portale = :attivo';
    $params['attivo'] = (int)$attivo_filtro;
}
if ($tipologia_filtro > 0) {
    $sql .= ' AND t.id_tipologia = :tipologia';
    $params['tipologia'] = $tipologia_filtro;
}
if ($tessera_filtro !== '') {
    $sql .= ' AND t.tessera_fisica = :tessera';
    $params['tessera'] = $tessera_filtro;
}

// Filtro per stato pagamento: condizioni HAVING sugli aggregati
if ($stato_filtro !== '') {
    switch ($stato_filtro) {
        case 'quota_sconosciuta':
            $sql .= ' HAVING quota_dovuta IS NULL';
            break;
        case 'da_pagare':
            $sql .= ' HAVING quota_dovuta IS NOT NULL AND COALESCE(versato_netto,0) BETWEEN -0.004 AND 0.004';
            break;
        case 'acconto':
            $sql .= ' HAVING quota_dovuta IS NOT NULL AND versato_netto > 0.004 AND versato_netto < quota_dovuta - 0.004';
            break;
        case 'saldato':
            $sql .= ' HAVING quota_dovuta IS NOT NULL AND versato_netto >= quota_dovuta - 0.004 AND versato_netto <= quota_dovuta + 0.004';
            break;
        case 'eccedenza':
            $sql .= ' HAVING quota_dovuta IS NOT NULL AND versato_netto > quota_dovuta + 0.004';
            break;
    }
}

$sql .= ' ORDER BY s.cognome, s.nome';

// ---------------------------------------------------------------------
// Export CSV: tutti i risultati dei filtri correnti (nessun LIMIT)
// ---------------------------------------------------------------------
if ($export_csv) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tesseramenti_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM per Excel
    fputcsv($out, ['Cognome', 'Nome', 'Stagione', 'N tessera', 'Tipologia', 'Quota (EUR)',
                   'Versato (EUR)', 'Residuo (EUR)', 'Stato', 'Attivo portale', 'Tessera fisica', 'Telefono', 'Email'], ';');
    foreach ($rows as $r) {
        $quota = $r['quota_dovuta'] !== null ? (float)$r['quota_dovuta'] : null;
        $netto = (float)$r['versato_netto'];
        $stato = stato_quota($quota, $netto);
        $residuo = tess_residuo($quota, $netto);
        fputcsv($out, [
            $r['cognome'], $r['nome'], $r['codice_stagione'],
            $r['numero_tessera'] ?? '',
            $r['listino_label'] ?: ($r['tipo'] ?? ''),
            $quota !== null ? number_format($quota, 2, ',', '') : '',
            number_format($netto, 2, ',', ''),
            $residuo !== null ? number_format($residuo, 2, ',', '') : '',
            $stato['label'],
            $r['attivo_portale'] ? 'Si' : 'No',
            tessera_fisica_label($r['tessera_fisica']),
            $r['telefono'] ?? '',
            $r['email'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}

// Vista normale: prime 300 righe (l'export CSV restituisce tutto)
$stmt = $pdo->prepare($sql . ' LIMIT 300');
$stmt->execute($params);
$tesseramenti = $stmt->fetchAll();

// ---------------------------------------------------------------------
// Contatori rapidi per la stagione selezionata
// ---------------------------------------------------------------------
$totale = $totale_attivi = 0;
if ($id_stagione_filtro > 0) {
    $conta = $pdo->prepare('SELECT COUNT(*) FROM tesseramenti WHERE id_stagione = :id');
    $conta->execute(['id' => $id_stagione_filtro]);
    $totale = (int)$conta->fetchColumn();

    $conta_attivi = $pdo->prepare('SELECT COUNT(*) FROM tesseramenti WHERE id_stagione = :id AND attivo_portale = 1');
    $conta_attivi->execute(['id' => $id_stagione_filtro]);
    $totale_attivi = (int)$conta_attivi->fetchColumn();
} else {
    $totale = (int)$pdo->query('SELECT COUNT(*) FROM tesseramenti')->fetchColumn();
    $totale_attivi = (int)$pdo->query('SELECT COUNT(*) FROM tesseramenti WHERE attivo_portale = 1')->fetchColumn();
}

// Query string corrente per i link (preserva i filtri)
$qs_current = http_build_query(array_filter([
    'id_stagione' => $id_stagione_filtro ?: null,
    'q' => $q !== '' ? $q : null,
    'stato_pagamento' => $stato_filtro ?: null,
    'attivo_portale' => $attivo_filtro !== '' ? $attivo_filtro : null,
    'id_tipologia' => $tipologia_filtro ?: null,
    'tessera_fisica' => $tessera_filtro ?: null,
], fn($v) => $v !== null && $v !== ''));

$page_title = 'Tesseramenti';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Tesseramenti</h1>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a class="btn" href="<?= u('/tesseramenti/create.php') ?><?= $id_stagione_filtro ? '?id_stagione=' . $id_stagione_filtro : '' ?>">+ Nuovo tesseramento</a>
        <a class="btn btn-secondary" href="<?= u('/tesseramenti/list.php') ?>?<?= h($qs_current) ?>&export=csv">⬇ Esporta CSV</a>
    </div>
</div>

<!-- Filtri -->
<form method="get" class="search-form">
    <select name="id_stagione">
        <option value="">— Tutte le stagioni —</option>
        <?php foreach ($stagioni as $s): ?>
            <option value="<?= (int)$s['id_stagione'] ?>" <?= $id_stagione_filtro == $s['id_stagione'] ? 'selected' : '' ?>>
                <?= h($s['codice_stagione']) ?> <?= $s['attiva'] ? '(attiva)' : '' ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="stato_pagamento">
        <option value="">— Stato pagamento —</option>
        <option value="da_pagare" <?= $stato_filtro === 'da_pagare' ? 'selected' : '' ?>>Da saldare</option>
        <option value="acconto" <?= $stato_filtro === 'acconto' ? 'selected' : '' ?>>Solo acconto</option>
        <option value="saldato" <?= $stato_filtro === 'saldato' ? 'selected' : '' ?>>Saldati</option>
        <option value="eccedenza" <?= $stato_filtro === 'eccedenza' ? 'selected' : '' ?>>Eccedenza</option>
        <option value="quota_sconosciuta" <?= $stato_filtro === 'quota_sconosciuta' ? 'selected' : '' ?>>Quota non definita</option>
    </select>
    <select name="attivo_portale">
        <option value="">— Attivo portale —</option>
        <option value="1" <?= $attivo_filtro === '1' ? 'selected' : '' ?>>Sì</option>
        <option value="0" <?= $attivo_filtro === '0' ? 'selected' : '' ?>>No</option>
    </select>
    <select name="id_tipologia">
        <option value="">— Tipologia —</option>
        <?php foreach ($tipologie as $tp): ?>
            <option value="<?= (int)$tp['id_tipologia'] ?>" <?= $tipologia_filtro == $tp['id_tipologia'] ? 'selected' : '' ?>>
                <?= h($tp['listino_label'] ?: $tp['tipo']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="tessera_fisica">
        <option value="">— Tessera fisica —</option>
        <?php foreach (TESSERA_FISICA_STATI as $val => $label): ?>
            <option value="<?= h($val) ?>" <?= $tessera_filtro === $val ? 'selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Cerca nome, cognome, telefono, tessera">
    <button type="submit" class="btn">Cerca</button>
    <a class="btn btn-secondary" href="<?= u('/tesseramenti/list.php') ?>">Reset</a>
</form>

<div class="stats-bar">
    <span>Totale: <strong><?= $totale ?></strong></span>
    <span>Attivi portale: <strong><?= $totale_attivi ?></strong></span>
    <span>Non attivi: <strong><?= $totale - $totale_attivi ?></strong></span>
    <?php if (count($tesseramenti) >= 300): ?>
    <span style="color:#b00">Mostrate le prime 300 righe: usa l'export CSV per l'elenco completo.</span>
    <?php endif; ?>
</div>

<table class="data-table">
    <thead>
    <tr>
        <th>Cognome</th>
        <th>Nome</th>
        <th>Stagione</th>
        <th>N° tessera</th>
        <th>Listino</th>
        <th>Quota</th>
        <th>Versato</th>
        <th>Residuo</th>
        <th>Stato</th>
        <th>Attivo</th>
        <th>Socio+</th>
        <th>Fonte</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($tesseramenti)): ?>
        <tr><td colspan="13">Nessun tesseramento trovato.</td></tr>
    <?php endif; ?>
    <?php foreach ($tesseramenti as $t): ?>
        <?php
            $quota = $t['quota_dovuta'] !== null ? (float)$t['quota_dovuta'] : null;
            $netto = (float)$t['versato_netto'];
            $stato = stato_quota($quota, $netto);
            $residuo = tess_residuo($quota, $netto);
        ?>
        <tr>
            <td><?= h($t['cognome']) ?></td>
            <td><?= h($t['nome']) ?></td>
            <td><?= h($t['codice_stagione']) ?></td>
            <td><?= h($t['numero_tessera']) ?: '—' ?></td>
            <td><?= h($t['listino_label']) ?: '—' ?></td>
            <td><?= $quota !== null ? '€ ' . euro($quota) : '—' ?></td>
            <td><?= $netto != 0.0 ? '€ ' . euro($netto) : '—' ?></td>
            <td><?= $residuo !== null ? '€ ' . euro($residuo) : '—' ?></td>
            <td><span class="badge <?= $stato['badge'] ?>"><?= h($stato['label']) ?></span></td>
            <td><?= $t['attivo_portale'] ? '<span class="badge badge-ok">Sì</span>' : '<span class="badge">No</span>' ?></td>
            <td><?= $t['socio_plus'] ? '<span class="badge badge-plus">Plus</span>' : '' ?></td>
            <td><?= h($t['fonte_inserimento']) ?></td>
            <td>
                <a href="<?= u('/tesseramenti/view.php') ?>?id=<?= (int)$t['id_tesseramento'] ?>">Apri</a>
                ·
                <a href="<?= u('/tesseramenti/edit.php') ?>?id=<?= (int)$t['id_tesseramento'] ?>">Modifica</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
