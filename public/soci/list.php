<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

$q           = trim($_GET['q'] ?? '');
$solo_attivi = !isset($_GET['tutti']);

// Stagione attiva per mostrare il numero tessera corrente
$stagione_attiva = $pdo->query(
    "SELECT id_stagione, codice_stagione FROM stagioni ORDER BY codice_stagione DESC LIMIT 1"
)->fetch();

$sql = "SELECT s.id_socio, s.nome, s.cognome, s.data_nascita,
               s.comune, s.provincia, s.telefono, s.email,
               s.codice_fiscale, s.attivo_record,
               t.numero_tessera, t.attivo_portale,
               st.codice_stagione
        FROM soci s
        LEFT JOIN tesseramenti t ON t.id_socio = s.id_socio
            AND t.id_stagione = :id_stagione
        LEFT JOIN stagioni st ON st.id_stagione = t.id_stagione
        WHERE 1=1";
$params = ['id_stagione' => $stagione_attiva['id_stagione'] ?? 0];

if ($q !== '') {
    $sql .= ' AND (s.nome LIKE :q1 OR s.cognome LIKE :q2 OR s.telefono LIKE :q3 OR s.codice_fiscale LIKE :q4 OR s.email LIKE :q5)';
    $like = '%' . $q . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
    $params['q5'] = $like;
}

if ($solo_attivi) {
    $sql .= ' AND s.attivo_record = 1';
}

$sql .= ' ORDER BY s.cognome, s.nome LIMIT 500';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$soci = $stmt->fetchAll();

$page_title = 'Anagrafica soci';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Anagrafica soci
        <?php if ($stagione_attiva): ?>
            <small style="font-size:.6em;font-weight:normal;color:var(--color-muted)">
                — Tessera mostrata: <?= h($stagione_attiva['codice_stagione']) ?>
            </small>
        <?php endif; ?>
    </h1>
    <a class="btn" href="<?= $base ?>/soci/create.php">+ Nuovo socio</a>
</div>

<form method="get" class="search-form">
    <input type="text" name="q" value="<?= h($q) ?>"
           placeholder="Cerca per nome, cognome, telefono, email o codice fiscale">
    <label class="checkbox-inline">
        <input type="checkbox" name="tutti" value="1" <?= !$solo_attivi ? 'checked' : '' ?>>
        Mostra anche disattivati
    </label>
    <button type="submit" class="btn">Cerca</button>
    <?php if ($q !== '' || !$solo_attivi): ?>
        <a class="btn btn-secondary" href="<?= $base ?>/soci/list.php">Azzera</a>
    <?php endif; ?>
</form>

<p class="note"><?= count($soci) ?> record trovati<?= count($soci) >= 500 ? ' (limite 500 — usa la ricerca per restringere)' : '' ?>.</p>

<table class="data-table">
    <thead>
    <tr>
        <th>Cognome</th>
        <th>Nome</th>
        <th>Data nascita</th>
        <th>Comune</th>
        <th>Telefono</th>
        <th>Email</th>
        <th>Cod. fiscale</th>
        <th>N. Tessera<?= $stagione_attiva ? ' (' . h($stagione_attiva['codice_stagione']) . ')' : '' ?></th>
        <th>Portale<?= $stagione_attiva ? ' (' . h($stagione_attiva['codice_stagione']) . ')' : '' ?></th>
        <th>Stato</th>
        <th style="white-space:nowrap">Azioni</th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($soci)): ?>
        <tr><td colspan="11">Nessun socio trovato.</td></tr>
    <?php endif; ?>
    <?php foreach ($soci as $s): ?>
        <tr class="<?= $s['attivo_record'] ? '' : 'row-disabled' ?>">
            <td><?= h($s['cognome']) ?></td>
            <td><?= h($s['nome']) ?></td>
            <td><?= $s['data_nascita'] ? date('d/m/Y', strtotime($s['data_nascita'])) : '-' ?></td>
            <td><?= h(trim(($s['comune'] ?? '') . ' (' . ($s['provincia'] ?? '') . ')')) ?: '-' ?></td>
            <td><?= h($s['telefono']) ?: '-' ?></td>
            <td><?= h($s['email']) ?: '-' ?></td>
            <td><?= h($s['codice_fiscale']) ?: '-' ?></td>
            <td><?= h($s['numero_tessera']) ?: '<span class="badge badge-gray">—</span>' ?></td>
            <td>
                <?php if ($s['numero_tessera'] !== null): ?>
                    <span class="badge <?= $s['attivo_portale'] ? 'badge-green' : 'badge-red' ?>">
                        <?= $s['attivo_portale'] ? 'Sì' : 'No' ?>
                    </span>
                <?php else: ?>
                    <span class="badge badge-gray">—</span>
                <?php endif; ?>
            </td>
            <td>
                <span class="badge <?= $s['attivo_record'] ? 'badge-green' : 'badge-gray' ?>">
                    <?= $s['attivo_record'] ? 'Attivo' : 'Disattivato' ?>
                </span>
            </td>
            <td style="white-space:nowrap">
                <a class="btn btn-sm" href="<?= $base ?>/soci/view.php?id=<?= (int)$s['id_socio'] ?>">Scheda</a>
                <a class="btn btn-sm btn-secondary" href="<?= $base ?>/soci/edit.php?id=<?= (int)$s['id_socio'] ?>">Modifica</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
