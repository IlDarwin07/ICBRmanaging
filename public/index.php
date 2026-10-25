<?php
require_once __DIR__ . '/../includes/public_layout.php';
start_secure_session();
$errors = $_SESSION['contact_errors'] ?? [];
$old = $_SESSION['contact_old'] ?? [];
$sent = $_SESSION['contact_sent'] ?? false;
unset($_SESSION['contact_errors'], $_SESSION['contact_old'], $_SESSION['contact_sent']);
public_header('Da soli si tifa. Insieme si vive.', 'Inter Club Javier Zanetti Brindisi: partite, amicizie e passione nerazzurra. Scopri il club, il tesseramento e la nostra sede a ' . $site['indirizzo'] . '.');
?>
<main id="contenuto">
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-copy">
            <div class="eyebrow"><span class="tiny-square"></span> BRINDISI NERAZZURRA · STAGIONE <?= h($site['stagione']) ?></div>
            <h1 id="hero-title"><?= nl2br(h($site['hero_titolo'])) ?></h1>
            <p><?= nl2br(h($site['hero_testo'])) ?></p>
            <a class="public-button button-white" href="#tesseramento"><?= h($site['hero_pulsante']) ?> <span aria-hidden="true">↗</span></a>
            <div class="hero-bottom"><span>DAL <?= h($site['fondazione']) ?>, INSIEME.</span><a href="#club" aria-label="Scopri il club">SCOPRI IL CLUB <span aria-hidden="true">↓</span></a></div>
        </div>
        <figure class="hero-photo">
            <img src="<?= u('/assets/images/' . $site['hero_foto']) ?>" width="1430" height="804" fetchpriority="high" alt="Un angolo della vita dell’Inter Club Brindisi.">
            <figcaption><span><?= h($site['indirizzo']) ?><br><?= h($site['citta']) ?></span><span>IL NOSTRO POSTO<br>NEL MONDO.</span></figcaption>
            <span class="photo-credit"><?= h(site_image_credit($site['hero_foto'])) ?></span>
        </figure>
    </section>
    <div class="manifesto-strip" aria-label="Il nostro spirito"><span>BRINDISI NEL CUORE.</span><span class="strip-mark" aria-hidden="true">/</span><span>L’INTER NELL’ANIMA.</span><span class="strip-mark" aria-hidden="true">/</span><span>INSIEME, SEMPRE.</span></div>

    <section class="section club-section" id="club" aria-labelledby="club-title">
        <div class="section-intro"><span class="eyebrow">QUESTI SIAMO NOI</span><h2 id="club-title"><?= nl2br(h($site['club_titolo'])) ?></h2></div>
        <div class="club-layout">
            <div class="club-story"><p class="large-copy"><?= h($site['club_intro']) ?></p>
                <?php foreach (preg_split('/\n\s*\n/', $site['club_testo']) as $paragraph): ?><p><?= nl2br(h($paragraph)) ?></p><?php endforeach; ?>
                <a class="text-link" href="#contatti">Facciamo due chiacchiere <span aria-hidden="true">↗</span></a>
                <p class="source-note">La storia e la sede del club sono raccontate da <a href="<?= h($site['fonte_club']) ?>" target="_blank" rel="noopener noreferrer">Agenda Brindisi ↗</a>.</p>
            </div>
            <figure class="club-memory"><img src="<?= u('/assets/images/' . $site['ricordi_foto']) ?>" width="1024" height="576" loading="eager" alt="La sede e i ricordi nerazzurri del club."><figcaption><span>Un posto, tante storie.</span><small><?= h(site_image_credit($site['ricordi_foto'])) ?></small></figcaption></figure>
        </div>
    </section>

    <section class="membership-section section" id="tesseramento" aria-labelledby="membership-title">
        <div class="membership-heading"><div><span class="eyebrow">TESSERAMENTO <?= h($site['stagione']) ?></span><h2 id="membership-title"><?= nl2br(h($site['tesseramento_titolo'])) ?></h2></div><p>Ci sei anche tu?<br>Ti aspettiamo.</p></div>
        <div class="membership-grid">
            <?php foreach ([1,2,3] as $i): ?><article class="membership-card <?= $i === 3 ? 'membership-card-outline' : '' ?>"><span class="membership-tag">LA TUA TESSERA / 0<?= $i ?></span><h3><?= h($site["tessera_{$i}_titolo"]) ?></h3><p><?= h($site["tessera_{$i}_testo"]) ?></p><a href="#contatti" data-topic="Tesseramento" data-message="<?= h('Ciao! Mi dite qualcosa in più sulla tessera ' . $site["tessera_{$i}_titolo"] . '?') ?>">Parliamone <span aria-hidden="true">↗</span></a></article><?php endforeach; ?>
        </div>
        <div class="membership-footnote"><p>Quote, scadenze, kit e disponibilità sono confermati dal direttivo per la stagione in corso. La richiesta di informazioni non costituisce tesseramento né prenotazione di biglietti.</p><a href="<?= h($site['fonte_inter']) ?>" target="_blank" rel="noopener noreferrer">Il mondo Inter Club su inter.it ↗</a></div>
        <div class="joining-steps"><div><span>01</span><p><strong>Scrivici.</strong> Dicci se vuoi iscriverti o rinnovare.</p></div><div><span>02</span><p><strong>Conosciamoci.</strong> Il direttivo ti spiega quote e modalità.</p></div><div><span>03</span><p><strong>Vivi il club.</strong> Completa l’adesione con il direttivo.</p></div></div>
    </section>

    <section class="venue-section" id="sede" aria-labelledby="venue-title">
        <figure class="venue-photo"><img src="<?= u('/assets/images/' . $site['sede_foto']) ?>" width="1024" height="576" loading="eager" alt="La sede dell’Inter Club Javier Zanetti Brindisi."><figcaption><?= h(site_image_credit($site['sede_foto'])) ?></figcaption></figure>
        <div class="venue-copy"><span class="eyebrow">CI VEDIAMO AL CLUB</span><h2 id="venue-title"><?= nl2br(h($site['sede_titolo'])) ?></h2><address><?= h($site['indirizzo']) ?><br><?= h($site['citta']) ?></address><p><?= nl2br(h($site['sede_testo'])) ?></p><a class="public-button button-blue" href="<?= h($site['mappe']) ?>" target="_blank" rel="noopener noreferrer">Portami al club <span aria-hidden="true">↗</span></a><p class="source-note">Le aperture si concordano con il direttivo.</p></div>
    </section>

    <section class="section contact-section" id="contatti" aria-labelledby="contact-title">
        <div class="contact-copy"><span class="eyebrow">SCRIVICI, SIAMO QUI</span><h2 id="contact-title"><?= nl2br(h($site['contatti_titolo'])) ?></h2><p><?= nl2br(h($site['contatti_testo'])) ?></p><p class="contact-note">Lasciaci un recapito giusto e ti ricontattiamo. Non parte un’email automatica: il messaggio arriva direttamente nella nostra area riservata.</p></div>
        <div class="contact-form-panel">
            <?php if ($sent): ?><div class="contact-success" role="status"><span class="eyebrow">MESSAGGIO RICEVUTO</span><h3>CI SIAMO. GRAZIE!</h3><p>La tua richiesta è stata registrata nell’area riservata del club. Il direttivo potrà risponderti al recapito che hai indicato.</p><a href="#tesseramento" class="text-link">Torna al tesseramento ↑</a></div><?php endif; ?>
            <?php if ($errors): ?><div class="contact-error" role="alert"><strong>Controlla la richiesta.</strong><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form method="post" action="<?= u('/contatti.php') ?>" class="public-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <div class="form-duo"><div><label for="contact-name">Nome e cognome *</label><input id="contact-name" name="nome" autocomplete="name" required maxlength="100" value="<?= h($old['nome'] ?? '') ?>"></div><div><label for="contact-email">Email *</label><input id="contact-email" type="email" name="email" autocomplete="email" required maxlength="190" value="<?= h($old['email'] ?? '') ?>"></div></div>
                <div class="form-duo"><div><label for="contact-phone">Telefono <span>(facoltativo)</span></label><input id="contact-phone" type="tel" name="telefono" autocomplete="tel" maxlength="30" value="<?= h($old['telefono'] ?? '') ?>"></div><div><label for="contact-topic">Di cosa parliamo? *</label><select id="contact-topic" name="oggetto" required><?php foreach (['Tesseramento','Rinnovo','Visita alla sede','Altre informazioni'] as $topic): ?><option <?= ($old['oggetto'] ?? '') === $topic ? 'selected' : '' ?>><?= h($topic) ?></option><?php endforeach; ?></select></div></div>
                <label for="contact-message">Il tuo messaggio *</label><textarea id="contact-message" name="messaggio" rows="4" required minlength="10" maxlength="3000" placeholder="Ciao, vorrei entrare a far parte del club…"><?= h($old['messaggio'] ?? '') ?></textarea>
                <div class="honeypot" aria-hidden="true"><label for="contact-website">Lascia questo campo vuoto</label><input id="contact-website" name="website" tabindex="-1" autocomplete="off"></div>
                <label class="privacy-check"><input type="checkbox" name="privacy" value="1" required><span>Ho letto l’<a href="<?= u('/privacy.php') ?>" target="_blank" rel="noopener">informativa privacy</a> e chiedo di essere ricontattato per questa richiesta. *</span></label>
                <button class="public-button button-blue" type="submit">Invia al direttivo <span aria-hidden="true">↗</span></button>
                <p class="form-fineprint">* Campi obbligatori. Non inserire documenti, codici fiscali o dati di minori nel messaggio.</p>
            </form>
        </div>
    </section>
    <section class="section faq-section" aria-labelledby="faq-title"><span class="eyebrow">PRIMA DI SCRIVERCI</span><h2 id="faq-title">DUE COSE DA SAPERE.</h2>
        <details><summary>Posso iscrivermi direttamente online?</summary><p>Qui puoi chiedere informazioni e lasciare il tuo recapito. L’adesione effettiva, la verifica dei dati e il pagamento vengono concordati con il direttivo: questo sito non incassa pagamenti.</p></details>
        <details><summary>La tessera garantisce i biglietti per le partite?</summary><p>No. Eventuali opportunità per i soci dipendono dalle regole di vendita, dalle disponibilità e dalle condizioni comunicate per ciascuna partita. Chiedi sempre conferma al direttivo prima di organizzare una trasferta.</p></details>
        <details><summary>Come faccio a conoscere gli orari della sede?</summary><p>Scrivici selezionando “Visita alla sede”. Ci mettiamo d’accordo prima di vederci in <?= h($site['indirizzo']) ?>, <?= h($site['citta']) ?>.</p></details>
    </section>
</main>
<?php public_footer(); ?>
