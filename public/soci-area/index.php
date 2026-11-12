<?php
require_once __DIR__ . '/../../includes/auth_soci.php';
require_once __DIR__ . '/../../includes/soci_layout.php';
require_once __DIR__ . '/../../includes/famiglia.php';

// Link diretto a una partita (?partita=ID): se il socio non e' ancora entrato, lo si ricorda per dopo il login.
$partita_link = isset($_GET['partita']) && ctype_digit((string)$_GET['partita']) ? (int)$_GET['partita'] : 0;
if ($partita_link > 0) {
    start_soci_session();
    if (!current_socio()) $_SESSION['socio_dopo_login'] = $partita_link;
}
$socio = require_socio_login();

$partite = partite_per_socio($socio['id_socio']);
$tesserato = socio_tesserato($socio['id_socio']);
$stagione = stagione_corrente();

$familiari = famiglia_familiari($socio['id_socio']);
$ids_soci = array_merge([(int)$socio['id_socio']], array_map(fn($f) => (int)$f['id_socio'], $familiari));
$extra = famiglia_prenotazioni($ids_soci, array_map(fn($p) => (int)$p['id_partita'], $partite));
$sostenitori = soci_sostenitori($ids_soci);
$quota_testo = number_format(PARTITA_QUOTA_SOSTENITORE, 2, ',', '.');
$tess_fam = [];
foreach ($familiari as $f) {
    $tess_fam[(int)$f['id_socio']] = socio_tesserato((int)$f['id_socio']);
}

soci_header('Le partite in sede', $socio);
?>
<div class="sa-hello">
    <span class="sa-kicker">CIAO <?= h(mb_strtoupper($socio['nome'])) ?></span>
    <h1>Le partite in sede</h1>
    <?php if (!$tesserato): ?>
        <p class="sa-note">Non risulti ancora tesserato per la stagione <?= h($stagione['codice_stagione'] ?? 'in corso') ?>: alcune partite sono riservate ai tesserati. Per rinnovare parla con il direttivo.</p>
    <?php endif; ?>
    <p class="sa-small">Le partite in sede sono gratuite; i soci sostenitori contribuiscono con € <?= h($quota_testo) ?> a partita.</p>
</div>

<?php if (!$partite): ?>
    <section class="sa-card"><h2>Nessuna partita in programma</h2><p>Quando il direttivo apre le prenotazioni, la partita compare qui.</p></section>
<?php endif; ?>

<?php foreach ($partite as $p):
    $aperta = partita_prenotabile($p);
    $mia = in_array($p['mia_stato'], ['presente', 'attesa'], true) ? $p['mia_stato'] : null;
    $liberi = max(0, (int)$p['capienza'] - (int)$p['presenti']);
    $riservata = (int)$p['solo_tesserati'] === 1 && !$tesserato;
    $mio_extra = $extra[(int)$p['id_partita']][(int)$socio['id_socio']] ?? null; ?>
