<?php
/** Conferma o annulla la presenza del socio collegato (solo POST). */
require_once __DIR__ . '/../../includes/auth_soci.php';
require_once __DIR__ . '/../../includes/soci_layout.php';
$socio = require_socio_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . base_path('/soci-area/index.php')); exit; }
soci_verify_csrf();

$id_partita = (int)($_POST['id_partita'] ?? 0);
$azione = is_string($_POST['azione'] ?? null) ? $_POST['azione'] : '';
try {
    if ($azione === 'conferma') {
        $stato = prenotazione_conferma($id_partita, $socio['id_socio']);
        redirect_with_message('/soci-area/index.php', $stato === 'presente'
            ? 'Presenza confermata! Il direttivo ti assegnerà la sedia.'
            : 'La sala è piena: sei in lista d\'attesa. Se si libera un posto passi tra i presenti.');
    }
    if ($azione === 'annulla') {
        $promosso = prenotazione_annulla($id_partita, $socio['id_socio']);
        if ($promosso) partita_notifica_promozione($id_partita, $promosso);
        redirect_with_message('/soci-area/index.php', 'Prenotazione annullata. Grazie per averlo segnalato!');
    }
    redirect_with_message('/soci-area/index.php', 'Operazione non valida.', 'error');
} catch (DomainException $e) {
    redirect_with_message('/soci-area/index.php', $e->getMessage(), 'error');
}
