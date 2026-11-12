<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/partite.php';
start_secure_session();
require_login();
partite_require_ready();

$filtro = ($_GET['filtro'] ?? '') === 'passate' ? 'passate' : 'prossime';
$partite = partite_admin_list($filtro);

$page_title = 'Partite e presenze';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <div>
        <span class="admin-eyebrow">SEDE / PARTITE</span>
        <h1>Partite e presenze</h1>
    </div>
    <div class="quick-links">
        <a class="btn btn-secondary" href="<?= u('/partite/list.php') ?>" <?= $filtro === 'prossime' ? 'aria-current="page"' : '' ?>>Prossime</a>
        <a class="btn btn-secondary" href="<?= u('/partite/list.php?filtro=passate') ?>" <?= $filtro === 'passate' ? 'aria-current="page"' : '' ?>>Archivio</a>
        <?php if (can('partite.write')): ?><a class="btn" href="<?= u('/partite/edit.php') ?>">+ Nuova partita</a><?php endif; ?>
    </div>
</div>

<?php if (!$partite): ?>
    <section class="admin-panel">
        <h2><?= $filtro === 'passate' ? 'Nessuna partita in archivio' : 'Nessuna partita in programma' ?></h2>
        <p>Crea la partita, scegli la sala e apri le prenotazioni: i soci con l'accesso all'area soci potranno confermare la presenza dal telefono.</p>
    </section>
<?php else: ?>
    <table class="data-table">
        <thead><tr><th>Data</th><th>Partita</th><th>Sala</th><th>Stato</th><th>Presenti</th><th>Attesa</th><th>Sedie assegnate</th><th>Azioni</th></tr></thead>
        <tbody>
        <?php foreach ($partite as $p): ?>
            <tr>
                <td><?= h(date('d/m/Y H:i', strtotime($p['data_ora']))) ?></td>
                <td><strong><?= h($p['titolo']) ?></strong><?= $p['competizione'] ? '<br><small>' . h($p['competizione']) . '</small>' : '' ?></td>
                <td><?= h($p['sala_nome']) ?></td>
                <td><span class="badge <?= h(partita_stato_badge($p['stato'])) ?>"><?= h(partita_stato_label($p['stato'])) ?></span></td>
                <td><?= (int)$p['presenti'] ?> / <?= (int)$p['capienza'] ?></td>
                <td><?= (int)$p['in_attesa'] ?></td>
                <td><?= (int)$p['assegnati'] ?> / <?= (int)$p['presenti'] ?></td>
                <td class="td-actions">
                    <a class="btn btn-sm" href="<?= u('/partite/view.php?id=' . (int)$p['id_partita']) ?>">Presenze e sedie</a>
                    <a class="btn btn-secondary btn-sm" href="<?= u('/partite/sostenitori.php?id=' . (int)$p['id_partita']) ?>">Sostenitori</a>
                    <?php if (can('partite.write') && !in_array($p['stato'], ['conclusa'], true)): ?><a class="btn btn-secondary btn-sm" href="<?= u('/partite/edit.php?id=' . (int)$p['id_partita']) ?>">Modifica</a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
