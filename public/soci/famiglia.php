<?php
/** Famiglie: il direttivo decide per quali soci un titolare puo prenotare le partite. */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/famiglia.php';
start_secure_session();
require_login();
partite_require_ready();
if (!famiglia_ready()) {
    render_error_page('Famiglie non ancora installate', 'Applica la migrazione database/migrations/014_famiglia_sostenitori.sql e ricarica la pagina.', 503);
}

$pdo = get_db_connection();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

function fam_socio(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT id_socio, nome, cognome, comune FROM soci WHERE id_socio = ? AND attivo_record = 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function fam_cerca(PDO $pdo, string $q, int $escludi = 0): array
{
    $q = trim(str_replace(['%', '_'], '', $q));
    if (mb_strlen($q) < 2) return [];
    $like = '%' . $q . '%';
    $st = $pdo->prepare(
        "SELECT id_socio, nome, cognome, comune FROM soci
         WHERE attivo_record = 1 AND id_socio <> ?
           AND (cognome LIKE ? OR nome LIKE ? OR CONCAT(cognome, ' ', nome) LIKE ? OR CONCAT(nome, ' ', cognome) LIKE ? OR codice_fiscale = ?)
         ORDER BY cognome, nome LIMIT 20"
    );
    $st->execute([$escludi, $like, $like, $like, $like, strtoupper($q)]);
    return $st->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $azione = is_string($_POST['azione'] ?? null) ? $_POST['azione'] : '';
    $fam = (int)($_POST['id_familiare'] ?? 0);
    $utente = current_user();
    $op = $utente ? (int)$utente['id_utente'] : null;
    $ritorno = '/soci/famiglia.php?id=' . $id;

    if ($id <= 0 || $fam <= 0 || $fam === $id || !fam_socio($pdo, $id) || !fam_socio($pdo, $fam)) {
        redirect_with_message('/soci/famiglia.php', 'Soci non validi.', 'error');
    }
    if ($azione === 'aggiungi') {
        $ins = $pdo->prepare('INSERT IGNORE INTO soci_famiglia (id_titolare, id_familiare, creato_da) VALUES (?, ?, ?)');
        $ins->execute([$id, $fam, $op]);
        $reciproco = !empty($_POST['reciproco']);
        if ($reciproco) {
            $ins->execute([$fam, $id, $op]);
        }
        audit_event('area_soci', 'famiglia_aggiunta', 'soci', (string)$id, ['familiare' => $fam, 'reciproco' => $reciproco]);
        redirect_with_message($ritorno, 'Familiare associato.');
    }
    if ($azione === 'rimuovi') {
        $pdo->prepare('DELETE FROM soci_famiglia WHERE id_titolare = ? AND id_familiare = ?')->execute([$id, $fam]);
        audit_event('area_soci', 'famiglia_rimossa', 'soci', (string)$id, ['familiare' => $fam]);
        redirect_with_message($ritorno, 'Associazione rimossa.');
    }
    redirect_with_message($ritorno, 'Operazione non valida.', 'error');
}

$titolare = $id > 0 ? fam_socio($pdo, $id) : null;
$q = clean_text($_GET['q'] ?? '') ?? '';
$q2 = clean_text($_GET['q2'] ?? '') ?? '';
$trovati = !$titolare ? fam_cerca($pdo, $q) : [];
$candidati = $titolare ? fam_cerca($pdo, $q2, $id) : [];
$familiari = $titolare ? famiglia_familiari($id) : [];
$inversi = [];
if ($titolare) {
    $st = $pdo->prepare('SELECT s.id_socio, s.nome, s.cognome FROM soci_famiglia f JOIN soci s ON s.id_socio = f.id_titolare WHERE f.id_familiare = ? AND s.attivo_record = 1 ORDER BY s.cognome, s.nome');
    $st->execute([$id]);
    $inversi = $st->fetchAll();
}
$csrf = generate_csrf_token();

$page_title = 'Famiglie e prenotazioni';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <div>
        <span class="admin-eyebrow">SOCI / FAMIGLIE</span>
        <h1>Famiglie e prenotazioni</h1>
    </div>
    <?php if ($titolare): ?><a class="btn btn-secondary" href="<?= u('/soci/famiglia.php') ?>">Cerca un altro socio</a><?php endif; ?>
</div>

<?php if (!$titolare): ?>
    <form method="get" class="form-card">
        <div class="form-group">
            <label>Cerca il socio che prenota (cognome, nome o codice fiscale)</label>
            <input type="text" name="q" value="<?= h($q) ?>" minlength="2" required autofocus>
        </div>
        <div class="form-actions"><button type="submit" class="btn">Cerca</button></div>
    </form>
    <?php if ($q !== ''): ?>
        <?php if (!$trovati): ?>
            <p class="note">Nessun socio trovato.</p>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Socio</th><th>Comune</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($trovati as $s): ?>
                    <tr>
                        <td><strong><?= h($s['cognome'] . ' ' . $s['nome']) ?></strong></td>
                        <td><?= h((string)$s['comune']) ?></td>
                        <td><a class="btn btn-sm" href="<?= u('/soci/famiglia.php?id=' . (int)$s['id_socio']) ?>">Gestisci famiglia</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
<?php else: ?>
    <section class="admin-panel">
        <h2><?= h($titolare['cognome'] . ' ' . $titolare['nome']) ?> puo prenotare per</h2>
        <?php if (!$familiari): ?>
            <p class="note">Nessun familiare associato.</p>
        <?php else: ?>
            <table class="data-table">
                <tbody>
                <?php foreach ($familiari as $f): ?>
                    <tr>
                        <td><strong><?= h($f['cognome'] . ' ' . $f['nome']) ?></strong></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Rimuovere questa associazione?');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="id_familiare" value="<?= (int)$f['id_socio'] ?>">
                                <button type="submit" name="azione" value="rimuovi" class="btn btn-secondary btn-sm">Rimuovi</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php if ($inversi): ?>
            <p class="note">Puo essere prenotato da: <?= h(implode(', ', array_map(fn($s) => $s['cognome'] . ' ' . $s['nome'], $inversi))) ?>.</p>
        <?php endif; ?>
    </section>

    <section class="admin-panel">
        <h2>Aggiungi un familiare</h2>
        <form method="get" class="form-card">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="form-group">
                <label>Cerca il familiare (cognome, nome o codice fiscale)</label>
                <input type="text" name="q2" value="<?= h($q2) ?>" minlength="2" required>
            </div>
            <div class="form-actions"><button type="submit" class="btn">Cerca</button></div>
        </form>
        <?php if ($q2 !== ''): ?>
            <?php if (!$candidati): ?>
                <p class="note">Nessun socio trovato.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Socio</th><th>Comune</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($candidati as $s): ?>
                        <tr>
                            <td><strong><?= h($s['cognome'] . ' ' . $s['nome']) ?></strong></td>
                            <td><?= h((string)$s['comune']) ?></td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <input type="hidden" name="id_familiare" value="<?= (int)$s['id_socio'] ?>">
                                    <label><input type="checkbox" name="reciproco" value="1"> anche lui puo prenotare per <?= h($titolare['nome']) ?></label>
                                    <button type="submit" name="azione" value="aggiungi" class="btn btn-sm">Aggiungi</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
