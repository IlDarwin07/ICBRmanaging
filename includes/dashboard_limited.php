<?php
$page_title = 'Il tuo spazio nel club';
require __DIR__ . '/layout_header.php';
?>
<span class="admin-eyebrow">DIRETTIVO / AREA PERSONALE</span><h1>Il tuo spazio nel club</h1>
<p>Benvenuto, <?= h(current_user()['nome']) ?>. Qui trovi le sezioni abilitate per il tuo account.</p>
<div class="cards-grid">
<?php foreach ([
    ['soci.view','Soci','Consulta l’anagrafica del club.','/soci/list.php'],
    ['tesseramenti.view','Tesseramenti','Consulta adesioni e quote.','/tesseramenti/list.php'],
    ['prima_nota.view','Prima nota','Consulta i movimenti economici.','/prima_nota/list.php'],
    ['whatsapp.view','WhatsApp','Prepara le comunicazioni del club.','/messaggi/index.php'],
    ['richieste.view','Richieste dal sito','Leggi i contatti arrivati dalla vetrina.','/richieste/list.php'],
    ['importazioni.write','Importazioni','Aggiorna il gestionale dai file del portale.','/importazioni/upload.php'],
    ['configurazione.view','Configurazione','Consulta stagioni e listini.','/stagioni/list.php'],
    ['sito.write','Sito vetrina','Aggiorna i contenuti pubblici.','/sito/edit.php'],
    ['attivita.view','Registro attività','Consulta le operazioni registrate.','/attivita/list.php'],
] as [$permission,$label,$description,$url]): if (!can($permission)) continue; ?>
<article class="admin-panel"><h2><?= h($label) ?></h2><p><?= h($description) ?></p><a class="btn btn-secondary" href="<?= u($url) ?>">Apri la sezione ↗</a></article><?php endforeach; ?>
</div><p class="note">Se ti serve un’altra sezione, chiedi a un amministratore. Il nome del ruolo non assegna automaticamente nuovi permessi.</p>
<?php require __DIR__ . '/layout_footer.php'; ?>
