<?php
/** Conferma o annulla la presenza del socio collegato, o di un suo familiare (solo POST). */
require_once __DIR__ . '/../../includes/auth_soci.php';
require_once __DIR__ . '/../../includes/soci_layout.php';
require_once __DIR__ . '/../../includes/famiglia.php';
$socio = require_socio_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . base_path('/soci-area/index.php')); exit; }
soci_verify_csrf();

$id_partita = (int)($_POST['id_partita'] ?? 0);
$azione = is_string($_POST['azione'] ?? null) ? $_POST['azione'] : '';

// Prenotazione per un familiare: solo se il direttivo ha associato i due soci.
$target = (int)$socio['id_socio'];
$da = null;
$nome_per = '';
$per = (int)($_POST['id_socio_per'] ?? 0);
if ($per > 0 && $per !== $target) {
    if (!famiglia_puo_prenotare_per($target, $per)) {
        redirect_with_message('/soci-area/index.php', 'Non puoi prenotare per questa persona.', 'error');
    }
    foreach (famiglia_familiari($target) as $f) {
        if ((int)$f['id_socio'] === $per) $nome_per = $f['nome'];
    }
    $da = $target;
    $target = $per;
}

try {
    if ($azione === 'conferma') {
        $stato = prenotazione_conferma_da($id_partita, $target, $da);
        if ($da !== null) {
            redirect_with_message('/soci-area/index.php', $stato === 'presente'
                ? 'Presenza confermata per ' . $nome_per . '.'
                : 'La sala e piena: ' . $nome_per . ' e in lista d\'attesa.');
        }
        if ($stato === 'presente') {
            // Nessun messaggio di conferma: solo ai soci sostenitori compare il promemoria della quota.
            $sostenitori = soci_sostenitori([$target]);
            if (!empty($sostenitori[$target])) {
                redirect_with_message('/soci-area/index.php', 'Promemoria: in sede sono € ' . number_format(PARTITA_QUOTA_SOSTENITORE, 2, ',', '.') . ' per questa partita.');
            }
            header('Location: ' . base_path('/soci-area/index.php'));
            exit;
        }
        redirect_with_message('/soci-area/index.php', 'La sala è piena: sei in lista d\'attesa. Se si libera un posto passi tra i presenti.');
    }
    if ($azione === 'annulla') {
        $promosso = prenotazione_annulla($id_partita, $target);
        if ($promosso) partita_notifica_promozione($id_partita, $promosso);
        redirect_with_message('/soci-area/index.php', $da !== null
            ? 'Prenotazione di ' . $nome_per . ' annullata.'
            : 'Prenotazione annullata. Grazie per averlo segnalato!');
    }
    redirect_with_message('/soci-area/index.php', 'Operazione non valida.', 'error');
} catch (DomainException $e) {
    redirect_with_message('/soci-area/index.php', $e->getMessage(), 'error');
}
