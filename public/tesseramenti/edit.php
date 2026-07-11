<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo    = get_db_connection();
$id     = (int)($_GET['id'] ?? 0);
$errors = [];

$stmt = $pdo->prepare(
    'SELECT t.*, s.nome, s.cognome
     FROM tesseramenti t
     JOIN soci s ON s.id_socio = t.id_socio
     WHERE t.id_tesseramento = :id'
);
$stmt->execute(['id' => $id]);
$t = $stmt->fetch();

if (!$t) {
    redirect_with_message('/tesseramenti/list.php', 'Tesseramento non trovato.', 'error');
}

$stagioni  = $pdo->query('SELECT * FROM stagioni ORDER BY codice_stagione DESC')->fetchAll();
$tipologie = $pdo->query('SELECT * FROM tipologie_tesseramento ORDER BY tipo')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $id_tipologia    = (int)($_POST['id_tipologia'] ?? 0) ?: null;
    $numero_tessera  = clean_text($_POST['numero_tessera'] ?? '');
    $ruolo_portale   = clean_text($_POST['ruolo_portale'] ?? '');
    $tipo_portale    = clean_text($_POST['tipo_portale'] ?? '');
    $listino_orig    = clean_text($_POST['listino_originale'] ?? '');
    $socio_plus      = isset($_POST['socio_plus']) ? 1 : 0;
    $attivo_portale  = isset($_POST['attivo_portale']) ? 1 : 0;
    $attivo_scorsa   = isset($_POST['attivo_scorsa_stagione']) ? 1 : 0;
    $conf_anagrafica = isset($_POST['conferma_anagrafica']) ? 1 : 0;
    $tessera_fisica  = isset($_POST['tessera_fisica']) ? 1 : 0;
    $data_att_raw    = trim($_POST['data_ora_attivazione'] ?? '');
    $data_att        = $data_att_raw !== '' ? $data_att_raw : null;
    $fonte           = $_POST['fonte_inserimento'] ?? $t['fonte_inserimento'];

    $stmt = $pdo->prepare(
        'UPDATE tesseramenti SET
            id_tipologia=:tipologia, listino_originale=:listino, numero_tessera=:tessera,
            ruolo_portale=:ruolo, tipo_portale=:tipo, socio_plus=:plus,
            attivo_portale=:attivo, attivo_scorsa_stagione=:scorsa,
            conferma_anagrafica=:conferma, tessera_fisica=:fisica,
            data_ora_attivazione=:data_att, fonte_inserimento=:fonte
         WHERE id_tesseramento=:id'
    );
    $stmt->execute([
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
        'id'        => $id,
    ]);
    redirect_with_message('/tesseramenti/view.php?id=' . $id, 'Tesseramento aggiornato.');
}

$csrf = generate_csrf_token();
$page_title = 'Modifica tesseramento';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Modifica tesseramento: <?= h($t['cognome'] . ' ' . $t['nome']) ?></h1>
    <a class="btn btn-secondary" href="/tesseramenti/view.php?id=<?= $id ?>">← Indietro</a>
</div>

<?php if ($errors): ?>
<div class="flash flash-error"><?= implode('<br>', array_map('h', $errors)) ?></div>
<?php endif; ?>

<form method="post" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <h3>Stagione: <?= h($t['codice_stagione'] ?? '') ?> — non modificabile</h3>

    <h3>Tipologia e dati portale</h3>
    <div class="form-row">
        <div class="form-group">
            <label>Tipologia</label>
            <select name="id_tipologia">
                <option value="">— Nessuna —</option>
                <?php foreach ($tipologie as $tp): ?>
                    <option value="<?= (int)$tp['id_tipologia'] ?>" <?= ((int)($_POST['id_tipologia'] ?? $t['id_tipologia']) == $tp['id_tipologia']) ? 'selected' : '' ?>>
                        <?= h($tp['listino_label'] ?: $tp['tipo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Listino originale</label>
            <input type="text" name="listino_originale" value="<?= h($_POST['listino_originale'] ?? $t['listino_originale']) ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>N° tessera</label>
            <input type="text" name="numero_tessera" value="<?= h($_POST['numero_tessera'] ?? $t['numero_tessera']) ?>">
        </div>
        <div class="form-group">
            <label>Ruolo portale</label>
            <input type="text" name="ruolo_portale" value="<?= h($_POST['ruolo_portale'] ?? $t['ruolo_portale']) ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Tipo portale</label>
            <input type="text" name="tipo_portale" value="<?= h($_POST['tipo_portale'] ?? $t['tipo_portale']) ?>">
        </div>
        <div class="form-group">
            <label>Data/ora attivazione</label>
            <input type="datetime-local" name="data_ora_attivazione"
                   value="<?= h($_POST['data_ora_attivazione'] ?? ($t['data_ora_attivazione'] ? date('Y-m-d\TH:i', strtotime($t['data_ora_attivazione'])) : '')) ?>">
        </div>
    </div>
    <div class="form-group">
        <label>Fonte inserimento</label>
        <select name="fonte_inserimento">
            <?php foreach (['manuale','portale','misto'] as $f): ?>
                <option value="<?= $f ?>" <?= ($t['fonte_inserimento'] === $f) ? 'selected' : '' ?>><?= $f ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <h3>Flag</h3>
    <div class="form-group form-checks">
        <label class="checkbox-inline"><input type="checkbox" name="attivo_portale" value="1" <?= $t['attivo_portale'] ? 'checked' : '' ?>> Attivo portale</label>
        <label class="checkbox-inline"><input type="checkbox" name="socio_plus" value="1" <?= $t['socio_plus'] ? 'checked' : '' ?>> Socio+</label>
        <label class="checkbox-inline"><input type="checkbox" name="attivo_scorsa_stagione" value="1" <?= $t['attivo_scorsa_stagione'] ? 'checked' : '' ?>> Attivo scorsa stagione</label>
        <label class="checkbox-inline"><input type="checkbox" name="conferma_anagrafica" value="1" <?= $t['conferma_anagrafica'] ? 'checked' : '' ?>> Anagrafica confermata</label>
        <label class="checkbox-inline"><input type="checkbox" name="tessera_fisica" value="1" <?= $t['tessera_fisica'] ? 'checked' : '' ?>> Tessera fisica consegnata</label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Salva modifiche</button>
    </div>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
