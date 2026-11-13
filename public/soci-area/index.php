<?php
/**
 * Profilo del socio: in un'unica pagina le sue prenotazioni (e quelle dei familiari),
 * le partite in sede da prenotare e la gestione dell'account.
 */
require_once __DIR__ . '/../../includes/auth_soci.php';
require_once __DIR__ . '/../../includes/soci_layout.php';
require_once __DIR__ . '/../../includes/famiglia.php';
require_once __DIR__ . '/../../includes/socio_anagrafica.php';

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
$codice_stagione = $stagione['codice_stagione'] ?? 'in corso';

$anagrafica = socio_anagrafica((int)$socio['id_socio']);
$ultima_tessera = socio_ultima_tessera((int)$socio['id_socio']);

$familiari = famiglia_familiari($socio['id_socio']);
$ids_soci = array_merge([(int)$socio['id_socio']], array_map(fn($f) => (int)$f['id_socio'], $familiari));
$extra = famiglia_prenotazioni($ids_soci, array_map(fn($p) => (int)$p['id_partita'], $partite));
$sostenitori = soci_sostenitori($ids_soci);
$quota_testo = number_format(PARTITA_QUOTA_SOSTENITORE, 2, ',', '.');
$tess_fam = [];
foreach ($familiari as $f) {
    $tess_fam[(int)$f['id_socio']] = socio_tesserato((int)$f['id_socio']);
}

// Riepilogo "Le mie prenotazioni": per ogni partita, chi di casa ha un posto o è in attesa.
$mie = [];
foreach ($partite as $p) {
    $pid = (int)$p['id_partita'];
    $persone = [];
    if (in_array($p['mia_stato'], ['presente', 'attesa'], true)) {
        $persone[] = ['nome' => 'Tu', 'stato' => $p['mia_stato'], 'posto' => $p['mio_posto']];
    }
    foreach ($familiari as $f) {
        $fx = $extra[$pid][(int)$f['id_socio']] ?? null;
        if ($fx && in_array($fx['stato'], ['presente', 'attesa'], true)) {
            $persone[] = ['nome' => $f['nome'], 'stato' => $fx['stato'], 'posto' => $fx['numero_posto']];
        }
    }
    if ($persone) $mie[] = ['partita' => $p, 'persone' => $persone];
}
$da_prenotare = count(array_filter($partite, fn($p) => partita_prenotabile($p) && $p['mia_stato'] === null));
$ts_mese = fn(string $d) => ['gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'][(int)date('n', strtotime($d)) - 1];

soci_header('Il mio profilo', $socio);
?>
<section class="sa-hello" aria-labelledby="sa-hello-title">
    <div>
        <span class="sa-kicker">IL MIO PROFILO</span>
        <h1 id="sa-hello-title">Ciao <?= h($socio['nome']) ?>.</h1>
        <p class="sa-lead"><?php if ($mie): ?>Hai <?= count($mie) === 1 ? 'una partita prenotata' : count($mie) . ' partite prenotate' ?>. Ci vediamo in sede!<?php elseif ($da_prenotare): ?>Ci sono <?= $da_prenotare === 1 ? 'una partita aperta' : $da_prenotare . ' partite aperte' ?> alle prenotazioni: scegli dove esserci.<?php else: ?>Per ora non ci sono partite da prenotare. Appena il direttivo apre la prossima, la trovi qui.<?php endif; ?></p>
    </div>
    <ul class="sa-facts">
        <li class="<?= $tesserato ? 'is-ok' : 'is-warn' ?>"><small>TESSERA <?= h($codice_stagione) ?></small><strong><?= $tesserato ? 'Attiva' : 'Da rinnovare' ?></strong></li>
        <li><small>PRENOTAZIONI</small><strong><?= count($mie) ?></strong></li>
        <?php if ($familiari): ?><li><small>FAMILIARI</small><strong><?= count($familiari) ?></strong></li><?php endif; ?>
    </ul>
    <?php if (!$tesserato): ?>
        <p class="sa-note">Per la stagione <?= h($codice_stagione) ?> non risulti ancora tesserato: alcune partite sono riservate ai tesserati. Per rinnovare <a href="<?= u('/index.php') ?>#contatti">scrivi al direttivo</a> o passa in sede.</p>
    <?php endif; ?>
</section>

