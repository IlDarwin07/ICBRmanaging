<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/init_db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $partita_id = $_GET['partita_id'] ?? null;
        $user_id = $_GET['user_id'] ?? $_SESSION['user_id'];

        if ($partita_id) {
            if (in_array($_SESSION['ruolo'], ['admin', 'collaboratore'])) {
                $stmt = $db->prepare("
                    SELECT pr.*, u.nome, u.cognome, u.username,
                        ap.numero_sedia
                    FROM prenotazioni pr
                    JOIN users u ON pr.user_id = u.id
                    LEFT JOIN assegnazioni_posti ap ON ap.prenotazione_id = pr.id
                    WHERE pr.partita_id = ? AND pr.stato = 'confermata'
                    ORDER BY pr.created_at ASC
                ");
                $stmt->execute([$partita_id]);
            } else {
                $stmt = $db->prepare("
                    SELECT pr.*, u.nome, u.cognome,
                        ap.numero_sedia
                    FROM prenotazioni pr
                    JOIN users u ON pr.user_id = u.id
                    LEFT JOIN assegnazioni_posti ap ON ap.prenotazione_id = pr.id
                    WHERE pr.partita_id = ? AND pr.user_id = ? AND pr.stato = 'confermata'
                ");
                $stmt->execute([$partita_id, $_SESSION['user_id']]);
            }
            $prenotazioni = $stmt->fetchAll();
            echo json_encode(['success' => true, 'prenotazioni' => $prenotazioni]);
        } else {
            $stmt = $db->prepare("
                SELECT pr.*, p.titolo, p.squadra_casa, p.squadra_ospite,
                    p.data_partita, p.ora_partita, p.luogo, p.stato as stato_partita,
                    ap.numero_sedia
                FROM prenotazioni pr
                JOIN partite p ON pr.partita_id = p.id
                LEFT JOIN assegnazioni_posti ap ON ap.prenotazione_id = pr.id
                WHERE pr.user_id = ? AND pr.stato = 'confermata'
                ORDER BY p.data_partita DESC
            ");
            $stmt->execute([$user_id]);
            $prenotazioni = $stmt->fetchAll();
            echo json_encode(['success' => true, 'prenotazioni' => $prenotazioni]);
        }
        break;

    case 'POST':
        $action = $_POST['action'] ?? 'prenota';
        $partita_id = intval($_POST['partita_id'] ?? 0);

        if ($action === 'prenota') {
            $stmt = $db->prepare("SELECT * FROM partite WHERE id = ?");
            $stmt->execute([$partita_id]);
            $partita = $stmt->fetch();

            if (!$partita) {
                echo json_encode(['success' => false, 'message' => 'Partita non trovata']);
                exit;
            }
            if ($partita['stato'] !== 'aperta') {
                echo json_encode(['success' => false, 'message' => 'Le prenotazioni per questa partita sono chiuse']);
                exit;
            }

            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM prenotazioni WHERE partita_id = ? AND stato = 'confermata'");
            $stmt->execute([$partita_id]);
            if ($stmt->fetch()['cnt'] >= $partita['num_posti']) {
                echo json_encode(['success' => false, 'message' => 'Posti esauriti per questa partita']);
                exit;
            }

            $stmt = $db->prepare("SELECT * FROM prenotazioni WHERE user_id = ? AND partita_id = ? AND stato = 'confermata'");
            $stmt->execute([$_SESSION['user_id'], $partita_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Hai già una prenotazione per questa partita']);
                exit;
            }

            $stmt = $db->prepare("
                INSERT OR REPLACE INTO prenotazioni (user_id, partita_id, stato)
                VALUES (?, ?, 'confermata')
            ");
            $stmt->execute([$_SESSION['user_id'], $partita_id]);
            echo json_encode(['success' => true, 'message' => 'Prenotazione confermata']);

        } elseif ($action === 'cancella') {
            $stmt = $db->prepare("DELETE FROM assegnazioni_posti WHERE user_id = ? AND partita_id = ?");
            $stmt->execute([$_SESSION['user_id'], $partita_id]);

            $stmt = $db->prepare("UPDATE prenotazioni SET stato = 'cancellata' WHERE user_id = ? AND partita_id = ?");
            $stmt->execute([$_SESSION['user_id'], $partita_id]);
            echo json_encode(['success' => true, 'message' => 'Prenotazione cancellata']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
}
