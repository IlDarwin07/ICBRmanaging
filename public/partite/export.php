<?php
/** Elenco presenze della serata in PDF da stampare. Il CSV resta disponibile con ?formato=csv. */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/partite.php';
require_once __DIR__ . '/../../includes/pdf_simple.php';
start_secure_session();
require_login();
partite_require_ready();
if (!can('partite.export')) {
    render_error_page('Questa operazione non è abilitata', 'Non hai il permesso di esportare le presenze.', 403);
}

$id = (int)($_GET['id'] ?? 0);
$partita = partita_get($id);
if (!$partita) render_error_page('Partita non trovata', 'Torna all\'elenco delle partite.');

$righe = array_values(array_filter(prenotazioni_partita($id), fn($r) => $r['stato'] !== 'annullata'));
$base = 'presenze-' . date('Y-m-d', strtotime($partita['data_ora'])) . '-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($partita['titolo'])), '-');

if (($_GET['formato'] ?? '') === 'csv') {
    audit_event('partite', 'esportazione', 'partite', (string)$id);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $base . '.csv"');
    header('Cache-Control: no-store');

    // Evita che Excel interpreti come formule i campi che iniziano con = + - @
    $cell = fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v;
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [$partita['titolo'], partita_data_label($partita['data_ora']), $partita['sala_nome']], ';');
    fputcsv($out, ['Sedia', 'Cognome', 'Nome', 'Telefono', 'Stato', 'Prenotato il'], ';');
    foreach ($righe as $r) {
        fputcsv($out, array_map($cell, [$r['numero_posto'] ?? '', $r['cognome'], $r['nome'], $r['telefono'] ?? '',
            prenotazione_stato_label($r['stato']), date('d/m/Y H:i', strtotime($r['created_at']))]), ';');
    }
    fclose($out);
    exit;
}

audit_event('partite', 'esportazione_pdf', 'partite', (string)$id);

$presenti = array_values(array_filter($righe, fn($r) => $r['stato'] === 'presente'));
usort($presenti, function ($a, $b) {
    $pa = $a['numero_posto'] !== null ? (int)$a['numero_posto'] : PHP_INT_MAX;
    $pb = $b['numero_posto'] !== null ? (int)$b['numero_posto'] : PHP_INT_MAX;
    return $pa <=> $pb ?: strcmp($a['cognome'] . ' ' . $a['nome'], $b['cognome'] . ' ' . $b['nome']);
});
$attesa = array_values(array_filter($righe, fn($r) => $r['stato'] === 'attesa'));
$capienza = (int)$partita['capienza'];

$colonne = [['Sedia', 40], ['Socio', 85], ['Telefono', 290], ['Prenotato il', 385], ['Presente', 500]];
$pdf = new SimplePdf();

$nuovaPagina = function () use ($pdf, $partita, $presenti, $attesa, $capienza, $colonne): float {
    $pdf->addPage();
    $pdf->text(40, 50, 'Inter Club Javier Zanetti Brindisi', 11, true);
    $pdf->text(40, 72, 'Elenco presenze', 16, true);
    $pdf->text(40, 92, $pdf->fit($partita['titolo'] . ($partita['competizione'] ? ' - ' . $partita['competizione'] : ''), 515, 11), 11);
    $pdf->text(40, 108, partita_data_label($partita['data_ora']) . ' - ' . $partita['sala_nome'], 10);
    $pdf->text(40, 124, 'Presenti: ' . count($presenti) . ' su ' . $capienza . ' posti - In lista d\'attesa: ' . count($attesa), 10);
    $y = 152.0;
    $pdf->fillRect(40, $y - 13, 515, 19, 0.9);
    foreach ($colonne as [$t, $x]) {
        $pdf->text($x, $y, $t, 9, true);
    }
    return $y + 22;
};

$y = $nuovaPagina();
foreach ([['Presenti', $presenti], ['Lista d\'attesa', $attesa]] as [$etichetta, $gruppo]) {
    if (!$gruppo) continue;
    if ($y > 740) {
        $y = $nuovaPagina();
    }
    $pdf->text(40, $y, $etichetta . ' (' . count($gruppo) . ')', 10, true);
    $y += 18;
    foreach ($gruppo as $r) {
        if ($y > 770) {
            $y = $nuovaPagina();
        }
        $pdf->text(40, $y, $r['numero_posto'] !== null ? (string)(int)$r['numero_posto'] : '-', 9);
        $pdf->text(85, $y, $pdf->fit($r['cognome'] . ' ' . $r['nome'], 195, 9), 9);
        $pdf->text(290, $y, $pdf->fit((string)($r['telefono'] ?? ''), 90, 9), 9);
        $pdf->text(385, $y, date('d/m/Y H:i', strtotime($r['created_at'])), 9);
        $pdf->rect(510, $y - 9, 11, 11);
        $pdf->line(40, $y + 6, 555, $y + 6, 0.25);
        $y += 20;
    }
    $y += 8;
}
if (!$presenti && !$attesa) {
    $pdf->text(40, $y, 'Nessuna prenotazione per questa partita.', 10);
}

$n = $pdf->pageCount();
for ($p = 0; $p < $n; $p++) {
    $pdf->text(40, 815, 'Stampato il ' . date('d/m/Y H:i') . ' - Pagina ' . ($p + 1) . ' di ' . $n, 8, false, $p);
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $base . '.pdf"');
header('Cache-Control: no-store');
echo $pdf->output();
