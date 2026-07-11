<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $tipo          = clean_text($_POST['tipo'] ?? '');
    $listino_label = clean_text($_POST['listino_label'] ?? '');
    $quota_raw     = trim($_POST['quota_standard'] ?? '');
    $quota         = $quota_raw !== '' ? (float)str_replace(',', '.', $quota_raw) : null;
    $attiva        = isset($_POST['attiva']) ? 1 : 0;

    if (!$tipo) $errors[] = 'Il tipo è obbligatorio.';

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO tipologie_tesseramento (tipo, listino_label, quota_standard, attiva)
             VALUES (:tipo, :label, :quota, :attiva)'
        );
        $stmt->execute(['tipo' => $tipo, 'label' => $listino_label, 'quota' => $quota, 'attiva' => $attiva]);
        redirect_with_message('/tipologie/list.php', 'Tipologia creata.');
    }
}

$csrf = generate_csrf_token();
$page_title = 'Nuova tipologia';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Nuova tipologia tesseramento</h1>
    <a class="btn btn-secondary" href="/tipologie/list.php">← Torna</a>
</div>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<form method="post" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <div class="form-group">
        <label>Tipo *</label>
        <input type="text" name="tipo" value="<?= h($_POST['tipo'] ?? '') ?>" placeholder="es. senior" required>
    </div>
    <div class="form-group">
        <label>Etichetta listino</label>
        <input type="text" name="listino_label" value="<?= h($_POST['listino_label'] ?? '') ?>" placeholder="es. SENIOR 2026/27">
    </div>
    <div class="form-group">
        <label>Quota standard (€)</label>
        <input type="text" name="quota_standard" value="<?= h($_POST['quota_standard'] ?? '') ?>" placeholder="es. 45.00">
    </div>
    <div class="form-group">
        <label class="checkbox-inline">
            <input type="checkbox" name="attiva" value="1" checked>
            Tipologia attiva
        </label>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn">Salva</button>
    </div>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
