<?php
/** Elenco presenze della serata in CSV (separatore ";" per Excel in italiano). */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/partite.php';
start_secure_session();
require_login();
partite_require_ready();

$id = (int)($_GET['id'] ?? 0);
$partita = partita_get($id);
if (!$partita) render_error_page('Partita non trovata', 'Torna all\'elenco delle partite.');

audit_event('partite', 'esportazione', 'partite', (string)$id);
$file = 'presenze-' . date('Y-m-d', strtotime($partita['data_ora'])) . '-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($partita['titolo'])), '-') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Cache-Control: no-store');

// Evita che Excel interpreti come formule i campi che iniziano con = + - @
$cell = fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v;
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, [$partita['titolo'], partita_data_label($partita['data_ora']), $partita['sala_nome']], ';');
fputcsv($out, ['Sedia', 'Cognome', 'Nome', 'Telefono', 'Stato', 'Prenotato il'], ';');
foreach (prenotazioni_partita($id) as $r) {
    if ($r['stato'] === 'annullata') continue;
    fputcsv($out, array_map($cell, [$r['numero_posto'] ?? '', $r['cognome'], $r['nome'], $r['telefono'] ?? '',
        prenotazione_stato_label($r['stato']), date('d/m/Y H:i', strtotime($r['created_at']))]), ';');
}
fclose($out);
