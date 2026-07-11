<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

$id_socio = (int)($_GET['id'] ?? 0);
if ($id_socio <= 0) {
    http_response_code(404);
    die('Socio non trovato.');
}

$stmt = $pdo->prepare('SELECT * FROM soci WHERE id_socio = :id');
$stmt->execute(['id' => $id_socio]);
$socio = $stmt->fetch();

if (!$socio) {
    http_response_code(404);
    die('Socio non trovato.');
}

$stmt = $pdo->prepare(
    'SELECT t.*, s.codice_stagione, tt.tipo AS tipologia_tipo
     FROM tesseramenti t
     LEFT JOIN stagioni s ON s.id_stagione = t.id_stagione
     LEFT JOIN tipologie_tesseramento tt ON tt.id_tipologia = t.id_tipologia
     WHERE t.id_socio = :id
     ORDER BY s.codice_stagione DESC'
);
$stmt->execute(['id' => $id_socio]);
$tesseramenti = $stmt->fetchAll();

$page_title = 'Scheda socio';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1><?= h($socio['nome'] . ' ' . $socio['cognome']) ?></h1>
    <a class="btn" href="/soci/edit.php?id=<?= $id_socio ?>">Modifica</a>
</div>

<section class="detail-grid">
    <div><strong>Sesso:</strong> <?= h($socio['sesso']) ?: '-' ?></div>
    <div><strong>Data di nascita:</strong> <?= h($socio['data_nascita']) ?: '-' ?></div>
    <div><strong>Comune di nascita:</strong> <?= h($socio['comune_nascita']) ?: '-' ?></div>
    <div><strong>Codice fiscale:</strong> <?= h($socio['codice_fiscale']) ?: '-' ?></div>
    <div><strong>Nazionalità:</strong> <?= h($socio['nazionalita']) ?: '-' ?></div>
    <div><strong>Indirizzo:</strong> <?= h(trim(($socio['indirizzo'] ?? '') . ' ' . ($socio['numero_civico'] ?? ''))) ?: '-' ?></div>
    <div><strong>CAP/Comune/Prov:</strong> <?= h(trim(($socio['cap'] ?? '') . ' ' . ($socio['comune'] ?? '') . ' ' . ($socio['provincia'] ?? ''))) ?: '-' ?></div>
    <div><strong>Telefono:</strong> <?= h($socio['telefono']) ?: '-' ?></div>
    <div><strong>Email:</strong> <?= h($socio['email']) ?: '-' ?></div>
    <div><strong>Stato record:</strong> <?= $socio['attivo_record'] ? 'Attivo' : 'Disattivato' ?></div>
</section>

<h2>Storico tesseramenti</h2>
<?php if (empty($tesseramenti)): ?>
    <p class="note">Nessun tesseramento registrato. Il modulo di gestione tesseramenti stagionali
        e l'import dal portale Inter Club saranno disponibili nelle fasi successive della roadmap.</p>
<?php else: ?>
    <table class="data-table">
        <thead>
        <tr>
            <th>Stagione</th>
            <th>Tipo</th>
            <th>N. tessera</th>
            <th>Attivo portale</th>
            <th>Tessera fisica</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tesseramenti as $t): ?>
            <tr>
                <td><?= h($t['codice_stagione']) ?></td>
                <td><?= h($t['tipologia_tipo'] ?? $t['tipo_portale']) ?></td>
                <td><?= h($t['numero_tessera']) ?: '-' ?></td>
                <td><?= $t['attivo_portale'] ? 'Sì' : 'No' ?></td>
                <td><?= $t['tessera_fisica'] ? 'Sì' : 'No' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p><a href="/soci/list.php">&laquo; Torna all'elenco soci</a></p>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
