<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/partite.php';
start_secure_session();
require_login();
partite_require_ready();

$id = (int)($_GET['id'] ?? 0);
$partita = partita_get($id);
if (!$partita) render_error_page('Partita non trovata', 'Torna all\'elenco delle partite.');

$prenotazioni = prenotazioni_partita($id);
$presenti = array_values(array_filter($prenotazioni, fn($r) => $r['stato'] === 'presente'));
$attesa = array_values(array_filter($prenotazioni, fn($r) => $r['stato'] === 'attesa'));
$annullate = array_values(array_filter($prenotazioni, fn($r) => $r['stato'] === 'annullata'));
$occupati = [];
foreach ($presenti as $r) if ($r['numero_posto'] !== null) $occupati[(int)$r['numero_posto']] = $r;
$capienza = (int)$partita['capienza'];
$liberi = array_values(array_filter(range(1, max(1, $capienza)), fn($n) => !isset($occupati[$n])));
$modificabile = !in_array($partita['stato'], ['conclusa', 'annullata'], true);
$puo_assegnare = can('partite.assegna') && $modificabile;

// Ricerca soci da aggiungere a mano (es. chi prenota per telefono o in sede).
$q = clean_text($_GET['q'] ?? '') ?? '';
$risultati = [];
if ($puo_assegnare && mb_strlen($q) >= 2) {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $stmt = get_db_connection()->prepare(
        "SELECT s.id_socio, s.nome, s.cognome, s.comune, r.stato AS stato_prenotazione
         FROM soci s LEFT JOIN prenotazioni r ON r.id_socio = s.id_socio AND r.id_partita = ?
         WHERE s.attivo_record = 1 AND (s.cognome LIKE ? OR s.nome LIKE ? OR CONCAT(s.nome,' ',s.cognome) LIKE ? OR CONCAT(s.cognome,' ',s.nome) LIKE ?)
         ORDER BY s.cognome, s.nome LIMIT 15"
    );
    $stmt->execute([$id, $like, $like, $like, $like]);
    $risultati = $stmt->fetchAll();
}

$csrf = generate_csrf_token();
$per_fila = max(1, (int)$partita['posti_per_fila']);
$page_title = 'Presenze — ' . $partita['titolo'];
require __DIR__ . '/../../includes/layout_header.php';

$action = function (string $azione, int $id_socio, string $label, string $class = 'btn-secondary', array $extra = [], string $confirm = '') use ($csrf, $id): string {
    $html = '<form method="post" action="' . u('/partite/prenotazione.php') . '" class="inline-form"' . ($confirm !== '' ? ' data-confirm="' . h($confirm) . '"' : '') . '>'
        . '<input type="hidden" name="csrf_token" value="' . h($csrf) . '"><input type="hidden" name="id_partita" value="' . $id . '">'
        . '<input type="hidden" name="id_socio" value="' . $id_socio . '"><input type="hidden" name="azione" value="' . h($azione) . '">';
    foreach ($extra as $k => $v) $html .= '<input type="hidden" name="' . h($k) . '" value="' . h((string)$v) . '">';
    return $html . '<button class="btn btn-sm ' . h($class) . '" type="submit">' . h($label) . '</button></form>';
};
?>
<div class="page-header">
    <div>
        <span class="admin-eyebrow">SEDE / <?= h(strtoupper($partita['sala_nome'])) ?></span>
        <h1><?= h($partita['titolo']) ?></h1>
        <p><?= h(partita_data_label($partita['data_ora'])) ?><?= $partita['competizione'] ? ' · ' . h($partita['competizione']) : '' ?>
            · <span class="badge <?= h(partita_stato_badge($partita['stato'])) ?>"><?= h(partita_stato_label($partita['stato'])) ?></span></p>
        <p><small>Prenotazioni fino a <?= h(date('d/m/Y H:i', strtotime($partita['prenotazioni_chiudono']))) ?><?= (int)$partita['solo_tesserati'] === 1 ? ' · solo soci tesserati' : ' · aperta a tutti i soci con accesso' ?></small></p>
    </div>
    <div class="quick-links">
        <a class="btn btn-secondary" href="<?= u('/partite/list.php') ?>">← Partite</a>
        <?php if (can('partite.write') && $partita['stato'] !== 'conclusa'): ?><a class="btn btn-secondary" href="<?= u('/partite/edit.php?id=' . $id) ?>">Modifica</a><?php endif; ?>
        <?php if (can('partite.export')): ?><a class="btn btn-secondary" href="<?= u('/partite/export.php?id=' . $id) ?>" target="_blank" rel="noopener">Esporta PDF</a><?php endif; ?>
    </div>
</div>

