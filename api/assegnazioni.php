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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($_SESSION['ruolo'], ['admin', 'collaboratore'])) {
        echo json_encode(['success' => false, 'message' => 'Permesso negato']);
        exit;
    }

    $action = $_POST['action'] ?? 'assign';

    if ($action === 'assign') {
        $prenotazione_id = intval($_POST['prenotazione_id'] ?? 0);
        $numero_sedia = intval($_POST['numero_sedia'] ?? 0);

        if ($prenotazione_id <= 0 || $numero_sedia <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dati non validi']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM prenotazioni WHERE id = ? AND stato = 'confermata'");
        $stmt->execute([$prenotazione_id]);
        $prenotazione = $stmt->fetch();

        if (!$prenotazione) {
            echo json_encode(['success' => false, 'message' => 'Prenotazione non trovata']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM partite WHERE id = ?");
        $stmt->execute([$prenotazione['partita_id']]);
        $partita = $stmt->fetch();

        if ($numero_sedia > $partita['num_posti']) {
            echo json_encode(['success' => false, 'message' => 'Numero sedia superiore ai posti disponibili (' . $partita['num_posti'] . ')']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM assegnazioni_posti WHERE partita_id = ? AND numero_sedia = ?");
        $stmt->execute([$prenotazione['partita_id'], $numero_sedia]);
        $existing = $stmt->fetch();
        if ($existing && $existing['user_id'] != $prenotazione['user_id']) {
            echo json_encode(['success' => false, 'message' => 'Sedia già assegnata ad un altro socio']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM assegnazioni_posti WHERE user_id = ? AND partita_id = ?");
        $stmt->execute([$prenotazione['user_id'], $prenotazione['partita_id']]);

        $stmt = $db->prepare("
            INSERT INTO assegnazioni_posti (prenotazione_id, user_id, partita_id, numero_sedia, assigned_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $prenotazione_id,
            $prenotazione['user_id'],
            $prenotazione['partita_id'],
            $numero_sedia,
            $_SESSION['user_id']
        ]);

        echo json_encode(['success' => true, 'message' => 'Sedia ' . $numero_sedia . ' assegnata con successo']);

    } elseif ($action === 'remove') {
        $assegnazione_id = intval($_POST['assegnazione_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM assegnazioni_posti WHERE id = ?");
        $stmt->execute([$assegnazione_id]);
        echo json_encode(['success' => true, 'message' => 'Assegnazione rimossa']);

    } elseif ($action === 'auto_assign') {
        $partita_id = intval($_POST['partita_id'] ?? 0);

        $stmt = $db->prepare("
            SELECT pr.id as prenotazione_id, pr.user_id
            FROM prenotazioni pr
            LEFT JOIN assegnazioni_posti ap ON ap.prenotazione_id = pr.id
            WHERE pr.partita_id = ? AND pr.stato = 'confermata' AND ap.id IS NULL
            ORDER BY pr.created_at ASC
        ");
        $stmt->execute([$partita_id]);
        $senza_posto = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT num_posti FROM partite WHERE id = ?");
        $stmt->execute([$partita_id]);
        $partita = $stmt->fetch();

        $stmt = $db->prepare("SELECT numero_sedia FROM assegnazioni_posti WHERE partita_id = ?");
        $stmt->execute([$partita_id]);
        $occupati = array_column($stmt->fetchAll(), 'numero_sedia');

        $sedia = 1;
        $assegnati = 0;
        foreach ($senza_posto as $pren) {
            while (in_array($sedia, $occupati) && $sedia <= $partita['num_posti']) {
                $sedia++;
            }
            if ($sedia > $partita['num_posti']) break;

            $stmt = $db->prepare("
                INSERT INTO assegnazioni_posti (prenotazione_id, user_id, partita_id, numero_sedia, assigned_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$pren['prenotazione_id'], $pren['user_id'], $partita_id, $sedia, $_SESSION['user_id']]);
            $occupati[] = $sedia;
            $sedia++;
            $assegnati++;
        }

        echo json_encode(['success' => true, 'message' => $assegnati . ' posti assegnati automaticamente']);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $partita_id = $_GET['partita_id'] ?? null;

    if ($partita_id) {
        $stmt = $db->prepare("
            SELECT ap.*, u.nome, u.cognome, u.username
            FROM assegnazioni_posti ap
            JOIN users u ON ap.user_id = u.id
            WHERE ap.partita_id = ?
            ORDER BY ap.numero_sedia ASC
        ");
        $stmt->execute([$partita_id]);
        $assegnazioni = $stmt->fetchAll();
        echo json_encode(['success' => true, 'assegnazioni' => $assegnazioni]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ID partita richiesto']);
    }
}
