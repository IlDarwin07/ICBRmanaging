<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

$q = trim($_GET['q'] ?? '');
$solo_attivi = !isset($_GET['tutti']); // di default nasconde i record disattivati

$sql = 'SELECT id_socio, nome, cognome, telefono, email, codice_fiscale, attivo_record
        FROM soci WHERE 1=1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (nome LIKE :q1 OR cognome LIKE :q2 OR telefono LIKE :q3 OR codice_fiscale LIKE :q4)';
    $like = '%' . $q . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}

if ($solo_attivi) {
    $sql .= ' AND attivo_record = 1';
}

$sql .= ' ORDER BY cognome, nome LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$soci = $stmt->fetchAll();

$page_title = 'Anagrafica soci';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Anagrafica soci</h1>
    <a class="btn" href="/soci/create.php">+ Nuovo socio</a>
</div>

<form method="get" class="search-form">
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Cerca per nome, cognome, telefono o codice fiscale">
    <label class="checkbox-inline">
        <input type="checkbox" name="tutti" value="1" <?= !$solo_attivi ? 'checked' : '' ?>>
        Mostra anche disattivati
    </label>
    <button type="submit" class="btn">Cerca</button>
</form>

<table class="data-table">
    <thead>
    <tr>
        <th>Cognome</th>
        <th>Nome</th>
        <th>Telefono</th>
        <th>Email</th>
        <th>Codice fiscale</th>
        <th>Stato</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($soci)): ?>
        <tr><td colspan="7">Nessun socio trovato.</td></tr>
    <?php endif; ?>
    <?php foreach ($soci as $socio): ?>
        <tr>
            <td><?= h($socio['cognome']) ?></td>
            <td><?= h($socio['nome']) ?></td>
            <td><?= h($socio['telefono']) ?></td>
            <td><?= h($socio['email']) ?></td>
            <td><?= h($socio['codice_fiscale']) ?></td>
            <td><?= $socio['attivo_record'] ? 'Attivo' : 'Disattivato' ?></td>
            <td>
                <a href="/soci/view.php?id=<?= (int)$socio['id_socio'] ?>">Apri</a>
                ·
                <a href="/soci/edit.php?id=<?= (int)$socio['id_socio'] ?>">Modifica</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