<section class="sa-section" id="prenotazioni" aria-labelledby="sa-pren-title">
    <h2 id="sa-pren-title">Le mie prenotazioni</h2>
    <?php if (!$mie): ?>
        <div class="sa-empty">
            <p>Nessuna prenotazione in corso.</p>
            <?php if ($da_prenotare): ?><a class="sa-btn" href="#partite">Prenota una partita</a><?php endif; ?>
        </div>
    <?php else: ?>
    <ul class="sa-bookings">
        <?php foreach ($mie as $m): $p = $m['partita']; $pid = (int)$p['id_partita']; ?>
        <li class="sa-booking<?= $p['stato'] === 'annullata' ? ' is-cancelled' : '' ?>">
            <time class="sa-date" datetime="<?= h(date('c', strtotime($p['data_ora']))) ?>"><b><?= h(date('d', strtotime($p['data_ora']))) ?></b><span><?= h($ts_mese($p['data_ora'])) ?></span><small><?= h(date('H:i', strtotime($p['data_ora']))) ?></small></time>
            <div class="sa-booking-body">
                <strong><?= h($p['titolo']) ?></strong>
                <span class="sa-small"><?= h($p['sala_nome']) ?><?= $p['competizione'] ? ' · ' . h($p['competizione']) : '' ?></span>
                <?php if ($p['stato'] === 'annullata'): ?>
                    <span class="sa-chip sa-chip-off">Annullata dal direttivo</span>
                <?php else: ?>
                <ul class="sa-people">
                    <?php foreach ($m['persone'] as $pers):
                        $sn = $pers['posto'] !== null ? partita_nome_sala_posto($p['settori'], (int)$pers['posto']) : ''; ?>
                    <li>
                        <span><?= h($pers['nome']) ?></span>
                        <?php if ($pers['stato'] === 'attesa'): ?>
                            <span class="sa-chip sa-chip-wait">In lista d’attesa</span>
                        <?php elseif ($pers['posto'] !== null): ?>
                            <span class="sa-chip sa-chip-seat">Sedia <?= (int)$pers['posto'] ?><?= $sn !== '' ? ' · ' . h($sn) : '' ?></span>
                        <?php else: ?>
                            <span class="sa-chip sa-chip-ok">Confermato · sedia in arrivo</span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <a class="sa-link" href="#partita-<?= $pid ?>">Gestisci</a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<section class="sa-section" id="partite" aria-labelledby="sa-partite-title">
    <h2 id="sa-partite-title">Partite in sede</h2>
    <p class="sa-small">Guardarle in sede è gratis per i soci; i soci sostenitori contribuiscono con € <?= h($quota_testo) ?> a partita. Le sedie le assegna il direttivo.</p>

<?php if (!$partite): ?>
    <div class="sa-empty"><p>Nessuna partita in programma. Quando il direttivo apre le prenotazioni, la trovi qui (e di solito arriva anche un messaggio su WhatsApp).</p></div>
<?php endif; ?>

<?php foreach ($partite as $p):
    $aperta = partita_prenotabile($p);
    $mia = in_array($p['mia_stato'], ['presente', 'attesa'], true) ? $p['mia_stato'] : null;
    $liberi = max(0, (int)$p['capienza'] - (int)$p['presenti']);
    $riservata = (int)$p['solo_tesserati'] === 1 && !$tesserato;
    $mio_extra = $extra[(int)$p['id_partita']][(int)$socio['id_socio']] ?? null; ?>
