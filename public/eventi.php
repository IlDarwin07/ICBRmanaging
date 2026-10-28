<?php
require_once __DIR__ . '/../includes/public_layout.php';
require_once __DIR__ . '/../includes/articoli.php';

$articles = articoli_public_list(60);
public_header(
    'Eventi e racconti del club | Inter Club Javier Zanetti Brindisi',
    'Serate in sede, trasferte e momenti vissuti insieme: gli eventi organizzati dall’Inter Club Javier Zanetti Brindisi.',
    '/eventi.php'
);
?>
<main id="contenuto" class="section events-page">
    <header class="events-heading">
        <span class="eyebrow">EVENTI E RACCONTI</span>
        <h1>Quello che<br>viviamo insieme.</h1>
        <p>Serate in sede, trasferte, feste: i momenti del club raccontati da chi c’era.</p>
    </header>
    <?php if (!$articles): ?>
        <p class="events-empty">Stiamo preparando i primi racconti. Intanto <a class="text-link" href="<?= u('/index.php') ?>#contatti">scrivici</a> o passa a trovarci in sede.</p>
    <?php else: ?>
    <div class="events-grid">
        <?php foreach ($articles as $a): ?>
        <article class="event-card">
            <a class="event-card-link" href="<?= u('/evento.php?slug=' . rawurlencode($a['slug'])) ?>">
                <figure class="event-cover"><?= site_picture((string)($a['copertina'] ?? ''), (string)($a['copertina_alt'] ?: $a['titolo']), 1024, 576) ?></figure>
                <div class="event-body">
                    <p class="event-meta"><time datetime="<?= h($a['data_evento']) ?>"><?= h(articoli_date_label($a['data_evento'])) ?></time><?php if (!empty($a['luogo'])): ?> · <?= h($a['luogo']) ?><?php endif; ?></p>
                    <h2><?= h($a['titolo']) ?></h2>
                    <p><?= h($a['sommario']) ?></p>
                    <span class="text-link">Leggi il racconto <span aria-hidden="true">↗</span></span>
                </div>
            </a>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
<?php public_footer(); ?>
