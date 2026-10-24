<?php
require_once __DIR__ . '/../includes/public_layout.php';
public_header('Privacy e cookie', 'Informazioni sul trattamento delle richieste di contatto inviate al club e sui cookie tecnici del sito.');
?>
<main id="contenuto" class="section privacy-page">
    <span class="eyebrow">INFORMATIVA · VERSIONE OTTOBRE 2026</span>
    <h1>PRIVACY E COOKIE.</h1>
    <p>Questa pagina descrive il funzionamento del modulo contatti del sito dell’Inter Club Javier Zanetti Brindisi. Il modulo serve soltanto per richieste di informazioni, non per completare il tesseramento o trasmettere documenti.</p>
    <h2>CHI RICEVE LA RICHIESTA</h2>
    <p>Il destinatario e titolare del trattamento per le richieste è l’Inter Club Javier Zanetti Brindisi, con recapito presso la sede in via San Benedetto 8, 72100 Brindisi. Per chiarimenti sul trattamento e per esercitare i tuoi diritti puoi scrivere tramite il <a href="<?= u('/index.php') ?>#contatti">modulo contatti</a>, indicando “Privacy” nel messaggio, oppure rivolgerti al club presso la sede.</p>
    <h2>DATI E FINALITÀ</h2>
    <p>Il modulo raccoglie nome, email, eventuale telefono, argomento e messaggio per consentire al direttivo di gestire la richiesta e ricontattarti. La base giuridica è la gestione della richiesta e delle eventuali misure precontrattuali da te richieste (art. 6, par. 1, lett. b del GDPR); la prevenzione degli abusi è fondata sul legittimo interesse alla sicurezza del servizio (lett. f). Non vengono attivati iscrizioni a newsletter o invii promozionali tramite questo modulo.</p>
    <p>Nome, email, argomento e messaggio sono necessari per rispondere. Il telefono è facoltativo: se lo indichi, il direttivo può usarlo per ricontattarti anche tramite WhatsApp in relazione alla richiesta. Non inviare dati sensibili, documenti, codici fiscali o dati di minori; per richieste Junior deve scrivere un adulto.</p>
    <h2>CONSERVAZIONE E ACCESSO</h2>
    <p>Le richieste sono salvate nel database del sito e sono accessibili agli incaricati che accedono all’area amministrativa. Vanno conservate soltanto per il tempo necessario alla gestione della richiesta e poi eliminate, salvi obblighi di legge. Il direttivo può cancellare la richiesta e le risposte WhatsApp collegate dall’area riservata. Non sono adottate decisioni automatizzate né profilazione.</p>
    <h2>I TUOI DIRITTI</h2>
    <p>Puoi chiedere accesso, rettifica, cancellazione, limitazione, portabilità ove applicabile e opporti al trattamento nei casi previsti dagli artt. 15–22 GDPR. Puoi inoltre proporre reclamo al Garante per la protezione dei dati personali. Per richieste sui tuoi dati usa i recapiti del club indicati sopra.</p>
    <h2>COOKIE E SERVIZI ESTERNI</h2>
    <p>Il sito utilizza un cookie tecnico di sessione PHP per proteggere il modulo con un token anti-CSRF e per l’autenticazione dell’area riservata. Non include strumenti analitici, pubblicità, mappe incorporate o font remoti. Immagini e font sono serviti localmente.</p>
    <p>I collegamenti a Google Maps, inter.it e agli articoli esterni aprono siti terzi, soggetti alle rispettive informative. WhatsApp viene aperto solo dagli operatori quando scelgono di rispondere con quel servizio; l’invio avviene manualmente e non è automatico.</p>
    <a class="text-link" href="<?= u('/index.php') ?>">Torna al sito ←</a>
</main>
<?php public_footer(); ?>
