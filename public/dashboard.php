<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

// 1. Soci registrati (tutti i record attivi in anagrafica)
$totale_soci = (int)$pdo->query('SELECT COUNT(*) FROM soci WHERE attivo_record = 1')->fetchColumn();

// Stagione attiva e stagione precedente
$stagioni = $pdo->query(
    "SELECT id_stagione, codice_stagione FROM stagioni ORDER BY codice_stagione DESC LIMIT 2"
)->fetchAll();

$stagione_attiva    = $stagioni[0] ?? null;
$stagione_precedente = $stagioni[1] ?? null;

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
    <a class="btn" href="/soci/list.php">Anagrafica soci</a>
    <a class="btn btn-secondary" href="/soci/create.php">+ Nuovo socio</a>
    <a class="btn btn-secondary" href="/importazioni/upload.php">&#8682; Importa XLSX</a>
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
