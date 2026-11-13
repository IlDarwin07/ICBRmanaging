<?php
/** Cambio password dal profilo. La pagina del profilo è /soci-area/index.php#account. */
require_once __DIR__ . '/../../includes/auth_soci.php';
require_once __DIR__ . '/../../includes/soci_layout.php';
$socio = require_socio_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_path('/soci-area/index.php#account'));
    exit;
}
soci_verify_csrf();
$attuale = is_string($_POST['password_attuale'] ?? null) ? $_POST['password_attuale'] : '';
$nuova = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$conferma = is_string($_POST['password_conferma'] ?? null) ? $_POST['password_conferma'] : '';
$stmt = get_db_connection()->prepare('SELECT password_hash FROM soci_account WHERE id_account = ?');
$stmt->execute([$socio['id_account']]);
$hash = $stmt->fetchColumn();
$errors = [];
if (!$hash || !password_verify($attuale, $hash)) $errors[] = 'La password attuale non è corretta.';
$errors = array_merge($errors, socio_password_errors($nuova, $conferma));
if ($errors) {
    redirect_with_message('/soci-area/index.php#account', 'Password non cambiata. ' . implode(' ', $errors), 'error');
}
soci_account_set_password($socio['id_account'], $nuova);
session_regenerate_id(true);
redirect_with_message('/soci-area/index.php#account', 'Fatto: da ora entri con la nuova password.');
