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
    'SELECT t.*, s.nome, s.cognome, s.telefono, st.codice_stagione,
            tip.quota_standard AS quota_standard_tip
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

// Quota e stato attuale (logica centralizzata)
$quota = tess_quota($t);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $data_pag   = parse_date_to_sql($_POST['data_pagamento'] ?? '') ?? date('Y-m-d');
    $tipo_pag   = $_POST['tipo_pagamento'] ?? 'acconto';
    $importo    = (float)str_replace(',', '.', $_POST['importo'] ?? '0');
    $metodo     = $_POST['metodo_pagamento'] ?? 'contanti';
    $riferimento= clean_text($_POST['riferimento'] ?? '');
    $note       = clean_text($_POST['note'] ?? '');

    // Prima nota: registrazione opzionale, con conto scelto esplicitamente
    $registra_prima_nota = isset($_POST['registra_prima_nota']);
    $conto_pn            = $_POST['conto_prima_nota'] ?? 'cassa';
    if (!in_array($conto_pn, ['cassa', 'banca', 'altro'], true)) {
        $conto_pn = 'cassa';
    }

    $utente = current_user();
    $op_id  = $utente ? (int)$utente['id_utente'] : null;

    if ($importo <= 0) $errors[] = "L'importo deve essere maggiore di zero.";
    if (!in_array($tipo_pag, ['acconto', 'saldo', 'integrazione', 'rimborso'], true)) {
        $errors[] = 'Tipo pagamento non valido.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

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
            $id_pagamento = (int)$pdo->lastInsertId();

            // Movimento in prima nota nella stessa transazione.
            // La chiave unica uq_primanota_pagamento impedisce duplicazioni
            // anche in caso di reinvio del form.
            if ($registra_prima_nota) {
                $stmt_pn = $pdo->prepare(
                    'INSERT INTO prima_nota
                     (data_movimento, tipo_movimento, conto, categoria, causale, importo,
                      id_socio, id_tesseramento, id_pagamento, riferimento_documento, note, registrato_da)
                     VALUES (:data, :tipo_mov, :conto, :categoria, :causale, :importo,
                             :socio, :tess, :pag, :rif_doc, :note_pn, :op)
                     ON DUPLICATE KEY UPDATE id_movimento = id_movimento'
                );
                $stmt_pn->execute([
                    'data'      => $data_pag,
                    'tipo_mov'  => $tipo_pag === 'rimborso' ? 'uscita' : 'entrata',
                    'conto'     => $conto_pn,
                    'categoria' => 'tesseramenti',
                    'causale'   => 'Quota tesseramento ' . $t['cognome'] . ' ' . $t['nome']
                                   . ' (' . $t['codice_stagione'] . ') - ' . $tipo_pag,
                    'importo'   => $importo,
                    'socio'     => $t['id_socio'],
                    'tess'      => $id_tes,
                    'pag'       => $id_pagamento,
                    'rif_doc'   => $riferimento,
                    'note_pn'   => $note,
                    'op'        => $op_id,
                ]);
            }

            $pdo->commit();
            redirect_with_message(
                '/tesseramenti/view.php?id=' . $id_tes,
                'Pagamento registrato' . ($registra_prima_nota ? ' e movimento inserito in prima nota.' : '.')
            );
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Errore durante la registrazione: ' . h($e->getMessage());
        }
    }
}

$csrf = generate_csrf_token();
$page_title = 'Registra pagamento';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Registra pagamento</h1>
    <a class="btn btn-secondary" href="<?= u('/tesseramenti/view.php') ?>?id=<?= $id_tes ?>">← Indietro</a>
</div>

<p>Socio: <strong><?= h($t['cognome'] . ' ' . $t['nome']) ?></strong>
   — Stagione: <strong><?= h($t['codice_stagione']) ?></strong>
   <?php if ($quota !== null): ?>
   — Quota: <strong>€ <?= euro($quota) ?></strong>
   <?php else: ?>
   — Quota: <strong>non impostata</strong> <small>(residuo non calcolabile)</small>
   <?php endif; ?>
</p>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', $errors) ?></div>
<?php endif; ?>

<form method="post" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="id_tesseramento" value="<?= $id_tes ?>">

    <div class="form-row">
        <div class="form-group">
            <label>Data pagamento *</label>
            <input type="date" name="data_pagamento" value="<?= h($_POST['data_pagamento'] ?? date('Y-m-d')) ?>" required>
        </div>
        <div class="form-group">
            <label>Tipo pagamento</label>
            <select name="tipo_pagamento">
                <option value="acconto"     <?= ($_POST['tipo_pagamento'] ?? 'acconto') === 'acconto' ? 'selected' : '' ?>>Acconto</option>
                <option value="saldo"       <?= ($_POST['tipo_pagamento'] ?? '') === 'saldo' ? 'selected' : '' ?>>Saldo</option>
                <option value="integrazione" <?= ($_POST['tipo_pagamento'] ?? '') === 'integrazione' ? 'selected' : '' ?>>Integrazione</option>
                <option value="rimborso"    <?= ($_POST['tipo_pagamento'] ?? '') === 'rimborso' ? 'selected' : '' ?>>Rimborso</option>
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
                <option value="contanti" <?= ($_POST['metodo_pagamento'] ?? 'contanti') === 'contanti' ? 'selected' : '' ?>>Contanti</option>
                <option value="bonifico" <?= ($_POST['metodo_pagamento'] ?? '') === 'bonifico' ? 'selected' : '' ?>>Bonifico</option>
                <option value="pos"      <?= ($_POST['metodo_pagamento'] ?? '') === 'pos' ? 'selected' : '' ?>>POS</option>
                <option value="altro"    <?= ($_POST['metodo_pagamento'] ?? '') === 'altro' ? 'selected' : '' ?>>Altro</option>
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

    <div class="form-group form-checks">
        <label class="checkbox-inline">
            <input type="checkbox" name="registra_prima_nota" value="1" <?= isset($_POST['registra_prima_nota']) ? 'checked' : '' ?>>
            Registra anche il movimento in prima nota
        </label>
    </div>
    <div class="form-group">
        <label>Conto prima nota <small>(se spuntato sopra)</small></label>
        <select name="conto_prima_nota">
            <option value="cassa" <?= ($_POST['conto_prima_nota'] ?? 'cassa') === 'cassa' ? 'selected' : '' ?>>Cassa</option>
            <option value="banca" <?= ($_POST['conto_prima_nota'] ?? '') === 'banca' ? 'selected' : '' ?>>Banca</option>
            <option value="altro" <?= ($_POST['conto_prima_nota'] ?? '') === 'altro' ? 'selected' : '' ?>>Altro</option>
        </select>
        <small style="color:#888">Il tipo di movimento è determinato automaticamente:
        entrata per acconto/saldo/integrazione, uscita per rimborso.</small>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Registra pagamento</button>
    </div>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
