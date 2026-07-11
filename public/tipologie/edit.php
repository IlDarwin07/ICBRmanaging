<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo    = get_db_connection();
$id     = (int)($_GET['id'] ?? 0);
$errors = [];

$stmt = $pdo->prepare('SELECT * FROM tipologie_tesseramento WHERE id_tipologia = :id');
$stmt->execute(['id' => $id]);
$tipo_row = $stmt->fetch();

if (!$tipo_row) {
    redirect_with_message('/tipologie/list.php', 'Tipologia non trovata.', 'error');
}

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
            'UPDATE tipologie_tesseramento
             SET tipo=:tipo, listino_label=:label, quota_standard=:quota, attiva=:attiva
             WHERE id_tipologia=:id'
        );
        $stmt->execute(['tipo' => $tipo, 'label' => $listino_label, 'quota' => $quota, 'attiva' => $attiva, 'id' => $id]);
        redirect_with_message('/tipologie/list.php', 'Tipologia aggiornata.');
    }
}

$csrf = generate_csrf_token();
$page_title = 'Modifica tipologia';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Modifica tipologia: <?= h($tipo_row['tipo']) ?></h1>
    <a class="btn btn-secondary" href="/tipologie/list.php">← Torna</a>
</div>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<form method="post" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <div class="form-group">
        <label>Tipo *</label>
        <input type="text" name="tipo" value="<?= h($_POST['tipo'] ?? $tipo_row['tipo']) ?>" required>
    </div>
    <div class="form-group">
        <label>Etichetta listino</label>
        <input type="text" name="listino_label" value="<?= h($_POST['listino_label'] ?? $tipo_row['listino_label']) ?>">
    </div>
    <div class="form-group">
        <label>Quota standard (€)</label>
        <input type="text" name="quota_standard" value="<?= h($_POST['quota_standard'] ?? $tipo_row['quota_standard']) ?>">
    </div>
    <div class="form-group">
        <label class="checkbox-inline">
            <input type="checkbox" name="attiva" value="1" <?= ($tipo_row['attiva'] || isset($_POST['attiva'])) ? 'checked' : '' ?>>
            Tipologia attiva
        </label>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn">Salva modifiche</button>
    </div>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
