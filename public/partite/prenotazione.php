<?php
/** Azioni del direttivo sulle prenotazioni di una partita (solo POST). */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/partite.php';
start_secure_session();
require_login();
partite_require_ready();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect_with_message('/partite/list.php', 'Operazione non valida.', 'error');
verify_csrf_token($_POST['csrf_token'] ?? '');

$id_partita = (int)($_POST['id_partita'] ?? 0);
$id_socio = (int)($_POST['id_socio'] ?? 0);
$azione = is_string($_POST['azione'] ?? null) ? $_POST['azione'] : '';
$back = '/partite/view.php?id=' . $id_partita;

try {
    switch ($azione) {
        case 'assegna':
            $numero = filter_var($_POST['numero'] ?? '', FILTER_VALIDATE_INT);
            if ($id_socio <= 0) throw new DomainException('Seleziona prima un socio dall\'elenco dei presenti.');
            if ($numero === false) throw new DomainException('Scegli una sedia.');
            prenotazione_assegna_posto($id_partita, $id_socio, $numero);
            redirect_with_message($back, "Sedia $numero assegnata.");
        case 'libera':
            prenotazione_assegna_posto($id_partita, $id_socio, null);
            redirect_with_message($back, 'Sedia liberata.');
        case 'auto':
            $n = prenotazioni_assegna_automatico($id_partita);
            redirect_with_message($back, $n ? "Assegnate $n sedie in ordine di prenotazione." : 'Nessuna sedia libera da assegnare.', $n ? 'success' : 'error');
        case 'aggiungi':
            $stmt = get_db_connection()->prepare('SELECT 1 FROM soci WHERE id_socio = ? AND attivo_record = 1');
            $stmt->execute([$id_socio]);
            if (!$stmt->fetchColumn()) throw new DomainException('Socio non trovato.');
            $stato = prenotazione_conferma($id_partita, $id_socio, true);
            redirect_with_message($back, $stato === 'presente' ? 'Socio aggiunto ai presenti.' : 'Sala piena: socio messo in lista d\'attesa.', $stato === 'presente' ? 'success' : 'error');
        case 'promuovi':
            $pdo = get_db_connection();
            $p = partita_get($id_partita);
            if (!$p || in_array($p['stato'], ['conclusa', 'annullata'], true)) throw new DomainException('La partita non è modificabile.');
            $pdo->prepare("UPDATE prenotazioni SET stato = 'presente' WHERE id_partita = ? AND id_socio = ? AND stato = 'attesa'")->execute([$id_partita, $id_socio]);
            partita_notifica_promozione($id_partita, $id_socio);
            redirect_with_message($back, 'Socio spostato tra i presenti.');
        case 'annulla':
            $promosso = prenotazione_annulla($id_partita, $id_socio, true);
            if ($promosso) partita_notifica_promozione($id_partita, $promosso);
            redirect_with_message($back, 'Presenza annullata.' . ($promosso ? ' Il primo della lista d\'attesa è passato tra i presenti.' : ''));
        default:
            throw new DomainException('Operazione non riconosciuta.');
    }
} catch (DomainException $e) {
    redirect_with_message($back, $e->getMessage(), 'error');
}
