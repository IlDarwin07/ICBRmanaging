<?php
/** Soci sostenitori che devono pagare la quota per una partita: vista e PDF da stampare. */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/famiglia.php';
require_once __DIR__ . '/../../includes/pdf_simple.php';
start_secure_session();
require_login();
partite_require_ready();

$id = (int)($_GET['id'] ?? 0);
$partita = partita_get($id);
if (!$partita) render_error_page('Partita non trovata', 'Torna all\'elenco delle partite.');

$elenco = partita_sostenitori_da_pagare($id);
$quota = PARTITA_QUOTA_SOSTENITORE;
$totale = $quota * count($elenco);
$euro = fn(float $v): string => number_format($v, 2, ',', '.');

if (isset($_GET['pdf'])) {
    audit_event('partite', 'esportazione_pdf_sostenitori', 'partite', (string)$id);
    $colonne = [['N.', 40], ['Socio', 62], ['Tessera', 220], ['Telefono', 295], ['Prenotato da', 375], ['Importo', 475], ['Pagato', 520]];
    $pdf = new SimplePdf();

    $intestazione = function () use ($pdf, $partita, $quota, $euro): float {
        $pdf->addPage();
        $pdf->text(40, 50, 'Inter Club Javier Zanetti Brindisi', 11, true);
        $pdf->text(40, 72, 'Soci sostenitori da incassare', 16, true);
        $pdf->text(40, 92, $pdf->fit($partita['titolo'] . ($partita['competizione'] ? ' - ' . $partita['competizione'] : ''), 515, 11), 11);
        $pdf->text(40, 108, partita_data_label($partita['data_ora']) . ' - ' . $partita['sala_nome'], 10);
        $pdf->text(40, 124, 'Quota per ogni socio sostenitore: € ' . $euro($quota), 10);
        return 152.0;
    };
    $titoli = function (float $y) use ($pdf, $colonne): float {
        $pdf->fillRect(40, $y - 13, 515, 19, 0.9);
        foreach ($colonne as [$t, $x]) {
            $pdf->text($x, $y, $t, 9, true);
        }
        return $y + 20;
    };

    $y = $titoli($intestazione());
    foreach ($elenco as $i => $r) {
        if ($y > 770) {
            $y = $titoli($intestazione());
        }
        $pdf->text(40, $y, (string)($i + 1), 9);
        $pdf->text(62, $y, $pdf->fit($r['cognome'] . ' ' . $r['nome'], 152, 9), 9);
        $pdf->text(220, $y, $pdf->fit((string)($r['numero_tessera'] ?? ''), 70, 9), 9);
        $pdf->text(295, $y, $pdf->fit((string)($r['telefono'] ?? ''), 75, 9), 9);
        $pdf->text(375, $y, $pdf->fit((string)($r['prenotata_da_nome'] ?? ''), 95, 9), 9);
        $pdf->text(475, $y, '€ ' . $euro($quota), 9);
        $pdf->rect(523, $y - 9, 11, 11);
        $pdf->line(40, $y + 6, 555, $y + 6, 0.25);
        $y += 20;
    }
    if (!$elenco) {
        $pdf->text(40, $y, 'Nessun socio sostenitore prenotato per questa partita.', 10);
    } else {
        if ($y > 760) {
            $y = $titoli($intestazione());
        }
        $y += 10;
        $pdf->text(40, $y, 'Soci sostenitori: ' . count($elenco), 10, true);
        $pdf->text(300, $y, 'Totale da incassare: € ' . $euro($totale), 10, true);
    }
    $n = $pdf->pageCount();
    for ($p = 0; $p < $n; $p++) {
        $pdf->text(40, 815, 'Stampato il ' . date('d/m/Y H:i') . ' - Pagina ' . ($p + 1) . ' di ' . $n, 8, false, $p);
    }

    $file = 'sostenitori-' . date('Y-m-d', strtotime($partita['data_ora'])) . '-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($partita['titolo'])), '-') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $file . '"');
    header('Cache-Control: no-store');
    echo $pdf->output();
    exit;
}

$page_title = 'Sostenitori da incassare';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <div>
        <span class="admin-eyebrow">SEDE / PARTITE</span>
        <h1>Sostenitori da incassare</h1>
        <p><strong><?= h($partita['titolo']) ?></strong> &middot; <?= h(partita_data_label($partita['data_ora'])) ?> &middot; <?= h($partita['sala_nome']) ?></p>
    </div>
    <div class="quick-links">
        <a class="btn" href="<?= u('/partite/sostenitori.php?id=' . $id . '&pdf=1') ?>" target="_blank" rel="noopener">Apri PDF da stampare</a>
        <a class="btn btn-secondary" href="<?= u('/partite/view.php?id=' . $id) ?>">Presenze e sedie</a>
        <a class="btn btn-secondary" href="<?= u('/partite/list.php') ?>">Partite</a>
    </div>
</div>

<p>In sede la partita e gratis per tutti, tranne i soci sostenitori: <strong>&euro; <?= $euro($quota) ?></strong> a testa. Qui compaiono solo i sostenitori con presenza confermata.</p>

<?php if (!$elenco): ?>
    <section class="admin-panel">
        <h2>Nessun sostenitore da incassare</h2>
        <p>Nessun socio sostenitore ha la presenza confermata per questa partita. Se ti aspettavi qualcuno, controlla che il suo tesseramento abbia la categoria sostenitore.</p>
    </section>
<?php else: ?>
    <table class="data-table">
        <thead><tr><th>N.</th><th>Socio</th><th>Tessera</th><th>Telefono</th><th>Prenotato da</th><th>Sedia</th><th>Importo</th></tr></thead>
        <tbody>
        <?php foreach ($elenco as $i => $r): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= h($r['cognome'] . ' ' . $r['nome']) ?></strong></td>
                <td><?= h((string)($r['numero_tessera'] ?? '')) ?></td>
                <td><?= h((string)($r['telefono'] ?? '')) ?></td>
                <td><?= h((string)($r['prenotata_da_nome'] ?? '')) ?></td>
                <td><?= $r['numero_posto'] !== null ? (int)$r['numero_posto'] : '&mdash;' ?></td>
                <td>&euro; <?= $euro($quota) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="6">Totale da incassare (<?= count($elenco) ?> soci)</th><th>&euro; <?= $euro($totale) ?></th></tr></tfoot>
    </table>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
