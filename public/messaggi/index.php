<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
start_secure_session();
require_login();
$pdo = get_db_connection();
$counts = ['preparato'=>0,'bozza'=>0,'inviato'=>0,'fallito'=>0];
foreach ($pdo->query('SELECT stato_messaggio, COUNT(*) n FROM messaggi_whatsapp GROUP BY stato_messaggio') as $r) $counts[$r['stato_messaggio']] = (int)$r['n'];
$templates = (int)$pdo->query('SELECT COUNT(*) FROM template_whatsapp WHERE attivo = 1')->fetchColumn();
$page_title = 'Centro WhatsApp';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header"><h1>Centro WhatsApp</h1><a class="btn" href="<?= u('/messaggi/direct.php') ?>">Nuovo messaggio singolo</a></div>
<div class="workflow-note"><strong>Invio assistito, sotto il tuo controllo.</strong> Prepara il testo, apri WhatsApp Web o l’app e invia dalla chat. Solo dopo l’invio effettivo premi “Conferma inviato” nella coda. Non è un invio automatico, né una verifica di consegna o lettura.</div>
<div class="cards-grid">
<a class="card" href="<?= u('/messaggi/list.php') ?>?stato=preparato"><span class="card-value"><?= $counts['preparato'] ?></span><span class="card-label">Messaggi da inviare</span></a>
<a class="card" href="<?= u('/messaggi/list.php') ?>?stato=inviato"><span class="card-value"><?= $counts['inviato'] ?></span><span class="card-label">Invii confermati manualmente</span></a>
<a class="card" href="<?= u('/messaggi/template_list.php') ?>"><span class="card-value"><?= $templates ?></span><span class="card-label">Template attivi</span></a>
<a class="card" href="<?= u('/richieste/list.php') ?>"><span class="card-value">SITO ↗</span><span class="card-label">Apri le richieste della vetrina</span></a>
</div>
<div class="admin-grid">
<section class="admin-panel"><div class="admin-eyebrow">PERSONALIZZA</div><h2>Template del club</h2><p>Crea messaggi per rinnovi, quote, tessere e comunicazioni di servizio. I segnaposto personalizzano nome, stagione e importi.</p><a class="btn" href="<?= u('/messaggi/template_list.php') ?>">Gestisci template</a><a class="btn btn-secondary" href="<?= u('/messaggi/template_create.php') ?>">Crea template</a></section>
<section class="admin-panel"><div class="admin-eyebrow">SELEZIONA</div><h2>Messaggi ai soci</h2><p>Filtra per stagione, quota o tessera. Controlla i destinatari e l’anteprima, quindi salva i testi preparati nella coda.</p><a class="btn" href="<?= u('/messaggi/compose.php') ?>">Seleziona destinatari</a></section>
<section class="admin-panel"><div class="admin-eyebrow">RISPONDI</div><h2>Dalla vetrina a WhatsApp</h2><p>Apri una richiesta del sito e prepara una risposta per il numero fornito. Il collegamento alla richiesta resta nello storico.</p><a class="btn" href="<?= u('/richieste/list.php') ?>">Apri richieste sito</a><a class="btn btn-secondary" href="<?= u('/messaggi/list.php') ?>">Coda e storico</a></section>
</div>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
