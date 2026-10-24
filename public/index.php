<?php
require_once __DIR__ . '/../includes/public_layout.php';
start_secure_session();
$errors = $_SESSION['contact_errors'] ?? [];
$old = $_SESSION['contact_old'] ?? [];
$sent = $_SESSION['contact_sent'] ?? false;
unset($_SESSION['contact_errors'], $_SESSION['contact_old'], $_SESSION['contact_sent']);
public_header('La nostra casa nerazzurra', 'Inter Club Javier Zanetti Brindisi: scopri il club, chiedi informazioni sul tesseramento e vieni a trovarci in via San Benedetto 8.');
?>
<main id="contenuto">
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-copy">
            <div class="eyebrow"><span class="tiny-square"></span> INTER CLUB JAVIER ZANETTI · BRINDISI</div>
            <h1 id="hero-title">LA NOSTRA<br>CASA.<br><span>NERAZZURRA.</span></h1>
            <p>Da Brindisi, con l’Inter nel cuore.<br>Le partite, le trasferte, le amicizie.<br>La stessa passione, vissuta insieme.</p>
            <a class="public-button button-white" href="#tesseramento">Entra nella famiglia <span aria-hidden="true">↗</span></a>
            <div class="hero-bottom"><span>DAL <?= h($site['fondazione']) ?>, INSIEME.</span><a href="#club" aria-label="Scopri il club">SCOPRI IL CLUB <span aria-hidden="true">↓</span></a></div>
        </div>
        <figure class="hero-photo">
            <img src="<?= u('/assets/images/club-sede.jpg') ?>" width="1430" height="804" fetchpriority="high" alt="L’interno della sede del club: volte in pietra, sciarpe nerazzurre e schermo per le partite. Foto d’archivio Agenda Brindisi.">
            <figcaption><span>VIA SAN BENEDETTO, 8<br>BRINDISI, PUGLIA</span><span>IL NOSTRO POSTO<br>NEL MONDO.</span></figcaption>
            <a class="photo-credit" href="<?= h($site['fonte_club']) ?>" target="_blank" rel="noopener noreferrer">Foto d’archivio: Agenda Brindisi, 2022 ↗</a>
        </figure>
    </section>
    <div class="manifesto-strip" aria-label="Il nostro spirito"><span>BRINDISI NEL CUORE.</span><span class="strip-mark" aria-hidden="true">/</span><span>L’INTER NELL’ANIMA.</span><span class="strip-mark" aria-hidden="true">/</span><span>INSIEME, SEMPRE.</span></div>

    <section class="section club-section" id="club" aria-labelledby="club-title">
        <div class="section-intro"><span class="eyebrow">01 / IL NOSTRO CLUB</span><h2 id="club-title">NON SOLO UNA SQUADRA.<br>UN MODO DI STARE INSIEME.</h2></div>
        <div class="club-layout">
            <div class="club-story"><p class="large-copy">Ci sono partite che ricordi.<br>E persone con cui vuoi viverle.</p>
                <p>Il nostro club porta il nome di Javier Zanetti. Dal 2003, a Brindisi, è un punto d’incontro per chi vuole condividere la passione nerazzurra, dentro e fuori dai novanta minuti.</p>
                <p>Una sede, tante storie e la voglia di ritrovarsi. Che tu sia interista da una vita o alla tua prima stagione, il primo passo è conoscerci.</p>
                <a class="text-link" href="#contatti">Facciamo due chiacchiere <span aria-hidden="true">↗</span></a>
                <p class="source-note">La storia e la sede del club sono raccontate da <a href="<?= h($site['fonte_club']) ?>" target="_blank" rel="noopener noreferrer">Agenda Brindisi ↗</a>.</p>
            </div>
            <div class="club-values">
                <article><span class="value-number">01</span><div><h3>LA PARTITA È PIÙ BELLA INSIEME.</h3><p>La sede è il nostro punto di ritrovo per condividere emozioni, attese e novanta minuti di passione.</p></div></article>
                <article><span class="value-number">02</span><div><h3>DA BRINDISI, VERSO L’INTER.</h3><p>Chiedi al direttivo informazioni su trasferte e iniziative: disponibilità e condizioni si verificano per ogni evento.</p></div></article>
                <article><span class="value-number">03</span><div><h3>UNA FAMIGLIA CHE TI SOMIGLIA.</h3><p>Una passione che unisce generazioni. Lo spazio per conoscersi e far crescere nuove amicizie nerazzurre.</p></div></article>
            </div>
        </div>
    </section>

    <section class="membership-section section" id="tesseramento" aria-labelledby="membership-title">
        <div class="membership-heading"><div><span class="eyebrow">02 / TESSERAMENTO</span><h2 id="membership-title">LA PASSIONE NON HA ETÀ.<br>LA TUA TESSERA TI ASPETTA.</h2></div><p>Nuovo socio o rinnovo?<br>Cominciamo da qui.</p></div>
        <div class="membership-grid">
            <article class="membership-card"><span class="membership-tag">PER CHI C’È SEMPRE</span><h3>SENIOR</h3><p>Vivi il club, condividi le partite e scopri le opportunità riservate ai soci Inter Club.</p><a href="#contatti" data-topic="Tesseramento" data-message="Ciao, vorrei informazioni sul tesseramento Senior.">Voglio diventare socio <span aria-hidden="true">↗</span></a></article>
            <article class="membership-card"><span class="membership-tag">PICCOLI CUORI NERAZZURRI</span><h3>JUNIOR</h3><p>La passione cresce insieme a te. Chiedi al club requisiti d’età e modalità di adesione per i più giovani.</p><a href="#contatti" data-topic="Tesseramento" data-message="Ciao, vorrei informazioni sul tesseramento Junior per mio/a figlio/a.">Informazioni Junior <span aria-hidden="true">↗</span></a></article>
            <article class="membership-card membership-card-outline"><span class="membership-tag">STESSI COLORI, NUOVA STAGIONE</span><h3>RINNOVO</h3><p>Sei già dei nostri? Contatta il direttivo per confermare la tua adesione e aggiornare i tuoi riferimenti.</p><a href="#contatti" data-topic="Rinnovo" data-message="Ciao, sono già socio/a e vorrei informazioni sul rinnovo.">Resto in famiglia <span aria-hidden="true">↗</span></a></article>
        </div>
        <div class="membership-footnote"><p>Quote, scadenze, kit e disponibilità sono confermati dal direttivo per la stagione in corso. La richiesta di informazioni non costituisce tesseramento né prenotazione di biglietti.</p><a href="<?= h($site['fonte_inter']) ?>" target="_blank" rel="noopener noreferrer">Il mondo Inter Club su inter.it ↗</a></div>
        <div class="joining-steps"><div><span>01</span><p><strong>Scrivici.</strong> Dicci se vuoi iscriverti o rinnovare.</p></div><div><span>02</span><p><strong>Conosciamoci.</strong> Il direttivo ti spiega quote e modalità.</p></div><div><span>03</span><p><strong>Vivi il club.</strong> Completa l’adesione con il direttivo.</p></div></div>
    </section>

    <section class="venue-section" id="sede" aria-labelledby="venue-title">
        <figure class="venue-photo"><img src="<?= u('/assets/images/club-interno.jpg') ?>" width="1024" height="576" loading="lazy" alt="La sala della sede dell’Inter Club Javier Zanetti Brindisi. Foto d’archivio Agenda Brindisi."><figcaption>LA NOSTRA SEDE · FOTO D’ARCHIVIO 2022</figcaption></figure>
        <div class="venue-copy"><span class="eyebrow">03 / CI VEDIAMO AL CLUB</span><h2 id="venue-title">IL NOSTRO<br>PUNTO DI<br>RITROVO.</h2><address><?= h($site['indirizzo']) ?><br><?= h($site['citta']) ?></address><p>Prima di passare, scrivici per concordare un incontro e conoscere le aperture. Non pubblichiamo orari non confermati.</p><a class="public-button button-blue" href="<?= h($site['mappe']) ?>" target="_blank" rel="noopener noreferrer">Come arrivare <span aria-hidden="true">↗</span></a><p class="source-note">Indirizzo riportato da <a href="<?= h($site['fonte_club']) ?>" target="_blank" rel="noopener noreferrer">Agenda Brindisi ↗</a>. Conferma l’apertura con il direttivo.</p></div>
    </section>

    <section class="section contact-section" id="contatti" aria-labelledby="contact-title">
        <div class="contact-copy"><span class="eyebrow">04 / PARLIAMONE</span><h2 id="contact-title">IL PROSSIMO<br>“FORZA INTER”<br>DICIAMOLO<br>INSIEME.</h2><p>Vuoi tesserarti, visitare la sede o sapere di più? Lascia un messaggio al direttivo.</p><p class="contact-note">Il messaggio viene salvato nell’area riservata del club. Ti ricontatteremo al recapito indicato; non viene inviata una conferma automatica via email.</p></div>
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
        <details><summary>Come faccio a conoscere gli orari della sede?</summary><p>Scrivici selezionando “Visita alla sede”. Ti chiediamo di concordare l’apertura prima di raggiungerci in via San Benedetto 8 a Brindisi.</p></details>
    </section>
</main>
<?php public_footer(); ?>