<?php if (can('partite.write') && PARTITE_TRANSIZIONI[$partita['stato']]): ?>
<section class="admin-panel partite-stati">
    <h2>Stato della partita</h2>
    <div class="quick-links">
    <?php foreach (PARTITE_TRANSIZIONI[$partita['stato']] as $nuovo):
        $labels = ['aperta' => $partita['stato'] === 'chiusa' ? 'Riapri prenotazioni' : 'Apri prenotazioni', 'chiusa' => 'Chiudi prenotazioni',
            'conclusa' => 'Segna come conclusa', 'annullata' => 'Annulla partita', 'bozza' => 'Riporta in bozza'];
        $confirm = $nuovo === 'annullata' ? 'Annullare la partita? I soci prenotati la vedranno come annullata.' : ($nuovo === 'conclusa' ? 'Segnare la partita come conclusa? Non sarà più modificabile.' : ''); ?>
        <form method="post" action="<?= u('/partite/stato.php') ?>" class="inline-form"<?= $confirm ? ' data-confirm="' . h($confirm) . '"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="id_partita" value="<?= $id ?>">
            <input type="hidden" name="stato" value="<?= h($nuovo) ?>">
            <button class="btn <?= $nuovo === 'annullata' ? 'btn-danger' : ($nuovo === 'aperta' ? '' : 'btn-secondary') ?>" type="submit"><?= h($labels[$nuovo]) ?></button>
        </form>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<div class="partite-kpi">
    <div><strong><?= count($presenti) ?> / <?= $capienza ?></strong><span>presenti</span></div>
    <div><strong><?= count($occupati) ?></strong><span>sedie assegnate</span></div>
    <div><strong><?= max(0, $capienza - count($presenti)) ?></strong><span>posti liberi</span></div>
    <div><strong><?= count($attesa) ?></strong><span>in lista d'attesa</span></div>
</div>
<?php if (count($presenti) > $capienza): ?><div class="flash flash-error">I presenti superano la capienza della sala: sposta qualcuno in un'altra partita o cambia sala.</div><?php endif; ?>

<section class="admin-panel">
    <div class="section-head">
        <h2>Piantina — <?= h($partita['sala_nome']) ?></h2>
        <?php if ($puo_assegnare && $presenti && count($occupati) < count($presenti)): ?>
            <?= $action('auto', 0, 'Assegna in automatico le sedie libere', '', [], 'Assegnare le sedie libere ai presenti senza posto, in ordine di prenotazione?') ?>
        <?php endif; ?>
    </div>
    <?php if ($puo_assegnare): ?><p class="seat-help" id="seat-help">Seleziona un socio nell'elenco qui sotto, poi clicca una sedia libera.</p><?php endif; ?>
    <form method="post" action="<?= u('/partite/prenotazione.php') ?>" id="seat-form">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="id_partita" value="<?= $id ?>">
        <input type="hidden" name="azione" value="assegna">
        <input type="hidden" name="id_socio" id="seat-socio" value="">
        <div class="seat-grid" style="--seats-per-row: <?= $per_fila ?>" aria-label="Sedie della <?= h($partita['sala_nome']) ?>">
            <?php for ($n = 1; $n <= $capienza; $n++): $occ = $occupati[$n] ?? null; ?>
                <?php if ($occ): ?>
                    <span class="seat seat-taken" title="<?= h($occ['cognome'] . ' ' . $occ['nome']) ?>"><b><?= $n ?></b><small><?= h(mb_substr($occ['cognome'], 0, 10)) ?></small></span>
                <?php elseif ($puo_assegnare): ?>
                    <button class="seat seat-free" type="submit" name="numero" value="<?= $n ?>" disabled><b><?= $n ?></b><small>libera</small></button>
                <?php else: ?>
                    <span class="seat seat-free"><b><?= $n ?></b><small>libera</small></span>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </form>
</section>

<section class="admin-panel">
    <h2>Presenti (<?= count($presenti) ?>)</h2>
    <?php if (!$presenti): ?><p>Nessun socio ha ancora confermato.</p><?php else: ?>
    <table class="data-table">
        <thead><tr><?php if ($puo_assegnare): ?><th><span class="sr-only">Seleziona</span></th><?php endif; ?><th>#</th><th>Socio</th><th>Sedia</th><th>Prenotato il</th><th>Azioni</th></tr></thead>
        <tbody>
        <?php foreach ($presenti as $i => $r): $nome = $r['cognome'] . ' ' . $r['nome']; $wa = wa_phone($r['telefono']); ?>
            <tr>
                <?php if ($puo_assegnare): ?><td><input type="radio" name="seat-pick" value="<?= (int)$r['id_socio'] ?>" aria-label="Seleziona <?= h($nome) ?> per assegnare la sedia"></td><?php endif; ?>
                <td><?= $i + 1 ?></td>
                <td><a href="<?= u('/soci/view.php?id=' . (int)$r['id_socio']) ?>"><?= h($nome) ?></a></td>
                <td><?= $r['numero_posto'] !== null ? '<strong>' . (int)$r['numero_posto'] . '</strong>' : '<span class="badge badge-gray">da assegnare</span>' ?></td>
                <td><?= h(date('d/m H:i', strtotime($r['created_at']))) ?></td>
                <td class="td-actions">
                    <?php if ($puo_assegnare): ?>
                    <form method="post" action="<?= u('/partite/prenotazione.php') ?>" class="inline-form">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="id_partita" value="<?= $id ?>">
                        <input type="hidden" name="id_socio" value="<?= (int)$r['id_socio'] ?>"><input type="hidden" name="azione" value="assegna">
                        <label class="sr-only" for="posto-<?= (int)$r['id_socio'] ?>">Sedia per <?= h($nome) ?></label>
                        <select id="posto-<?= (int)$r['id_socio'] ?>" name="numero">
                            <?php if ($r['numero_posto'] !== null): ?><option value="<?= (int)$r['numero_posto'] ?>" selected>Sedia <?= (int)$r['numero_posto'] ?></option><?php else: ?><option value="">—</option><?php endif; ?>
                            <?php foreach ($liberi as $n): ?><option value="<?= $n ?>">Sedia <?= $n ?></option><?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm" type="submit">Assegna</button>
                    </form>
                    <?php if ($r['numero_posto'] !== null): ?><?= $action('libera', (int)$r['id_socio'], 'Libera sedia') ?><?php endif; ?>
                    <?= $action('annulla', (int)$r['id_socio'], 'Annulla', 'btn-danger', [], 'Annullare la presenza di ' . $nome . '?') ?>
                    <?php endif; ?>
                    <?php if ($wa && can('whatsapp.view')): ?><a class="btn btn-secondary btn-sm" target="_blank" rel="noopener" href="https://wa.me/<?= h($wa) ?>?text=<?= h(rawurlencode(partita_messaggio_posto($partita, $r))) ?>">WhatsApp ↗</a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<?php if ($attesa): ?>
