<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$errors = [];

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
            'INSERT INTO template_whatsapp (nome_template, categoria, testo_template, attivo)
             VALUES (:nome, :categoria, :testo, :attivo)'
        );
        $stmt->execute(['nome' => $nome, 'categoria' => $categoria, 'testo' => $testo, 'attivo' => $attivo]);
        redirect_with_message('/messaggi/template_list.php', 'Template creato.');
    }
}

$csrf = generate_csrf_token();
$page_title = 'Nuovo template messaggio';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Nuovo template messaggio</h1>
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
            <input type="text" name="nome_template" value="<?= h($_POST['nome_template'] ?? '') ?>" required placeholder="es. Sollecito saldo quota">
        </div>
        <div class="form-group">
            <label>Categoria</label>
            <input type="text" name="categoria" value="<?= h($_POST['categoria'] ?? '') ?>" placeholder="es. rinnovi, eventi">
        </div>
    </div>
    <div class="form-group">
        <label>Testo * <small>(usa i placeholder elencati qui sotto)</small></label>
        <textarea name="testo_template" rows="6" required placeholder="Ciao {{nome_completo}}, ti ricordiamo che il residuo della tua quota {{stagione}} è di €{{residuo}}."><?= h($_POST['testo_template'] ?? '') ?></textarea>
        <?php if (!empty($_POST['testo_template'])): $sconosciuti = placeholder_sconosciuti($_POST['testo_template']); ?>
            <?php if ($sconosciuti): ?>
            <div class="flash flash-error" style="margin-top:.5rem;">Placeholder non riconosciuti (resteranno invariati nel messaggio): <strong><?= h(implode(' ', $sconosciuti)) ?></strong></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="form-group form-checks">
        <label class="checkbox-inline"><input type="checkbox" name="attivo" value="1" checked> Attivo</label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Salva template</button>
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
