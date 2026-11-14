<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/whatsapp_config.php';
$valid = ['phone_number_id' => '123456789', 'business_account_id' => '987654321', 'graph_version' => 'v23.0'];
if (icbr_wa_validate_settings($valid) !== $valid) throw new RuntimeException('Configurazione valida rifiutata');
if (icbr_wa_validate_settings([]) !== ['phone_number_id' => '', 'business_account_id' => '', 'graph_version' => '']) throw new RuntimeException('Reset non valido');
$cases = [
    ['phone_number_id' => ['12345']],
    ['phone_number_id' => '+3912345'],
    ['business_account_id' => '12/../../'],
    ['business_account_id' => str_repeat('1', 41)],
    ['graph_version' => 'https://example.test'],
    ['graph_version' => ['v23.0']],
    ['graph_version' => "v23.0\n"],
];
foreach ($cases as $case) {
    $rejected = false;
    try { icbr_wa_validate_settings($case); } catch (InvalidArgumentException $e) { $rejected = true; }
    if (!$rejected) throw new RuntimeException('Input non valido accettato');
}
echo 'OK: configurazione WhatsApp, ' . count($cases) . ' input non validi rifiutati.' . PHP_EOL;
