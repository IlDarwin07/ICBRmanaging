<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM template_whatsapp WHERE id_template = :id');
$stmt->execute(['id' => $id]);
$t = $stmt->fetch();

if (!$t) {
    redirect_with_message('/messaggi/template_list.php', 'Template non trovato.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    // I messaggi già generati restano (hanno il testo finale salvato),
    // ma viene staccato il collegamento al template.
    $del = $pdo->prepare('DELETE FROM template_whatsapp WHERE id_template = :id');
    $del->execute(['id' => $id]);
    redirect_with_message('/messaggi/template_list.php', 'Template eliminato.');
}

$csrf = generate_csrf_token();
$page_title = 'Elimina template';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Elimina template messaggio</h1>
    <a class="btn btn-secondary" href="<?= u('/messaggi/template_list.php') ?>">← Annulla</a>
</div>

<div class="flash flash-error">
    Confermi l'eliminazione del template <strong><?= h($t['nome_template']) ?></strong>?<br>
    I messaggi già generati con questo template non verranno cancellati.
</div>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <button type="submit" class="btn btn-danger">Sì, elimina template</button>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
