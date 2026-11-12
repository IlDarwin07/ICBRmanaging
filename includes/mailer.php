<?php
/**
 * Client SMTP minimo (STARTTLS/SSL + AUTH LOGIN/PLAIN), senza dipendenze esterne.
 * Usato solo per le email transazionali dell'area soci.
 */

/** Valore di una impostazione salvata nel database, o null se manca (o la tabella non esiste). */
function mail_setting(string $chiave): ?string
{
    try {
        $stmt = get_db_connection()->prepare('SELECT valore FROM impostazioni WHERE chiave = ?');
        $stmt->execute([$chiave]);
        $valore = $stmt->fetchColumn();
        return $valore === false ? null : (string)$valore;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Configurazione email. Host, porta e credenziali arrivano da Apache (config/mail.php);
 * l'indirizzo mittente, se impostato dall'admin nel portale, ha la precedenza.
 */
function mail_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/mail.php';
        $mittente = mail_setting('mail_mittente');
        if ($mittente !== null && filter_var($mittente, FILTER_VALIDATE_EMAIL) !== false) {
            $config['from'] = $mittente;
        }
    }
    return $config;
}

function mail_enabled(): bool
{
    $c = mail_config();
    return $c['host'] !== '' && filter_var($c['from'], FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * URL assoluto per i link nelle email. Usa ICBR_PUBLIC_URL se configurato,
 * altrimenti l'host della richiesta corrente.
 */
function absolute_url(string $path): string
{
    $root = mail_config()['public_url'];
    if ($root === '') {
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $root = $scheme . '://' . $host;
    }
    return $root . base_path($path);
}

function mail_header_encode(string $value): string
{
    $value = str_replace(["\r", "\n"], ' ', $value);
    return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
}

/**
 * Invia un'email di solo testo. Restituisce true se il server l'ha accettata.
 * Gli errori finiscono in error_log, mai nella pagina.
 */
function send_mail(string $to, string $subject, string $body): bool
{
    if (!mail_enabled() || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $c = mail_config();
    $remote = ($c['secure'] === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'];
    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $c['host']]]);
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, $c['timeout'], STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        error_log("ICBR mail: connessione SMTP non riuscita ($errno $errstr)");
        return false;
    }
    stream_set_timeout($fp, $c['timeout']);

    $read = function () use ($fp): array {
        $text = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $text .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return [(int)substr($text, 0, 3), trim($text)];
    };
    $cmd = function (string $line, array $expect, string $label = '') use ($fp, $read): string {
        fwrite($fp, $line . "\r\n");
        [$code, $text] = $read();
        if (!in_array($code, $expect, true)) {
            throw new RuntimeException('SMTP ' . ($label !== '' ? $label : $line) . " -> $text");
        }
        return $text;
    };

    $sent = false;
    try {
        [$code, $text] = $read();
        if ($code !== 220) throw new RuntimeException("SMTP saluto: $text");
        $ehlo = 'EHLO ' . (preg_replace('/[^A-Za-z0-9.\-]/', '', (string)($_SERVER['SERVER_NAME'] ?? '')) ?: 'localhost');
        $caps = $cmd($ehlo, [250]);
        if ($c['secure'] === 'tls') {
            if (stripos($caps, 'STARTTLS') === false) {
                throw new RuntimeException('SMTP: il server non offre STARTTLS');
            }
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('SMTP: negoziazione TLS non riuscita');
            }
            $caps = $cmd($ehlo, [250]);
        }
        if ($c['user'] !== '') {
            if ($c['secure'] !== 'ssl' && $c['secure'] !== 'tls') {
                throw new RuntimeException('SMTP: AUTH rifiutata senza connessione cifrata (imposta secure=ssl o tls)');
            }
            $plain = (bool)preg_match('/AUTH[ =][^\r\n]*\bPLAIN\b/i', $caps);
            $login = (bool)preg_match('/AUTH[ =][^\r\n]*\bLOGIN\b/i', $caps);
            if ($plain && !$login) {
                $cmd('AUTH PLAIN ' . base64_encode("\0" . $c['user'] . "\0" . $c['pass']), [235], 'AUTH PLAIN');
            } else {
                $cmd('AUTH LOGIN', [334]);
                $cmd(base64_encode($c['user']), [334], 'AUTH utente');
                $cmd(base64_encode($c['pass']), [235], 'AUTH password');
            }
        }
        $cmd('MAIL FROM:<' . $c['from'] . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);

        $domain = substr(strrchr($c['from'], '@') ?: '@localhost', 1);
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . mail_header_encode($c['from_name']) . ' <' . $c['from'] . '>',
            'To: <' . $to . '>',
            'Subject: ' . mail_header_encode($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Auto-Submitted: auto-generated',
        ];
        $payload = implode("\r\n", $headers) . "\r\n\r\n"
            . rtrim(chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $body)), 76, "\r\n"));
        // base64 non produce mai righe che iniziano con ".", niente dot-stuffing.
        $cmd($payload . "\r\n.", [250], 'DATA corpo');
        $sent = true;
    } catch (Throwable $e) {
        error_log('ICBR mail: ' . $e->getMessage());
    }

    // Il messaggio è già stato accettato: un QUIT fallito non deve trasformare l'invio in errore.
    if ($sent) {
        @fwrite($fp, "QUIT\r\n");
    }
    @fclose($fp);
    return $sent;
}
