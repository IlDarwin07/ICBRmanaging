<?php
require_once __DIR__ . '/articoli.php';
$homeLatestArticle = null;
try {
    $homeLatestArticle = get_db_connection()->query(
        'SELECT slug, titolo, sommario, data_evento, copertina, copertina_alt FROM articoli_eventi WHERE pubblicato = 1 ORDER BY created_at DESC, id_articolo DESC LIMIT 1'
    )->fetch() ?: null;
} catch (Throwable $e) {
    error_log('ICBR: articolo in evidenza non disponibile.');
}
?>
<?php if ($homeLatestArticle): ?>
<style>
.home-latest-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:clamp(24px,5vw,64px);align-items:center}
.home-latest-photo{display:block;margin:0;background:#0b0e18;border-radius:6px;overflow:hidden}
.home-latest-photo img{display:block;width:100%;height:auto;max-height:520px;object-fit:contain}
.home-latest-copy{min-width:0}
.home-latest-copy h2{font-size:clamp(30px,4vw,58px);line-height:1.08;overflow-wrap:anywhere;margin:14px 0 20px}
.home-latest-copy h2 a{color:inherit;text-decoration:none}
.home-latest-copy h2 a:hover{text-decoration:underline}
.home-latest-copy p{font-size:clamp(17px,2vw,21px);line-height:1.6;margin:0 0 24px}
.home-latest-date{display:block;font-size:14px;margin-top:12px}
.home-latest-links{display:flex;flex-wrap:wrap;align-items:center;gap:20px}
.home-latest-layout-no-photo{grid-template-columns:1fr}
@media(max-width:700px){.home-latest-layout{grid-template-columns:1fr}.home-latest-photo img{max-height:420px}}
</style>
<section class="section home-latest" id="in-evidenza" aria-labelledby="home-latest-title">
    <?php $homeLatestUrl = u('/evento.php?slug=' . rawurlencode($homeLatestArticle['slug'])); ?>
    <?php $homeLatestHasPhoto = !empty($homeLatestArticle['copertina']) && site_image_valid($homeLatestArticle['copertina']); ?>
    <article class="home-latest-layout<?= $homeLatestHasPhoto ? '' : ' home-latest-layout-no-photo' ?>">
        <?php if ($homeLatestHasPhoto): ?>
        <a class="home-latest-photo" href="<?= $homeLatestUrl ?>">
            <?= site_picture($homeLatestArticle['copertina'], (string)($homeLatestArticle['copertina_alt'] ?: $homeLatestArticle['titolo']), 1200, 900) ?>
        </a>
        <?php endif; ?>
        <div class="home-latest-copy">
            <span class="eyebrow">IN EVIDENZA · DAL CLUB</span>
            <time class="home-latest-date" datetime="<?= h($homeLatestArticle['data_evento']) ?>"><?= h(articoli_date_label($homeLatestArticle['data_evento'])) ?></time>
            <h2 id="home-latest-title"><a href="<?= $homeLatestUrl ?>"><?= h($homeLatestArticle['titolo']) ?></a></h2>
            <p><?= h($homeLatestArticle['sommario']) ?></p>
            <div class="home-latest-links">
                <a class="public-button button-blue" href="<?= $homeLatestUrl ?>">Leggi l’articolo <span aria-hidden="true">↗</span></a>
                <a class="text-link" href="<?= u('/eventi.php') ?>">Tutti gli eventi <span aria-hidden="true">↗</span></a>
            </div>
        </div>
    </article>
</section>
<?php endif; ?>