<article id="partita-<?= (int)$p['id_partita'] ?>" class="sa-match<?= $mia === 'presente' ? ' is-booked' : '' ?><?= $p['stato'] === 'annullata' ? ' is-cancelled' : '' ?><?= $partita_link === (int)$p['id_partita'] ? ' is-target' : '' ?>">
    <div class="sa-match-head">
        <?php if ($p['competizione']): ?><span class="sa-comp"><?= h($p['competizione']) ?></span><?php endif; ?>
        <h3><?= h($p['titolo']) ?></h3>
        <p class="sa-when"><?= h(partita_data_label($p['data_ora'])) ?> · <?= h($p['sala_nome']) ?></p>
        <?php if ($p['note']): ?><p class="sa-small"><?= nl2br(h($p['note'])) ?></p><?php endif; ?>
    </div>

    <div class="sa-match-status">
        <?php if ($p['stato'] === 'annullata'): ?>
            <p class="sa-status sa-status-off">Partita annullata dal direttivo.</p>
        <?php elseif ($mia === 'presente'): ?>
            <?php if ($p['mio_posto'] !== null): ?>
                <div class="sa-seat" aria-label="La tua sedia"><small>LA TUA SEDIA</small><b><?= (int)$p['mio_posto'] ?></b><?php $sn_mio = partita_nome_sala_posto($p['settori'], (int)$p['mio_posto']); if ($sn_mio !== ''): ?><small><?= h($sn_mio) ?></small><?php endif; ?></div>
            <?php else: ?>
                <p class="sa-status sa-status-ok">✓ Ci sei. La sedia te la assegna il direttivo prima della partita.</p>
            <?php endif; ?>
            <?php if (!empty($mio_extra['da_nome'])): ?><p class="sa-small">Prenotato per te da <?= h($mio_extra['da_nome']) ?>.</p><?php endif; ?>
            <?php if (!empty($sostenitori[(int)$socio['id_socio']])): ?><p class="sa-small">Da socio sostenitore, per questa partita porta € <?= h($quota_testo) ?>.</p><?php endif; ?>
        <?php elseif ($mia === 'attesa'): ?>
            <p class="sa-status sa-status-wait">Sei in lista d'attesa: se si libera un posto passi tra i presenti in automatico e ti avvisiamo.</p>
            <?php if (!empty($mio_extra['da_nome'])): ?><p class="sa-small">Prenotato per te da <?= h($mio_extra['da_nome']) ?>.</p><?php endif; ?>
        <?php elseif ($aperta): ?>
            <p class="sa-status"><?= $liberi > 0 ? $liberi . ($liberi === 1 ? ' posto libero' : ' posti liberi') : 'Posti finiti: puoi metterti in lista d\'attesa' ?> · prenoti fino a <?= h(date('d/m H:i', strtotime($p['prenotazioni_chiudono']))) ?></p>
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
        <p class="sa-small">Le prenotazioni sono chiuse: per annullare avvisa il direttivo.</p>
    <?php endif; ?>

    <?php if ($familiari && $p['stato'] !== 'annullata'): ?>
    <div class="sa-match-family">
        <p class="sa-small"><strong>I tuoi familiari</strong></p>
        <?php foreach ($familiari as $f):
            $fid = (int)$f['id_socio'];
            $fx = $extra[(int)$p['id_partita']][$fid] ?? null;
            $fstato = $fx['stato'] ?? null; ?>
        <form method="post" action="<?= u('/soci-area/prenota.php') ?>" class="sa-family-row">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="id_partita" value="<?= (int)$p['id_partita'] ?>">
            <input type="hidden" name="id_socio_per" value="<?= $fid ?>">
            <span class="sa-family-name"><?= h($f['nome'] . ' ' . $f['cognome']) ?><?php if (!empty($sostenitori[$fid])): ?> <small>(sostenitore, € <?= h($quota_testo) ?>)</small><?php endif; ?></span>
            <?php if ($fstato === 'presente'): ?>
                <?php $sn_f = $fx['numero_posto'] !== null ? partita_nome_sala_posto($p['settori'], (int)$fx['numero_posto']) : ''; ?>
                <span class="sa-status sa-status-ok">✓ Prenotato<?= $fx['numero_posto'] !== null ? ' · sedia ' . (int)$fx['numero_posto'] . ($sn_f !== '' ? ' (' . h($sn_f) . ')' : '') : '' ?></span>
                <?php if ($aperta): ?><button type="submit" name="azione" value="annulla" class="sa-btn sa-btn-ghost sa-btn-sm">Annulla</button><?php endif; ?>
            <?php elseif ($fstato === 'attesa'): ?>
                <span class="sa-status sa-status-wait">In lista d'attesa</span>
                <?php if ($aperta): ?><button type="submit" name="azione" value="annulla" class="sa-btn sa-btn-ghost sa-btn-sm">Annulla</button><?php endif; ?>
            <?php elseif ($aperta && ((int)$p['solo_tesserati'] !== 1 || !empty($tess_fam[$fid]))): ?>
                <button type="submit" name="azione" value="conferma" class="sa-btn sa-btn-sm">Prenota</button>
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
</section>

<section class="sa-section" id="account" aria-labelledby="sa-account-title">
    <h2 id="sa-account-title">Account</h2>
    <div class="sa-account">
        <div class="sa-card">
            <h3>I tuoi dati</h3>
            <?php render_socio_anagrafica($anagrafica ?: ['nome' => $socio['nome'], 'cognome' => $socio['cognome'], 'email' => $socio['email']], $ultima_tessera); ?>
            <dl class="sa-dl">
                <div><dt>Email di accesso</dt><dd><?= h($socio['email']) ?></dd></div>
                <div><dt>Tessera <?= h($codice_stagione) ?></dt><dd><?= $tesserato ? 'Registrata' : 'Non ancora registrata' ?></dd></div>
                <?php if ($familiari): ?><div><dt>Familiari collegati</dt><dd><?= h(implode(', ', array_map(fn($f) => $f['nome'] . ' ' . $f['cognome'], $familiari))) ?></dd></div><?php endif; ?>
            </dl>
            <p class="sa-small">Qualcosa non torna, o vuoi cambiare email? Dillo al direttivo e lo sistemiamo noi.</p>
        </div>
        <?php render_invito_interista($ultima_tessera); ?>
        <div class="sa-card">
            <h3>Cambia password</h3>
            <form method="post" class="sa-form" action="<?= u('/soci-area/profilo.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="email" name="username" value="<?= h($socio['email']) ?>" autocomplete="username" hidden>
                <label for="password_attuale">Password attuale</label>
                <input type="password" id="password_attuale" name="password_attuale" autocomplete="current-password" required>
                <label for="password">Nuova password (almeno <?= SOCI_PASSWORD_MIN ?> caratteri)</label>
                <input type="password" id="password" name="password" autocomplete="new-password" minlength="<?= SOCI_PASSWORD_MIN ?>" required>
                <label for="password_conferma">Ripeti la nuova password</label>
                <input type="password" id="password_conferma" name="password_conferma" autocomplete="new-password" minlength="<?= SOCI_PASSWORD_MIN ?>" required>
                <button type="submit" class="sa-btn">Aggiorna password</button>
            </form>
        </div>
    </div>
</section>
<?php soci_footer(); ?>
