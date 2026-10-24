<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$errors = [];

$categorie = $pdo->query('SELECT DISTINCT categoria FROM prima_nota WHERE categoria IS NOT NULL AND categoria <> \'\' ORDER BY categoria')->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $data_mov   = parse_date_to_sql($_POST['data_movimento'] ?? '') ?? date('Y-m-d');
    $tipo_mov   = $_POST['tipo_movimento'] ?? '';
    $conto      = $_POST['conto'] ?? 'cassa';
    $categoria  = clean_text($_POST['categoria'] ?? '');
    $causale    = clean_text($_POST['causale'] ?? '');
    $importo    = (float)str_replace(',', '.', $_POST['importo'] ?? '0');
    $id_socio   = (int)($_POST['id_socio'] ?? 0) ?: null;
    $rif_doc    = clean_text($_POST['riferimento_documento'] ?? '');
    $note       = clean_text($_POST['note'] ?? '');

    if (!in_array($tipo_mov, ['entrata', 'uscita'], true)) $errors[] = 'Seleziona il tipo di movimento.';
    if (!in_array($conto, ['cassa', 'banca', 'altro'], true)) $errors[] = 'Conto non valido.';
    if ($importo <= 0) $errors[] = "L'importo deve essere maggiore di zero.";
    if ($causale === null) $errors[] = 'La causale è obbligatoria.';

    if (empty($errors)) {
        $utente = current_user();
        $stmt = $pdo->prepare(
            'INSERT INTO prima_nota
             (data_movimento, tipo_movimento, conto, categoria, causale, importo,
              id_socio, riferimento_documento, note, registrato_da)
             VALUES (:data, :tipo, :conto, :categoria, :causale, :importo,
                     :socio, :rif_doc, :note, :op)'
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
            'op'        => $utente ? (int)$utente['id_utente'] : null,
        ]);
        redirect_with_message('/prima_nota/list.php', 'Movimento registrato in prima nota.');
    }
}

$csrf = generate_csrf_token();
$page_title = 'Nuovo movimento prima nota';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Nuovo movimento prima nota</h1>
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
            <input type="date" name="data_movimento" value="<?= h($_POST['data_movimento'] ?? date('Y-m-d')) ?>" required>
        </div>
        <div class="form-group">
            <label>Tipo *</label>
            <select name="tipo_movimento" required>
                <option value="entrata" <?= ($_POST['tipo_movimento'] ?? 'entrata') === 'entrata' ? 'selected' : '' ?>>Entrata</option>
                <option value="uscita" <?= ($_POST['tipo_movimento'] ?? '') === 'uscita' ? 'selected' : '' ?>>Uscita</option>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Conto *</label>
            <select name="conto" required>
                <option value="cassa" <?= ($_POST['conto'] ?? 'cassa') === 'cassa' ? 'selected' : '' ?>>Cassa</option>
                <option value="banca" <?= ($_POST['conto'] ?? '') === 'banca' ? 'selected' : '' ?>>Banca</option>
                <option value="altro" <?= ($_POST['conto'] ?? '') === 'altro' ? 'selected' : '' ?>>Altro</option>
            </select>
        </div>
        <div class="form-group">
            <label>Importo (€) *</label>
            <input type="text" name="importo" value="<?= h($_POST['importo'] ?? '') ?>" placeholder="es. 25.00" required>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Categoria</label>
            <input type="text" name="categoria" value="<?= h($_POST['categoria'] ?? '') ?>" list="categorie_pn" placeholder="es. tesseramenti, eventi, spedizioni">
            <datalist id="categorie_pn">
                <?php foreach ($categorie as $c): ?><option value="<?= h($c) ?>"></option><?php endforeach; ?>
            </datalist>
        </div>
        <div class="form-group">
            <label>Causale *</label>
            <input type="text" name="causale" value="<?= h($_POST['causale'] ?? '') ?>" required placeholder="es. Acquisto gadget rinnovo">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Socio collegato (opzionale)</label>
            <input type="number" name="id_socio" value="<?= h($_POST['id_socio'] ?? '') ?>" min="1" placeholder="ID socio (vuoto = nessuno)">
        </div>
        <div class="form-group">
            <label>Riferimento documento</label>
            <input type="text" name="riferimento_documento" value="<?= h($_POST['riferimento_documento'] ?? '') ?>" placeholder="es. ricevuta n. 42">
        </div>
    </div>
    <div class="form-group">
        <label>Note</label>
        <textarea name="note" rows="2"><?= h($_POST['note'] ?? '') ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Registra movimento</button>
    </div>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
