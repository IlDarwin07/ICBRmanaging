<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$id  = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT t.*,
            s.nome, s.cognome, s.telefono, s.email, s.codice_fiscale,
            st.codice_stagione, st.descrizione AS desc_stagione,
            tip.tipo AS tipo_tip, tip.listino_label, tip.quota_standard
     FROM tesseramenti t
     JOIN soci s ON s.id_socio = t.id_socio
     JOIN stagioni st ON st.id_stagione = t.id_stagione
     LEFT JOIN tipologie_tesseramento tip ON tip.id_tipologia = t.id_tipologia
     WHERE t.id_tesseramento = :id'
);
$stmt->execute(['id' => $id]);
$t = $stmt->fetch();

if (!$t) {
    redirect_with_message('/tesseramenti/list.php', 'Tesseramento non trovato.', 'error');
}

// Pagamenti associati
$stmt_p = $pdo->prepare(
    'SELECT p.*, ua.nome AS op_nome, ua.cognome AS op_cognome
     FROM pagamenti_tesseramenti p
     LEFT JOIN utenti_admin ua ON ua.id_utente = p.registrato_da
     WHERE p.id_tesseramento = :id
     ORDER BY p.data_pagamento DESC'
);
$stmt_p->execute(['id' => $id]);
$pagamenti = $stmt_p->fetchAll();

// Totale pagato
$stmt_tot = $pdo->prepare('SELECT COALESCE(SUM(importo),0) FROM pagamenti_tesseramenti WHERE id_tesseramento = :id');
$stmt_tot->execute(['id' => $id]);
$totale_pagato = (float)$stmt_tot->fetchColumn();

$quota_std = (float)($t['quota_standard'] ?? 0);
$residuo   = $quota_std > 0 ? max(0, $quota_std - $totale_pagato) : null;
$perc_pagato = $quota_std > 0 ? min(100, round($totale_pagato / $quota_std * 100)) : null;

// Label tessera fisica
$tf_labels = [
    'non_richiesta'  => ['label' => 'Non richiesta',  'badge' => 'badge-gray'],
    'non_consegnata' => ['label' => 'Non consegnata', 'badge' => 'badge-orange'],
    'consegnata'     => ['label' => 'Consegnata',     'badge' => 'badge-green'],
];
$tf = $tf_labels[$t['tessera_fisica']] ?? $tf_labels['non_richiesta'];

$page_title = $t['cognome'] . ' ' . $t['nome'] . ' — Tesseramento';
require __DIR__ . '/../../includes/layout_header.php';
?>

<style>
/* ---- VIEW TESSERAMENTO - stili scoped ---- */
.view-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}
.view-header-left h1 {
    font-size: 1.4rem;
    font-weight: 700;
    color: #111;
    margin: 0 0 .15rem;
}
.view-header-left .breadcrumb {
    font-size: .78rem;
    color: #888;
}
.view-header-left .breadcrumb a { color: #004e9a; }
.view-header-actions { display: flex; gap: .5rem; flex-wrap: wrap; }

/* Layout principale: sidebar destra per pagamenti */
.view-layout {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 1.25rem;
    align-items: start;
}
@media (max-width: 900px) {
    .view-layout { grid-template-columns: 1fr; }
}

/* Pannelli */
.view-panel {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
    overflow: hidden;
}
.view-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .75rem 1.1rem;
    border-bottom: 1px solid #f0f0f0;
    background: #fafafa;
}
.view-panel-header h2 {
    font-size: .8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #555;
    margin: 0;
}
.view-panel-body { padding: 1.1rem; }

/* Grid campi: 2 colonne */
.field-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .6rem 1.5rem;
}
@media (max-width: 600px) { .field-grid { grid-template-columns: 1fr; } }
.field-grid.cols-1 { grid-template-columns: 1fr; }

