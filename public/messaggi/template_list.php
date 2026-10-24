<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$templates = $pdo->query('SELECT * FROM template_whatsapp ORDER BY categoria, nome_template')->fetchAll();

$page_title = 'Template messaggi';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Template messaggi WhatsApp</h1>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a class="btn" href="<?= u('/messaggi/template_create.php') ?>">+ Nuovo template</a>
        <a class="btn btn-secondary" href="<?= u('/messaggi/compose.php') ?>">✎ Componi invio</a>
        <a class="btn btn-secondary" href="<?= u('/messaggi/list.php') ?>">Messaggi generati</a>
    </div>
</div>

<p style="color:#666;font-size:.88rem;">
    I template supportano questi placeholder:
    <?php foreach (WHATSAPP_PLACEHOLDER as $ph => $desc): ?>
        <code><?= h($ph) ?></code> <small>(<?= h($desc) ?>)</small>
    <?php endforeach; ?>
</p>

<table class="data-table">
    <thead>
    <tr>
        <th>Nome</th>
        <th>Categoria</th>
        <th>Anteprima testo</th>
        <th>Attivo</th>
        <th>Placeholder non validi</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($templates)): ?>
        <tr><td colspan="6">Nessun template. Creane uno per iniziare.</td></tr>
    <?php endif; ?>
    <?php foreach ($templates as $t): ?>
        <?php $sconosciuti = placeholder_sconosciuti($t['testo_template']); ?>
        <tr>
            <td><strong><?= h($t['nome_template']) ?></strong></td>
            <td><?= h($t['categoria']) ?: '—' ?></td>
            <td style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= h($t['testo_template']) ?>">
                <?= h(mb_strimwidth($t['testo_template'], 0, 90, '…')) ?>
            </td>
            <td><?= $t['attivo'] ? '<span class="badge badge-green">Sì</span>' : '<span class="badge badge-gray">No</span>' ?></td>
            <td><?= $sconosciuti
                    ? '<span class="badge badge-red">' . h(implode(' ', $sconosciuti)) . '</span>'
                    : '<span class="badge badge-ok">OK</span>' ?></td>
            <td>
                <a href="<?= u('/messaggi/template_edit.php') ?>?id=<?= (int)$t['id_template'] ?>">Modifica</a>
                ·
                <a href="<?= u('/messaggi/template_delete.php') ?>?id=<?= (int)$t['id_template'] ?>">Elimina</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
