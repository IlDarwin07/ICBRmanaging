<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/whatsapp_transport.php';
function transport_check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$body = '{"object":"whatsapp_business_account"}';
$secret = 'unit-test-secret-not-a-production-credential';
$signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
transport_check(icbr_wa_valid_signature($body, $signature, $secret), 'Firma valida rifiutata');
transport_check(!icbr_wa_valid_signature($body . ' ', $signature, $secret), 'Payload alterato accettato');
transport_check(!icbr_wa_valid_signature($body, $signature, ''), 'Secret vuoto accettato');
transport_check(!icbr_wa_valid_signature($body, '', $secret), 'Firma assente accettata');
transport_check(!icbr_wa_valid_signature($body, $signature, 'different'), 'Secret errato accettato');
$accepted = '{"messages":[{"id":"wamid.unit-test"}]}';
transport_check(icbr_wa_classify_response(200, $accepted)['state'] === 'accepted', 'Accettazione non riconosciuta');
transport_check(icbr_wa_classify_response(200, '{}')['state'] === 'uncertain', 'Risposta senza ID accettata');
transport_check(icbr_wa_classify_response(200, $accepted, true)['state'] === 'uncertain', 'Errore rete ignorato');
transport_check(icbr_wa_classify_response(500, '{"error":{"code":1}}')['state'] === 'uncertain', 'Errore server non ambiguo');
transport_check(icbr_wa_classify_response(408, '{"error":{"code":1}}')['state'] === 'uncertain', 'Timeout non ambiguo');
$rejected = icbr_wa_classify_response(400, '{"error":{"code":100,"message":"private detail"}}');
transport_check($rejected['state'] === 'rejected' && $rejected['error_code'] === 100, 'Rifiuto non riconosciuto');
transport_check(!str_contains(json_encode($rejected), 'private detail'), 'Dettaglio remoto esposto');
transport_check(icbr_wa_classify_response(200, 'not-json')['state'] === 'uncertain', 'JSON invalido accettato');
putenv('ICBR_WA_SEND_ENABLED=0');
$blocked = false;
try { icbr_wa_send_template([], '393331234567', 'avviso', 'it', []); } catch (RuntimeException $e) { $blocked = true; }
transport_check($blocked, 'Trasporto non bloccato');
echo 'OK: firme webhook, classificazione risposte e blocco trasporto. Nessuna richiesta di rete eseguita.' . PHP_EOL;
