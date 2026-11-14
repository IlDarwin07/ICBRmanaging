<?php
declare(strict_types=1);
require_once __DIR__ . '/whatsapp_payload.php';
require_once __DIR__ . '/whatsapp_config.php';

function icbr_wa_valid_signature(string $body, string $signature, string $appSecret): bool
{
    return $appSecret !== '' && preg_match('/^sha256=[a-f0-9]{64}$/D', $signature) === 1
        && hash_equals('sha256=' . hash_hmac('sha256', $body, $appSecret), $signature);
}

/** Never treat an HTTP acceptance as delivery. Ambiguous outcomes must not be retried blindly. */
function icbr_wa_classify_response(int $http, string $body, bool $networkError = false): array
{
    $result = ['state' => 'uncertain', 'message_id' => null, 'http_status' => $http, 'error_code' => null];
    if ($networkError) return $result;
    $data = json_decode($body, true);
    if (!is_array($data)) return $result;
    $id = $data['messages'][0]['id'] ?? null;
    if ($http >= 200 && $http < 300 && is_string($id) && $id !== '' && strlen($id) <= 255 && !isset($data['error'])) {
        $result['state'] = 'accepted';
        $result['message_id'] = $id;
    } elseif ($http >= 400 && $http < 500 && $http !== 408 && is_array($data['error'] ?? null)) {
        $result['state'] = 'rejected';
        $code = $data['error']['code'] ?? null;
        if (is_int($code)) $result['error_code'] = $code;
    }
    return $result;
}

/** Internal transport only. Caller must persist consent checks and an idempotent claim BEFORE calling. */
function icbr_wa_send_template(array $settings, string $phone, string $template, string $language, array $parameters): array
{
    if (getenv('ICBR_WA_SEND_ENABLED') !== '1') throw new RuntimeException('Trasporto WhatsApp disabilitato sul server.');
    $config = icbr_wa_validate_settings($settings);
    if ($config['phone_number_id'] === '' || $config['graph_version'] === '') throw new RuntimeException('Configurazione WhatsApp incompleta.');
    $token = getenv('ICBR_WA_ACCESS_TOKEN');
    if (!is_string($token) || !preg_match('/^[A-Za-z0-9._~-]{10,4096}$/D', $token)) throw new RuntimeException('Token WhatsApp mancante o non valido.');
    if (!function_exists('curl_init')) throw new RuntimeException('Estensione cURL non disponibile.');
    $json = json_encode(icbr_wa_template_payload($phone, $template, $language, $parameters), JSON_THROW_ON_ERROR);
    $url = 'https://graph.facebook.com/' . $config['graph_version'] . '/' . $config['phone_number_id'] . '/messages';
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('Impossibile inizializzare il trasporto.');
    $response = '';
    try {
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$response): int {
                if (strlen($response) + strlen($chunk) > 65536) return 0;
                $response .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return icbr_wa_classify_response($http, $response, $ok === false || curl_errno($ch) !== 0);
    } finally {
        curl_close($ch);
        unset($token, $response);
    }
}
