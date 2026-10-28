<?php
require_once __DIR__ . '/../includes/public_layout.php';
require_once __DIR__ . '/../includes/articoli.php';

$slug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
$article = preg_match('/\A[a-z0-9-]{1,160}\z/', $slug) ? articoli_public_find($slug) : null;

if (!$article) {
    http_response_code(404);
    public_header('Racconto non trovato | Inter Club Javier Zanetti Brindisi', 'Il racconto richiesto non è disponibile.', '/eventi.php');
    echo '<main id="contenuto" class="section events-page"><span class="eyebrow">EVENTI / 404</span><h1>Racconto non trovato</h1><p>Forse è stato rimosso o non è ancora pubblicato.</p><a class="text-link" href="' . u('/eventi.php') . '">Tutti gli eventi ↗</a></main>';
    public_footer();
    exit;
}

$path = '/evento.php?slug=' . rawurlencode($article['slug']);
$cover = (string)($article['copertina'] ?? '');
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $article['titolo'],
    'description' => $article['sommario'],
    'datePublished' => $article['data_evento'],
    'dateModified' => substr((string)$article['updated_at'], 0, 10),
    'author' => ['@type' => 'Organization', 'name' => 'Inter Club Javier Zanetti Brindisi'],
    'publisher' => ['@type' => 'Organization', 'name' => 'Inter Club Javier Zanetti Brindisi'],
];
if ($cover !== '' && site_image_valid($cover)) {
    $schema['image'] = site_absolute_url('/assets/images/' . $cover);
}
public_header($article['titolo'] . ' | Inter Club Javier Zanetti Brindisi', $article['sommario'], $path, [$schema]);
?>
<main id="contenuto" class="section events-page">
    <article class="event-article">
        <a class="text-link" href="<?= u('/eventi.php') ?>">← Tutti gli eventi</a>
        <header>
            <p class="event-meta"><time datetime="<?= h($article['data_evento']) ?>"><?= h(articoli_date_label($article['data_evento'])) ?></time><?php if (!empty($article['luogo'])): ?> · <?= h($article['luogo']) ?><?php endif; ?></p>
            <h1><?= h($article['titolo']) ?></h1>
            <p class="event-lead"><?= h($article['sommario']) ?></p>
        </header>
        <?php if ($cover !== '' && site_image_valid($cover)): ?>
        <figure class="event-hero"><?= site_picture($cover, (string)($article['copertina_alt'] ?: $article['titolo']), 1600, 900, true) ?></figure>
        <?php endif; ?>
        <div class="event-text">
            <?php foreach (articoli_paragraphs((string)$article['testo']) as $paragraph): ?><p><?= nl2br(h($paragraph)) ?></p><?php endforeach; ?>
        </div>
        <a class="public-button button-blue" href="<?= u('/index.php') ?>#contatti">Vieni a trovarci <span aria-hidden="true">↗</span></a>
    </article>
</main>
<?php public_footer(); ?>
