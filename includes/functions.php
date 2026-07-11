<?php
/**
 * Funzioni di utilita globali per Inter Club Brindisi Gestionale.
 */

function get_db_connection(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $config = require __DIR__ . '/../config/database.php';
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function require_login(): void
{
    if (empty($_SESSION['id_utente'])) {
        header('Location: /login.php'); exit;
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['id_utente'])) return null;
    static $utente = null;
    if ($utente !== null) return $utente;
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT * FROM utenti_admin WHERE id_utente = :id');
    $stmt->execute(['id' => (int)$_SESSION['id_utente']]);
    $utente = $stmt->fetch() ?: null;
    return $utente;
}

function redirect_with_message(string $url, string $message, string $type = 'success'): never
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    header('Location: ' . $url); exit;
}

function get_flash_message(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function h(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function clean_text(?string $v): string
{
    return trim(strip_tags((string)($v ?? '')));
}

function clean_codice_fiscale(?string $v): string
{
    $cf = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)($v ?? '')));
    return (strlen($cf) === 16) ? $cf : '';
}

function clean_phone(?string $v): string
{
    return trim(preg_replace('/[^\d+\s\-().]/u', '', (string)($v ?? '')));
}

function clean_email(?string $v): string
{
    $e = strtolower(trim((string)($v ?? '')));
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : '';
}

function parse_date_to_sql(?string $v): ?string
{
    $v = trim((string)($v ?? ''));
    if ($v === '') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) return substr($v, 0, 10);
    if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $v, $m)) {
        return $m[3] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    }
    return null;
}

function si_no_to_bool(?string $v): bool
{
    return in_array(strtolower(trim((string)($v ?? ''))), ['si','s','yes','y','1','true','x','vero'], true);
}

function redirect(string $url): never
{
    header('Location: ' . $url); exit;
}