<article id="partita-<?= (int)$p['id_partita'] ?>" style="scroll-margin-top:16px<?= $partita_link === (int)$p['id_partita'] ? ';outline:3px solid #011ea0;outline-offset:3px' : '' ?>" class="sa-match<?= $mia === 'presente' ? ' is-booked' : '' ?><?= $p['stato'] === 'annullata' ? ' is-cancelled' : '' ?>">
    <div class="sa-match-head">
        <?php if ($p['competizione']): ?><span class="sa-comp"><?= h($p['competizione']) ?></span><?php endif; ?>
        <h2><?= h($p['titolo']) ?></h2>
        <p class="sa-when"><?= h(partita_data_label($p['data_ora'])) ?> · <?= h($p['sala_nome']) ?></p>
        <?php if ($p['note']): ?><p class="sa-small"><?= nl2br(h($p['note'])) ?></p><?php endif; ?>
    </div>

    <div class="sa-match-status">
        <?php if ($p['stato'] === 'annullata'): ?>
            <p class="sa-status sa-status-off">Partita annullata dal direttivo.</p>
        <?php elseif ($mia === 'presente'): ?>
            <?php if ($p['mio_posto'] !== null): ?>
                <div class="sa-seat" aria-label="La tua sedia"><small>LA TUA SEDIA</small><b><?= (int)$p['mio_posto'] ?></b></div>
            <?php endif; ?>
            <?php if (!empty($mio_extra['da_nome'])): ?><p class="sa-small">Prenotato per te da <?= h($mio_extra['da_nome']) ?>.</p><?php endif; ?>
            <?php if (!empty($sostenitori[(int)$socio['id_socio']])): ?><p class="sa-small">Socio sostenitore: in sede sono € <?= h($quota_testo) ?> per questa partita.</p><?php endif; ?>
        <?php elseif ($mia === 'attesa'): ?>
            <p class="sa-status sa-status-wait">Sei in lista d'attesa: se si libera un posto passi tra i presenti automaticamente.</p>
            <?php if (!empty($mio_extra['da_nome'])): ?><p class="sa-small">Prenotato per te da <?= h($mio_extra['da_nome']) ?>.</p><?php endif; ?>
        <?php elseif ($aperta): ?>
            <p class="sa-status"><?= $liberi > 0 ? $liberi . ($liberi === 1 ? ' posto libero' : ' posti liberi') : 'Sala piena: puoi metterti in lista d\'attesa' ?> · prenotazioni fino a <?= h(date('d/m H:i', strtotime($p['prenotazioni_chiudono']))) ?></p>
        <?php else: ?>
            <p class="sa-status sa-status-off">Prenotazioni chiuse.</p>
        <?php endif; ?>
    </div>

    <?php if ($aperta && $p['stato'] !== 'annullata'): ?>
    <form method="post" action="<?= u('/soci-area/prenota.php') ?>" class="sa-match-actions">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id_partita" value="<?= (int)$p['id_partita'] ?>">
        <?php if ($mia): ?>
            <button type="submit" name="azione" value="annulla" class="sa-btn sa-btn-ghost">Non vengo più</button>
        <?php elseif ($riservata): ?>
            <p class="sa-small">Riservata ai soci tesserati <?= h($stagione['codice_stagione'] ?? '') ?>.</p>
        <?php else: ?>
            <button type="submit" name="azione" value="conferma" class="sa-btn"><?= $liberi > 0 ? 'Ci sarò' : 'Mettimi in lista d\'attesa' ?></button>
        <?php endif; ?>
    </form>
    <?php elseif ($mia && $p['stato'] !== 'annullata'): ?>
        <p class="sa-small">Per annullare ora contatta il direttivo.</p>
    <?php endif; ?>

    <?php if ($familiari && $p['stato'] !== 'annullata'): ?>
    <div class="sa-match-family" style="margin-top:12px;padding-top:12px;border-top:1px solid #e1e6f0">
        <p class="sa-small"><strong>I tuoi familiari</strong></p>
        <?php foreach ($familiari as $f):
            $fid = (int)$f['id_socio'];
            $fx = $extra[(int)$p['id_partita']][$fid] ?? null;
            $fstato = $fx['stato'] ?? null; ?>
        <form method="post" action="<?= u('/soci-area/prenota.php') ?>" style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:6px 0">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="id_partita" value="<?= (int)$p['id_partita'] ?>">
            <input type="hidden" name="id_socio_per" value="<?= $fid ?>">
            <span><?= h($f['nome'] . ' ' . $f['cognome']) ?><?php if (!empty($sostenitori[$fid])): ?> <small>(sostenitore, € <?= h($quota_testo) ?>)</small><?php endif; ?></span>
            <?php if ($fstato === 'presente'): ?>
                <span class="sa-status sa-status-ok">✓ Prenotato<?= $fx['numero_posto'] !== null ? ' · sedia ' . (int)$fx['numero_posto'] : '' ?></span>
                <?php if ($aperta): ?><button type="submit" name="azione" value="annulla" class="sa-btn sa-btn-ghost">Annulla</button><?php endif; ?>
            <?php elseif ($fstato === 'attesa'): ?>
                <span class="sa-status sa-status-wait">In lista d'attesa</span>
                <?php if ($aperta): ?><button type="submit" name="azione" value="annulla" class="sa-btn sa-btn-ghost">Annulla</button><?php endif; ?>
            <?php elseif ($aperta && ((int)$p['solo_tesserati'] !== 1 || !empty($tess_fam[$fid]))): ?>
                <button type="submit" name="azione" value="conferma" class="sa-btn">Prenota</button>
            <?php elseif ($aperta): ?>
                <small>Non tesserato: partita riservata ai tesserati.</small>
            <?php else: ?>
                <small>Prenotazioni chiuse.</small>
            <?php endif; ?>
        </form>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</article>
<?php endforeach; ?>
<?php soci_footer(); ?>
