<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

$id_socio = (int)($_GET['id'] ?? 0);
if ($id_socio <= 0) {
    http_response_code(404);
    die('Socio non trovato.');
}

$stmt = $pdo->prepare('SELECT * FROM soci WHERE id_socio = :id');
$stmt->execute(['id' => $id_socio]);
$socio = $stmt->fetch();

if (!$socio) {
    http_response_code(404);
    die('Socio non trovato.');
}

// Stagione attiva (per badge tessera rapido)
$stagione_attiva = $pdo->query(
    "SELECT id_stagione, codice_stagione FROM stagioni ORDER BY codice_stagione DESC LIMIT 1"
)->fetch();

// Tesseramento stagione corrente
$tessera_corrente = null;
if ($stagione_attiva) {
    $stx = $pdo->prepare(
        'SELECT t.*, tt.tipo AS tipologia_tipo
         FROM tesseramenti t
         LEFT JOIN tipologie_tesseramento tt ON tt.id_tipologia = t.id_tipologia
         WHERE t.id_socio = :id AND t.id_stagione = :id_stagione
         LIMIT 1'
    );
    $stx->execute(['id' => $id_socio, 'id_stagione' => $stagione_attiva['id_stagione']]);
    $tessera_corrente = $stx->fetch();
}

// Storico tesseramenti completo con pagamenti
$stmt = $pdo->prepare(
    'SELECT t.*,
            s.codice_stagione,
            tt.tipo AS tipologia_tipo,
            COALESCE(SUM(p.importo), 0) AS totale_pagato
     FROM tesseramenti t
     LEFT JOIN stagioni s ON s.id_stagione = t.id_stagione
     LEFT JOIN tipologie_tesseramento tt ON tt.id_tipologia = t.id_tipologia
     LEFT JOIN pagamenti p ON p.id_tesseramento = t.id_tesseramento
     WHERE t.id_socio = :id
     GROUP BY t.id_tesseramento
     ORDER BY s.codice_stagione DESC'
);
$stmt->execute(['id' => $id_socio]);
$tesseramenti = $stmt->fetchAll();

$page_title = 'Scheda socio — ' . ($socio['cognome'] ?? '') . ' ' . ($socio['nome'] ?? '');
require __DIR__ . '/../../includes/layout_header.php';
?>

