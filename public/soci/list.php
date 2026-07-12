<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

$q           = trim($_GET['q'] ?? '');
$solo_attivi = !isset($_GET['tutti']);

$stagione_attiva = $pdo->query(
    "SELECT id_stagione, codice_stagione FROM stagioni ORDER BY codice_stagione DESC LIMIT 1"
)->fetch();

$sql = "SELECT s.id_socio, s.nome, s.cognome, s.data_nascita,
               s.comune, s.provincia, s.codice_fiscale, s.attivo_record,
               t.numero_tessera, t.attivo_portale
        FROM soci s
        LEFT JOIN tesseramenti t ON t.id_socio = s.id_socio
            AND t.id_stagione = :id_stagione
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

// Risolvi BASE_URL una volta sola, compatibile con XAMPP e produzione
if (!defined('BASE_URL')) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (str_contains($script, '/ICBRmanaging/')) {
        define('BASE_URL', '/ICBRmanaging/public');
    } else {
        define('BASE_URL', '');
    }
}
$urlbase = rtrim(BASE_URL, '/');
?>

<div class="page-header">
    <h1>Anagrafica soci
        <?php if ($stagione_attiva): ?>
            <small style="font-size:.6em;font-weight:normal;color:#888">
                &mdash; Stagione: <?= h($stagione_attiva['codice_stagione']) ?>
            </small>
        <?php endif; ?>
    </h1>
    <a class="btn" href="<?= $urlbase ?>/soci/create.php">+ Nuovo socio</a>
</div>

<form method="get" class="search-form">
    <input type="text" name="q" value="<?= h($q) ?>"
           placeholder="Cerca nome, cognome, telefono, CF, email">
    <label class="checkbox-inline">
        <input type="checkbox" name="tutti" value="1" <?= !$solo_attivi ? 'checked' : '' ?>>
        Mostra disattivati
    </label>
    <button type="submit" class="btn">Cerca</button>
    <?php if ($q !== '' || !$solo_attivi): ?>
        <a class="btn btn-secondary" href="<?= $urlbase ?>/soci/list.php">Azzera</a>
    <?php endif; ?>
</form>

<p class="note"><?= count($soci) ?> soci trovati<?= count($soci) >= 500 ? ' (max 500 &mdash; affina la ricerca)' : '' ?>.</p>

<table class="data-table soci-table">
    <thead>
    <tr>
        <th>Cognome e Nome</th>
        <th>Data nascita</th>
        <th>Comune</th>
        <th>Cod. fiscale</th>
        <th>N. Tessera<?= $stagione_attiva ? '<br><small>' . h($stagione_attiva['codice_stagione']) . '</small>' : '' ?></th>
        <th>Portale<?= $stagione_attiva ? '<br><small>' . h($stagione_attiva['codice_stagione']) . '</small>' : '' ?></th>
        <th>Stato</th>
        <th>Azioni</th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($soci)): ?>
        <tr><td colspan="8" style="text-align:center;padding:2rem">Nessun socio trovato.</td></tr>
    <?php endif; ?>
    <?php foreach ($soci as $s): ?>
        <tr class="<?= $s['attivo_record'] ? '' : 'row-disabled' ?>">
            <td><strong><?= h($s['cognome']) ?></strong> <?= h($s['nome']) ?></td>
            <td><?= $s['data_nascita'] ? date('d/m/Y', strtotime($s['data_nascita'])) : '&mdash;' ?></td>
            <td><?php
                $loc = trim(($s['comune'] ?? ''));
                if ($s['provincia']) $loc .= ' (' . h($s['provincia']) . ')';
                echo $loc ?: '&mdash;';
            ?></td>
            <td style="font-family:monospace;font-size:.8rem"><?= h($s['codice_fiscale']) ?: '&mdash;' ?></td>
            <td><?= h($s['numero_tessera']) ?: '<span class="badge badge-gray">&mdash;</span>' ?></td>
            <td>
                <?php if ($s['numero_tessera'] !== null): ?>
                    <span class="badge <?= $s['attivo_portale'] ? 'badge-green' : 'badge-red' ?>">
                        <?= $s['attivo_portale'] ? 'S&igrave;' : 'No' ?>
                    </span>
                <?php else: ?>
                    <span class="badge badge-gray">&mdash;</span>
                <?php endif; ?>
            </td>
            <td>
                <span class="badge <?= $s['attivo_record'] ? 'badge-green' : 'badge-gray' ?>">
                    <?= $s['attivo_record'] ? 'Attivo' : 'Disatt.' ?>
                </span>
            </td>
            <td class="td-actions">
                <a class="btn btn-sm" href="<?= $urlbase ?>/soci/view.php?id=<?= (int)$s['id_socio'] ?>">Scheda</a>
                <a class="btn btn-sm btn-secondary" href="<?= $urlbase ?>/soci/edit.php?id=<?= (int)$s['id_socio'] ?>">Modifica</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
