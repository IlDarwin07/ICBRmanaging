<?php
/**
 * Autenticazione dell'AREA SOCI.
 *
 * Completamente separata dal gestionale:
 * - cookie di sessione diverso (ICBR_SOCI) limitato al percorso /soci-area/;
 * - chiave di sessione $_SESSION['socio'] (mai $_SESSION['user']);
 * - tabella soci_account (mai utenti_admin).
 * Un socio autenticato non può quindi superare require_login() del gestionale.
 */

require_once __DIR__ . '/auth.php';       // csrf_token(), csrf_verify(), DB, audit
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/partite.php';

const SOCI_SESSION_NAME = 'ICBR_SOCI';
const SOCI_TOKEN_ORE = 48;
const SOCI_MAX_TENTATIVI = 5;
const SOCI_BLOCCO_MINUTI = 15;
const SOCI_PASSWORD_MIN = 10;

function start_soci_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() !== SOCI_SESSION_NAME) {
            // Mai riusare una sessione del gestionale nell'area soci.
            http_response_code(500);
            exit('Sessione non valida.');
        }
        return;
    }
    ini_set('session.use_strict_mode', '1');
    session_name(SOCI_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (base_url() ?: '') . '/soci-area/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer'); // i token nei link non escono dalla pagina
}

function soci_ready(): bool
{
    return partite_ready();
}

function current_socio(): ?array
{
    return $_SESSION['socio'] ?? null;
}

/** Da richiamare in cima a ogni pagina riservata dell'area soci. */
function require_socio_login(): array
{
    start_soci_session();
    $session = current_socio();
    if ($session && soci_ready()) {
        $stmt = get_db_connection()->prepare(
            'SELECT a.id_account, a.id_socio, a.email, a.attivo, s.nome, s.cognome
             FROM soci_account a JOIN soci s ON s.id_socio = a.id_socio
             WHERE a.id_account = ? AND s.attivo_record = 1'
        );
        $stmt->execute([$session['id_account']]);
        $row = $stmt->fetch();
        if ($row && (int)$row['attivo'] === 1) {
            $_SESSION['socio'] = soci_session_payload($row);
            return $_SESSION['socio'];
        }
        socio_logout();
        header('Location: ' . base_path('/soci-area/login.php?revocato=1'));
        exit;
    }
    header('Location: ' . base_path('/soci-area/login.php'));
    exit;
}

function soci_session_payload(array $row): array
{
    return [
        'id_account' => (int)$row['id_account'],
        'id_socio' => (int)$row['id_socio'],
        'nome' => $row['nome'],
        'cognome' => $row['cognome'],
        'email' => $row['email'],
    ];
}

function socio_attempt_login(string $email, string $password): bool|string
{
    $pdo = get_db_connection();
    $email = clean_email($email) ?? '';
    $stmt = $pdo->prepare(
        'SELECT a.*, s.nome, s.cognome, s.attivo_record,
                (a.bloccato_fino IS NOT NULL AND a.bloccato_fino > NOW()) AS bloccato
         FROM soci_account a JOIN soci s ON s.id_socio = a.id_socio WHERE a.email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if ($row && (int)$row['bloccato'] === 1) {
        return 'Troppi tentativi non riusciti. Riprova tra qualche minuto.';
    }
    // Hash fittizio per tempi di risposta simili anche se l'email non esiste.
    $hash = $row['password_hash'] ?? null;
    static $dummy = null;
    $dummy ??= password_hash(random_bytes(12), PASSWORD_DEFAULT);
    $ok = password_verify($password, $hash ?: $dummy);

    if (!$row || !$hash || !$ok || (int)$row['attivo'] !== 1 || (int)$row['attivo_record'] !== 1) {
        if ($row) {
            $pdo->prepare(
                // MySQL valuta le assegnazioni da sinistra a destra: incremento,
                // eventuale blocco, poi azzeramento del contatore dopo il blocco.
                'UPDATE soci_account SET tentativi_falliti = tentativi_falliti + 1,
                 bloccato_fino = IF(tentativi_falliti >= ?, NOW() + INTERVAL ? MINUTE, bloccato_fino),
                 tentativi_falliti = IF(tentativi_falliti >= ?, 0, tentativi_falliti)
                 WHERE id_account = ?'
            )->execute([SOCI_MAX_TENTATIVI, SOCI_BLOCCO_MINUTI, SOCI_MAX_TENTATIVI, $row['id_account']]);
        }
        audit_event('area_soci', 'login_fallito', null, null, [], 'Area soci');
        return false;
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE soci_account SET password_hash = ? WHERE id_account = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id_account']]);
    }
    $pdo->prepare('UPDATE soci_account SET ultimo_accesso = NOW(), tentativi_falliti = 0, bloccato_fino = NULL WHERE id_account = ?')
        ->execute([$row['id_account']]);
    socio_open_session($row);
    return true;
}

function socio_open_session(array $row): void
{
    start_soci_session();
    session_regenerate_id(true);
    $_SESSION['socio'] = soci_session_payload($row);
    audit_event('area_soci', 'login', 'soci_account', (string)$row['id_account']);
}

function socio_logout(): void
{
    start_soci_session();
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}

function socio_password_errors(string $password, string $confirm): array
{
    $errors = [];
    if (mb_strlen($password) < SOCI_PASSWORD_MIN) $errors[] = 'La password deve avere almeno ' . SOCI_PASSWORD_MIN . ' caratteri.';
    if (mb_strlen($password) > 200) $errors[] = 'La password è troppo lunga.';
    if ($password !== $confirm) $errors[] = 'Le due password non coincidono.';
    return $errors;
}

// -------------------------------------------------------------------------
// Token monouso (attivazione e recupero password)
// -------------------------------------------------------------------------

function soci_token_hash(string $token): string
{
    return hash('sha256', $token);
}

/**
 * Crea (o aggiorna) l'account di un socio e genera un nuovo token.
 * Restituisce il token in chiaro: va solo inviato, mai salvato.
 *
 * @throws InvalidArgumentException con un messaggio leggibile
 */
function soci_account_invite(int $id_socio, string $email): string
{
    $pdo = get_db_connection();
    $email = clean_email($email) ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Indirizzo email non valido.');
    }
    $stmt = $pdo->prepare('SELECT id_socio FROM soci_account WHERE email = ? AND id_socio <> ?');
    $stmt->execute([$email, $id_socio]);
    if ($stmt->fetch()) {
        throw new InvalidArgumentException('Questa email è già usata dall\'account di un altro socio. Ogni socio deve avere un indirizzo diverso.');
    }
    $token = bin2hex(random_bytes(32));
    $pdo->prepare(
        'INSERT INTO soci_account (id_socio, email, token_hash, token_scadenza, attivo)
         VALUES (?, ?, ?, NOW() + INTERVAL ' . SOCI_TOKEN_ORE . ' HOUR, 1)
         ON DUPLICATE KEY UPDATE email = VALUES(email), token_hash = VALUES(token_hash),
             token_scadenza = VALUES(token_scadenza), attivo = 1, tentativi_falliti = 0, bloccato_fino = NULL'
    )->execute([$id_socio, $email, soci_token_hash($token)]);
    return $token;
}

