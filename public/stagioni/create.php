<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $codice      = clean_text($_POST['codice_stagione'] ?? '');
    $descrizione = clean_text($_POST['descrizione'] ?? '');
    $data_inizio = parse_date_to_sql($_POST['data_inizio'] ?? '');
    $data_fine   = parse_date_to_sql($_POST['data_fine'] ?? '');
    $attiva      = isset($_POST['attiva']) ? 1 : 0;

    if (!$codice) $errors[] = 'Il codice stagione è obbligatorio.';

    if (empty($errors)) {
        // Se si imposta questa come attiva, disattiva le altre
        if ($attiva) {
            $pdo->exec('UPDATE stagioni SET attiva = 0');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO stagioni (codice_stagione, descrizione, attiva, data_inizio, data_fine)
             VALUES (:codice, :descrizione, :attiva, :data_inizio, :data_fine)'
        );
        $stmt->execute([
            'codice'      => $codice,
            'descrizione' => $descrizione,
            'attiva'      => $attiva,
            'data_inizio' => $data_inizio,
            'data_fine'   => $data_fine,
        ]);
        redirect_with_message('/stagioni/list.php', 'Stagione creata con successo.');
    }
}

$csrf = generate_csrf_token();
$page_title = 'Nuova stagione';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Nuova stagione</h1>
    <a class="btn btn-secondary" href="/stagioni/list.php">← Torna alla lista</a>
</div>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<form method="post" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <div class="form-group">
        <label>Codice stagione *</label>
        <input type="text" name="codice_stagione" value="<?= h($_POST['codice_stagione'] ?? '') ?>" placeholder="es. 2026-27" required>
    </div>
    <div class="form-group">
        <label>Descrizione</label>
        <input type="text" name="descrizione" value="<?= h($_POST['descrizione'] ?? '') ?>" placeholder="Stagione 2026/2027">
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Data inizio</label>
            <input type="date" name="data_inizio" value="<?= h($_POST['data_inizio'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Data fine</label>
            <input type="date" name="data_fine" value="<?= h($_POST['data_fine'] ?? '') ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="checkbox-inline">
            <input type="checkbox" name="attiva" value="1" <?= isset($_POST['attiva']) ? 'checked' : '' ?>>
            Imposta come stagione attiva corrente
        </label>
        <small class="form-hint">Attenzione: disattiverà automaticamente le altre stagioni.</small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Salva stagione</button>
    </div>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
