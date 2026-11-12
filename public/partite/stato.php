<?php
/** Cambio di stato di una partita: apri/chiudi prenotazioni, concludi, annulla. */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/partite.php';
start_secure_session();
require_login();
partite_require_ready();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect_with_message('/partite/list.php', 'Operazione non valida.', 'error');
verify_csrf_token($_POST['csrf_token'] ?? '');

$id = (int)($_POST['id_partita'] ?? 0);
$nuovo = is_string($_POST['stato'] ?? null) ? $_POST['stato'] : '';
$partita = partita_get($id);
if (!$partita) redirect_with_message('/partite/list.php', 'Partita non trovata.', 'error');
$back = '/partite/view.php?id=' . $id;

if (!in_array($nuovo, PARTITE_TRANSIZIONI[$partita['stato']] ?? [], true)) {
    redirect_with_message($back, 'Passaggio di stato non ammesso.', 'error');
}
$pdo = get_db_connection();
if ($nuovo === 'aperta' && strtotime($partita['prenotazioni_chiudono']) <= time()) {
    // Riaprire dopo la scadenza: sposta la chiusura a 30 minuti prima del calcio d'inizio.
    $chiusura = max(time() + 600, strtotime($partita['data_ora']) - 1800);
    if ($chiusura >= strtotime($partita['data_ora'])) {
        redirect_with_message($back, 'La partita è già iniziata: non si possono riaprire le prenotazioni.', 'error');
    }
    $pdo->prepare('UPDATE partite SET stato = ?, prenotazioni_chiudono = ? WHERE id_partita = ?')->execute([$nuovo, date('Y-m-d H:i:00', $chiusura), $id]);
    redirect_with_message($back, 'Prenotazioni riaperte fino alle ' . date('H:i', $chiusura) . '.');
}
$pdo->prepare('UPDATE partite SET stato = ? WHERE id_partita = ?')->execute([$nuovo, $id]);
$messaggi = ['aperta' => 'Prenotazioni aperte: i soci ora vedono la partita.', 'chiusa' => 'Prenotazioni chiuse.',
    'conclusa' => 'Partita conclusa e archiviata.', 'annullata' => 'Partita annullata.', 'bozza' => 'Partita riportata in bozza.'];
redirect_with_message($back, $messaggi[$nuovo]);
