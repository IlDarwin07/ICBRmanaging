<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$nome = trim($_POST['nome'] ?? '');
$cognome = trim($_POST['cognome'] ?? '');
$email = trim($_POST['email'] ?? '');

if (empty($username) || empty($password) || empty($nome) || empty($cognome) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Tutti i campi sono obbligatori']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'La password deve avere almeno 6 caratteri']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email non valida']);
    exit;
}

$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->fetch()['cnt'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Username già in uso']);
    exit;
}

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()['cnt'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Email già registrata']);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $db->prepare("
    INSERT INTO users (username, password_hash, nome, cognome, email, ruolo)
    VALUES (?, ?, ?, ?, ?, 'socio')
");
$stmt->execute([$username, $hash, $nome, $cognome, $email]);

echo json_encode(['success' => true, 'message' => 'Registrazione completata. Puoi effettuare il login.']);
