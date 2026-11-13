<?php
require_once __DIR__ . '/public_layout.php';

function soci_header(string $title, ?array $socio = null, string $tab = ''): void
{
    $flash = get_flash_message();
    public_header($title . ' · Area soci | Inter Club Javier Zanetti Brindisi', 'Area soci dell’Inter Club Javier Zanetti Brindisi: prenota il tuo posto per le partite in sede.', '/soci-area/', [], ['noindex' => true, 'active' => 'soci', 'styles' => ['soci-area', 'soci-profile']]);
    ?>
<main id="contenuto" class="sa-main<?= $socio ? ' sa-main-in' : '' ?>">
<?php if ($socio): ?>
<div class="sa-bar">
    <div class="sa-bar-who"><span class="sa-avatar" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($socio['nome'], 0, 1) . mb_substr($socio['cognome'], 0, 1))) ?></span><span><small>AREA SOCI</small><strong><?= h($socio['nome'] . ' ' . $socio['cognome']) ?></strong></span></div>
    <nav class="sa-tabs" aria-label="Il mio profilo">
    <?php foreach (['prenotazioni' => 'Le mie prenotazioni', 'partite' => 'Partite in sede', 'account' => 'Account'] as $key => $label): ?>
        <a href="<?= u('/soci-area/index.php') ?>#<?= $key ?>"<?= $tab === $key ? ' aria-current="true"' : '' ?>><?= h($label) ?></a>
    <?php endforeach; ?>
    </nav>
    <form method="post" action="<?= u('/soci-area/logout.php') ?>" class="sa-logout"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button type="submit">Esci</button></form>
</div>
<?php endif; ?>
<?php if ($flash): ?><div class="sa-flash sa-flash-<?= h($flash['type']) ?>" role="status"><?= h($flash['message']) ?></div><?php endif; ?>
<?php
}

function soci_footer(): void
{
    echo "</main>\n";
    public_footer();
}

function soci_message_page(string $title, string $message, int $status = 200, ?array $socio = null): void
{
    http_response_code($status);
    soci_header($title, $socio);
    echo '<section class="sa-card sa-narrow"><span class="sa-kicker">AREA SOCI</span><h1>' . h($title) . '</h1><p>' . h($message) . '</p><a class="sa-btn" href="' . u($socio ? '/soci-area/index.php' : '/soci-area/login.php') . '">' . ($socio ? 'Torna al tuo profilo' : 'Vai all’accesso') . '</a></section>';
    soci_footer();
    exit;
}

function soci_verify_csrf(): void
{
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        soci_message_page('Pagina scaduta', 'Il modulo è rimasto aperto troppo a lungo. Ricarica la pagina e riprova.', 403, current_socio());
    }
}
