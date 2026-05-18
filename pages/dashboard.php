<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$pageTitle = 'Dashboard';
$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

// Stats
$stmt = $db->query("SELECT COUNT(*) as cnt FROM partite WHERE stato = 'aperta'");
$partiteAperte = $stmt->fetch()['cnt'];

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM prenotazioni WHERE user_id = ? AND stato = 'confermata'");
$stmt->execute([$_SESSION['user_id']]);
$miePrenotazioni = $stmt->fetch()['cnt'];

// Next matches
$stmt = $db->query("
    SELECT p.*, 
        (SELECT COUNT(*) FROM prenotazioni WHERE partita_id = p.id AND stato = 'confermata') as num_prenotazioni
    FROM partite p
    WHERE p.data_partita >= date('now') AND p.stato = 'aperta'
    ORDER BY p.data_partita ASC, p.ora_partita ASC
    LIMIT 5
");
$prossimePartite = $stmt->fetchAll();

// My upcoming reservations with seat
$stmt = $db->prepare("
    SELECT pr.*, p.titolo, p.squadra_casa, p.squadra_ospite,
        p.data_partita, p.ora_partita, p.luogo, p.stato as stato_partita,
        ap.numero_sedia
    FROM prenotazioni pr
    JOIN partite p ON pr.partita_id = p.id
    LEFT JOIN assegnazioni_posti ap ON ap.prenotazione_id = pr.id
    WHERE pr.user_id = ? AND pr.stato = 'confermata' AND p.data_partita >= date('now')
    ORDER BY p.data_partita ASC
");
$stmt->execute([$_SESSION['user_id']]);
$miePrenotazioniDettaglio = $stmt->fetchAll();

if (isAdmin()) {
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM users WHERE attivo = 1");
    $totaleSoci = $stmt->fetch()['cnt'];
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard">
    <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
    <p class="welcome">Benvenuto, <strong><?php echo htmlspecialchars($user['nome'] . ' ' . $user['cognome']); ?></strong>!</p>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-info">
                <span class="stat-number"><?php echo $partiteAperte; ?></span>
                <span class="stat-label">Partite Aperte</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
            <div class="stat-info">
                <span class="stat-number"><?php echo $miePrenotazioni; ?></span>
                <span class="stat-label">Le Mie Prenotazioni</span>
            </div>
        </div>
        <?php if (isAdmin()): ?>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <span class="stat-number"><?php echo $totaleSoci; ?></span>
                <span class="stat-label">Soci Attivi</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($miePrenotazioniDettaglio)): ?>
    <div class="section">
        <h3><i class="fas fa-chair"></i> Le Mie Prenotazioni</h3>
        <div class="cards-grid">
            <?php foreach ($miePrenotazioniDettaglio as $pren): ?>
            <div class="card">
                <div class="card-header">
                    <h4><?php echo htmlspecialchars($pren['titolo']); ?></h4>
                    <span class="badge badge-<?php echo $pren['stato_partita']; ?>">
                        <?php echo ucfirst($pren['stato_partita']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <p><i class="fas fa-futbol"></i> <?php echo htmlspecialchars($pren['squadra_casa'] . ' vs ' . $pren['squadra_ospite']); ?></p>
                    <p><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($pren['data_partita'])); ?> ore <?php echo $pren['ora_partita']; ?></p>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($pren['luogo']); ?></p>
                    <?php if ($pren['numero_sedia']): ?>
                    <div class="seat-assigned">
                        <i class="fas fa-chair"></i>
                        <span>Sedia N. <strong><?php echo $pren['numero_sedia']; ?></strong></span>
                    </div>
                    <?php else: ?>
                    <div class="seat-pending">
                        <i class="fas fa-hourglass-half"></i>
                        <span>Posto non ancora assegnato</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($prossimePartite)): ?>
    <div class="section">
        <h3><i class="fas fa-calendar"></i> Prossime Partite Disponibili</h3>
        <div class="cards-grid">
            <?php foreach ($prossimePartite as $partita): ?>
            <div class="card">
                <div class="card-header">
                    <h4><?php echo htmlspecialchars($partita['titolo']); ?></h4>
                    <span class="badge badge-aperta">Aperta</span>
                </div>
                <div class="card-body">
                    <p><i class="fas fa-futbol"></i> <?php echo htmlspecialchars($partita['squadra_casa'] . ' vs ' . $partita['squadra_ospite']); ?></p>
                    <p><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($partita['data_partita'])); ?> ore <?php echo $partita['ora_partita']; ?></p>
                    <p><i class="fas fa-users"></i> <?php echo $partita['num_prenotazioni']; ?> / <?php echo $partita['num_posti']; ?> posti</p>
                </div>
                <div class="card-footer">
                    <a href="/pages/partite.php" class="btn btn-primary btn-sm">Prenota</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