.field-item { display: flex; flex-direction: column; gap: .1rem; padding-bottom: .55rem; border-bottom: 1px solid #f5f5f5; }
.field-item:last-child, .field-item.no-border { border-bottom: none; }
.field-label {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #999;
}
.field-value {
    font-size: .88rem;
    color: #1a1a1a;
    font-weight: 500;
}
.field-value.mono { font-variant-numeric: tabular-nums; letter-spacing: .02em; }
.field-value a { color: #004e9a; }

/* Sezione separatore */
.section-sep {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #003580;
    padding: .5rem 0 .25rem;
    margin: .5rem 0 .1rem;
    border-bottom: 2px solid #003580;
    grid-column: 1 / -1;
}

/* Progress pagamento */
.pay-summary {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: .5rem;
    margin-bottom: 1rem;
}
.pay-kpi { background: #f7f8fb; border-radius: 7px; padding: .6rem .75rem; }
.pay-kpi-label { font-size: .67rem; text-transform: uppercase; letter-spacing: .05em; color: #888; font-weight: 600; }
.pay-kpi-value { font-size: 1.05rem; font-weight: 700; color: #003580; font-variant-numeric: tabular-nums; }
.pay-kpi-value.paid  { color: #155724; }
.pay-kpi-value.due   { color: #721c24; }

.progress-wrap { margin-bottom: 1rem; }
.progress-bar-bg {
    height: 7px;
    background: #e9ecef;
    border-radius: 99px;
    overflow: hidden;
    margin-top: .35rem;
}
.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #003580, #0057c8);
    border-radius: 99px;
    transition: width .4s ease;
}
.progress-label { display: flex; justify-content: space-between; font-size: .72rem; color: #888; margin-top: .25rem; }

/* Tabella pagamenti compatta */
.pay-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .8rem;
    font-family: inherit;
}
.pay-table th {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #888;
    text-align: left;
    padding: .3rem .4rem;
    border-bottom: 2px solid #f0f0f0;
}
.pay-table td {
    padding: .4rem .4rem;
    border-bottom: 1px solid #f5f5f5;
    vertical-align: middle;
    color: #1a1a1a;
}
.pay-table tr:last-child td { border-bottom: none; }
.pay-table tbody tr:hover { background: #fafbff; }
.pay-table .amount { font-variant-numeric: tabular-nums; font-weight: 600; }

/* Badge saldi */
.badge-ok   { background: #d4edda; color: #155724; }
.badge-plus { background: #cce5ff; color: #004085; }
</style>

<div class="view-header">
    <div class="view-header-left">
        <div class="breadcrumb"><a href="/tesseramenti/list.php">Tesseramenti</a> &rsaquo; Scheda</div>
        <h1><?= h($t['cognome'] . ' ' . $t['nome']) ?></h1>
    </div>
    <div class="view-header-actions">
        <a class="btn btn-secondary" href="/tesseramenti/list.php">← Lista</a>
        <a class="btn" href="/tesseramenti/edit.php?id=<?= $id ?>">&#9998; Modifica</a>
        <a class="btn" href="/tesseramenti/pagamento_add.php?id_tesseramento=<?= $id ?>">+ Pagamento</a>
    </div>
</div>

<div class="view-layout">

    <!-- COLONNA SINISTRA: dati socio + dati tesseramento -->
    <div style="display:flex; flex-direction:column; gap:1.25rem;">

        <!-- SOCIO -->
        <div class="view-panel">
            <div class="view-panel-header">
                <h2>Socio</h2>
                <a class="btn btn-sm btn-secondary" href="/soci/view.php?id=<?= (int)$t['id_socio'] ?>">Apri scheda →</a>
            </div>
            <div class="view-panel-body">
                <div class="field-grid">
                    <div class="field-item">
                        <span class="field-label">Nome completo</span>
                        <span class="field-value"><?= h($t['nome'] . ' ' . $t['cognome']) ?></span>
                    </div>
                    <div class="field-item">
                        <span class="field-label">Codice fiscale</span>
                        <span class="field-value mono"><?= h($t['codice_fiscale']) ?: '<span style="color:#bbb">—</span>' ?></span>
                    </div>
                    <div class="field-item">
                        <span class="field-label">Telefono</span>
                        <span class="field-value"><?php
                            $tel = h($t['telefono']);
                            echo $tel ? "<a href='tel:{$tel}'>{$tel}</a>" : '<span style="color:#bbb">—</span>';
                        ?></span>
                    </div>
                    <div class="field-item no-border">
                        <span class="field-label">Email</span>
                        <span class="field-value"><?php
                            $mail = h($t['email']);
                            echo $mail ? "<a href='mailto:{$mail}'>{$mail}</a>" : '<span style="color:#bbb">—</span>';
                        ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- TESSERAMENTO -->
        <div class="view-panel">
            <div class="view-panel-header">
                <h2>Dati tesseramento</h2>
                <span class="badge <?= $t['attivo_portale'] ? 'badge-ok' : 'badge-gray' ?>">
                    <?= $t['attivo_portale'] ? 'Portale attivo' : 'Portale non attivo' ?>
                </span>
            </div>
            <div class="view-panel-body">
                <div class="field-grid">

                    <span class="section-sep">Identificazione</span>

                    <div class="field-item">
                        <span class="field-label">Stagione</span>
                        <span class="field-value"><?= h($t['codice_stagione']) ?>
                            <?php if ($t['desc_stagione']): ?>
                                <span style="color:#888;font-weight:400;">— <?= h($t['desc_stagione']) ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="field-item">
                        <span class="field-label">N° tessera</span>
                        <span class="field-value mono"><?= h($t['numero_tessera']) ?: '<span style="color:#bbb">—</span>' ?></span>
                    </div>
                    <div class="field-item">
                        <span class="field-label">Tipologia</span>
                        <span class="field-value"><?= h($t['listino_label'] ?: $t['tipo_tip']) ?: '<span style="color:#bbb">—</span>' ?></span>
                    </div>
                    <div class="field-item">
                        <span class="field-label">Listino originale</span>
                        <span class="field-value"><?= h($t['listino_originale']) ?: '<span style="color:#bbb">—</span>' ?></span>
                    </div>

                    <span class="section-sep">Stato</span>

                    <div class="field-item">
                        <span class="field-label">Tessera fisica</span>
                        <span class="field-value">
                            <span class="badge <?= $tf['badge'] ?>"><?= $tf['label'] ?></span>
                        </span>
                    </div>
                    <div class="field-item">
                        <span class="field-label">Anagrafica confermata</span>
                        <span class="field-value">
                            <?= $t['conferma_anagrafica']
                                ? '<span class="badge badge-ok">Sì</span>'
                                : '<span class="badge badge-gray">No</span>' ?>
                        </span>
                    </div>
                    <div class="field-item">
                        <span class="field-label">Socio Plus</span>
                        <span class="field-value">
                            <?= $t['socio_plus']
                                ? '<span class="badge badge-plus">Sì</span>'
                                : '<span style="color:#bbb">No</span>' ?>
                        </span>
                    </div>
                    <div class="field-item">
                        <span class="field-label">Attivo scorsa stagione</span>
                        <span class="field-value"><?= $t['attivo_scorsa_stagione'] ? 'Sì' : 'No' ?></span>
                    </div>

                    <span class="section-sep">Portale &amp; Fonte</span>

                    <div class="field-item">
                        <span class="field-label">Ruolo portale</span>
                        <span class="field-value"><?= h($t['ruolo_portale']) ?: '<span style="color:#bbb">—</span>' ?></span>
                    </div>
                    <div class="field-item">
                        <span class="field-label">Tipo portale</span>
                        <span class="field-value"><?= h($t['tipo_portale']) ?: '<span style="color:#bbb">—</span>' ?></span>
                    </div>
                    <div class="field-item">
                        <span class="field-label">Data/ora attivazione</span>
                        <span class="field-value mono"><?= $t['data_ora_attivazione']
                            ? date('d/m/Y H:i', strtotime($t['data_ora_attivazione']))
                            : '<span style="color:#bbb">—</span>' ?></span>
                    </div>
                    <div class="field-item no-border">
                        <span class="field-label">Fonte inserimento</span>
                        <span class="field-value"><?= h($t['fonte_inserimento']) ?></span>
                    </div>

                </div><!-- /field-grid -->
            </div>
        </div>

    </div><!-- /colonna sinistra -->

    <!-- COLONNA DESTRA: pagamenti -->
    <div class="view-panel" style="position:sticky;top:70px;">
        <div class="view-panel-header">
            <h2>Pagamenti</h2>
            <a class="btn btn-sm" href="/tesseramenti/pagamento_add.php?id_tesseramento=<?= $id ?>">+ Aggiungi</a>
        </div>
        <div class="view-panel-body">

            <!-- KPI quota/pagato/residuo -->
            <div class="pay-summary">
                <?php if ($quota_std > 0): ?>
                <div class="pay-kpi">
                    <div class="pay-kpi-label">Quota</div>
                    <div class="pay-kpi-value">€&nbsp;<?= number_format($quota_std, 2, ',', '.') ?></div>
                </div>
                <?php endif; ?>
                <div class="pay-kpi">
                    <div class="pay-kpi-label">Pagato</div>
                    <div class="pay-kpi-value paid">€&nbsp;<?= number_format($totale_pagato, 2, ',', '.') ?></div>
                </div>
                <?php if ($residuo !== null): ?>
                <div class="pay-kpi">
                    <div class="pay-kpi-label">Residuo</div>
                    <div class="pay-kpi-value <?= $residuo > 0 ? 'due' : 'paid' ?>">€&nbsp;<?= number_format($residuo, 2, ',', '.') ?></div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($perc_pagato !== null): ?>
            <div class="progress-wrap">
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width:<?= $perc_pagato ?>%"></div>
                </div>
                <div class="progress-label">
                    <span><?= $perc_pagato ?>% pagato</span>
                    <?php if ($residuo > 0): ?><span style="color:#721c24">Mancano €&nbsp;<?= number_format($residuo, 2, ',', '.') ?></span><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($pagamenti)): ?>
                <p style="color:#888;font-size:.85rem;text-align:center;padding:1.5rem 0;"><em>Nessun pagamento registrato.</em></p>
            <?php else: ?>
            <table class="pay-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Importo</th>
                        <th>Metodo</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pagamenti as $p): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($p['data_pagamento'])) ?></td>
                        <td><?= h($p['tipo_pagamento']) ?></td>
                        <td class="amount">€&nbsp;<?= number_format((float)$p['importo'], 2, ',', '.') ?></td>
                        <td><?= h($p['metodo_pagamento']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

        </div>
    </div><!-- /colonna destra -->

</div><!-- /view-layout -->

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
