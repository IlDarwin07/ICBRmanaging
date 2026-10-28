<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/articoli.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_path('/articoli/list.php'));
    exit;
}
verify_csrf_token($_POST['csrf_token'] ?? null);

$id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$article = $id > 0 ? articoli_admin_find($id) : null;
if (!$article) {
    redirect_with_message('/articoli/list.php', 'Articolo non trovato.', 'error');
}

$pdo = get_db_connection();
$stmt = $pdo->prepare('DELETE FROM articoli_eventi WHERE id_articolo = :id');
$stmt->execute(['id' => $id]);

$files = array_merge([(string)($article['copertina'] ?? '')], array_column(articoli_gallery_decode($article['galleria'] ?? null), 'file'));
foreach ($files as $file) {
    if (str_starts_with($file, 'evento-') && site_image_valid($file)) {
        @unlink(__DIR__ . '/../assets/images/' . $file);
    }
}
try {
    audit_event('articoli', 'eliminazione', 'articolo', (string)$id, ['titolo' => $article['titolo']]);
} catch (Throwable $auditError) {
    error_log('ICBR: articolo eliminato, registro attività non disponibile.');
}
redirect_with_message('/articoli/list.php', 'Articolo eliminato.');
