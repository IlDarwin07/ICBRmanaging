<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
start_secure_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_path('/index.php#contatti'));
    exit;
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    render_error_page('Sessione da aggiornare', 'Per proteggere i tuoi dati, il modulo richiede una sessione valida. Torna al sito e ricarica il modulo prima di riprovare.', 403, true);
}
$data = [];
foreach (['nome','email','telefono','oggetto','messaggio'] as $key) {
    $data[$key] = trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : '');
}
$errors = [];
if (mb_strlen($data['nome']) < 2 || mb_strlen($data['nome']) > 100) $errors[] = 'Inserisci nome e cognome (da 2 a 100 caratteri).';
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 190) $errors[] = 'Inserisci un indirizzo email valido.';
if ($data['telefono'] !== '' && (!wa_phone($data['telefono']) || strlen($data['telefono']) > 30)) $errors[] = 'Controlla il telefono, oppure lascia il campo vuoto.';
if (!in_array($data['oggetto'], ['Tesseramento','Rinnovo','Visita alla sede','Altre informazioni'], true)) $errors[] = 'Scegli un argomento disponibile.';
if (mb_strlen($data['messaggio']) < 10 || mb_strlen($data['messaggio']) > 3000) $errors[] = 'Scrivi un messaggio da 10 a 3000 caratteri.';
if (($_POST['privacy'] ?? '') !== '1') $errors[] = 'Leggi l’informativa privacy e conferma la richiesta di ricontatto.';
if (!empty($_POST['website'])) $errors[] = 'Richiesta non valida.';
if (time() - (int)($_SESSION['contact_last_sent'] ?? 0) < 60) $errors[] = 'Hai già inviato una richiesta. Attendi almeno un minuto prima di inviarne un’altra.';

if (!$errors) {
    // Connessione separata: se il DB/migrazione non è disponibile non mostrare
    // una falsa conferma e non bloccare il resto del sito pubblico.
    try {
        $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // Limite server aggiuntivo, indipendente dal cookie di sessione.
        $recent = $db->prepare('SELECT COUNT(*) FROM richieste_contatto WHERE email = ? AND data_creazione > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
        $recent->execute([$data['email']]);
        if ((int)$recent->fetchColumn() >= 5) {
            $errors[] = 'Troppe richieste per questo indirizzo. Riprova più tardi.';
        } else {
            $stmt = $db->prepare('INSERT INTO richieste_contatto (nome,email,telefono,oggetto,messaggio) VALUES (?,?,?,?,?)');
            $stmt->execute([$data['nome'], $data['email'], $data['telefono'] ?: null, $data['oggetto'], $data['messaggio']]);
            $_SESSION['contact_last_sent'] = time();
            $_SESSION['contact_sent'] = true;
        }
    } catch (PDOException $e) {
        error_log('Modulo contatti ICBR non disponibile: ' . $e->getMessage());
        $errors[] = 'Non siamo riusciti a registrare la richiesta. Riprova più tardi; nessun messaggio è stato inviato.';
    }
}
if ($errors) {
    $_SESSION['contact_errors'] = $errors;
    $_SESSION['contact_old'] = $data;
}
header('Location: ' . base_path('/index.php#contatti'), true, 303);
exit;
