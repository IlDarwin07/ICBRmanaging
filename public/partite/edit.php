<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/partite.php';
start_secure_session();
require_login();
partite_require_ready();

$pdo = get_db_connection();
$id = (int)($_GET['id'] ?? 0);
$partita = $id > 0 ? partita_get($id) : null;
if ($id > 0 && !$partita) render_error_page('Partita non trovata', 'Torna all\'elenco delle partite.');
if ($partita && $partita['stato'] === 'conclusa') redirect_with_message('/partite/view.php?id=' . $id, 'Una partita conclusa non si modifica più.', 'error');

$sale = sale_list(true);
$errors = [];
$form = $partita ?? ['titolo' => '', 'competizione' => '', 'data_ora' => null, 'prenotazioni_chiudono' => null,
    'id_sala' => $sale[0]['id_sala'] ?? 0, 'solo_tesserati' => 1, 'note' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    [$data, $errors] = partita_validate($_POST);
    // Titolo sempre in MAIUSCOLO e senza spazi doppi: "inter  juve" diventa "INTER JUVE".
    if (isset($data['titolo']) && is_string($data['titolo'])) {
        $data['titolo'] = mb_strtoupper(trim((string)preg_replace('/\s+/u', ' ', $data['titolo'])), 'UTF-8');
    }
    $form = array_merge($form, $data);
    if (!$errors) {
        if ($partita) {
            $pdo->beginTransaction();
            $pdo->prepare(
                'UPDATE partite SET titolo = ?, competizione = ?, data_ora = ?, id_sala = ?, prenotazioni_chiudono = ?,
                 solo_tesserati = ?, note = ? WHERE id_partita = ?'
            )->execute([$data['titolo'], $data['competizione'], $data['data_ora'], $data['id_sala'],
                $data['prenotazioni_chiudono'], $data['solo_tesserati'], $data['note'], $id]);
            $liberate = partita_salva_settori($id, $data['settori']);
            $msg = 'Partita aggiornata.';
            if ($liberate > 0) {
                $msg .= ' ' . $liberate . ($liberate === 1 ? ' sedia assegnata non rientra' : ' sedie assegnate non rientrano') . ' più nelle sale indicate: sono state liberate e vanno riassegnate.';
            }
            $nuova = partita_get($id);
            if ((int)$nuova['presenti'] > (int)$nuova['capienza']) {
                $msg .= ' Attenzione: i presenti (' . (int)$nuova['presenti'] . ') superano le sedie disponibili (' . (int)$nuova['capienza'] . ').';
            }
            $pdo->commit();
            redirect_with_message('/partite/view.php?id=' . $id, $msg);
        }
        $stato = !empty($_POST['apri_subito']) ? 'aperta' : 'bozza';
        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO partite (titolo, competizione, data_ora, id_sala, prenotazioni_chiudono, solo_tesserati, note, stato, creata_da)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$data['titolo'], $data['competizione'], $data['data_ora'], $data['id_sala'],
            $data['prenotazioni_chiudono'], $data['solo_tesserati'], $data['note'], $stato, current_user()['id_utente']]);
        $new = (int)$pdo->lastInsertId();
        partita_salva_settori($new, $data['settori']);
        $pdo->commit();
        redirect_with_message('/partite/view.php?id=' . $new, $stato === 'aperta' ? 'Partita creata e prenotazioni aperte ai soci.' : 'Partita salvata in bozza: aprila quando vuoi rendere visibili le prenotazioni.');
    }
}

// Valori da mostrare per ogni sala: dati inviati, partita esistente, ultima partita creata oppure numerazione progressiva.
$valori = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($sale as $s) {
        $k = (int)$s['id_sala'];
        $valori[$k] = ['da' => is_scalar($_POST['sala_da'][$k] ?? null) ? (string)$_POST['sala_da'][$k] : '', 'a' => is_scalar($_POST['sala_a'][$k] ?? null) ? (string)$_POST['sala_a'][$k] : ''];
    }
} else {
    $base = [];
    if ($partita) {
        $base = $partita['settori'];
    } else {
        $ultima = (int)$pdo->query('SELECT MAX(id_partita) FROM partite')->fetchColumn();
        if ($ultima > 0) $base = partita_settori($ultima);
    }
    if ($base) {
        foreach ($base as $b) $valori[(int)$b['id_sala']] = ['da' => (string)$b['posto_da'], 'a' => (string)$b['posto_a']];
    } else {
        $inizio = 1;
        foreach ($sale as $s) {
            $cap = (int)$s['capienza'];
            if ($cap < 1) continue;
            $valori[(int)$s['id_sala']] = ['da' => (string)$inizio, 'a' => (string)($inizio + $cap - 1)];
            $inizio += $cap;
        }
    }
}

