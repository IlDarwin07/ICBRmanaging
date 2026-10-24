<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$id = (int)($_GET['id'] ?? 0);
$errors = [];

$stmt = $pdo->prepare('SELECT * FROM prima_nota WHERE id_movimento = :id');
$stmt->execute(['id' => $id]);
$m = $stmt->fetch();

if (!$m) {
    redirect_with_message('/prima_nota/list.php', 'Movimento non trovato.', 'error');
}

// I movimenti collegati a un pagamento seguono il pagamento stesso:
// non sono modificabili qui (si modifica/elimina il pagamento).
$collegato_pagamento = !empty($m['id_pagamento']);

if ($collegato_pagamento) {
    redirect_with_message('/prima_nota/list.php', 'Il movimento è collegato a un pagamento: modificarlo dal pagamento associato.', 'error');
}

$categorie = $pdo->query('SELECT DISTINCT categoria FROM prima_nota WHERE categoria IS NOT NULL AND categoria <> \'\' ORDER BY categoria')->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $data_mov   = parse_date_to_sql($_POST['data_movimento'] ?? '') ?? $m['data_movimento'];
    $tipo_mov   = $_POST['tipo_movimento'] ?? $m['tipo_movimento'];
    $conto      = $_POST['conto'] ?? $m['conto'];
    $categoria  = clean_text($_POST['categoria'] ?? '');
    $causale    = clean_text($_POST['causale'] ?? '');
    $importo    = (float)str_replace(',', '.', $_POST['importo'] ?? '0');
    $id_socio   = (int)($_POST['id_socio'] ?? 0) ?: null;
    $rif_doc    = clean_text($_POST['riferimento_documento'] ?? '');
    $note       = clean_text($_POST['note'] ?? '');

    if (!in_array($tipo_mov, ['entrata', 'uscita'], true)) $errors[] = 'Tipo di movimento non valido.';
    if (!in_array($conto, ['cassa', 'banca', 'altro'], true)) $errors[] = 'Conto non valido.';
    if ($importo <= 0) $errors[] = "L'importo deve essere maggiore di zero.";
    if ($causale === null) $errors[] = 'La causale è obbligatoria.';

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE prima_nota SET
                data_movimento = :data, tipo_movimento = :tipo, conto = :conto,
                categoria = :categoria, causale = :causale, importo = :importo,
                id_socio = :socio, riferimento_documento = :rif_doc, note = :note
             WHERE id_movimento = :id'
        );
        $stmt->execute([
            'data'      => $data_mov,
            'tipo'      => $tipo_mov,
            'conto'     => $conto,
            'categoria' => $categoria,
            'causale'   => $causale,
            'importo'   => $importo,
            'socio'     => $id_socio,
            'rif_doc'   => $rif_doc,
            'note'      => $note,
            'id'        => $id,
        ]);
        redirect_with_message('/prima_nota/list.php', 'Movimento aggiornato.');
    }
}

$csrf = generate_csrf_token();
$page_title = 'Modifica movimento prima nota';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Modifica movimento prima nota</h1>
    <a class="btn btn-secondary" href="<?= u('/prima_nota/list.php') ?>">← Indietro</a>
</div>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<form method="post" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <div class="form-row">
        <div class="form-group">
            <label>Data movimento *</label>
            <input type="date" name="data_movimento" value="<?= h($_POST['data_movimento'] ?? $m['data_movimento']) ?>" required>
        </div>
        <div class="form-group">
            <label>Tipo *</label>
            <select name="tipo_movimento" required>
                <option value="entrata" <?= ($_POST['tipo_movimento'] ?? $m['tipo_movimento']) === 'entrata' ? 'selected' : '' ?>>Entrata</option>
                <option value="uscita" <?= ($_POST['tipo_movimento'] ?? $m['tipo_movimento']) === 'uscita' ? 'selected' : '' ?>>Uscita</option>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Conto *</label>
            <select name="conto" required>
                <?php foreach (['cassa', 'banca', 'altro'] as $c): ?>
                    <option value="<?= $c ?>" <?= ($_POST['conto'] ?? $m['conto']) === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Importo (€) *</label>
            <input type="text" name="importo" value="<?= h($_POST['importo'] ?? $m['importo']) ?>" required>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Categoria</label>
            <input type="text" name="categoria" value="<?= h($_POST['categoria'] ?? $m['categoria']) ?>" list="categorie_pn">
            <datalist id="categorie_pn">
                <?php foreach ($categorie as $c): ?><option value="<?= h($c) ?>"></option><?php endforeach; ?>
            </datalist>
        </div>
        <div class="form-group">
            <label>Causale *</label>
            <input type="text" name="causale" value="<?= h($_POST['causale'] ?? $m['causale']) ?>" required>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Socio collegato (opzionale)</label>
            <input type="number" name="id_socio" value="<?= h($_POST['id_socio'] ?? $m['id_socio']) ?>" min="1" placeholder="ID socio (vuoto = nessuno)">
        </div>
        <div class="form-group">
            <label>Riferimento documento</label>
            <input type="text" name="riferimento_documento" value="<?= h($_POST['riferimento_documento'] ?? $m['riferimento_documento']) ?>">
        </div>
    </div>
    <div class="form-group">
        <label>Note</label>
        <textarea name="note" rows="2"><?= h($_POST['note'] ?? $m['note']) ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Salva modifiche</button>
    </div>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
