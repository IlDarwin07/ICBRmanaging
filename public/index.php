<?php
require_once __DIR__ . '/../includes/public_layout.php';
start_secure_session();
$memberships = site_membership_types();
$faq = require __DIR__ . '/../config/public_faq.php';
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
                <a class="text-link" href="#contatti">Contatta il direttivo <span aria-hidden="true">↗</span></a>
            </div>
            <figure class="club-memory"><img src="<?= u('/assets/images/' . $site['ricordi_foto']) ?>" width="1024" height="576" loading="eager" alt="La sede e i ricordi nerazzurri del club."><figcaption><span>Un posto, tante storie.</span><small><?= h(site_image_credit($site['ricordi_foto'])) ?></small></figcaption></figure>
        </div>
    </section>

    <section class="membership-section section" id="tesseramento" aria-labelledby="membership-title">
        <div class="membership-heading"><div><span class="eyebrow">TESSERAMENTO <?= h($site['stagione']) ?></span><h2 id="membership-title"><?= nl2br(h($site['tesseramento_titolo'])) ?></h2></div><p>Ci sei anche tu?<br>Ti aspettiamo.</p></div>
        <p class="membership-lead">Gli stessi colori, modi diversi di esserci. Premi Info sulla tessera che ti interessa: ti raccontiamo come funziona.</p>
        <div class="membership-grid membership-grid-complete">
            <?php foreach ($memberships as $i=>$membership): ?>
            <article class="membership-card flip-card <?= $i===6 ? 'membership-card-outline' : '' ?>" id="tessera-<?= h($membership['tipo']) ?>" aria-label="<?= h($site["tessera_{$i}_titolo"]) ?>">
                <div class="membership-flipper">
                    <div class="membership-face membership-front">
                        <div class="membership-card-top"><span class="membership-tag"><?= $i===6 ? 'UPGRADE SENIOR' : 'IL TUO MODO DI ESSERCI' ?></span><span class="membership-season"><?= h($site['stagione']) ?></span></div>
                        <h3><?= h($site["tessera_{$i}_titolo"]) ?></h3>
                        <p class="membership-audience"><?= h($site["tessera_{$i}_testo"]) ?></p>
                        <div class="membership-price"><strong><?= h($site["tessera_{$i}_quota"]) ?></strong><span><?= h($i===6 ? 'in aggiunta alla tessera Senior' : 'quota nuovo socio') ?></span></div>
                        <button class="membership-info-toggle" type="button" data-flip-open aria-expanded="false" aria-controls="retro-<?= h($membership['tipo']) ?>" aria-label="<?= h('Info su '.$site["tessera_{$i}_titolo"]) ?>">Info</button>
                    </div>
                    <div class="membership-face membership-back" id="retro-<?= h($membership['tipo']) ?>" role="region" aria-label="<?= h('Dettagli '.$site["tessera_{$i}_titolo"]) ?>">
                        <div class="membership-back-heading"><span class="membership-tag">LA TESSERA, DA VICINO</span><button class="membership-back-toggle" type="button" data-flip-close aria-label="<?= h('Torna alla tessera '.$site["tessera_{$i}_titolo"]) ?>">Indietro</button></div>
                        <h3><?= h($site["tessera_{$i}_titolo"]) ?></h3>
                        <p class="membership-description"><?= h($site["tessera_{$i}_dettagli"]) ?></p>
                        <?php if ($i===6): ?><p class="membership-plus-benefit">La <a href="https://www.inter.it/it/inter-club" target="_blank" rel="noopener noreferrer">pagina ufficiale Inter Club</a> prevede per i soci Plus una vendita anticipata per le partite casalinghe di Campionato e Champions League, subordinata al programma Loyalty INTERISTA e ai relativi termini. <a href="#faq-plus" data-faq-link>Leggi le condizioni nelle FAQ ↗</a></p><?php endif; ?>
                        <a class="membership-cta" href="#contatti" data-topic="Tesseramento" data-message="<?= h('Ciao! Vorrei informazioni sulla '.($i===6 ? '' : 'tessera ').$site["tessera_{$i}_titolo"].' per la stagione '.$site['stagione'].': potete confermarmi quota, requisiti e servizi inclusi?') ?>">Contatta il direttivo <span aria-hidden="true">↗</span></a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <p class="membership-quote-note"><?= h($site['tessera_quote_nota']) ?></p>
        <div class="membership-info">
            <div class="membership-benefits"><span class="eyebrow">COSA SIGNIFICA ESSERE SOCIO</span><h3>Una famiglia.<br>Tanti modi di esserci.</h3>
                <p>La <a href="https://www.inter.it/it/notizie/inter-club-campagna-tesseramenti-2026-2027" target="_blank" rel="noopener noreferrer">campagna ufficiale Inter Club 2026/27</a> prevede Welcome Kit Senior o Junior fino a esaurimento scorte, agevolazioni e prelazioni per partite selezionate e iniziative dedicate ai soci.</p>
                <p>Non considerare biglietti, trasferte o altri costi inclusi nella quota senza una conferma del direttivo: ti spieghiamo tutto prima dell’iscrizione.</p>
            </div>
            <div class="membership-questions" aria-label="Domande sulle formule di tesseramento">
                <details><summary>Chi può scegliere Fuori sede?</summary><div class="membership-answer"><p>La formula è riservata a chi risiede fuori dal Comune di Brindisi, anche in un comune vicino. Conta la residenza fuori dal territorio comunale, non quanto sei lontano dalla sede.</p><a href="#contatti" data-topic="Tesseramento" data-message="Ciao! Risiedo fuori dal Comune di Brindisi e vorrei informazioni sulla formula Fuori sede.">Contatta il direttivo ↗</a></div></details>
                <details><summary>Family e Junior: chi può iscriversi?</summary><div class="membership-answer"><p>La tessera Family è attivabile in abbinamento a un tesseramento Fedelissimo: la quota Family non comprende automaticamente tutto il nucleo familiare. Tutti i soci under 14, cioè con meno di 14 anni, possono scegliere la Junior. Scrivici per organizzare l’iscrizione di un minore, senza inviare i suoi documenti nel modulo pubblico.</p></div></details>
                <details><summary>La Quota Plus comprende un biglietto?</summary><div class="membership-answer"><p>No: è un upgrade del tesseramento Senior, non un biglietto o una tessera autonoma. I benefit della <a href="https://www.inter.it/it/inter-club" target="_blank" rel="noopener noreferrer">pagina ufficiale Inter Club</a> prevedono una vendita anticipata, subordinata al programma Loyalty INTERISTA e ai relativi termini, non un posto garantito allo stadio.</p><a href="#faq-due-plus" data-faq-link>Non confonderla con il biglietto PLUS ↗</a></div></details>
                <details><summary>E se devo rinnovare la tessera?</summary><div class="membership-answer"><p>Contattaci per la quota applicabile oggi al tuo rinnovo. Il listino conserva anche le voci agevolate entro il <strong>30 giugno 2026</strong>: quella scadenza è passata e non è una promozione attualmente aperta.</p><dl class="membership-renewals"><?php foreach ($memberships as $membership): if ($membership['rinnovo']===null) continue; ?><div><dt><?= h($membership['nome']) ?></dt><dd><?= h($membership['rinnovo']) ?></dd></div><?php endforeach; ?></dl><p class="membership-history-note">Importi storici riservati ai rinnovi entro la scadenza indicata, non quote da versare oggi.</p></div></details>
            </div>
        </div>
        <div class="membership-footnote"><p>Quote, scadenze, kit e disponibilità sono confermati dal direttivo per la stagione in corso. La richiesta di informazioni non costituisce tesseramento né prenotazione di biglietti.</p><a href="<?= h($site['fonte_inter']) ?>" target="_blank" rel="noopener noreferrer">Il mondo Inter Club su inter.it ↗</a></div>
        <a class="membership-faq-link" href="#faq">Kit, MyInter e biglietti: altre risposte nelle FAQ <span aria-hidden="true">↗</span></a>
        <div class="joining-steps"><div><p><strong>Facciamo il primo passo.</strong> Contatta il direttivo per iscriverti o rinnovare.</p></div><div><p><strong>Trova la tua tessera.</strong> Scegliamo insieme la formula più adatta a te.</p></div><div><p><strong>Ci vediamo al club.</strong> Completa l’adesione e vivi con noi la stagione nerazzurra.</p></div></div>
    </section>

    <section class="venue-section" id="sede" aria-labelledby="venue-title">
        <figure class="venue-photo"><img src="<?= u('/assets/images/' . $site['sede_foto']) ?>" width="1024" height="576" loading="eager" alt="La sede dell’Inter Club Javier Zanetti Brindisi."><figcaption><?= h(site_image_credit($site['sede_foto'])) ?></figcaption></figure>
        <div class="venue-copy"><span class="eyebrow">CI VEDIAMO AL CLUB</span><h2 id="venue-title"><?= nl2br(h($site['sede_titolo'])) ?></h2><address><?= h($site['indirizzo']) ?><br><?= h($site['citta']) ?></address><p><?= nl2br(h($site['sede_testo'])) ?></p><a class="public-button button-blue" href="<?= h($site['mappe']) ?>" target="_blank" rel="noopener noreferrer">Portami al club <span aria-hidden="true">↗</span></a><p class="source-note">Le aperture si concordano con il direttivo.</p></div>
    </section>

    <section class="section contact-section" id="contatti" aria-labelledby="contact-title">
        <div class="contact-copy"><span class="eyebrow">IL PROSSIMO CORO, CANTIAMOLO INSIEME</span><h2 id="contact-title"><?= nl2br(h($site['contatti_titolo'])) ?></h2><p><?= nl2br(h($site['contatti_testo'])) ?></p><p class="contact-note">Lascia un recapito a cui possiamo risponderti. La richiesta arriva al direttivo nell’area riservata: l’invio del modulo non completa il tesseramento.</p></div>
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
    <section class="section faq-section" id="faq" aria-labelledby="faq-title">
        <div class="faq-heading"><div><span class="eyebrow">FACCIAMO CHIAREZZA</span><h2 id="faq-title">Il dubbio ci sta.<br>La risposta è qui.</h2></div><p>Dal kit alla prima volta a San Siro.<br>Risposte semplici, fonti ufficiali, niente promesse a metà.</p></div>
        <div class="faq-groups"><?php foreach ($faq['groups'] as $group): ?>
            <section class="faq-group" id="<?= h($group['id']) ?>" aria-labelledby="<?= h($group['id']) ?>-title">
                <header class="faq-group-heading"><h3 id="<?= h($group['id']) ?>-title"><?= h($group['title']) ?></h3><p><?= h($group['intro']) ?></p></header>
                <?php foreach ($group['items'] as $item): ?>
                <details id="<?= h($item['id']) ?>"><summary><?= h($item['question']) ?></summary><div class="faq-answer">
                    <span class="faq-kind"><?= $item['kind'] === 'official' ? 'INFORMAZIONI UFFICIALI INTER' : 'INDICAZIONI ICBR' ?></span>
                    <p><?= h($item['answer']) ?><?php foreach ($item['sources'] as $source): ?> <a class="faq-source" href="<?= h($source['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($source['label']) ?><span aria-hidden="true"> ↗</span></a><?php endforeach; ?></p>
                    <?php if ($item['kind'] === 'local'): ?><a class="faq-contact" href="#contatti" data-topic="<?= $item['id'] === 'faq-visita' ? 'Visita alla sede' : 'Tesseramento' ?>">Chiedi al direttivo <span aria-hidden="true">↗</span></a><?php endif; ?>
                </div></details>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?></div>
        <div class="faq-note"><a href="#contatti">Non trovi la tua risposta? Scrivici ↗</a></div>
    </section>
</main>
<?php public_footer(); ?>
