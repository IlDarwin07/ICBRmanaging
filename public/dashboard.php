<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

// 1. Soci registrati (tutti i record attivi in anagrafica)
$totale_soci = (int)$pdo->query('SELECT COUNT(*) FROM soci WHERE attivo_record = 1')->fetchColumn();

// Stagione corrente: quella contrassegnata come attiva; se nessuna lo è,
// fallback all'ultima inserita. Stagione precedente = quella subito prima.
$stagione_attiva = $pdo->query(
    "SELECT id_stagione, codice_stagione FROM stagioni WHERE attiva = 1 ORDER BY codice_stagione DESC LIMIT 1"
)->fetch();
if (!$stagione_attiva) {
    $stagione_attiva = $pdo->query(
        'SELECT id_stagione, codice_stagione FROM stagioni ORDER BY codice_stagione DESC LIMIT 1'
    )->fetch();
}
$stagione_precedente = null;
if ($stagione_attiva) {
    $stmt_prec = $pdo->prepare(
        'SELECT id_stagione, codice_stagione FROM stagioni WHERE codice_stagione < :cod ORDER BY codice_stagione DESC LIMIT 1'
    );
    $stmt_prec->execute(['cod' => $stagione_attiva['codice_stagione']]);
    $stagione_precedente = $stmt_prec->fetch();
}

// 2. Soci attivi nella stagione corrente (attivo_portale = 1)
$soci_attivi_corrente = 0;
// 3. Anagrafica confermata nella stagione corrente
$soci_anagrafica_confermata = 0;
// 4. Attivi nell'ultima stagione ma NON rinnovati in quella corrente
$non_rinnovati = 0;

if ($stagione_attiva) {
    $id_att = $stagione_attiva['id_stagione'];

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM tesseramenti WHERE id_stagione = :id AND attivo_portale = 1'
    );
    $stmt->execute(['id' => $id_att]);
    $soci_attivi_corrente = (int)$stmt->fetchColumn();

    $stmt2 = $pdo->prepare(
        'SELECT COUNT(*) FROM tesseramenti WHERE id_stagione = :id AND conferma_anagrafica = 1'
    );
    $stmt2->execute(['id' => $id_att]);
    $soci_anagrafica_confermata = (int)$stmt2->fetchColumn();
}

if ($stagione_precedente) {
    $id_prec = $stagione_precedente['id_stagione'];
    $id_att  = $stagione_attiva['id_stagione'] ?? null;

    if ($id_att) {
        $stmt3 = $pdo->prepare(
            'SELECT COUNT(DISTINCT t_prec.id_socio)
             FROM tesseramenti t_prec
             WHERE t_prec.id_stagione = :id_prec
               AND t_prec.attivo_portale = 1
               AND NOT EXISTS (
                   SELECT 1 FROM tesseramenti t_att
                   WHERE t_att.id_socio = t_prec.id_socio
                     AND t_att.id_stagione = :id_att
               )'
        );
        $stmt3->execute(['id_prec' => $id_prec, 'id_att' => $id_att]);
        $non_rinnovati = (int)$stmt3->fetchColumn();
    }
}

// ---------------------------------------------------------------------
// KPI economici stagione corrente (logica centralizzata sulle quote)
// ---------------------------------------------------------------------
$incassato_stagione = 0.0;
$residuo_previsto   = 0.0;
$soci_da_saldare    = 0;

if ($stagione_attiva) {
    $stmt_kpi = $pdo->prepare(
        "SELECT
             COALESCE(NULLIF(t.quota_tesseramento, 0), NULLIF(tip.quota_standard, 0)) AS quota_dovuta,
             COALESCE(pag.versato_netto, 0) AS versato_netto
         FROM tesseramenti t
         LEFT JOIN tipologie_tesseramento tip ON tip.id_tipologia = t.id_tipologia
         LEFT JOIN (
             SELECT id_tesseramento,
                    SUM(CASE WHEN tipo_pagamento = 'rimborso' THEN -importo ELSE importo END) AS versato_netto
             FROM pagamenti_tesseramenti
             GROUP BY id_tesseramento
         ) pag ON pag.id_tesseramento = t.id_tesseramento
         WHERE t.id_stagione = :id_stagione"
    );
    $stmt_kpi->execute(['id_stagione' => $stagione_attiva['id_stagione']]);
    foreach ($stmt_kpi->fetchAll() as $r) {
        $quota = $r['quota_dovuta'] !== null ? (float)$r['quota_dovuta'] : null;
        $netto = (float)$r['versato_netto'];
        // incassato netto: anche i netti negativi (anomalia rimborsi)
        // vanno sommati per com'è, altrimenti l'incassato è sovrastimato
        $incassato_stagione += $netto;
        if ($quota !== null) {
            $residuo_previsto += tess_residuo($quota, $netto) ?? 0.0;
            if ($netto < $quota - 0.004) $soci_da_saldare++;
        }
    }
}

