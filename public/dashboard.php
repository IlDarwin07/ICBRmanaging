<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

// Totale soci in anagrafica (record attivi)
$totale_soci = (int)$pdo->query('SELECT COUNT(*) FROM soci WHERE attivo_record = 1')->fetchColumn();

// Tesserati totali (tutte le stagioni, attivo_portale = 1)
$tesserati_totali = (int)$pdo->query('SELECT COUNT(DISTINCT id_socio) FROM tesseramenti WHERE attivo_portale = 1')->fetchColumn();

// Stagione attiva
$stagione_attiva = $pdo->query(
    "SELECT id_stagione, codice_stagione FROM stagioni WHERE attiva = 1 ORDER BY id_stagione DESC LIMIT 1"
)->fetch();

$tesserati_stagione_corrente = 0;
$soci_anagrafica_confermata  = 0;

if ($stagione_attiva) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM tesseramenti WHERE id_stagione = :id_stagione AND attivo_portale = 1'
    );
    $stmt->execute(['id_stagione' => $stagione_attiva['id_stagione']]);
    $tesserati_stagione_corrente = (int)$stmt->fetchColumn();

    $stmt2 = $pdo->prepare(
        'SELECT COUNT(*) FROM tesseramenti WHERE id_stagione = :id_stagione AND conferma_anagrafica = 1'
    );
    $stmt2->execute(['id_stagione' => $stagione_attiva['id_stagione']]);
    $soci_anagrafica_confermata = (int)$stmt2->fetchColumn();
}

// Distribuzione soci per paese
$soci_per_paese = $pdo->query(
    "SELECT
        COALESCE(NULLIF(TRIM(nazionalita), ''), 'Non specificata') AS paese,
        COUNT(*) AS totale
     FROM soci
     WHERE attivo_record = 1
     GROUP BY paese
     ORDER BY totale DESC, paese ASC"
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
        <span class="card-value"><?= $tesserati_totali ?></span>
        <span class="card-label">Tesserati totali</span>
    </div>
    <div class="card">
        <span class="card-value"><?= $soci_anagrafica_confermata ?></span>
        <span class="card-label">
            Anagrafica confermata
            <?= $stagione_attiva ? '(' . h($stagione_attiva['codice_stagione']) . ')' : '(nessuna stagione attiva)' ?>
        </span>
    </div>
    <div class="card">
        <span class="card-value"><?= $tesserati_stagione_corrente ?></span>
        <span class="card-label">
            Tesserati<?= $stagione_attiva ? ' (' . h($stagione_attiva['codice_stagione']) . ')' : '' ?>
        </span>
    </div>
</div>

<div class="quick-links">
    <a class="btn" href="/soci/list.php">Anagrafica soci</a>
    <a class="btn btn-secondary" href="/soci/create.php">+ Nuovo socio</a>
</div>

<h2>Soci per nazionalit&agrave;</h2>
<?php if (empty($soci_per_paese)): ?>
    <p class="note">Nessun dato disponibile.</p>
<?php else: ?>
    <table class="data-table" style="max-width:420px">
        <thead>
            <tr>
                <th>Nazionalit&agrave;</th>
                <th style="text-align:right">N. soci</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($soci_per_paese as $row): ?>
                <tr>
                    <td><?= h($row['paese']) ?></td>
                    <td style="text-align:right"><?= (int)$row['totale'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p class="note" style="margin-top:1.5rem">
    I tesseramenti di ogni socio sono consultabili dalla relativa scheda socio.
    Moduli import Excel, gestione pagamenti, prima nota e messaggi WhatsApp
    sono pianificati nelle fasi successive della roadmap (Fase 3 in poi).
</p>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
