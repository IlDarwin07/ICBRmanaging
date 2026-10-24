<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$id = (int)($_GET['id'] ?? 0);
$errors = [];

$stmt = $pdo->prepare('SELECT * FROM template_whatsapp WHERE id_template = :id');
$stmt->execute(['id' => $id]);
$t = $stmt->fetch();

if (!$t) {
    redirect_with_message('/messaggi/template_list.php', 'Template non trovato.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $nome = clean_text($_POST['nome_template'] ?? '');
    $categoria = clean_text($_POST['categoria'] ?? '');
    $testo = trim($_POST['testo_template'] ?? '');
    $attivo = isset($_POST['attivo']) ? 1 : 0;

    if ($nome === null) $errors[] = 'Il nome del template è obbligatorio.';
    if ($testo === '') $errors[] = 'Il testo del template è obbligatorio.';

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE template_whatsapp
             SET nome_template = :nome, categoria = :categoria, testo_template = :testo, attivo = :attivo
             WHERE id_template = :id'
        );
        $stmt->execute(['nome' => $nome, 'categoria' => $categoria, 'testo' => $testo, 'attivo' => $attivo, 'id' => $id]);
        redirect_with_message('/messaggi/template_list.php', 'Template aggiornato.');
    }
}

$csrf = generate_csrf_token();
$testo_corrente = $_POST['testo_template'] ?? $t['testo_template'];
$sconosciuti = placeholder_sconosciuti((string)$testo_corrente);

$page_title = 'Modifica template messaggio';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Modifica template messaggio</h1>
    <a class="btn btn-secondary" href="<?= u('/messaggi/template_list.php') ?>">← Indietro</a>
</div>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<form method="post" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <div class="form-row">
        <div class="form-group">
            <label>Nome template *</label>
            <input type="text" name="nome_template" value="<?= h($_POST['nome_template'] ?? $t['nome_template']) ?>" required>
        </div>
        <div class="form-group">
            <label>Categoria</label>
            <input type="text" name="categoria" value="<?= h($_POST['categoria'] ?? $t['categoria']) ?>">
        </div>
    </div>
    <div class="form-group">
        <label>Testo *</label>
        <textarea name="testo_template" rows="6" required><?= h($testo_corrente) ?></textarea>
        <?php if ($sconosciuti): ?>
        <div class="flash flash-error" style="margin-top:.5rem;">Placeholder non riconosciuti (resteranno invariati nel messaggio): <strong><?= h(implode(' ', $sconosciuti)) ?></strong></div>
        <?php endif; ?>
    </div>
    <div class="form-group form-checks">
        <label class="checkbox-inline"><input type="checkbox" name="attivo" value="1" <?= ($_POST['attivo'] ?? $t['attivo']) ? 'checked' : '' ?>> Attivo</label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Salva modifiche</button>
    </div>
</form>

<div class="note" style="margin-top:1rem;">
    <strong>Placeholder disponibili:</strong>
    <ul style="margin:.4rem 0 0 1.2rem;">
        <?php foreach (WHATSAPP_PLACEHOLDER as $ph => $desc): ?>
            <li><code><?= h($ph) ?></code> — <?= h($desc) ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
