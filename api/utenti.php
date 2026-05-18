<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['ruolo'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Permesso negato']);
    exit;
}

$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query("SELECT id, username, nome, cognome, email, ruolo, attivo, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
    echo json_encode(['success' => true, 'users' => $users]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = intval($_POST['user_id'] ?? 0);

    if ($action === 'change_role') {
        $nuovo_ruolo = $_POST['ruolo'] ?? '';
        if (!in_array($nuovo_ruolo, ['admin', 'collaboratore', 'socio'])) {
            echo json_encode(['success' => false, 'message' => 'Ruolo non valido']);
            exit;
        }
        if ($user_id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Non puoi cambiare il tuo ruolo']);
            exit;
        }
        $stmt = $db->prepare("UPDATE users SET ruolo = ? WHERE id = ?");
        $stmt->execute([$nuovo_ruolo, $user_id]);
        echo json_encode(['success' => true, 'message' => 'Ruolo aggiornato']);

    } elseif ($action === 'toggle_active') {
        if ($user_id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Non puoi disattivare te stesso']);
            exit;
        }
        $stmt = $db->prepare("UPDATE users SET attivo = CASE WHEN attivo = 1 THEN 0 ELSE 1 END WHERE id = ?");
        $stmt->execute([$user_id]);
        echo json_encode(['success' => true, 'message' => 'Stato utente aggiornato']);

    } elseif ($action === 'delete') {
        if ($user_id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Non puoi eliminare te stesso']);
            exit;
        }
        $db->prepare("DELETE FROM assegnazioni_posti WHERE user_id = ?")->execute([$user_id]);
        $db->prepare("DELETE FROM prenotazioni WHERE user_id = ?")->execute([$user_id]);
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
        echo json_encode(['success' => true, 'message' => 'Utente eliminato']);

    } else {
        echo json_encode(['success' => false, 'message' => 'Azione non valida']);
    }
}
