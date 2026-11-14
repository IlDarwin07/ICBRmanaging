<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/whatsapp_payload.php';

function wa_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$payload = icbr_wa_template_payload('393331234567', 'promemoria_quota', 'it', ['Mario', '25,00 EUR']);
wa_check($payload['recipient_type'] === 'individual', 'Destinatario individuale');
wa_check($payload['type'] === 'template', 'Solo template');
wa_check($payload['template']['components'][0]['parameters'][1]['text'] === '25,00 EUR', 'Ordine parametri');
$empty = icbr_wa_template_payload('393331234567', 'avviso_sede', 'it');
wa_check(!isset($empty['template']['components']), 'Template senza parametri');
$cases = [
    ['+393331234567', 'avviso', 'it', []],
    ['00393331234567', 'avviso', 'it', []],
    ['123', 'avviso', 'it', []],
    ['393331234567', 'Nome con spazi', 'it', []],
    ['393331234567', 'avviso', '../it', []],
    ['393331234567', 'avviso', 'it', ['']],
    ['393331234567', 'avviso', 'it', [123]],
    ['393331234567', 'avviso', 'it', ['nome' => 'Mario']],
    ['393331234567', 'avviso', 'it', [str_repeat('x', 4097)]],
];
foreach ($cases as $case) {
    $rejected = false;
    try { icbr_wa_template_payload(...$case); }
    catch (InvalidArgumentException $e) { $rejected = true; }
    wa_check($rejected, 'Input non valido accettato');
}
echo 'OK: payload WhatsApp validati (' . count($cases) . ' casi negativi).' . PHP_EOL;
