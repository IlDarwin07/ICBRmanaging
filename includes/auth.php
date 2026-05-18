<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /pages/login.php');
        exit;
    }
}

function requireRole($roles) {
    requireLogin();
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    if (!in_array($_SESSION['ruolo'], $roles)) {
        header('HTTP/1.1 403 Forbidden');
        echo '<h1>Accesso negato</h1><p>Non hai i permessi per accedere a questa pagina.</p>';
        echo '<a href="/pages/dashboard.php">Torna alla dashboard</a>';
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, username, nome, cognome, email, ruolo, attivo FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function isAdmin() {
    return isset($_SESSION['ruolo']) && $_SESSION['ruolo'] === 'admin';
}

function isCollaboratore() {
    return isset($_SESSION['ruolo']) && $_SESSION['ruolo'] === 'collaboratore';
}

function isAdminOrCollaboratore() {
    return isAdmin() || isCollaboratore();
}
