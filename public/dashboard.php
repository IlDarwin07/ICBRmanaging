<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

// --- KPI soci ---
$totale_soci = (int)$pdo->query(
    'SELECT COUNT(*) FROM soci'
)->fetchColumn();

$soci_attivi = (int)$pdo->query(
    'SELECT COUNT(*) FROM soci WHERE attivo_record = 1'
)->fetchColumn();

// --- Stagione attiva ---
$stagione_attiva = $pdo->query(
    "SELECT id_stagione, codice_stagione FROM stagioni WHERE attiva = 1 ORDER BY id_stagione DESC LIMIT 1"
)->fetch();

$tesserati_stagione_corrente = 0;
$anagrafica_confermata       = 0;

if ($stagione_attiva) {
    $stmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS totale_tesserati,
            SUM(conferma_anagrafica) AS confermati
         FROM tesseramenti
         WHERE id_stagione = :id_stagione AND attivo_portale = 1'
    );
    $stmt->execute(['id_stagione' => $stagione_attiva['id_stagione']]);
    $row = $stmt->fetch();
    $tesserati_stagione_corrente = (int)($row['totale_tesserati'] ?? 0);
    $anagrafica_confermata       = (int)($row['confermati']       ?? 0);
}

// --- Distribuzione soci per nazionalità ---
$soci_per_nazione = $pdo->query(
    "SELECT
        COALESCE(NULLIF(TRIM(nazionalita), ''), '—') AS nazione,
        COUNT(*) AS totale
     FROM soci
     WHERE attivo_record = 1
     GROUP BY nazione
     ORDER BY totale DESC, nazione ASC"
)->fetchAll();

$page_title = 'Dashboard';
require __DIR__ . '/../includes/layout_header.php';
?>
<h1>Dashboard</h1>

<div class="cards-grid">
    <div class="card">
        <span class="card-value"><?= $totale_soci ?></span>
        <span class="card-label">Soci in anagrafica</span>
    </div>
    <div class="card">
        <span class="card-value"><?= $soci_attivi ?></span>
        <span class="card-label">Soci attivi</span>
    </div>
    <div class="card">
        <span class="card-value"><?= $tesserati_stagione_corrente ?></span>
        <span class="card-label">
            Tesserati<?= $stagione_attiva ? ' (' . h($stagione_attiva['codice_stagione']) . ')' : '' ?>
        </span>
    </div>
    <div class="card">
        <span class="card-value"><?= $anagrafica_confermata ?></span>
        <span class="card-label">
            Anagrafica confermata<?= $stagione_attiva ? ' (' . h($stagione_attiva['codice_stagione']) . ')' : '' ?>
        </span>
    </div>
</div>

<div class="quick-links">
    <a class="btn" href="/soci/list.php">Anagrafica soci</a>
    <a class="btn btn-secondary" href="/soci/create.php">+ Nuovo socio</a>
</div>

<h2>Soci per nazionalità</h2>
<?php if (empty($soci_per_nazione)): ?>
    <p class="note">Nessun dato disponibile.</p>
<?php else: ?>
    <table class="data-table" style="max-width:420px">
        <thead>
            <tr>
                <th>Nazionalità</th>
                <th style="text-align:right">N. soci</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($soci_per_nazione as $row): ?>
                <tr>
                    <td><?= h($row['nazione']) ?></td>
                    <td style="text-align:right"><?= (int)$row['totale'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p class="note" style="margin-top:1.5rem">
    Moduli import Excel, gestione pagamenti, prima nota e messaggi WhatsApp
    sono pianificati nelle fasi successive della roadmap (Fase 3 in poi).
</p>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
