<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/articoli.php';
require_login();

$ready = articoli_table_ready();
$articoli = $ready ? articoli_admin_list() : [];

$page_title = 'Articoli ed eventi';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <div>
        <span class="admin-eyebrow">SITO PUBBLICO / EVENTI</span>
        <h1>Articoli ed eventi</h1>
    </div>
    <div class="quick-links">
        <a class="btn btn-secondary" href="<?= u('/eventi.php') ?>" target="_blank" rel="noopener">Apri la pagina Eventi ↗</a>
        <?php if (can('articoli.write')): ?><a class="btn" href="<?= u('/articoli/edit.php') ?>">+ Nuovo articolo</a><?php endif; ?>
    </div>
</div>

<?php if (!$ready): ?>
    <div class="flash flash-error" role="alert">Non riesco a preparare la tabella degli articoli. Applica la migrazione database/migrations/011_articoli_eventi.sql e ricarica la pagina.</div>
<?php elseif (!$articoli): ?>
    <section class="admin-panel"><h2>Ancora nessun articolo</h2><p>Racconta il primo evento del club: una serata in sede, una trasferta, una festa. Il testo semplice basta, con foto di copertina facoltativa.</p></section>
<?php else: ?>
    <table class="data-table">
        <thead><tr><th>Data evento</th><th>Titolo</th><th>Stato</th><th>Azioni</th></tr></thead>
        <tbody>
        <?php foreach ($articoli as $a): ?>
            <tr>
                <td><?= h(articoli_date_label($a['data_evento'])) ?></td>
                <td><?= h($a['titolo']) ?></td>
                <td><?= (int)$a['pubblicato'] === 1 ? '<span class="badge badge-green">Pubblicato</span>' : '<span class="badge badge-gray">Bozza</span>' ?></td>
                <td class="td-actions">
                    <?php if (can('articoli.write')): ?><a class="btn btn-secondary btn-sm" href="<?= u('/articoli/edit.php?id=' . (int)$a['id_articolo']) ?>">Modifica</a><?php endif; ?>
                    <?php if ((int)$a['pubblicato'] === 1): ?><a class="btn btn-secondary btn-sm" href="<?= u('/evento.php?slug=' . rawurlencode($a['slug'])) ?>" target="_blank" rel="noopener">Vedi ↗</a><?php endif; ?>
                    <?php if (can('articoli.delete')): ?>
                    <form method="post" action="<?= u('/articoli/delete.php') ?>" style="display:inline" data-confirm="Eliminare definitivamente «<?= h($a['titolo']) ?>»?">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$a['id_articolo'] ?>">
                        <button class="btn btn-danger btn-sm" type="submit">Elimina</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