/** Account valido per il token, oppure null (scaduto, usato, disattivato). */
function soci_account_by_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $stmt = get_db_connection()->prepare(
        'SELECT a.*, s.nome, s.cognome FROM soci_account a JOIN soci s ON s.id_socio = a.id_socio
         WHERE a.token_hash = ? AND a.token_scadenza > NOW() AND a.attivo = 1 AND s.attivo_record = 1'
    );
    $stmt->execute([soci_token_hash($token)]);
    return $stmt->fetch() ?: null;
}

function soci_account_set_password(int $id_account, string $password): void
{
    get_db_connection()->prepare(
        'UPDATE soci_account SET password_hash = ?, token_hash = NULL, token_scadenza = NULL,
         tentativi_falliti = 0, bloccato_fino = NULL WHERE id_account = ?'
    )->execute([password_hash($password, PASSWORD_DEFAULT), $id_account]);
}

/**
 * Recupero password: risposta sempre identica, per non rivelare quali
 * email sono registrate. Un nuovo link al massimo ogni 5 minuti.
 */
function soci_request_reset(string $email): void
{
    $pdo = get_db_connection();
    $stmt = $pdo->prepare(
        'SELECT a.id_account, a.id_socio, a.email, s.nome FROM soci_account a JOIN soci s ON s.id_socio = a.id_socio
         WHERE a.email = ? AND a.attivo = 1 AND s.attivo_record = 1
           AND (a.token_scadenza IS NULL OR a.token_scadenza < NOW() + INTERVAL ' . (SOCI_TOKEN_ORE * 60 - 5) . ' MINUTE)'
    );
    $stmt->execute([clean_email($email) ?? '']);
    $row = $stmt->fetch();
    if (!$row) return;
    $token = soci_account_invite((int)$row['id_socio'], $row['email']);
    soci_send_access_email($row['email'], $row['nome'], $token, 'recupero');
}

function soci_access_link(string $token): string
{
    return absolute_url('/soci-area/attiva.php?token=' . $token);
}

function soci_send_access_email(string $email, string $nome, string $token, string $tipo = 'attivazione'): bool
{
    $link = soci_access_link($token);
    if ($tipo === 'recupero') {
        $subject = 'Nuova password per l\'area soci';
        $intro = 'abbiamo ricevuto una richiesta di nuova password per la tua area soci.';
        $outro = 'Se non sei stato tu, ignora questa email: la password attuale resta valida.';
    } else {
        $subject = 'Il tuo accesso all\'area soci dell\'Inter Club Brindisi';
        $intro = 'il direttivo ti ha attivato l\'accesso all\'area soci: da qui potrai confermare la presenza alle partite in sede e vedere il tuo posto.';
        $outro = 'Se non ti aspettavi questa email, scrivi al direttivo del club.';
    }
    $body = "Ciao $nome,\n\n$intro\n\nScegli la tua password da questo link (valido " . SOCI_TOKEN_ORE . " ore, utilizzabile una sola volta):\n$link\n\n$outro\n\nForza Inter!\nInter Club Javier Zanetti Brindisi\n";
    return send_mail($email, $subject, $body);
}