$csrf = generate_csrf_token();
$page_title = $partita ? 'Modifica partita' : 'Nuova partita';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <div>
        <span class="admin-eyebrow">SEDE / PARTITE</span>
        <h1><?= $partita ? 'Modifica: ' . h($partita['titolo']) : 'Nuova partita' ?></h1>
    </div>
    <a class="btn btn-secondary" href="<?= u($partita ? '/partite/view.php?id=' . $id : '/partite/list.php') ?>">← Indietro</a>
</div>

<?php if ($errors): ?><div class="flash flash-error" role="alert"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>
<?php if (!$sale): ?><div class="flash flash-error">Non ci sono sale attive. Configurale in <a href="<?= u('/partite/sale.php') ?>">Sale e capienze</a>.</div><?php endif; ?>

<form method="post" class="form-card">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <div class="form-row">
        <div class="form-group">
            <label for="titolo">Partita *</label>
            <input type="text" id="titolo" name="titolo" maxlength="150" placeholder="Inter - Juventus" value="<?= h($form['titolo']) ?>" style="text-transform:uppercase" required>
            <small>Il titolo viene salvato in maiuscolo.</small>
        </div>
        <div class="form-group">
            <label for="competizione">Competizione</label>
            <input type="text" id="competizione" name="competizione" maxlength="80" list="competizioni" placeholder="Serie A" value="<?= h($form['competizione']) ?>">
            <datalist id="competizioni"><option value="Serie A"><option value="Champions League"><option value="Coppa Italia"><option value="Supercoppa Italiana"></datalist>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="data_ora">Data e ora del calcio d'inizio *</label>
            <input type="datetime-local" id="data_ora" name="data_ora" value="<?= h(datetime_local_value($form['data_ora'])) ?>" required>
        </div>
        <div class="form-group">
            <label for="prenotazioni_chiudono">Chiusura prenotazioni</label>
            <input type="datetime-local" id="prenotazioni_chiudono" name="prenotazioni_chiudono" value="<?= h(datetime_local_value($form['prenotazioni_chiudono'])) ?>">
            <small>Se vuoto: 2 ore prima della partita.</small>
        </div>
    </div>
    <fieldset class="form-group">
        <legend>Sale e sedie *</legend>
        <p><small>La partita è trasmessa in tutte le sale indicate. Per ogni sala scrivi la prima e l'ultima sedia (es. Sala Proiettore da 1 a 20, Sala TV da 21 a 32). La numerazione è unica: il numero della sedia dice anche la sala. Lascia vuote le sale non usate.</small></p>
        <?php foreach ($sale as $s): $k = (int)$s['id_sala']; $v = $valori[$k] ?? ['da' => '', 'a' => '']; ?>
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:6px 0">
            <strong style="min-width:160px"><?= h($s['nome']) ?></strong>
            <label for="sala_da_<?= $k ?>">dalla sedia</label>
            <input type="number" id="sala_da_<?= $k ?>" name="sala_da[<?= $k ?>]" min="1" max="<?= PARTITE_MAX_SEDIA ?>" value="<?= h($v['da']) ?>" style="width:90px">
            <label for="sala_a_<?= $k ?>">alla sedia</label>
            <input type="number" id="sala_a_<?= $k ?>" name="sala_a[<?= $k ?>]" min="1" max="<?= PARTITE_MAX_SEDIA ?>" value="<?= h($v['a']) ?>" style="width:90px">
        </div>
        <?php endforeach; ?>
        <?php if ($partita): ?><small>Se riduci o sposti gli intervalli, le sedie assegnate fuori dai nuovi numeri vengono liberate.</small><?php endif; ?>
    </fieldset>
    <div class="form-row">
        <div class="form-group">
            <label class="checkbox-inline"><input type="checkbox" name="solo_tesserati" value="1" <?= (int)$form['solo_tesserati'] === 1 ? 'checked' : '' ?>> Solo soci tesserati nella stagione in corso</label>
            <?php if (!$partita): ?><label class="checkbox-inline"><input type="checkbox" name="apri_subito" value="1" <?= !empty($_POST['apri_subito']) ? 'checked' : '' ?>> Apri subito le prenotazioni</label><?php endif; ?>
        </div>
    </div>
    <div class="form-group">
        <label for="note">Note per i soci</label>
        <textarea id="note" name="note" rows="3" maxlength="500" placeholder="Es. apertura sede alle 20:00, consumazione inclusa"><?= h($form['note']) ?></textarea>
    </div>
    <div class="form-actions"><button type="submit" class="btn"><?= $partita ? 'Salva modifiche' : 'Crea partita' ?></button></div>
</form>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
