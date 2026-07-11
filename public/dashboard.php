<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

// Totale soci in anagrafica (record attivi)
$totale_soci = (int)$pdo->query('SELECT COUNT(*) FROM soci WHERE attivo_record = 1')->fetchColumn();

// Soci attivi (attivo_record = 1, già uguale a totale_soci, ma esplicitiamo il conteggio)
$soci_attivi = (int)$pdo->query('SELECT COUNT(*) FROM soci WHERE attivo_record = 1')->fetchColumn();

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

// Distribuzione soci per paese (nazionalita), solo soci con record attivo
$soci_per_paese_stmt = $pdo->query(
    "SELECT 
        COALESCE(NULLIF(TRIM(nazionalita), ''), 'Non specificata') AS paese,
        COUNT(*) AS totale
     FROM soci
     WHERE attivo_record = 1
     GROUP BY paese
     ORDER BY totale DESC, paese ASC"
);
$soci_per_paese = $soci_per_paese_stmt->fetchAll();

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
        <span class="card-value"><?= $soci_anagrafica_confermata ?></span>
        <span class="card-label">
            Anagrafica confermata
            <?= $stagione_attiva ? '(' . h($stagione_attiva['codice_stagione']) . ')' : '(nessuna stagione attiva)' ?>
        </span>
    </div>
    <div class="card">
        <span class="card-value"><?= $tesserati_stagione_corrente ?></span>
        <span class="card-label">
            Tesserati attivi
            <?= $stagione_attiva ? '(' . h($stagione_attiva['codice_stagione']) . ')' : '(nessuna stagione attiva)' ?>
        </span>
    </div>
</div>

<div class="quick-links">
    <a class="btn" href="/soci/list.php">Anagrafica soci</a>
    <a class="btn btn-secondary" href="/soci/create.php">+ Nuovo socio</a>
</div>

<?php if (!empty($soci_per_paese)): ?>
<section class="dashboard-section">
    <h2>Soci per paese</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Paese / Nazionalità</th>
                <th>Numero soci</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($soci_per_paese as $riga): ?>
            <tr>
                <td><?= h($riga['paese']) ?></td>
                <td><?= (int)$riga['totale'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<p class="note">
    I tesseramenti di ogni socio sono consultabili dalla relativa scheda socio.
    Moduli import Excel, gestione pagamenti, prima nota e messaggi WhatsApp
    sono pianificati nelle fasi successive della roadmap (Fase 3 in poi).
</p>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
