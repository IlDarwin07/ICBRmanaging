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
<div class="page-header">
    <div>
        <a href="<?= $base ?>/soci/list.php" style="font-size:.9em;color:var(--color-muted)">&laquo; Torna all'elenco</a>
        <h1 style="margin-top:.25rem">
            <?= h($socio['cognome'] . ' ' . $socio['nome']) ?>
            <?php if (!$socio['attivo_record']): ?>
                <span class="badge badge-gray" style="font-size:.5em;vertical-align:middle">Disattivato</span>
            <?php endif; ?>
        </h1>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a class="btn" href="<?= $base ?>/soci/edit.php?id=<?= $id_socio ?>">&#9998; Modifica anagrafica</a>
        <a class="btn btn-secondary" href="<?= $base ?>/tesseramenti/create.php?id_socio=<?= $id_socio ?>">+ Tesseramento</a>
    </div>
</div>

<!-- ANAGRAFICA -->
<section class="detail-card">
    <h2>Anagrafica</h2>
    <div class="detail-grid">
        <div><span class="detail-label">Cognome</span><span><?= h($socio['cognome']) ?: '—' ?></span></div>
        <div><span class="detail-label">Nome</span><span><?= h($socio['nome']) ?: '—' ?></span></div>
        <div><span class="detail-label">Sesso</span><span><?= h($socio['sesso']) ?: '—' ?></span></div>
        <div><span class="detail-label">Data di nascita</span>
            <span><?= $socio['data_nascita'] ? date('d/m/Y', strtotime($socio['data_nascita'])) : '—' ?></span></div>
        <div><span class="detail-label">Comune di nascita</span><span><?= h($socio['comune_nascita']) ?: '—' ?></span></div>
        <div><span class="detail-label">Codice fiscale</span><span><?= h($socio['codice_fiscale']) ?: '—' ?></span></div>
        <div><span class="detail-label">Nazionalità</span><span><?= h($socio['nazionalita']) ?: '—' ?></span></div>
        <div><span class="detail-label">Indirizzo</span>
            <span><?= h(trim(($socio['indirizzo'] ?? '') . ' ' . ($socio['numero_civico'] ?? ''))) ?: '—' ?></span></div>
        <div><span class="detail-label">CAP / Comune / Prov.</span>
            <span><?= h(trim(($socio['cap'] ?? '') . ' ' . ($socio['comune'] ?? '') . ' (' . ($socio['provincia'] ?? '') . ')')) ?: '—' ?></span></div>
        <div><span class="detail-label">Telefono</span>
            <span><?= $socio['telefono'] ? '<a href="tel:' . h($socio['telefono']) . '">' . h($socio['telefono']) . '</a>' : '—' ?></span></div>
        <div><span class="detail-label">Email</span>
            <span><?= $socio['email'] ? '<a href="mailto:' . h($socio['email']) . '">' . h($socio['email']) . '</a>' : '—' ?></span></div>
        <div><span class="detail-label">Stato record</span>
            <span class="badge <?= $socio['attivo_record'] ? 'badge-green' : 'badge-gray' ?>">
                <?= $socio['attivo_record'] ? 'Attivo' : 'Disattivato' ?>
            </span>
        </div>
    </div>
</section>

<!-- STORICO TESSERAMENTI -->
<section class="detail-card" style="margin-top:1.5rem">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem">
        <h2 style="margin:0">Storico tesseramenti</h2>
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
                <th>Attivo portale</th>
                <th>Tessera fisica</th>
                <th>Conf. anagrafica</th>
                <th>Quota dovuta</th>
                <th>Totale pagato</th>
                <th>Residuo</th>
                <th>Stato quota</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tesseramenti as $t): ?>
                <?php
                    $dovuta   = (float)($t['quota_associativa'] ?? 0);
                    $pagato   = (float)$t['totale_pagato'];
                    $residuo  = $dovuta - $pagato;
                    if ($dovuta <= 0)      { $stato_q = ['—', 'badge-gray']; }
                    elseif ($residuo <= 0) { $stato_q = ['Saldata', 'badge-green']; }
                    elseif ($pagato > 0)   { $stato_q = ['Acconto', 'badge-orange']; }
                    else                   { $stato_q = ['Da pagare', 'badge-red']; }
                ?>
                <tr>
                    <td><?= h($t['codice_stagione']) ?></td>
                    <td><?= h($t['tipologia_tipo'] ?? $t['tipo_portale'] ?? '—') ?></td>
                    <td><?= h($t['numero_tessera']) ?: '—' ?></td>
                    <td><span class="badge <?= $t['attivo_portale'] ? 'badge-green' : 'badge-gray' ?>"><?= $t['attivo_portale'] ? 'Sì' : 'No' ?></span></td>
                    <td><span class="badge <?= $t['tessera_fisica'] ? 'badge-green' : 'badge-gray' ?>"><?= $t['tessera_fisica'] ? 'Sì' : 'No' ?></span></td>
                    <td><span class="badge <?= $t['conferma_anagrafica'] ? 'badge-green' : 'badge-gray' ?>"><?= $t['conferma_anagrafica'] ? 'Sì' : 'No' ?></span></td>
                    <td><?= $dovuta > 0 ? number_format($dovuta, 2, ',', '.') . ' €' : '—' ?></td>
                    <td><?= $pagato > 0 ? number_format($pagato, 2, ',', '.') . ' €' : '—' ?></td>
                    <td><?= $dovuta > 0 ? number_format($residuo, 2, ',', '.') . ' €' : '—' ?></td>
                    <td><span class="badge <?= $stato_q[1] ?>"><?= $stato_q[0] ?></span></td>
                    <td style="white-space:nowrap">
                        <a class="btn btn-sm btn-secondary"
                           href="<?= $base ?>/tesseramenti/view.php?id=<?= (int)$t['id_tesseramento'] ?>">Dettaglio</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
