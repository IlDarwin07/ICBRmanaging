<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo    = get_db_connection();
$id_tes = (int)($_GET['id_tesseramento'] ?? $_POST['id_tesseramento'] ?? 0);
$errors = [];

// Carica tesseramento + socio
$stmt = $pdo->prepare(
    'SELECT t.*, s.nome, s.cognome, st.codice_stagione,
            tip.quota_standard
     FROM tesseramenti t
     JOIN soci s ON s.id_socio = t.id_socio
     JOIN stagioni st ON st.id_stagione = t.id_stagione
     LEFT JOIN tipologie_tesseramento tip ON tip.id_tipologia = t.id_tipologia
     WHERE t.id_tesseramento = :id'
);
$stmt->execute(['id' => $id_tes]);
$t = $stmt->fetch();

if (!$t) {
    redirect_with_message('/tesseramenti/list.php', 'Tesseramento non trovato.', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $data_pag   = parse_date_to_sql($_POST['data_pagamento'] ?? '') ?? date('Y-m-d');
    $tipo_pag   = $_POST['tipo_pagamento'] ?? 'acconto';
    $importo    = (float)str_replace(',', '.', $_POST['importo'] ?? '0');
    $metodo     = $_POST['metodo_pagamento'] ?? 'contanti';
    $riferimento= clean_text($_POST['riferimento'] ?? '');
    $note       = clean_text($_POST['note'] ?? '');

    $utente = current_user();
    $op_id  = $utente ? (int)$utente['id_utente'] : null;

    if ($importo <= 0) $errors[] = "L'importo deve essere maggiore di zero.";

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO pagamenti_tesseramenti
             (id_tesseramento, data_pagamento, tipo_pagamento, importo, metodo_pagamento, riferimento, note, registrato_da)
             VALUES (:id_tes, :data, :tipo, :importo, :metodo, :rif, :note, :op)'
        );
        $stmt->execute([
            'id_tes'  => $id_tes,
            'data'    => $data_pag,
            'tipo'    => $tipo_pag,
            'importo' => $importo,
            'metodo'  => $metodo,
            'rif'     => $riferimento,
            'note'    => $note,
            'op'      => $op_id,
        ]);
        redirect_with_message('/tesseramenti/view.php?id=' . $id_tes, 'Pagamento registrato.');
    }
}

$csrf = generate_csrf_token();
$page_title = 'Registra pagamento';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Registra pagamento</h1>
    <a class="btn btn-secondary" href="/tesseramenti/view.php?id=<?= $id_tes ?>">← Indietro</a>
</div>

<p>Socio: <strong><?= h($t['cognome'] . ' ' . $t['nome']) ?></strong>
   — Stagione: <strong><?= h($t['codice_stagione']) ?></strong>
   <?php if ($t['quota_standard']): ?>
   — Quota: <strong>€ <?= number_format((float)$t['quota_standard'], 2, ',', '.') ?></strong>
   <?php endif; ?>
</p>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<form method="post" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="id_tesseramento" value="<?= $id_tes ?>">

    <div class="form-row">
        <div class="form-group">
            <label>Data pagamento *</label>
            <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
            <label>Tipo pagamento</label>
            <select name="tipo_pagamento">
                <option value="acconto">Acconto</option>
                <option value="saldo">Saldo</option>
                <option value="integrazione">Integrazione</option>
                <option value="rimborso">Rimborso</option>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Importo (€) *</label>
            <input type="text" name="importo" value="<?= h($_POST['importo'] ?? '') ?>" placeholder="es. 45.00" required>
        </div>
        <div class="form-group">
            <label>Metodo di pagamento</label>
            <select name="metodo_pagamento">
                <option value="contanti">Contanti</option>
                <option value="bonifico">Bonifico</option>
                <option value="pos">POS</option>
                <option value="altro">Altro</option>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label>Riferimento (es. n° ricevuta)</label>
        <input type="text" name="riferimento" value="<?= h($_POST['riferimento'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label>Note</label>
        <textarea name="note" rows="2"><?= h($_POST['note'] ?? '') ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Registra pagamento</button>
    </div>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
