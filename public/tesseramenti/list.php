<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

// Stagione di filtro: di default quella attiva
$stmt_s = $pdo->query('SELECT * FROM stagioni ORDER BY attiva DESC, codice_stagione DESC');
$stagioni = $stmt_s->fetchAll();

$id_stagione_filtro = (int)($_GET['id_stagione'] ?? 0);
if (!$id_stagione_filtro) {
    // Trova la stagione attiva
    foreach ($stagioni as $s) {
        if ($s['attiva']) { $id_stagione_filtro = (int)$s['id_stagione']; break; }
    }
}

$q = trim($_GET['q'] ?? '');

$sql = 'SELECT t.id_tesseramento, t.numero_tessera, t.attivo_portale, t.socio_plus,
               t.fonte_inserimento, t.data_ora_attivazione,
               s.nome, s.cognome, s.telefono, s.email,
               st.codice_stagione,
               tip.listino_label
        FROM tesseramenti t
        JOIN soci s ON s.id_socio = t.id_socio
        JOIN stagioni st ON st.id_stagione = t.id_stagione
        LEFT JOIN tipologie_tesseramento tip ON tip.id_tipologia = t.id_tipologia
        WHERE t.id_stagione = :id_stagione';
$params = ['id_stagione' => $id_stagione_filtro ?: 0];

if ($q !== '') {
    $sql .= ' AND (s.nome LIKE :q1 OR s.cognome LIKE :q2 OR s.telefono LIKE :q3 OR t.numero_tessera LIKE :q4)';
    $like = '%' . $q . '%';
    $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
}

$sql .= ' ORDER BY s.cognome, s.nome LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tesseramenti = $stmt->fetchAll();

// Contatori rapidi per la stagione selezionata
$conta = $pdo->prepare('SELECT COUNT(*) FROM tesseramenti WHERE id_stagione = :id');
$conta->execute(['id' => $id_stagione_filtro]);
$totale = (int)$conta->fetchColumn();

$conta_attivi = $pdo->prepare('SELECT COUNT(*) FROM tesseramenti WHERE id_stagione = :id AND attivo_portale = 1');
$conta_attivi->execute(['id' => $id_stagione_filtro]);
$totale_attivi = (int)$conta_attivi->fetchColumn();

$page_title = 'Tesseramenti';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Tesseramenti</h1>
    <a class="btn" href="/tesseramenti/create.php<?= $id_stagione_filtro ? '?id_stagione=' . $id_stagione_filtro : '' ?>">+ Nuovo tesseramento</a>
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
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Cerca nome, cognome, telefono, tessera">
    <button type="submit" class="btn">Cerca</button>
</form>

<?php if ($id_stagione_filtro): ?>
<div class="stats-bar">
    <span>Totale: <strong><?= $totale ?></strong></span>
    <span>Attivi portale: <strong><?= $totale_attivi ?></strong></span>
    <span>Non attivi: <strong><?= $totale - $totale_attivi ?></strong></span>
</div>
<?php endif; ?>

<table class="data-table">
    <thead>
    <tr>
        <th>Cognome</th>
        <th>Nome</th>
        <th>Stagione</th>
        <th>N° tessera</th>
        <th>Listino</th>
        <th>Attivo portale</th>
        <th>Socio+</th>
        <th>Fonte</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($tesseramenti)): ?>
        <tr><td colspan="9">Nessun tesseramento trovato.</td></tr>
    <?php endif; ?>
    <?php foreach ($tesseramenti as $t): ?>
        <tr>
            <td><?= h($t['cognome']) ?></td>
            <td><?= h($t['nome']) ?></td>
            <td><?= h($t['codice_stagione']) ?></td>
            <td><?= h($t['numero_tessera']) ?: '—' ?></td>
            <td><?= h($t['listino_label']) ?: '—' ?></td>
            <td><?= $t['attivo_portale'] ? '<span class="badge badge-ok">Sì</span>' : '<span class="badge">No</span>' ?></td>
            <td><?= $t['socio_plus'] ? '<span class="badge badge-plus">Plus</span>' : '' ?></td>
            <td><?= h($t['fonte_inserimento']) ?></td>
            <td>
                <a href="/tesseramenti/view.php?id=<?= (int)$t['id_tesseramento'] ?>">Apri</a>
                ·
                <a href="/tesseramenti/edit.php?id=<?= (int)$t['id_tesseramento'] ?>">Modifica</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
