<?php
$utente = current_user();
$flash = get_flash_message();
$base = base_url();
$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$asset_version = function (string $relative): string {
    $file = __DIR__ . '/../public/assets/' . $relative;
    return is_file($file) ? '?v=' . filemtime($file) : '';
};
$groups = [
    'Contabilità' => [['Prima nota','/prima_nota/list.php','prima_nota.view','/prima_nota/']],
    'Comunicazioni' => [
        ['WhatsApp','/messaggi/index.php','whatsapp.view','/messaggi/'],
        ['Richieste dal sito','/richieste/list.php','richieste.view','/richieste/'],
    ],
    'Gestione' => [
        ['Importa soci','/importazioni/upload.php','importazioni.write','/importazioni/'],
        ['Sito vetrina','/sito/edit.php','sito.write','/sito/'],
        ['Direttivo','/utenti/list.php','utenti.manage','/utenti/'],
        ['Registro attività','/attivita/list.php','attivita.view','/attivita/'],
        ['Stagioni','/stagioni/list.php','configurazione.view','/stagioni/'],
        ['Tipologie tessera','/tipologie/list.php','configurazione.view','/tipologie/'],
    ],
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title ?? 'Gestionale') ?> - Inter Club Brindisi</title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#011ea0">
    <?php foreach (['base','style','admin','accessibility','details','brand','editor','access-control','workspace'] as $sheet): ?>
    <link rel="stylesheet" href="<?= h($base) ?>/assets/css/<?= h($sheet) ?>.css<?= h($asset_version('css/' . $sheet . '.css')) ?>">
    <?php endforeach; ?>
    <script>window.icbrAccess=<?= json_encode(['admin'=>($utente['ruolo'] ?? '')==='admin','permissions'=>$utente['permessi'] ?? [],'routes'=>permission_routes(),'current'=>request_route()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
    <script src="<?= h($base) ?>/assets/js/access-control.js<?= h($asset_version('js/access-control.js')) ?>" defer></script>
    <link rel="icon" type="image/jpeg" href="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>">
    <script src="<?= h($base) ?>/assets/js/site.js<?= h($asset_version('js/site.js')) ?>" defer></script>
    <script src="<?= h($base) ?>/assets/js/admin.js<?= h($asset_version('js/admin.js')) ?>" defer></script>
</head>
<body class="admin-body">
<a href="#contenuto-admin" class="skip-link">Salta al contenuto</a>
<?php if (getenv('ICBR_PREVIEW') === '1'): ?><div class="admin-demo-note">Anteprima con soli dati sintetici. Nessuna connessione al database del club.</div><?php endif; ?>
<?php if (!security_ready()): ?><div class="flash flash-error">Migrazione 010 non applicata: gestione direttivo e registro attività non ancora attivi. Gli accessi non admin restano limitati.</div><?php endif; ?>
<header class="topbar">
    <a href="<?= u('/dashboard.php') ?>" class="topbar-brand"><img class="club-logo" src="<?= u('/assets/images/logo-inter-club-brindisi.jpg') ?>" alt="" width="48" height="48"><span class="admin-brand-copy"><strong>INTER CLUB <span>BRINDISI</span></strong><small>AREA RISERVATA</small></span></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="admin-navigation">Menu <span aria-hidden="true">☰</span></button>
    <nav class="topbar-nav" id="admin-navigation" aria-label="Gestionale">
        <a href="<?= u('/dashboard.php') ?>" <?= str_ends_with($current_path, '/dashboard.php') ? 'class="active" aria-current="page"' : '' ?>>Home</a>
        <?php foreach ([['Soci','/soci/list.php','soci.view','/soci/'],['Tesseramenti','/tesseramenti/list.php','tesseramenti.view','/tesseramenti/']] as [$label,$path,$permission,$prefix]): ?>
        <?php if (can($permission)): ?><a href="<?= u($path) ?>" <?= str_contains($current_path,$prefix) ? 'class="active" aria-current="location"' : '' ?>><?= h($label) ?></a><?php endif; ?>
        <?php endforeach; ?>
        <?php foreach ($groups as $group => $items): ?>
        <?php $visible = array_values(array_filter($items, fn($item) => can($item[2]))); if (!$visible) continue;
        $active = false; foreach ($visible as $item) { if (str_contains($current_path,$item[3])) $active = true; } ?>
        <details class="workspace-menu <?= $active ? 'active' : '' ?>">
            <summary><?= h($group) ?><span aria-hidden="true">⌄</span></summary>
            <div class="workspace-menu-items">
                <?php foreach ($visible as [$label,$path,$permission,$prefix]): ?>
                <a href="<?= u($path) ?>" <?= str_contains($current_path,$prefix) ? 'class="active" aria-current="location"' : '' ?>><?= h($label) ?></a>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endforeach; ?>
    </nav>
    <div class="topbar-user">
        <a href="<?= u('/index.php') ?>">Vedi sito ↗</a>
        <?php if ($utente): ?>
        <span title="<?= h($utente['etichetta_ruolo'] ?? $utente['ruolo']) ?>"><?= h($utente['nome'] . ' ' . $utente['cognome']) ?></span>
        <a href="<?= u('/logout.php') ?>">Esci</a>
        <?php endif; ?>
    </div>
</header>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var menus = Array.prototype.slice.call(document.querySelectorAll('.workspace-menu, .dash-config'));
    function closeOthers(current) {
        menus.forEach(function (menu) { if (menu !== current) menu.removeAttribute('open'); });
    }
    menus.forEach(function (menu) {
        var summary = menu.querySelector('summary');
        if (!summary) return;
        summary.addEventListener('click', function () { closeOthers(menu); });
    });
    document.addEventListener('click', function (event) {
        menus.forEach(function (menu) { if (!menu.contains(event.target)) menu.removeAttribute('open'); });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        menus.forEach(function (menu) { menu.removeAttribute('open'); });
    });
});
</script>
<main class="content" id="contenuto-admin">
    <?php if ($flash): ?><div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div><?php endif; ?>
