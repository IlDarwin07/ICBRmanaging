<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM prima_nota WHERE id_movimento = :id');
$stmt->execute(['id' => $id]);
$m = $stmt->fetch();

if (!$m) {
    redirect_with_message('/prima_nota/list.php', 'Movimento non trovato.', 'error');
}

// I movimenti collegati a un pagamento si eliminano dal pagamento stesso
if (!empty($m['id_pagamento'])) {
    redirect_with_message('/prima_nota/list.php', 'Il movimento è collegato a un pagamento: eliminarlo dal pagamento associato.', 'error');
}

// Cancellazione: POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $del = $pdo->prepare('DELETE FROM prima_nota WHERE id_movimento = :id');
    $del->execute(['id' => $id]);
    redirect_with_message('/prima_nota/list.php', 'Movimento eliminato.');
}

// GET: mostra conferma
$csrf = generate_csrf_token();
$page_title = 'Elimina movimento';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Elimina movimento prima nota</h1>
    <a class="btn btn-secondary" href="<?= u('/prima_nota/list.php') ?>">← Annulla</a>
</div>

<div class="flash flash-error">
    Confermi l'eliminazione definitiva di questo movimento?<br><br>
    <strong><?= date('d/m/Y', strtotime($m['data_movimento'])) ?></strong> —
    <?= h($m['conto']) ?> — <strong><?= $m['tipo_movimento'] === 'entrata' ? '+' : '−' ?>€ <?= euro((float)$m['importo']) ?></strong><br>
    <?= h($m['causale']) ?>
</div>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <button type="submit" class="btn btn-danger">Sì, elimina definitivamente</button>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
