<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

$totale_soci = (int)$pdo->query('SELECT COUNT(*) FROM soci WHERE attivo_record = 1')->fetchColumn();

$stagione_attiva = $pdo->query(
    "SELECT id_stagione, codice_stagione FROM stagioni WHERE attiva = 1 ORDER BY id_stagione DESC LIMIT 1"
)->fetch();

$tesserati_stagione_corrente = 0;
if ($stagione_attiva) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM tesseramenti WHERE id_stagione = :id_stagione AND attivo_portale = 1'
    );
    $stmt->execute(['id_stagione' => $stagione_attiva['id_stagione']]);
    $tesserati_stagione_corrente = (int)$stmt->fetchColumn();
}

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

<p class="note">
    I tesseramenti di ogni socio sono consultabili dalla relativa scheda socio.
    Moduli import Excel, gestione pagamenti, prima nota e messaggi WhatsApp
    sono pianificati nelle fasi successive della roadmap (Fase 3 in poi).
</p>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
