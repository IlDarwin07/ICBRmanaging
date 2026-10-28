<?php
require_once __DIR__ . '/../includes/public_layout.php';
start_secure_session();
$memberships = site_membership_types();
$faq = require __DIR__ . '/../config/public_faq.php';
$errors = $_SESSION['contact_errors'] ?? [];
$old = $_SESSION['contact_old'] ?? [];
$sent = $_SESSION['contact_sent'] ?? false;
unset($_SESSION['contact_errors'], $_SESSION['contact_old'], $_SESSION['contact_sent']);
$faqSchema = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => []];
foreach ($faq['groups'] as $group) foreach ($group['items'] as $item) {
    $faqSchema['mainEntity'][] = ['@type' => 'Question', 'name' => $item['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']]];
}
public_header(
    'Inter Club Brindisi · Tesseramento ' . $site['stagione'] . ' | Inter Club Javier Zanetti',
    'Inter Club Javier Zanetti Brindisi: partite dell’Inter in sede, trasferte e tesseramento ' . $site['stagione'] . ' da ' . $site['tessera_5_quota'] . '. Vieni a trovarci in ' . $site['indirizzo'] . '.',
    '/',
    [$faqSchema]
);
?>
<main id="contenuto">
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-copy">
            <p class="eyebrow"><span class="tiny-square" aria-hidden="true"></span> INTER CLUB BRINDISI · DAL <?= h($site['fondazione']) ?></p>
            <h1 id="hero-title"><?= nl2br(h($site['hero_titolo'])) ?></h1>
            <p><?= nl2br(h($site['hero_testo'])) ?></p>
            <a class="public-button button-white" href="#tesseramento"><?= h($site['hero_pulsante']) ?> <span aria-hidden="true">↗</span></a>
            <div class="hero-bottom"><a href="#club">SCOPRI IL CLUB <span aria-hidden="true">↓</span></a></div>
        </div>
        <figure class="hero-photo">
            <?php if (site_image_valid($site['hero_foto'])): ?><?= site_picture($site['hero_foto'], 'Tifosi nella sede dell’Inter Club Javier Zanetti Brindisi', 1600, 900, true) ?><?php endif; ?>
        </figure>
    </section>

    <?php require __DIR__ . '/../includes/home_latest_article.php'; ?>

    <section class="section club-section" id="club" aria-labelledby="club-title">
        <div class="section-intro"><span class="eyebrow">IL CLUB</span><h2 id="club-title"><?= nl2br(h($site['club_titolo'])) ?></h2></div>
        <div class="club-layout">
            <div class="club-story"><p class="large-copy"><?= h($site['club_intro']) ?></p>
                <?php foreach (preg_split('/\n\s*\n/', $site['club_testo']) as $paragraph): ?><p><?= nl2br(h($paragraph)) ?></p><?php endforeach; ?>
                <a class="text-link" href="#tesseramento">Scegli la tua tessera <span aria-hidden="true">↗</span></a>
            </div>
            <figure class="club-memory"><?= site_picture($site['ricordi_foto'], 'Cimeli e ricordi nerazzurri nella sede dell’Inter Club Brindisi', 1600, 900) ?></figure>
        </div>
    </section>

    <section class="membership-section section" id="tesseramento" aria-labelledby="membership-title">
        <div class="membership-heading"><div><span class="eyebrow">TESSERAMENTO <?= h($site['stagione']) ?></span><h2 id="membership-title"><?= nl2br(h($site['tesseramento_titolo'])) ?></h2></div></div>
        <div class="membership-grid membership-grid-complete">
            <?php foreach ($memberships as $i=>$membership): ?>
            <article class="membership-card flip-card <?= $i===6 ? 'membership-card-outline' : '' ?>" id="tessera-<?= h($membership['tipo']) ?>" aria-label="<?= h($site["tessera_{$i}_titolo"]) ?>">
                <div class="membership-flipper">
                    <div class="membership-face membership-front">
                        <div class="membership-card-top"><span class="membership-tag"><?= $i===6 ? 'UPGRADE SENIOR' : 'TESSERA' ?></span><span class="membership-season"><?= h($site['stagione']) ?></span></div>
                        <h3><?= h($site["tessera_{$i}_titolo"]) ?></h3>
                        <p class="membership-audience"><?= h($site["tessera_{$i}_testo"]) ?></p>
                        <div class="membership-price"><strong><?= h($site["tessera_{$i}_quota"]) ?></strong><span><?= h($i===6 ? 'in aggiunta alla tessera Senior' : 'quota nuovo socio') ?></span></div>
                        <button class="membership-info-toggle" type="button" data-flip-open aria-expanded="false" aria-controls="retro-<?= h($membership['tipo']) ?>" aria-label="<?= h('Info su '.$site["tessera_{$i}_titolo"]) ?>">Info</button>
                    </div>
                    <div class="membership-face membership-back" id="retro-<?= h($membership['tipo']) ?>" role="region" aria-label="<?= h('Dettagli '.$site["tessera_{$i}_titolo"]) ?>">
                        <div class="membership-back-heading"><span class="membership-tag">DETTAGLI</span><button class="membership-back-toggle" type="button" data-flip-close aria-label="<?= h('Torna alla tessera '.$site["tessera_{$i}_titolo"]) ?>">Indietro</button></div>
                        <h3><?= h($site["tessera_{$i}_titolo"]) ?></h3>
                        <p class="membership-description"><?= h($site["tessera_{$i}_dettagli"]) ?></p>
                        <?php if ($i===6): ?><p class="membership-plus-benefit">Vendita anticipata per le gare casalinghe di Campionato e Champions League. <a href="#faq-plus" data-faq-link>Condizioni ↗</a></p><?php endif; ?>
                        <a class="membership-cta" href="#contatti" data-topic="Tesseramento" data-message="<?= h('Ciao! Vorrei informazioni sulla '.($i===6 ? '' : 'tessera ').$site["tessera_{$i}_titolo"].' per la stagione '.$site['stagione'].'.') ?>">Richiedi la tessera <span aria-hidden="true">↗</span></a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <p class="membership-quote-note"><?= h($site['tessera_quote_nota']) ?></p>
        <div class="membership-info">
            <div class="membership-benefits"><span class="eyebrow">VANTAGGI DEI SOCI</span><h3>Welcome Kit, prelazioni<br>e sconti Inter Store.</h3>
                <p>Con la <a href="https://www.inter.it/it/notizie/inter-club-campagna-tesseramenti-2026-2027" target="_blank" rel="noopener noreferrer">tessera ufficiale Inter Club <?= h($site['stagione']) ?></a> ricevi il Welcome Kit (fino a esaurimento scorte), accedi a prelazioni su partite selezionate e alle iniziative riservate ai soci. <a href="#faq-soci">Tutti i dettagli ↗</a></p>
            </div>
            <div class="membership-questions" aria-label="Rinnovo della tessera">
                <details><summary>E se devo rinnovare la tessera?</summary><div class="membership-answer"><p>Quote agevolate valide per i rinnovi entro il <strong>30 giugno 2026</strong> (promozione chiusa). Scrivici per la quota attuale.</p><dl class="membership-renewals"><?php foreach ($memberships as $membership): if ($membership['rinnovo']===null) continue; ?><div><dt><?= h($membership['nome']) ?></dt><dd><?= h($membership['rinnovo']) ?></dd></div><?php endforeach; ?></dl><a href="#contatti" data-topic="Rinnovo" data-message="Ciao! Vorrei rinnovare la tessera per la stagione <?= h($site['stagione']) ?>: qual è la quota applicabile?">Contatta il direttivo ↗</a></div></details>
            </div>
        </div>
    </section>

    <section class="venue-section" id="sede" aria-labelledby="venue-title">
        <figure class="venue-photo"><?= site_picture($site['sede_foto'], 'Sede dell’Inter Club Javier Zanetti in ' . $site['indirizzo'] . ', Brindisi', 1024, 576) ?></figure>
        <div class="venue-copy"><span class="eyebrow">DOVE TROVARCI</span><h2 id="venue-title"><?= nl2br(h($site['sede_titolo'])) ?></h2><address><?= h($site['indirizzo']) ?><br><?= h($site['citta']) ?></address><p><?= nl2br(h($site['sede_testo'])) ?></p><a class="public-button button-blue" href="<?= h($site['mappe']) ?>" target="_blank" rel="noopener noreferrer">Portami al club <span aria-hidden="true">↗</span></a></div>
    </section>

    <section class="section contact-section" id="contatti" aria-labelledby="contact-title">
        <div class="contact-copy"><span class="eyebrow">CONTATTI</span><h2 id="contact-title"><?= nl2br(h($site['contatti_titolo'])) ?></h2><p><?= nl2br(h($site['contatti_testo'])) ?></p></div>
        <div class="contact-form-panel">
            <?php if ($sent): ?><div class="contact-success" role="status"><span class="eyebrow">MESSAGGGIO RICEVUTO</span><h3>CI SIAMO. GRAZIE!</h3><p>Il direttivo ti risponderà al recapito indicato.</p><a href="#tesseramento" class="text-link">Torna al tesseramento ↑</a></div><?php endif; ?>
            <?php if ($errors): ?><div class="contact-error" role="alert"><strong>Controlla la richiesta.</strong><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form method="post" action="<?= u('/contatti.php') ?>" class="public-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <div class="form-duo"><div><label for="contact-name">Nome e cognome *</label><input id="contact-name" name="nome" autocomplete="name" required maxlength="100" value="<?= h($old['nome'] ?? '') ?>"></div><div><label for="contact-email">Email *</label><input id="contact-email" type="email" name="email" autocomplete="email" required maxlength="190" value="<?= h($old['email'] ?? '') ?>"></div></div>
                <div class="form-duo"><div><label for="contact-phone">Telefono <span>(facoltativo)</span></label><input id="contact-phone" type="tel" name="telefono" autocomplete="tel" maxlength="30" value="<?= h($old['telefono'] ?? '') ?>"></div><div><label for="contact-topic">Di cosa parliamo? *</label><select id="contact-topic" name="oggetto" required><?php foreach (['Tesseramento','Rinnovo','Visita alla sede','Altre informazioni'] as $topic): ?><option <?= ($old['oggetto'] ?? '') === $topic ? 'selected' : '' ?>><?= h($topic) ?></option><?php endforeach; ?></select></div></div>
                <label for="contact-message">Il tuo messaggio *</label><textarea id="contact-message" name="messaggio" rows="4" required minlength="10" maxlength="3000" placeholder="Ciao, vorrei entrare a far parte del club…"><?= h($old['messaggio'] ?? '') ?></textarea>
                <div class="honeypot" aria-hidden="true"><label for="contact-website">Lascia questo campo vuoto</label><input id="contact-website" name="website" tabindex="-1" autocomplete="off"></div>
                <label class="privacy-check"><input type="checkbox" name="privacy" value="1" required><span>Ho letto l’<a href="<?= u('/privacy.php') ?>" target="_blank" rel="noopener">informativa privacy</a> e chiedo di essere ricontattato per questa richiesta. *</span></label>
                <button class="public-button button-blue" type="submit">Invia al direttivo <span aria-hidden="true">↗</span></button>
                <p class="form-fineprint">* Campi obbligatori. Non inserire documenti o dati di minori.</p>
            </form>
        </div>
    </section>
    <section class="section faq-section" id="faq" aria-labelledby="faq-title">
        <div class="faq-heading"><div><span class="eyebrow">FAQ INTER CLUB</span><h2 id="faq-title">Domande frequenti<br>sul tesseramento.</h2></div></div>
        <div class="faq-groups"><?php foreach ($faq['groups'] as $group): ?>
            <section class="faq-group" id="<?= h($group['id']) ?>" aria-labelledby="<?= h($group['id']) ?>-title">
                <header class="faq-group-heading"><h3 id="<?= h($group['id']) ?>-title"><?= h($group['title']) ?></h3></header>
                <?php foreach ($group['items'] as $item): ?>
                <details id="<?= h($item['id']) ?>"><summary><?= h($item['question']) ?></summary><div class="faq-answer">
                    <span class="faq-kind"><?= $item['kind'] === 'official' ? 'FONTE UFFICIALE INTER' : 'INTER CLUB BRINDISI' ?></span>
                    <p><?= h($item['answer']) ?><?php foreach ($item['sources'] as $source): ?> <a class="faq-source" href="<?= h($source['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($source['label']) ?><span aria-hidden="true"> ↗</span></a><?php endforeach; ?></p>
                    <?php if ($item['kind'] === 'local'): ?><a class="faq-contact" href="#contatti" data-topic="<?= $item['id'] === 'faq-visita' ? 'Visita alla sede' : 'Tesseramento' ?>">Chiedi al direttivo <span aria-hidden="true">↗</span></a><?php endif; ?>
                </div></details>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?></div>
    </section>
</main>
<?php public_footer(); ?>
