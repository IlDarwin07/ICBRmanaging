<?php
/** Sale della sede: nome, numero di sedie e sedie per fila nella piantina. */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/partite.php';
start_secure_session();
require_login();
partite_require_ready();

$pdo = get_db_connection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_sala = (int)($_POST['id_sala'] ?? 0);
    $nome = clean_text($_POST['nome'] ?? '') ?? '';
    $capienza = filter_var($_POST['capienza'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
    $per_fila = filter_var($_POST['posti_per_fila'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 40]]);
    $attiva = !empty($_POST['attiva']) ? 1 : 0;

    if ($nome === '' || mb_strlen($nome) > 60) $errors[] = 'Il nome della sala è obbligatorio (massimo 60 caratteri).';
    if ($capienza === false) $errors[] = 'Le sedie devono essere tra 1 e 500.';
    if ($per_fila === false) $errors[] = 'Le sedie per fila devono essere tra 1 e 40.';
    if (!$errors && $id_sala > 0) {
        // Non si riduce la sala sotto sedie già assegnate in partite non concluse.
        $stmt = $pdo->prepare(
            "SELECT MAX(r.numero_posto) FROM prenotazioni r JOIN partite p ON p.id_partita = r.id_partita
             WHERE p.id_sala = ? AND p.stato IN ('bozza','aperta','chiusa')"
        );
        $stmt->execute([$id_sala]);
        $max = (int)$stmt->fetchColumn();
        if ($max > $capienza) $errors[] = "La sedia $max è già assegnata in una partita in programma: libera prima le sedie oltre la $capienza.";
    }
    if (!$errors) {
        try {
            if ($id_sala > 0) {
                $pdo->prepare('UPDATE sale SET nome = ?, capienza = ?, posti_per_fila = ?, attiva = ? WHERE id_sala = ?')
                    ->execute([$nome, $capienza, $per_fila, $attiva, $id_sala]);
                redirect_with_message('/partite/sale.php', 'Sala aggiornata.');
            }
            $pdo->prepare('INSERT INTO sale (nome, capienza, posti_per_fila, attiva) VALUES (?, ?, ?, ?)')
                ->execute([$nome, $capienza, $per_fila, $attiva]);
            redirect_with_message('/partite/sale.php', 'Sala aggiunta.');
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            $errors[] = 'Esiste già una sala con questo nome.';
        }
    }
}

$sale = sale_list();
$csrf = generate_csrf_token();
$page_title = 'Sale e capienze';
require __DIR__ . '/../../includes/layout_header.php';
$row = function (?array $s) use ($csrf): void { ?>
    <form method="post" class="form-card sala-form">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="id_sala" value="<?= (int)($s['id_sala'] ?? 0) ?>">
        <div class="form-row">
            <div class="form-group"><label>Nome sala</label><input type="text" name="nome" maxlength="60" value="<?= h($s['nome'] ?? '') ?>" required></div>
            <div class="form-group"><label>Sedie numerate</label><input type="number" name="capienza" min="1" max="500" value="<?= (int)($s['capienza'] ?? 20) ?>" required></div>
            <div class="form-group"><label>Sedie per fila (piantina)</label><input type="number" name="posti_per_fila" min="1" max="40" value="<?= (int)($s['posti_per_fila'] ?? 10) ?>" required></div>
        </div>
        <label class="checkbox-inline"><input type="checkbox" name="attiva" value="1" <?= (int)($s['attiva'] ?? 1) === 1 ? 'checked' : '' ?>> Sala disponibile per nuove partite</label>
        <div class="form-actions"><button class="btn" type="submit"><?= $s ? 'Salva' : 'Aggiungi sala' ?></button></div>
    </form>
<?php };
?>
<div class="page-header">
    <div><span class="admin-eyebrow">SEDE / SALE</span><h1>Sale e capienze</h1></div>
    <a class="btn btn-secondary" href="<?= u('/partite/list.php') ?>">← Partite</a>
</div>
<?php if ($errors): ?><div class="flash flash-error" role="alert"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>
<p>Le sedie sono numerate da 1 al numero indicato. Le capienze iniziali sono provvisorie: inserisci quelle reali della sede.</p>
<?php foreach ($sale as $s): ?><section class="admin-panel"><h2><?= h($s['nome']) ?></h2><?php $row($s); ?></section><?php endforeach; ?>
<details class="admin-panel"><summary>+ Aggiungi un'altra sala</summary><?php $row(null); ?></details>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
