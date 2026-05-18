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
        $id = $_GET['id'] ?? null;
        if ($id) {
            $stmt = $db->prepare("
                SELECT p.*, u.nome || ' ' || u.cognome as creato_da,
                    (SELECT COUNT(*) FROM prenotazioni WHERE partita_id = p.id AND stato = 'confermata') as num_prenotazioni
                FROM partite p
                JOIN users u ON p.created_by = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $partita = $stmt->fetch();
            if (!$partita) {
                echo json_encode(['success' => false, 'message' => 'Partita non trovata']);
                exit;
            }
            echo json_encode(['success' => true, 'partita' => $partita]);
        } else {
            $stmt = $db->query("
                SELECT p.*, u.nome || ' ' || u.cognome as creato_da,
                    (SELECT COUNT(*) FROM prenotazioni WHERE partita_id = p.id AND stato = 'confermata') as num_prenotazioni
                FROM partite p
                JOIN users u ON p.created_by = u.id
                ORDER BY p.data_partita DESC, p.ora_partita DESC
            ");
            $partite = $stmt->fetchAll();
            echo json_encode(['success' => true, 'partite' => $partite]);
        }
        break;

    case 'POST':
        if (!in_array($_SESSION['ruolo'], ['admin', 'collaboratore'])) {
            echo json_encode(['success' => false, 'message' => 'Permesso negato']);
            exit;
        }

        $action = $_POST['action'] ?? 'create';

        if ($action === 'create') {
            $titolo = trim($_POST['titolo'] ?? '');
            $squadra_casa = trim($_POST['squadra_casa'] ?? '');
            $squadra_ospite = trim($_POST['squadra_ospite'] ?? '');
            $data_partita = $_POST['data_partita'] ?? '';
            $ora_partita = $_POST['ora_partita'] ?? '';
            $luogo = trim($_POST['luogo'] ?? 'Sede Interclub Brindisi');
            $num_posti = intval($_POST['num_posti'] ?? 50);

            if (empty($titolo) || empty($squadra_casa) || empty($squadra_ospite) || empty($data_partita) || empty($ora_partita)) {
                echo json_encode(['success' => false, 'message' => 'Tutti i campi sono obbligatori']);
                exit;
            }

            $stmt = $db->prepare("
                INSERT INTO partite (titolo, squadra_casa, squadra_ospite, data_partita, ora_partita, luogo, num_posti, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$titolo, $squadra_casa, $squadra_ospite, $data_partita, $ora_partita, $luogo, $num_posti, $_SESSION['user_id']]);
            echo json_encode(['success' => true, 'message' => 'Partita creata con successo', 'id' => $db->lastInsertId()]);

        } elseif ($action === 'update_stato') {
            $partita_id = intval($_POST['partita_id'] ?? 0);
            $stato = $_POST['stato'] ?? '';
            if (!in_array($stato, ['aperta', 'chiusa', 'conclusa'])) {
                echo json_encode(['success' => false, 'message' => 'Stato non valido']);
                exit;
            }
            $stmt = $db->prepare("UPDATE partite SET stato = ? WHERE id = ?");
            $stmt->execute([$stato, $partita_id]);
            echo json_encode(['success' => true, 'message' => 'Stato aggiornato']);

        } elseif ($action === 'delete') {
            if ($_SESSION['ruolo'] !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Solo l\'admin può eliminare le partite']);
                exit;
            }
            $partita_id = intval($_POST['partita_id'] ?? 0);
            $db->prepare("DELETE FROM assegnazioni_posti WHERE partita_id = ?")->execute([$partita_id]);
            $db->prepare("DELETE FROM prenotazioni WHERE partita_id = ?")->execute([$partita_id]);
            $db->prepare("DELETE FROM partite WHERE id = ?")->execute([$partita_id]);
            echo json_encode(['success' => true, 'message' => 'Partita eliminata']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
}