// Saldi complessivi di cassa e banca (prima nota)
$saldi_conti = ['cassa' => 0.0, 'banca' => 0.0, 'altro' => 0.0];
foreach ($pdo->query("SELECT conto, SUM(CASE WHEN tipo_movimento = 'entrata' THEN importo ELSE -importo END) AS saldo FROM prima_nota GROUP BY conto") as $r) {
    $saldi_conti[$r['conto']] = (float)$r['saldo'];
}

// Distribuzione soci per comune di residenza
$soci_per_comune = $pdo->query(
    "SELECT
        COALESCE(NULLIF(TRIM(comune), ''), 'Non specificato') AS comune,
        COUNT(*) AS totale
     FROM soci
     WHERE attivo_record = 1
     GROUP BY comune
     ORDER BY totale DESC, comune ASC"
)->fetchAll();

$page_title = 'Dashboard';
require __DIR__ . '/../includes/layout_header.php';
?>
<h1>Dashboard</h1>

<div class="cards-grid">
    <div class="card">
        <span class="card-value"><?= $totale_soci ?></span>
        <span class="card-label">Soci registrati</span>
    </div>
    <div class="card">
        <span class="card-value"><?= $soci_attivi_corrente ?></span>
        <span class="card-label">
            Soci attivi
            <?= $stagione_attiva ? '(' . h($stagione_attiva['codice_stagione']) . ')' : '(nessuna stagione attiva)' ?>
        </span>
    </div>
    <div class="card">
        <span class="card-value"><?= $soci_anagrafica_confermata ?></span>
        <span class="card-label">
            Anagrafica confermata
            <?= $stagione_attiva ? '(' . h($stagione_attiva['codice_stagione']) . ')' : '' ?>
        </span>
    </div>
    <div class="card card-warning">
        <span class="card-value"><?= $non_rinnovati ?></span>
        <span class="card-label">
            Non rinnovati
            <?php if ($stagione_precedente && $stagione_attiva): ?>
                (attivi in <?= h($stagione_precedente['codice_stagione']) ?>, assenti in <?= h($stagione_attiva['codice_stagione']) ?>)
            <?php elseif (!$stagione_precedente): ?>
                (nessuna stagione precedente)
            <?php endif; ?>
        </span>
    </div>
</div>

<div class="quick-links">
    <a class="btn" href="<?= u('/soci/list.php') ?>">Anagrafica soci</a>
    <a class="btn btn-secondary" href="<?= u('/soci/create.php') ?>">+ Nuovo socio</a>
    <a class="btn btn-secondary" href="<?= u('/tesseramenti/list.php') ?>">Tesseramenti</a>
    <a class="btn btn-secondary" href="<?= u('/importazioni/upload.php') ?>">&#8682; Importa XLSX</a>
</div>

<h2>Andamento economico
    <?= $stagione_attiva ? h($stagione_attiva['codice_stagione']) : '' ?></h2>
<div class="cards-grid">
    <div class="card">
        <span class="card-value">€ <?= euro($incassato_stagione) ?></span>
        <span class="card-label">Incassato stagione corrente</span>
    </div>
    <div class="card <?= $soci_da_saldare > 0 ? 'card-warning' : '' ?>">
        <span class="card-value">€ <?= euro($residuo_previsto) ?></span>
        <span class="card-label">Residuo previsto (<?= $soci_da_saldare ?> soci da saldare)</span>
    </div>
    <div class="card">
        <span class="card-value">€ <?= euro($saldi_conti['cassa']) ?></span>
        <span class="card-label">Saldo cassa (prima nota)</span>
    </div>
    <div class="card">
        <span class="card-value">€ <?= euro($saldi_conti['banca']) ?></span>
        <span class="card-label">Saldo banca (prima nota)</span>
    </div>
</div>
<div class="quick-links">
    <a class="btn btn-secondary" href="<?= u('/prima_nota/list.php') ?>">Prima nota</a>
    <a class="btn btn-secondary" href="<?= u('/tesseramenti/list.php') ?>?stato_pagamento=da_pagare">Soci da saldare</a>
    <a class="btn btn-secondary" href="<?= u('/messaggi/compose.php') ?>">Componi messaggi</a>
</div>

<h2>Soci per comune</h2>
<?php if (empty($soci_per_comune)): ?>
    <p class="note">Nessun dato disponibile.</p>
<?php else: ?>
    <table class="data-table" style="max-width:420px">
        <thead>
            <tr>
                <th>Comune</th>
                <th style="text-align:right">N. soci</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($soci_per_comune as $row): ?>
                <tr>
                    <td><?= h($row['comune']) ?></td>
                    <td style="text-align:right"><?= (int)$row['totale'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p class="note" style="margin-top:1.5rem">
    I tesseramenti di ogni socio sono consultabili dalla relativa scheda socio.
</p>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
