<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

$stmt = $pdo->query('SELECT * FROM stagioni ORDER BY codice_stagione DESC');
$stagioni = $stmt->fetchAll();

$page_title = 'Stagioni';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Stagioni</h1>
    <a class="btn" href="/stagioni/create.php">+ Nuova stagione</a>
</div>

<table class="data-table">
    <thead>
    <tr>
        <th>Codice</th>
        <th>Descrizione</th>
        <th>Inizio</th>
        <th>Fine</th>
        <th>Attiva</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($stagioni)): ?>
        <tr><td colspan="6">Nessuna stagione trovata.</td></tr>
    <?php endif; ?>
    <?php foreach ($stagioni as $s): ?>
        <tr>
            <td><strong><?= h($s['codice_stagione']) ?></strong></td>
            <td><?= h($s['descrizione']) ?></td>
            <td><?= $s['data_inizio'] ? date('d/m/Y', strtotime($s['data_inizio'])) : '—' ?></td>
            <td><?= $s['data_fine']  ? date('d/m/Y', strtotime($s['data_fine']))  : '—' ?></td>
            <td><?= $s['attiva'] ? '<span class="badge badge-ok">Sì</span>' : '<span class="badge">No</span>' ?></td>
            <td>
                <a href="/stagioni/edit.php?id=<?= (int)$s['id_stagione'] ?>">Modifica</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
