<?php
/**
 * Fase 4 – Log importazioni
 * Mostra lo storico delle importazioni o il dettaglio di una singola.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    // ── Dettaglio singola importazione ──────────────────────────────────────
    $stmt = $pdo->prepare(
        'SELECT imp.*, ua.nome AS op_nome, ua.cognome AS op_cognome
         FROM importazioni imp
         LEFT JOIN utenti_admin ua ON ua.id_utente = imp.importato_da
         WHERE imp.id_importazione = :id'
    );
    $stmt->execute(['id' => $id]);
    $log = $stmt->fetch();

    if (!$log) {
        redirect_with_message('/import/log.php', 'Importazione non trovata.', 'error');
    }

    $page_title = 'Dettaglio importazione #' . $id;
    require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Importazione #<?= $id ?></h1>
    <div class="btn-group">
        <a class="btn btn-secondary" href="/import/log.php">← Storico</a>
        <a class="btn" href="/import/upload.php">Nuova importazione</a>
    </div>
</div>

<div class="detail-card import-result">
    <dl class="import-stats">
        <div class="stat-item">
            <dt>File</dt>
            <dd><?= h($log['nome_file']) ?></dd>
        </div>
        <div class="stat-item">
            <dt>Data</dt>
            <dd><?= h($log['data_importazione']) ?></dd>
        </div>
        <div class="stat-item">
            <dt>Operatore</dt>
            <dd><?= $log['op_nome'] ? h($log['op_nome'] . ' ' . $log['op_cognome']) : '—' ?></dd>
        </div>
        <div class="stat-item">
            <dt>Righe totali</dt>
            <dd><?= (int)$log['righe_totali'] ?></dd>
        </div>
        <div class="stat-item stat-ok">
            <dt>Inserite</dt>
            <dd><?= (int)$log['righe_inserite'] ?></dd>
        </div>
        <div class="stat-item stat-warn">
            <dt>Aggiornate</dt>
            <dd><?= (int)$log['righe_aggiornate'] ?></dd>
        </div>
        <div class="stat-item <?= (int)$log['righe_scartate'] > 0 ? 'stat-err' : '' ?>">
            <dt>Scartate</dt>
            <dd><?= (int)$log['righe_scartate'] ?></dd>
        </div>
    </dl>

    <?php if ($log['note_esito']): ?>
    <div class="import-notes">
        <h3>Note / righe scartate</h3>
        <pre><?= h($log['note_esito']) ?></pre>
    </div>
    <?php endif; ?>
</div>

<?php
    require __DIR__ . '/../../includes/layout_footer.php';

} else {
    // ── Lista storico importazioni ───────────────────────────────────────────
    $logs = $pdo->query(
        'SELECT imp.*, ua.nome AS op_nome, ua.cognome AS op_cognome
         FROM importazioni imp
         LEFT JOIN utenti_admin ua ON ua.id_utente = imp.importato_da
         ORDER BY imp.data_importazione DESC
         LIMIT 100'
    )->fetchAll();

    $page_title = 'Storico importazioni';
    require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Storico importazioni</h1>
    <a class="btn" href="/import/upload.php">Nuova importazione</a>
</div>

<?php if (empty($logs)): ?>
    <p class="note">Nessuna importazione effettuata.</p>
<?php else: ?>
<table class="data-table">
    <thead>
    <tr>
        <th>#</th>
        <th>File</th>
        <th>Data</th>
        <th>Operatore</th>
        <th class="text-center">Totali</th>
        <th class="text-center">Inserite</th>
        <th class="text-center">Aggiornate</th>
        <th class="text-center">Scartate</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
    <tr>
        <td><?= (int)$l['id_importazione'] ?></td>
        <td><?= h($l['nome_file']) ?></td>
        <td><?= h($l['data_importazione']) ?></td>
        <td><?= $l['op_nome'] ? h($l['op_nome'] . ' ' . $l['op_cognome']) : '—' ?></td>
        <td class="text-center"><?= (int)$l['righe_totali'] ?></td>
        <td class="text-center badge-ok-cell"><?= (int)$l['righe_inserite'] ?></td>
        <td class="text-center"><?= (int)$l['righe_aggiornate'] ?></td>
        <td class="text-center <?= (int)$l['righe_scartate'] > 0 ? 'badge-err-cell' : '' ?>"><?= (int)$l['righe_scartate'] ?></td>
        <td><a href="/import/log.php?id=<?= (int)$l['id_importazione'] ?>">Dettaglio</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
<?php } ?>
