<?php
declare(strict_types=1);
if (getenv('ICBR_WA_DB_TEST')!=='1' || getenv('GITHUB_ACTIONS')!=='true') { fwrite(STDERR,'Solo servizio MySQL temporaneo di GitHub Actions.' . PHP_EOL); exit(1); }
require_once __DIR__ . '/../includes/whatsapp_dispatch.php';
function dispatch_check(bool $ok,string $why): void { if (!$ok) throw new RuntimeException($why); }
function dispatch_reject(callable $work): void { $failed=false; try { $work(); } catch (InvalidArgumentException|RuntimeException $e) { $failed=true; } dispatch_check($failed,'Operazione non valida accettata'); }
$db=new PDO('mysql:host=127.0.0.1;dbname=icbr_gestionale;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("INSERT INTO utenti_admin (nome,cognome,username,password_hash) VALUES ('Dispatch','CI','dispatch_ci','not-a-login-hash')"); $actor=(int)$db->lastInsertId();
$db->exec("INSERT INTO soci (nome,cognome,telefono) VALUES ('Mario','Dispatch','393331234568')"); $socio=(int)$db->lastInsertId();
$db->exec("INSERT INTO stagioni (codice_stagione) VALUES ('DISPATCH-CI')"); $season=(int)$db->lastInsertId();
$db->prepare('INSERT INTO tesseramenti (id_socio,id_stagione,quota_tesseramento) VALUES (?,?,50)')->execute([$socio,$season]); $enrolment=(int)$db->lastInsertId();
$db->prepare('INSERT INTO wa_modelli (nome_meta,lingua,testo_anteprima,parametri_json,attivo,verificato_da,verificato_il) VALUES (?,?,?,?,1,?,NOW())')->execute(['quota_ci','it','Ciao {{1}}, residuo {{2}} EUR',json_encode(['nome','residuo']),$actor]); $model=(int)$db->lastInsertId();
icbr_wa_save_settings($db,['phone_number_id'=>'123456789','business_account_id'=>'987654321','graph_version'=>'v23.0'],$actor);
$service=new ICBRWhatsAppDispatch($db,$actor);
dispatch_reject(fn()=>$service->prepare($season,$model,[$enrolment],'residuo'));
$db->prepare("INSERT INTO wa_consensi (id_socio,telefono,stato,fonte,registrato_da) VALUES (?,?,'concesso','unit-test',?)")->execute([$socio,'393331234568',$actor]);
$batch=$service->prepare($season,$model,[$enrolment],'residuo');
dispatch_check(str_contains($batch['destinatari'][0]['testo'],'50,00'),'Anteprima importo errata');
dispatch_reject(fn()=>$service->confirm($batch['id_lotto'],str_repeat('0',64)));
dispatch_check($service->confirm($batch['id_lotto'],$batch['token']),'Conferma fallita');
dispatch_check(!$service->confirm($batch['id_lotto'],$batch['token']),'Conferma non idempotente');
$calls=0;
$sender=function(array $settings,array $payload) use (&$calls): array { $calls++; return ['state'=>'accepted','message_id'=>'wamid.dispatch-ci-'.$calls,'http_status'=>200,'error_code'=>null]; };
$sent=$service->sendOne($batch['id_lotto'],$batch['token'],$sender);
dispatch_check($sent['stato']==='accettato' && $calls===1,'Invio simulato fallito');
dispatch_check($service->sendOne($batch['id_lotto'],$batch['token'],$sender)===null && $calls===1,'Doppio invio');
$revoked=$service->prepare($season,$model,[$enrolment],'residuo'); $service->confirm($revoked['id_lotto'],$revoked['token']);
$db->prepare("UPDATE wa_consensi SET stato='revocato' WHERE id_socio=?")->execute([$socio]);
$r=$service->sendOne($revoked['id_lotto'],$revoked['token'],$sender);
dispatch_check($r['stato']==='escluso' && $calls===1,'Revoca ignorata');
$db->prepare("UPDATE wa_consensi SET stato='concesso' WHERE id_socio=?")->execute([$socio]);
$timeout=$service->prepare($season,$model,[$enrolment],'residuo'); $service->confirm($timeout['id_lotto'],$timeout['token']);
$r=$service->sendOne($timeout['id_lotto'],$timeout['token'],function(){ throw new RuntimeException('simulated timeout'); });
dispatch_check($r['stato']==='incerto','Timeout non ambiguo');
dispatch_check($service->sendOne($timeout['id_lotto'],$timeout['token'],$sender)===null && $calls===1,'Retry automatico dopo timeout');
$changed=$service->prepare($season,$model,[$enrolment],'residuo');
$db->prepare("INSERT INTO pagamenti_tesseramenti (id_tesseramento,data_pagamento,tipo_pagamento,importo) VALUES (?,CURRENT_DATE(),'acconto',10)")->execute([$enrolment]);
dispatch_reject(fn()=>$service->confirm($changed['id_lotto'],$changed['token']));
$db->prepare('UPDATE utenti_admin SET attivo=0 WHERE id_utente=?')->execute([$actor]);
dispatch_reject(fn()=>$service->prepare($season,$model,[$enrolment],'residuo'));
echo 'OK: consenso, anteprima, conferma idempotente, revoca, importi cambiati, timeout e operatore disabilitato; nessuna rete.' . PHP_EOL;
