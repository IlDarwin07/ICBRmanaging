<?php
require_once __DIR__ . '/../../includes/auth_soci.php';
require_once __DIR__ . '/../../includes/soci_layout.php';
$socio = require_socio_login();

$partite = partite_per_socio($socio['id_socio']);
$tesserato = socio_tesserato($socio['id_socio']);
$stagione = stagione_corrente();

soci_header('Le partite in sede', $socio);
?>
<div class="sa-hello">
    <span class="sa-kicker">CIAO <?= h(mb_strtoupper($socio['nome'])) ?></span>
    <h1>Le partite in sede</h1>
    <?php if (!$tesserato): ?>
        <p class="sa-note">Non risulti ancora tesserato per la stagione <?= h($stagione['codice_stagione'] ?? 'in corso') ?>: alcune partite sono riservate ai tesserati. Per rinnovare parla con il direttivo.</p>
    <?php endif; ?>
</div>

<?php if (!$partite): ?>
    <section class="sa-card"><h2>Nessuna partita in programma</h2><p>Quando il direttivo apre le prenotazioni, la partita compare qui.</p></section>
<?php endif; ?>

<?php foreach ($partite as $p):
    $aperta = partita_prenotabile($p);
    $mia = in_array($p['mia_stato'], ['presente', 'attesa'], true) ? $p['mia_stato'] : null;
    $liberi = max(0, (int)$p['capienza'] - (int)$p['presenti']);
    $riservata = (int)$p['solo_tesserati'] === 1 && !$tesserato; ?>
<article class="sa-match<?= $mia === 'presente' ? ' is-booked' : '' ?><?= $p['stato'] === 'annullata' ? ' is-cancelled' : '' ?>">
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
            <?php else: ?>
                <p class="sa-status sa-status-ok">✓ Presenza confermata. La sedia te la assegna il direttivo: ricontrolla più avanti.</p>
            <?php endif; ?>
        <?php elseif ($mia === 'attesa'): ?>
            <p class="sa-status sa-status-wait">Sei in lista d'attesa: se si libera un posto passi tra i presenti automaticamente.</p>
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
</article>
<?php endforeach; ?>
<?php soci_footer(); ?>
