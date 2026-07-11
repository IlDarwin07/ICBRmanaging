<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

$stmt = $pdo->query('SELECT * FROM tipologie_tesseramento ORDER BY tipo');
$tipologie = $stmt->fetchAll();

$page_title = 'Tipologie tesseramento';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Tipologie tesseramento</h1>
    <a class="btn" href="/tipologie/create.php">+ Nuova tipologia</a>
</div>

<table class="data-table">
    <thead>
    <tr>
        <th>ID</th>
        <th>Tipo</th>
        <th>Etichetta listino</th>
        <th>Quota standard</th>
        <th>Attiva</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($tipologie)): ?>
        <tr><td colspan="6">Nessuna tipologia trovata.</td></tr>
    <?php endif; ?>
    <?php foreach ($tipologie as $t): ?>
        <tr>
            <td><?= (int)$t['id_tipologia'] ?></td>
            <td><?= h($t['tipo']) ?></td>
            <td><?= h($t['listino_label']) ?></td>
            <td><?= $t['quota_standard'] !== null ? '€ ' . number_format((float)$t['quota_standard'], 2, ',', '.') : '—' ?></td>
            <td><?= $t['attiva'] ? '<span class="badge badge-ok">Sì</span>' : '<span class="badge">No</span>' ?></td>
            <td><a href="/tipologie/edit.php?id=<?= (int)$t['id_tipologia'] ?>">Modifica</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
