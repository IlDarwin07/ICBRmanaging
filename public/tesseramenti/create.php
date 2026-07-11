<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$errors = [];

// Dati per i <select>
$stagioni   = $pdo->query('SELECT * FROM stagioni ORDER BY attiva DESC, codice_stagione DESC')->fetchAll();
$tipologie  = $pdo->query('SELECT * FROM tipologie_tesseramento WHERE attiva=1 ORDER BY tipo')->fetchAll();

// Pre-selezione stagione se passata via GET
$id_stagione_default = (int)($_GET['id_stagione'] ?? 0);
if (!$id_stagione_default) {
    foreach ($stagioni as $s) {
        if ($s['attiva']) { $id_stagione_default = (int)$s['id_stagione']; break; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $id_socio         = (int)($_POST['id_socio'] ?? 0);
    $id_stagione      = (int)($_POST['id_stagione'] ?? 0);
    $id_tipologia     = (int)($_POST['id_tipologia'] ?? 0) ?: null;
    $numero_tessera   = clean_text($_POST['numero_tessera'] ?? '');
    $ruolo_portale    = clean_text($_POST['ruolo_portale'] ?? '');
    $tipo_portale     = clean_text($_POST['tipo_portale'] ?? '');
    $listino_orig     = clean_text($_POST['listino_originale'] ?? '');
    $socio_plus       = isset($_POST['socio_plus']) ? 1 : 0;
    $attivo_portale   = isset($_POST['attivo_portale']) ? 1 : 0;
    $attivo_scorsa    = isset($_POST['attivo_scorsa_stagione']) ? 1 : 0;
    $conf_anagrafica  = isset($_POST['conferma_anagrafica']) ? 1 : 0;
    $tessera_fisica   = isset($_POST['tessera_fisica']) ? 1 : 0;
    $data_att_raw     = trim($_POST['data_ora_attivazione'] ?? '');
    $data_att         = $data_att_raw !== '' ? $data_att_raw : null; // datetime-local
    $fonte            = $_POST['fonte_inserimento'] ?? 'manuale';

    if (!$id_socio)    $errors[] = 'Seleziona un socio.';
    if (!$id_stagione) $errors[] = 'Seleziona una stagione.';

    // Verifica unicità socio+stagione
    if ($id_socio && $id_stagione) {
        $dup = $pdo->prepare('SELECT id_tesseramento FROM tesseramenti WHERE id_socio=:s AND id_stagione=:st');
        $dup->execute(['s' => $id_socio, 'st' => $id_stagione]);
        if ($dup->fetch()) $errors[] = 'Questo socio è già tesserat* per la stagione selezionata.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO tesseramenti
             (id_socio, id_stagione, id_tipologia, listino_originale, numero_tessera,
              ruolo_portale, tipo_portale, socio_plus, attivo_portale, attivo_scorsa_stagione,
              conferma_anagrafica, tessera_fisica, data_ora_attivazione, fonte_inserimento)
             VALUES
             (:socio, :stagione, :tipologia, :listino, :tessera,
              :ruolo, :tipo, :plus, :attivo, :scorsa,
              :conferma, :fisica, :data_att, :fonte)'
        );
        $stmt->execute([
            'socio'     => $id_socio,
            'stagione'  => $id_stagione,
            'tipologia' => $id_tipologia,
            'listino'   => $listino_orig,
            'tessera'   => $numero_tessera,
            'ruolo'     => $ruolo_portale,
            'tipo'      => $tipo_portale,
            'plus'      => $socio_plus,
            'attivo'    => $attivo_portale,
            'scorsa'    => $attivo_scorsa,
            'conferma'  => $conf_anagrafica,
            'fisica'    => $tessera_fisica,
            'data_att'  => $data_att,
            'fonte'     => $fonte,
        ]);
        $new_id = $pdo->lastInsertId();
        redirect_with_message('/tesseramenti/view.php?id=' . $new_id, 'Tesseramento creato con successo.');
    }
}

$csrf = generate_csrf_token();
$page_title = 'Nuovo tesseramento';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Nuovo tesseramento</h1>
    <a class="btn btn-secondary" href="/tesseramenti/list.php">← Torna alla lista</a>
</div>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<form method="post" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <h3>Socio</h3>
    <div class="form-group">
        <label>ID socio * <small>(cerca prima nella <a href="/soci/list.php" target="_blank">lista soci</a>)</small></label>
        <input type="number" name="id_socio" value="<?= h($_POST['id_socio'] ?? '') ?>" min="1" required
               placeholder="Inserisci ID numerico del socio">
    </div>

    <h3>Stagione e tipologia</h3>
    <div class="form-row">
        <div class="form-group">
            <label>Stagione *</label>
            <select name="id_stagione" required>
                <option value="">— Seleziona —</option>
                <?php foreach ($stagioni as $s): ?>
                    <option value="<?= (int)$s['id_stagione'] ?>" <?= ((int)($_POST['id_stagione'] ?? $id_stagione_default) == $s['id_stagione']) ? 'selected' : '' ?>>
                        <?= h($s['codice_stagione']) ?> <?= $s['attiva'] ? '(attiva)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Tipologia</label>
            <select name="id_tipologia">
                <option value="">— Nessuna —</option>
                <?php foreach ($tipologie as $tp): ?>
                    <option value="<?= (int)$tp['id_tipologia'] ?>" <?= ((int)($_POST['id_tipologia'] ?? 0) == $tp['id_tipologia']) ? 'selected' : '' ?>>
                        <?= h($tp['listino_label'] ?: $tp['tipo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <h3>Dati portale Inter Club</h3>
    <div class="form-row">
        <div class="form-group">
            <label>Numero tessera</label>
            <input type="text" name="numero_tessera" value="<?= h($_POST['numero_tessera'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Listino originale (portale)</label>
            <input type="text" name="listino_originale" value="<?= h($_POST['listino_originale'] ?? '') ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Ruolo portale</label>
            <input type="text" name="ruolo_portale" value="<?= h($_POST['ruolo_portale'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Tipo portale</label>
            <input type="text" name="tipo_portale" value="<?= h($_POST['tipo_portale'] ?? '') ?>" placeholder="senior, junior, ecc.">
        </div>
    </div>
    <div class="form-group">
        <label>Data/ora attivazione portale</label>
        <input type="datetime-local" name="data_ora_attivazione" value="<?= h($_POST['data_ora_attivazione'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label>Fonte inserimento</label>
        <select name="fonte_inserimento">
            <option value="manuale" <?= ($_POST['fonte_inserimento'] ?? 'manuale') === 'manuale' ? 'selected' : '' ?>>Manuale</option>
            <option value="portale" <?= ($_POST['fonte_inserimento'] ?? '') === 'portale' ? 'selected' : '' ?>>Portale</option>
            <option value="misto"   <?= ($_POST['fonte_inserimento'] ?? '') === 'misto'   ? 'selected' : '' ?>>Misto</option>
        </select>
    </div>

    <h3>Flag</h3>
    <div class="form-group form-checks">
        <label class="checkbox-inline"><input type="checkbox" name="attivo_portale" value="1" <?= isset($_POST['attivo_portale']) ? 'checked' : '' ?>> Attivo portale</label>
        <label class="checkbox-inline"><input type="checkbox" name="socio_plus" value="1" <?= isset($_POST['socio_plus']) ? 'checked' : '' ?>> Socio+</label>
        <label class="checkbox-inline"><input type="checkbox" name="attivo_scorsa_stagione" value="1" <?= isset($_POST['attivo_scorsa_stagione']) ? 'checked' : '' ?>> Attivo scorsa stagione</label>
        <label class="checkbox-inline"><input type="checkbox" name="conferma_anagrafica" value="1" <?= isset($_POST['conferma_anagrafica']) ? 'checked' : '' ?>> Anagrafica confermata</label>
        <label class="checkbox-inline"><input type="checkbox" name="tessera_fisica" value="1" <?= isset($_POST['tessera_fisica']) ? 'checked' : '' ?>> Tessera fisica consegnata</label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Salva tesseramento</button>
    </div>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