<style>
.scheda-hero {
    background: linear-gradient(135deg, var(--color-primary, #003f8a) 0%, #005cbf 100%);
    color: #fff;
    border-radius: 10px;
    padding: 1.5rem 2rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}
.scheda-hero-avatar {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: rgba(255,255,255,.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: bold; color: #fff;
    flex-shrink: 0;
}
.scheda-hero-info { flex: 1; min-width: 200px; }
.scheda-hero-info h1 { margin: 0 0 .3rem; font-size: 1.6rem; }
.scheda-hero-meta { font-size: .9rem; opacity: .85; display: flex; gap: 1rem; flex-wrap: wrap; }
.scheda-hero-badge { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
.scheda-hero-actions { display: flex; gap: .5rem; flex-wrap: wrap; align-items: flex-start; }
.scheda-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; }
.scheda-section h2 { font-size: 1rem; font-weight: 700; color: var(--color-primary, #003f8a); margin: 0 0 1rem; padding-bottom: .5rem; border-bottom: 2px solid #f0f0f0; text-transform: uppercase; letter-spacing: .04em; }
.scheda-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: .6rem 1.5rem; }
.scheda-field { display: flex; flex-direction: column; }
.scheda-field .lbl { font-size: .75rem; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; margin-bottom: .15rem; }
.scheda-field .val { font-size: .95rem; color: #111; }
.scheda-field .val a { color: var(--color-primary, #003f8a); text-decoration: none; }
.tessera-box { display: flex; align-items: center; gap: 1rem; padding: .75rem 1rem; background: #f8f9ff; border: 1px solid #d0d7ff; border-radius: 8px; margin-bottom: .75rem; }
.tessera-box .t-num { font-size: 1.4rem; font-weight: 700; color: #003f8a; }
.tessera-box .t-info { font-size: .85rem; color: #555; }
</style>

<!-- HERO -->
<div class="scheda-hero">
    <div class="scheda-hero-avatar">
        <?= mb_strtoupper(mb_substr($socio['nome'] ?? '?', 0, 1)) . mb_strtoupper(mb_substr($socio['cognome'] ?? '', 0, 1)) ?>
    </div>
    <div class="scheda-hero-info">
        <h1><?= h($socio['cognome'] . ' ' . $socio['nome']) ?></h1>
        <div class="scheda-hero-meta">
            <?php if ($socio['data_nascita']): ?>
                <span>&#128197; <?= date('d/m/Y', strtotime($socio['data_nascita'])) ?></span>
            <?php endif; ?>
            <?php if ($socio['comune']): ?>
                <span>&#128205; <?= h($socio['comune']) ?><?= $socio['provincia'] ? ' (' . h($socio['provincia']) . ')' : '' ?></span>
            <?php endif; ?>
            <?php if ($socio['telefono']): ?>
                <span>&#128222; <?= h($socio['telefono']) ?></span>
            <?php endif; ?>
        </div>
        <div class="scheda-hero-badge" style="margin-top:.5rem">
            <span class="badge <?= $socio['attivo_record'] ? 'badge-green' : 'badge-gray' ?>" style="background:rgba(255,255,255,.25);color:#fff;border:1px solid rgba(255,255,255,.4)">
                <?= $socio['attivo_record'] ? '✔ Attivo' : '✖ Disattivato' ?>
            </span>
            <?php if ($tessera_corrente && $tessera_corrente['numero_tessera']): ?>
                <span style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.35);border-radius:4px;padding:.2rem .6rem;font-size:.85rem">
                    Tessera <?= h($stagione_attiva['codice_stagione']) ?>: <strong><?= h($tessera_corrente['numero_tessera']) ?></strong>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="scheda-hero-actions">
        <a class="btn" href="<?= $base ?>/soci/edit.php?id=<?= $id_socio ?>">&#9998; Modifica</a>
        <a class="btn btn-secondary" href="<?= $base ?>/tesseramenti/create.php?id_socio=<?= $id_socio ?>">+ Tesseramento</a>
        <a class="btn btn-secondary" href="<?= $base ?>/soci/list.php" style="opacity:.85">&laquo; Elenco</a>
    </div>
</div>

<!-- DATI ANAGRAFICI -->
<div class="scheda-section">
    <h2>&#128100; Dati anagrafici</h2>
    <div class="scheda-grid">
        <div class="scheda-field"><span class="lbl">Cognome</span><span class="val"><?= h($socio['cognome']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Nome</span><span class="val"><?= h($socio['nome']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Sesso</span><span class="val"><?= h($socio['sesso']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Data di nascita</span><span class="val"><?= $socio['data_nascita'] ? date('d/m/Y', strtotime($socio['data_nascita'])) : '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Comune di nascita</span><span class="val"><?= h($socio['comune_nascita']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Nazionalità</span><span class="val"><?= h($socio['nazionalita']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Codice fiscale</span><span class="val" style="font-family:monospace;letter-spacing:.05em"><?= h($socio['codice_fiscale']) ?: '—' ?></span></div>
    </div>
</div>

<!-- CONTATTI E RESIDENZA -->
<div class="scheda-section">
    <h2>&#127968; Residenza &amp; Contatti</h2>
    <div class="scheda-grid">
        <div class="scheda-field"><span class="lbl">Indirizzo</span><span class="val"><?= h(trim(($socio['indirizzo'] ?? '') . ' ' . ($socio['numero_civico'] ?? ''))) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">CAP</span><span class="val"><?= h($socio['cap']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Comune</span><span class="val"><?= h($socio['comune']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Provincia</span><span class="val"><?= h($socio['provincia']) ?: '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Telefono</span><span class="val"><?= $socio['telefono'] ? '<a href="tel:' . h($socio['telefono']) . '">' . h($socio['telefono']) . '</a>' : '—' ?></span></div>
        <div class="scheda-field"><span class="lbl">Email</span><span class="val"><?= $socio['email'] ? '<a href="mailto:' . h($socio['email']) . '">' . h($socio['email']) . '</a>' : '—' ?></span></div>
    </div>
</div>

<!-- TESSERAMENTO STAGIONE CORRENTE -->
<?php if ($stagione_attiva): ?>
<div class="scheda-section">
    <h2>&#127913; Tesseramento <?= h($stagione_attiva['codice_stagione']) ?></h2>
    <?php if ($tessera_corrente): ?>
        <div class="tessera-box">
            <div>
                <div class="t-num"># <?= h($tessera_corrente['numero_tessera']) ?: '—' ?></div>
                <div class="t-info"><?= h($tessera_corrente['tipologia_tipo'] ?? $tessera_corrente['tipo_portale'] ?? '—') ?></div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem">
                <span class="badge <?= $tessera_corrente['attivo_portale'] ? 'badge-green' : 'badge-gray' ?>">Portale: <?= $tessera_corrente['attivo_portale'] ? 'Attivo' : 'Non attivo' ?></span>
                <span class="badge <?= $tessera_corrente['tessera_fisica'] ? 'badge-green' : 'badge-gray' ?>">Tessera fisica: <?= $tessera_corrente['tessera_fisica'] ? 'Consegnata' : 'Non consegnata' ?></span>
                <span class="badge <?= $tessera_corrente['conferma_anagrafica'] ? 'badge-green' : 'badge-orange' ?>">Anagrafica: <?= $tessera_corrente['conferma_anagrafica'] ? 'Confermata' : 'Da confermare' ?></span>
            </div>
            <?php
                $dovuta_c  = (float)($tessera_corrente['quota_associativa'] ?? 0);
                // recupera pagato stagione corrente
                $stpag = $pdo->prepare('SELECT COALESCE(SUM(importo),0) AS tot FROM pagamenti WHERE id_tesseramento = :id');
                $stpag->execute(['id' => $tessera_corrente['id_tesseramento']]);
                $pagato_c  = (float)$stpag->fetchColumn();
                $residuo_c = $dovuta_c - $pagato_c;
                if ($dovuta_c <= 0)       { $sq = ['—',           'badge-gray']; }
                elseif ($residuo_c <= 0)  { $sq = ['Saldata',     'badge-green']; }
                elseif ($pagato_c > 0)    { $sq = ['Acconto',     'badge-orange']; }
                else                      { $sq = ['Da pagare',   'badge-red']; }
            ?>
            <div style="margin-left:auto;text-align:right">
                <?php if ($dovuta_c > 0): ?>
                    <div style="font-size:.8rem;color:#555">Quota: <strong><?= number_format($dovuta_c, 2, ',', '.') ?> €</strong> &nbsp;|&nbsp; Pagato: <strong><?= number_format($pagato_c, 2, ',', '.') ?> €</strong></div>
                    <div style="margin-top:.3rem"><span class="badge <?= $sq[1] ?>"><?= $sq[0] ?></span></div>
                <?php endif; ?>
                <a class="btn btn-sm btn-secondary" style="margin-top:.5rem"
                   href="<?= $base ?>/tesseramenti/view.php?id=<?= (int)$tessera_corrente['id_tesseramento'] ?>">Dettaglio</a>
            </div>
        </div>
    <?php else: ?>
        <p class="note" style="margin:0">Nessun tesseramento per la stagione corrente. <a href="<?= $base ?>/tesseramenti/create.php?id_socio=<?= $id_socio ?>">+ Crea tesseramento</a></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- STORICO TESSERAMENTI -->
<div class="scheda-section">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
        <h2 style="margin:0;border:none;padding:0">&#128203; Storico tesseramenti</h2>
        <a class="btn btn-sm" href="<?= $base ?>/tesseramenti/create.php?id_socio=<?= $id_socio ?>">+ Nuovo</a>
    </div>

    <?php if (empty($tesseramenti)): ?>
        <p class="note">Nessun tesseramento registrato per questo socio.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
            <tr>
                <th>Stagione</th>
                <th>Tipologia</th>
                <th>N. Tessera</th>
                <th style="text-align:center">Tessera fisica</th>
                <th style="text-align:center">Conf. anagrafica</th>
                <th style="text-align:right">Quota</th>
                <th style="text-align:right">Pagato</th>
                <th style="text-align:right">Residuo</th>
                <th style="text-align:center">Stato quota</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tesseramenti as $t): ?>
                <?php
                    $dovuta   = (float)($t['quota_associativa'] ?? 0);
                    $pagato   = (float)$t['totale_pagato'];
                    $residuo  = $dovuta - $pagato;
                    if ($dovuta <= 0)      { $stato_q = ['—',         'badge-gray']; }
                    elseif ($residuo <= 0) { $stato_q = ['Saldata',   'badge-green']; }
                    elseif ($pagato > 0)   { $stato_q = ['Acconto',   'badge-orange']; }
                    else                   { $stato_q = ['Da pagare', 'badge-red']; }
                ?>
                <tr>
                    <td><?= h($t['codice_stagione']) ?></td>
                    <td><?= h($t['tipologia_tipo'] ?? $t['tipo_portale'] ?? '—') ?></td>
                    <td><?= h($t['numero_tessera']) ?: '—' ?></td>
                    <td style="text-align:center"><span class="badge <?= $t['tessera_fisica'] ? 'badge-green' : 'badge-gray' ?>"><?= $t['tessera_fisica'] ? 'Sì' : 'No' ?></span></td>
                    <td style="text-align:center"><span class="badge <?= $t['conferma_anagrafica'] ? 'badge-green' : 'badge-gray' ?>"><?= $t['conferma_anagrafica'] ? 'Sì' : 'No' ?></span></td>
                    <td style="text-align:right"><?= $dovuta > 0 ? number_format($dovuta, 2, ',', '.') . ' €' : '—' ?></td>
                    <td style="text-align:right"><?= $pagato > 0 ? number_format($pagato, 2, ',', '.') . ' €' : '—' ?></td>
                    <td style="text-align:right"><?= $dovuta > 0 ? number_format($residuo, 2, ',', '.') . ' €' : '—' ?></td>
                    <td style="text-align:center"><span class="badge <?= $stato_q[1] ?>"><?= $stato_q[0] ?></span></td>
                    <td style="white-space:nowrap">
                        <a class="btn btn-sm btn-secondary"
                           href="<?= $base ?>/tesseramenti/view.php?id=<?= (int)$t['id_tesseramento'] ?>">Dettaglio</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
