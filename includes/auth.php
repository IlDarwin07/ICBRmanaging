<?php
/**
 * Gestione autenticazione admin/segreteria - Gestionale Inter Club Brindisi
 */

require_once __DIR__ . '/../config/database.php';

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        if (!empty($_SERVER['HTTPS'])) {
            ini_set('session.cookie_secure', '1');
        }
        session_start();
    }
}

/**
 * Verifica le credenziali e apre la sessione se corrette.
 */
function attempt_login(string $username, string $password): bool
{
    $pdo = get_db_connection();

    $stmt = $pdo->prepare(
        'SELECT id_utente, nome, cognome, username, password_hash, ruolo, attivo
         FROM utenti_admin
         WHERE username = :username
         LIMIT 1'
    );
    $stmt->execute(['username' => $username]);
    $utente = $stmt->fetch();

    if (!$utente || (int)$utente['attivo'] !== 1) {
        return false;
    }

    if (!password_verify($password, $utente['password_hash'])) {
        return false;
    }

    // Rigenerare l'id di sessione ad ogni login per prevenire session fixation.
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id_utente' => $utente['id_utente'],
        'nome'      => $utente['nome'],
        'cognome'   => $utente['cognome'],
        'username'  => $utente['username'],
        'ruolo'     => $utente['ruolo'],
    ];

    $update = $pdo->prepare('UPDATE utenti_admin SET ultimo_accesso = NOW() WHERE id_utente = :id');
    $update->execute(['id' => $utente['id_utente']]);

    return true;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']['id_utente']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Da richiamare in cima ad ogni pagina protetta dell'area riservata.
 */
function require_login(): void
{
    start_secure_session();
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Da richiamare quando una pagina è riservata solo a determinati ruoli.
 *
 * @param string[] $ruoli_ammessi es. ['admin', 'tesoriere']
 */
function require_role(array $ruoli_ammessi): void
{
    require_login();
    $utente = current_user();
    if (!in_array($utente['ruolo'], $ruoli_ammessi, true)) {
        http_response_code(403);
        die('Non hai i permessi per accedere a questa sezione.');
    }
}

function do_logout(): void
{
    start_secure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Genera/verifica un token CSRF per i form dell'area admin.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
