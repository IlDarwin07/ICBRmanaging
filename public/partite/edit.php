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
            $msg = 'Partita aggiornata.';
            if ((int)$data['id_sala'] !== (int)$partita['id_sala']) {
                // Cambio sala: le sedie assegnate non valgono più.
                $pdo->prepare('UPDATE prenotazioni SET numero_posto = NULL WHERE id_partita = ? AND numero_posto IS NOT NULL')->execute([$id]);
                $nuova = partita_get($id);
                $msg .= ' Hai cambiato sala: le sedie vanno riassegnate.';
                if ((int)$nuova['presenti'] > (int)$nuova['capienza']) {
                    $msg .= ' Attenzione: i presenti (' . (int)$nuova['presenti'] . ') superano la capienza della nuova sala (' . (int)$nuova['capienza'] . ').';
                }
            }
            $pdo->commit();
            redirect_with_message('/partite/view.php?id=' . $id, $msg);
        }
        $stato = !empty($_POST['apri_subito']) ? 'aperta' : 'bozza';
        $pdo->prepare(
            'INSERT INTO partite (titolo, competizione, data_ora, id_sala, prenotazioni_chiudono, solo_tesserati, note, stato, creata_da)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$data['titolo'], $data['competizione'], $data['data_ora'], $data['id_sala'],
            $data['prenotazioni_chiudono'], $data['solo_tesserati'], $data['note'], $stato, current_user()['id_utente']]);
        $new = (int)$pdo->lastInsertId();
        redirect_with_message('/partite/view.php?id=' . $new, $stato === 'aperta' ? 'Partita creata e prenotazioni aperte ai soci.' : 'Partita salvata in bozza: aprila quando vuoi rendere visibili le prenotazioni.');
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
    <div class="form-row">
        <div class="form-group">
            <label for="id_sala">Sala *</label>
            <select id="id_sala" name="id_sala" required>
                <?php foreach ($sale as $s): ?>
                <option value="<?= (int)$s['id_sala'] ?>" <?= (int)$form['id_sala'] === (int)$s['id_sala'] ? 'selected' : '' ?>><?= h($s['nome']) ?> (<?= (int)$s['capienza'] ?> sedie)</option>
                <?php endforeach; ?>
            </select>
            <?php if ($partita): ?><small>Cambiando sala, le sedie già assegnate vengono liberate.</small><?php endif; ?>
        </div>
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
