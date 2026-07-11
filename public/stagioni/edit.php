<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo    = get_db_connection();
$id     = (int)($_GET['id'] ?? 0);
$errors = [];

$stmt = $pdo->prepare('SELECT * FROM stagioni WHERE id_stagione = :id');
$stmt->execute(['id' => $id]);
$stagione = $stmt->fetch();

if (!$stagione) {
    redirect_with_message('/stagioni/list.php', 'Stagione non trovata.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $codice      = clean_text($_POST['codice_stagione'] ?? '');
    $descrizione = clean_text($_POST['descrizione'] ?? '');
    $data_inizio = parse_date_to_sql($_POST['data_inizio'] ?? '');
    $data_fine   = parse_date_to_sql($_POST['data_fine'] ?? '');
    $attiva      = isset($_POST['attiva']) ? 1 : 0;

    if (!$codice) $errors[] = 'Il codice stagione è obbligatorio.';

    if (empty($errors)) {
        if ($attiva) {
            $pdo->exec('UPDATE stagioni SET attiva = 0');
        }
        $stmt = $pdo->prepare(
            'UPDATE stagioni SET codice_stagione=:codice, descrizione=:descrizione,
             attiva=:attiva, data_inizio=:data_inizio, data_fine=:data_fine
             WHERE id_stagione=:id'
        );
        $stmt->execute([
            'codice'      => $codice,
            'descrizione' => $descrizione,
            'attiva'      => $attiva,
            'data_inizio' => $data_inizio,
            'data_fine'   => $data_fine,
            'id'          => $id,
        ]);
        redirect_with_message('/stagioni/list.php', 'Stagione aggiornata.');
    }
}

$csrf = generate_csrf_token();
$page_title = 'Modifica stagione';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Modifica stagione: <?= h($stagione['codice_stagione']) ?></h1>
    <a class="btn btn-secondary" href="/stagioni/list.php">← Torna alla lista</a>
</div>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<form method="post" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <div class="form-group">
        <label>Codice stagione *</label>
        <input type="text" name="codice_stagione" value="<?= h($_POST['codice_stagione'] ?? $stagione['codice_stagione']) ?>" required>
    </div>
    <div class="form-group">
        <label>Descrizione</label>
        <input type="text" name="descrizione" value="<?= h($_POST['descrizione'] ?? $stagione['descrizione']) ?>">
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Data inizio</label>
            <input type="date" name="data_inizio" value="<?= h($_POST['data_inizio'] ?? $stagione['data_inizio']) ?>">
        </div>
        <div class="form-group">
            <label>Data fine</label>
            <input type="date" name="data_fine" value="<?= h($_POST['data_fine'] ?? $stagione['data_fine']) ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="checkbox-inline">
            <input type="checkbox" name="attiva" value="1" <?= ($stagione['attiva'] || isset($_POST['attiva'])) ? 'checked' : '' ?>>
            Stagione attiva corrente
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Salva modifiche</button>
    </div>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
