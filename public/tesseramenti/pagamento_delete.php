<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

// Cancellazione pagamento: solo POST + CSRF, per evitare link "cancellami"
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('/tesseramenti/list.php', 'Metodo non consentito.', 'error');
}
verify_csrf_token($_POST['csrf_token'] ?? '');

$pdo = get_db_connection();
$id_pagamento = (int)($_POST['id_pagamento'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT p.*, t.id_socio FROM pagamenti_tesseramenti p
     JOIN tesseramenti t ON t.id_tesseramento = p.id_tesseramento
     WHERE p.id_pagamento = :id'
);
$stmt->execute(['id' => $id_pagamento]);
$pag = $stmt->fetch();

if (!$pag) {
    redirect_with_message('/tesseramenti/list.php', 'Pagamento non trovato.', 'error');
}

try {
    $pdo->beginTransaction();
    $linked = $pdo->prepare('SELECT id_movimento FROM prima_nota WHERE id_pagamento=? FOR UPDATE');
    $linked->execute([$id_pagamento]);
    if ($linked->fetch() && !can('prima_nota.delete')) {
        $pdo->rollBack();
        require_permission('prima_nota.delete');
    }

    // Elimina prima il movimento di prima nota collegato (FK ON DELETE SET NULL
    // lo staccherebbe, ma un movimento orfano con lo stesso importo sarebbe fuorviante)
    $del_pn = $pdo->prepare('DELETE FROM prima_nota WHERE id_pagamento = :id');
    $del_pn->execute(['id' => $id_pagamento]);

    $del = $pdo->prepare('DELETE FROM pagamenti_tesseramenti WHERE id_pagamento = :id');
    $del->execute(['id' => $id_pagamento]);

    $pdo->commit();
    redirect_with_message(
        '/tesseramenti/view.php?id=' . (int)$pag['id_tesseramento'],
        'Pagamento eliminato (insieme al movimento di prima nota collegato).'
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect_with_message(
        '/tesseramenti/view.php?id=' . (int)$pag['id_tesseramento'],
        'Errore durante la cancellazione: ' . $e->getMessage(),
        'error'
    );
}
