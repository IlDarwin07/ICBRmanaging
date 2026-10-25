<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login();
$page_title = 'Registro attività';
if (!security_ready()) render_error_page('Registro non ancora attivo','Applica la migrazione 010 prima di usare il registro. Nessun evento precedente viene ricostruito.',503);
$pdo = get_db_connection();
$author = is_string($_GET['autore'] ?? null) ? mb_substr(trim($_GET['autore']),0,80) : '';
$module = is_string($_GET['modulo'] ?? null) ? $_GET['modulo'] : '';
$modules = ['accessi','sicurezza','utenti','soci','tesseramenti','pagamenti','prima_nota','whatsapp','richieste','importazioni','configurazione','sito'];
$labels = ['accessi'=>'Accessi','sicurezza'=>'Sicurezza','utenti'=>'Direttivo','soci'=>'Soci','tesseramenti'=>'Tesseramenti','pagamenti'=>'Pagamenti','prima_nota'=>'Prima nota','whatsapp'=>'WhatsApp','richieste'=>'Contatti sito','importazioni'=>'Importazioni','configurazione'=>'Configurazione','sito'=>'Sito vetrina'];
$entities = ['utenti_admin'=>'Account','utenti_accessi'=>'Permessi account','soci'=>'Socio','tesseramenti'=>'Tesseramento','pagamenti_tesseramenti'=>'Pagamento','prima_nota'=>'Movimento','messaggi_whatsapp'=>'Messaggio','template_whatsapp'=>'Template','richieste_contatto'=>'Richiesta','importazioni'=>'Importazione','righe_importazione'=>'Riga importata','stagioni'=>'Stagione','tipologie_tesseramento'=>'Tipo tessera'];
if (!in_array($module,$modules,true)) $module = '';
$from = is_string($_GET['dal'] ?? null) ? $_GET['dal'] : '';
$to = is_string($_GET['al'] ?? null) ? $_GET['al'] : '';
$where=[]; $params=[];
if ($author !== '') { $where[]='autore = ?'; $params[]=$author; }
if ($module !== '') { $where[]='modulo = ?'; $params[]=$module; }
foreach (['dal'=>$from,'al'=>$to] as $key=>$value) {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('Europe/Rome'));
    if ($date && $date->format('Y-m-d')===$value) {
        if ($key==='al') $date=$date->modify('+1 day');
        $where[]='data_evento '.($key==='dal' ? '>=' : '<').' ?';
        $params[]=$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
$sql=$where ? ' WHERE '.implode(' AND ',$where) : '';
$count=$pdo->prepare('SELECT COUNT(*) FROM registro_attivita'.$sql);$count->execute($params);$total=(int)$count->fetchColumn();
$page=max(1,min(10000,(int)($_GET['pagina'] ?? 1)));
$stmt=$pdo->prepare('SELECT * FROM registro_attivita'.$sql.' ORDER BY id_evento DESC LIMIT 50 OFFSET '.(($page-1)*50));$stmt->execute($params);$events=$stmt->fetchAll();
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header"><div><span class="admin-eyebrow">TRASPARENZA / OPERAZIONI</span><h1>Registro attività</h1><p class="muted">Chi ha fatto cosa, e quando. Consultazione soltanto: nessun pulsante per modificare o cancellare il registro.</p></div><span class="badge"><?= $total ?> eventi</span></div>
<form method="get" class="search-form audit-filters"><div><label for="autore">Username esatto</label><input id="autore" name="autore" value="<?= h($author) ?>" maxlength="80"></div><div><label for="modulo">Modulo</label><select id="modulo" name="modulo"><option value="">Tutti i moduli</option><?php foreach ($modules as $m): ?><option value="<?= h($m) ?>" <?= $m===$module ? 'selected' : '' ?>><?= h($labels[$m]) ?></option><?php endforeach; ?></select></div><div><label for="dal">Dal</label><input id="dal" type="date" name="dal" value="<?= h($from) ?>"></div><div><label for="al">Al</label><input id="al" type="date" name="al" value="<?= h($to) ?>"></div><button class="btn">Filtra attività</button><a class="btn btn-secondary" href="<?= u('/attivita/list.php') ?>">Tutte</a></form>
<table class="data-table"><thead><tr><th>Quando (Italia)</th><th>Persona</th><th>Modulo</th><th>Attività</th><th>Riferimento</th><th>Dettaglio</th></tr></thead><tbody>
<?php foreach ($events as $event): ?><tr><td><?= h((new DateTimeImmutable($event['data_evento'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Rome'))->format('d/m/Y H:i:s')) ?></td><td><?= h($event['autore']) ?></td><td><?= h($labels[$event['modulo']] ?? $event['modulo']) ?></td><td><?= h(str_replace('_',' ',$event['azione'])) ?></td><td><?= h($entities[$event['entita'] ?? ''] ?? $event['entita'] ?? '') ?><?= $event['id_entita'] !== null ? ' #'.h($event['id_entita']) : '' ?></td><td><?php $detail=json_decode($event['dettagli_json'] ?? '[]',true); ?><?= isset($detail['righe']) ? (int)$detail['righe'].((int)$detail['righe']===1 ? ' riga' : ' righe') : h($detail['permesso'] ?? '') ?></td></tr><?php endforeach; ?>
<?php if (!$events): ?><tr><td colspan="6">Nessuna attività corrisponde ai filtri.</td></tr><?php endif; ?></tbody></table>
<div class="pagination"><?php if ($page>1): ?><a class="btn btn-secondary" href="?<?= h(http_build_query(['autore'=>$author,'modulo'=>$module,'dal'=>$from,'al'=>$to,'pagina'=>$page-1])) ?>">Precedenti</a><?php endif; ?><?php if ($page*50<$total): ?><a class="btn btn-secondary" href="?<?= h(http_build_query(['autore'=>$author,'modulo'=>$module,'dal'=>$from,'al'=>$to,'pagina'=>$page+1])) ?>">Successivi</a><?php endif; ?></div>
<p class="note">Il registro non contiene password, token, contenuti dei messaggi o valori anagrafici modificati. Le modifiche al database vengono registrate nella stessa transazione; un rollback elimina anche il relativo evento. Un amministratore del database può comunque alterare i dati: non è un archivio a prova di manomissione.</p>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