<section class="admin-panel">
    <h2>Lista d'attesa (<?= count($attesa) ?>)</h2>
    <p>Quando un presente annulla, il primo della lista passa automaticamente tra i presenti.</p>
    <table class="data-table">
        <thead><tr><th>#</th><th>Socio</th><th>In attesa dal</th><th>Azioni</th></tr></thead>
        <tbody>
        <?php foreach ($attesa as $i => $r): $nome = $r['cognome'] . ' ' . $r['nome']; ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><a href="<?= u('/soci/view.php?id=' . (int)$r['id_socio']) ?>"><?= h($nome) ?></a></td>
                <td><?= h(date('d/m H:i', strtotime($r['created_at']))) ?></td>
                <td class="td-actions"><?php if ($puo_assegnare): ?>
                    <?= $action('promuovi', (int)$r['id_socio'], 'Sposta tra i presenti', '', [], count($presenti) >= $capienza ? 'La sala è piena: confermare comunque ' . $nome . ' oltre la capienza?' : '') ?>
                    <?= $action('annulla', (int)$r['id_socio'], 'Togli dalla lista', 'btn-danger') ?>
                <?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<?php if ($puo_assegnare): ?>
<section class="admin-panel">
    <h2>Aggiungi un socio</h2>
    <p>Per chi prenota in sede o per telefono. Il direttivo può aggiungere soci anche a prenotazioni chiuse.</p>
    <form method="get" class="search-form">
        <input type="hidden" name="id" value="<?= $id ?>">
        <label class="sr-only" for="q">Cerca socio</label>
        <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Cognome o nome" minlength="2">
        <button class="btn" type="submit">Cerca</button>
    </form>
    <?php if ($q !== '' && !$risultati): ?><p>Nessun socio trovato per «<?= h($q) ?>».</p><?php endif; ?>
    <?php if ($risultati): ?>
    <table class="data-table">
        <tbody>
        <?php foreach ($risultati as $s): ?>
            <tr>
                <td><?= h($s['cognome'] . ' ' . $s['nome']) ?><?= $s['comune'] ? ' <small>(' . h($s['comune']) . ')</small>' : '' ?></td>
                <td class="td-actions">
                    <?php if (in_array($s['stato_prenotazione'], ['presente', 'attesa'], true)): ?>
                        <span class="badge <?= h(prenotazione_stato_badge($s['stato_prenotazione'])) ?>"><?= h(prenotazione_stato_label($s['stato_prenotazione'])) ?></span>
                    <?php else: ?>
                        <?= $action('aggiungi', (int)$s['id_socio'], 'Aggiungi ai presenti', '', ['q' => $q]) ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($annullate): ?>
<details class="admin-panel">
    <summary>Annullate (<?= count($annullate) ?>)</summary>
    <ul><?php foreach ($annullate as $r): ?><li><?= h($r['cognome'] . ' ' . $r['nome']) ?> <small>(<?= h(date('d/m H:i', strtotime($r['updated_at']))) ?>)</small></li><?php endforeach; ?></ul>
</details>
<?php endif; ?>

<?php if ($puo_assegnare): ?>
<script>
(function () {
    var form = document.getElementById('seat-form'), hidden = document.getElementById('seat-socio'), help = document.getElementById('seat-help');
    var seats = form.querySelectorAll('.seat-free');
    document.querySelectorAll('input[name="seat-pick"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            hidden.value = radio.value;
            seats.forEach(function (s) { s.disabled = false; });
            var row = radio.closest('tr');
            help.textContent = 'Ora clicca la sedia per ' + row.querySelector('td:nth-child(3)').textContent.trim() + '.';
            form.scrollIntoView({behavior: 'smooth', block: 'center'});
        });
    });
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
