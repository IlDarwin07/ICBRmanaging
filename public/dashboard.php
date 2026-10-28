<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_login();
if ((current_user()['ruolo'] ?? '') !== 'admin') {
    require __DIR__ . '/../includes/dashboard_limited.php';
    exit;
}

const DASH_WIDGETS = [
    'azioni'   => 'Azioni rapide',
    'soci'     => 'Soci e rinnovi',
    'economia' => 'Andamento economico',
    'comuni'   => 'Soci per comune',
];
const DASH_COOKIE = 'icbr_dashboard';
const DASH_PALETTE = ['#123ed0', '#2f80ed', '#37b6e8', '#1fa971', '#f2a33a', '#8a5cf6', '#e0566b', '#e5c63c'];
const DASH_ALTRI = '#b8c1d6';

function dash_selected(): array
{
    if (!isset($_COOKIE[DASH_COOKIE])) {
        return array_keys(DASH_WIDGETS);
    }
    $chosen = array_filter(explode(',', (string)$_COOKIE[DASH_COOKIE]), fn($k) => isset(DASH_WIDGETS[$k]));
    return array_values($chosen);
}

function dash_pie_svg(array $slices, int $total): string
{
    if ($total <= 0) {
        return '';
    }
    $circ = 2 * M_PI * 40;
    $offset = 0.0;
    $svg = '<svg viewBox="0 0 120 120" class="dash-pie" role="img" aria-label="Distribuzione dei soci per comune">';
    foreach ($slices as $s) {
        $len = $circ * $s['value'] / $total;
        $svg .= sprintf(
            '<circle cx="60" cy="60" r="40" fill="none" stroke="%s" stroke-width="22" stroke-dasharray="%.3F %.3F" stroke-dashoffset="%.3F" transform="rotate(-90 60 60)"><title>%s: %d</title></circle>',
            $s['color'], $len, $circ - $len, -$offset, h($s['label']), $s['value']
        );
        $offset += $len;
    }
    $svg .= '<text x="60" y="60" text-anchor="middle" class="dash-pie-total">' . $total . '</text>';
    $svg .= '<text x="60" y="74" text-anchor="middle" class="dash-pie-label">soci</text></svg>';
    return $svg;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $scelti = array_values(array_filter((array)($_POST['widget'] ?? []), fn($k) => is_string($k) && isset(DASH_WIDGETS[$k])));
    setcookie(DASH_COOKIE, implode(',', $scelti), [
        'expires'  => time() + 31536000,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    redirect_with_message('/dashboard.php', 'Dashboard aggiornata.');
}

$attivi = dash_selected();
$mostra = fn(string $k): bool => in_array($k, $attivi, true);

$pdo = get_db_connection();

$stagione_attiva = $pdo->query(
    'SELECT id_stagione, codice_stagione FROM stagioni ORDER BY attiva DESC, codice_stagione DESC LIMIT 1'
)->fetch();
$codice = $stagione_attiva ? h($stagione_attiva['codice_stagione']) : '';

if ($mostra('soci')) {
    $totale_soci = (int)$pdo->query('SELECT COUNT(*) FROM soci WHERE attivo_record = 1')->fetchColumn();
    $soci_attivi_corrente = 0;
    $soci_anagrafica_confermata = 0;
    $non_rinnovati = 0;
    $stagione_precedente = null;

    if ($stagione_attiva) {
        $stmt_prec = $pdo->prepare('SELECT id_stagione, codice_stagione FROM stagioni WHERE codice_stagione < :cod ORDER BY codice_stagione DESC LIMIT 1');
        $stmt_prec->execute(['cod' => $stagione_attiva['codice_stagione']]);
        $stagione_precedente = $stmt_prec->fetch();

        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(attivo_portale = 1), 0) AS attivi, COALESCE(SUM(conferma_anagrafica = 1), 0) AS confermati
             FROM tesseramenti WHERE id_stagione = :id'
        );
        $stmt->execute(['id' => $stagione_attiva['id_stagione']]);
        $r = $stmt->fetch();
        $soci_attivi_corrente = (int)$r['attivi'];
        $soci_anagrafica_confermata = (int)$r['confermati'];

        if ($stagione_precedente) {
            $stmt3 = $pdo->prepare(
                'SELECT COUNT(DISTINCT t_prec.id_socio)
                 FROM tesseramenti t_prec
                 WHERE t_prec.id_stagione = :id_prec AND t_prec.attivo_portale = 1
                   AND NOT EXISTS (SELECT 1 FROM tesseramenti t_att WHERE t_att.id_socio = t_prec.id_socio AND t_att.id_stagione = :id_att)'
            );
            $stmt3->execute(['id_prec' => $stagione_precedente['id_stagione'], 'id_att' => $stagione_attiva['id_stagione']]);
            $non_rinnovati = (int)$stmt3->fetchColumn();
        }
    }
}

if ($mostra('economia')) {
    $incassato_stagione = 0.0;
    $residuo_previsto = 0.0;
    $soci_da_saldare = 0;
    if ($stagione_attiva) {
        $stmt_kpi = $pdo->prepare(
            "SELECT COALESCE(NULLIF(t.quota_tesseramento, 0), NULLIF(tip.quota_standard, 0)) AS quota_dovuta,
                    COALESCE(pag.versato_netto, 0) AS versato_netto
             FROM tesseramenti t
             LEFT JOIN tipologie_tesseramento tip ON tip.id_tipologia = t.id_tipologia
             LEFT JOIN (
                 SELECT id_tesseramento, SUM(CASE WHEN tipo_pagamento = 'rimborso' THEN -importo ELSE importo END) AS versato_netto
                 FROM pagamenti_tesseramenti GROUP BY id_tesseramento
             ) pag ON pag.id_tesseramento = t.id_tesseramento
             WHERE t.id_stagione = :id_stagione"
        );
        $stmt_kpi->execute(['id_stagione' => $stagione_attiva['id_stagione']]);
        foreach ($stmt_kpi->fetchAll() as $r) {
            $quota = $r['quota_dovuta'] !== null ? (float)$r['quota_dovuta'] : null;
            $netto = (float)$r['versato_netto'];
            $incassato_stagione += $netto;
            if ($quota !== null) {
                $residuo_previsto += tess_residuo($quota, $netto) ?? 0.0;
                if ($netto < $quota - 0.004) $soci_da_saldare++;
            }
        }
    }
    $saldi_conti = ['cassa' => 0.0, 'banca' => 0.0, 'altro' => 0.0];
    foreach ($pdo->query("SELECT conto, SUM(CASE WHEN tipo_movimento = 'entrata' THEN importo ELSE -importo END) AS saldo FROM prima_nota GROUP BY conto") as $r) {
        $saldi_conti[$r['conto']] = (float)$r['saldo'];
    }
}

if ($mostra('comuni')) {
    $tutti_comuni = $pdo->query(
        "SELECT COALESCE(NULLIF(TRIM(comune), ''), 'Non specificato') AS comune, COUNT(*) AS totale
         FROM soci WHERE attivo_record = 1 GROUP BY 1 ORDER BY totale DESC, comune ASC"
    )->fetchAll();

    $totale_comuni = 0;
    $principali = [];
    $piccoli_comuni = 0;
    $piccoli_soci = 0;
    foreach ($tutti_comuni as $r) {
        $n = (int)$r['totale'];
        $totale_comuni += $n;
        if ($n <= 2 && $r['comune'] !== 'Non specificato') {
            $piccoli_comuni++;
            $piccoli_soci += $n;
        } else {
            $principali[] = ['label' => $r['comune'], 'value' => $n];
        }
    }

    $righe_comuni = [];
    $fette = [];
    $resto_torta = $piccoli_soci;
    foreach ($principali as $i => $p) {
        if ($i < count(DASH_PALETTE)) {
            $colore = DASH_PALETTE[$i];
            $fette[] = ['label' => $p['label'], 'value' => $p['value'], 'color' => $colore];
        } else {
            $colore = DASH_ALTRI;
            $resto_torta += $p['value'];
        }
        $righe_comuni[] = $p + ['color' => $colore];
    }
    if ($piccoli_comuni > 0) {
        $righe_comuni[] = [
            'label' => 'Altri comuni (' . $piccoli_comuni . ', 1-2 soci ciascuno)',
            'value' => $piccoli_soci,
            'color' => DASH_ALTRI,
        ];
    }
    if ($resto_torta > 0) {
        $fette[] = ['label' => 'Altri comuni', 'value' => $resto_torta, 'color' => DASH_ALTRI];
    }
}

$page_title = 'Dashboard';
require __DIR__ . '/../includes/layout_header.php';
?>
<style>
.admin-body .dash-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:0 0 18px}
.admin-body .dash-head h1{margin:0}
.admin-body .dash-head .dash-config{position:relative;margin:0;padding:0;border:0;background:transparent;border-radius:0}
.admin-body .dash-head .dash-config>summary{display:flex;align-items:center;gap:8px;min-height:42px;padding:9px 14px;box-sizing:border-box;list-style:none;background:#fff;border:1px solid #d7dfec;border-radius:10px;font-size:13px;font-weight:600;color:#344260;cursor:pointer}
.admin-body .dash-head .dash-config>summary::-webkit-details-marker{display:none}
.admin-body .dash-head .dash-config[open]>summary{background:#eef3ff;border-color:#a8bce9}
.admin-body .dash-head .dash-config>form{position:absolute;z-index:30;top:calc(100% + 8px);right:0;width:290px;max-width:calc(100vw - 32px);box-sizing:border-box;display:flex;flex-direction:column;align-items:stretch;gap:0;margin:0;padding:10px;background:#fff;border:1px solid #dce3ef;border-radius:13px;box-shadow:0 14px 36px #182c5226}
.admin-body .dash-head .dash-config>form>label{display:flex!important;align-items:center;gap:10px;min-height:42px;padding:8px 10px;margin:0!important;border-radius:8px;font-size:14px;cursor:pointer}
.admin-body .dash-head .dash-config>form>label:hover{background:#f2f5fc}
.admin-body .dash-head .dash-config>form>button{width:100%;margin:10px 0 0}
.admin-body .dash-actions{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px}
.admin-body .dash-actions .btn{min-height:38px;padding:8px 14px;font-size:13px}
.admin-body .dash-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(440px,100%),1fr));gap:16px;align-items:stretch}
.admin-body .dash-panel{background:#fff;border:1px solid #e1e6f0;border-radius:14px;padding:20px;min-width:0}
.admin-body .dash-panel.is-wide{grid-column:1/-1}
.admin-body .dash-panel h2{margin:0 0 14px;font-size:15px;line-height:1.3;color:#1d2740}
.admin-body .dash-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.admin-body .dash-stat{display:flex;flex-direction:column;gap:3px;padding:14px 16px;background:#f5f7fc;border-radius:11px;min-width:0}
.admin-body .dash-stat strong{font-size:clamp(22px,2.4vw,30px);line-height:1.15;color:#0f2f9e;overflow-wrap:anywhere}
.admin-body .dash-stat span{font-size:12px;line-height:1.4;color:#66718a}
.admin-body .dash-stat.is-warn strong{color:#a85a05}
.admin-body .dash-comuni{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(220px,.75fr);gap:28px;align-items:center}
.admin-body .dash-list{max-height:360px;overflow:auto;border:1px solid #e5e9f2;border-radius:11px}
.admin-body .dash-table{width:100%;border-collapse:collapse;font-size:13px}
.admin-body .dash-table th{position:sticky;top:0;background:#f0f3fb;color:#4a5878;font-size:11px;letter-spacing:.04em;text-transform:uppercase;text-align:left;padding:10px 14px}
.admin-body .dash-table td{padding:10px 14px;border-top:1px solid #edf0f6}
.admin-body .dash-table .num{text-align:right;white-space:nowrap}
.admin-body .dash-table .muted{color:#7a8499}
.admin-body .dash-dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:9px;vertical-align:baseline}
.admin-body .dash-chart{display:flex;justify-content:center}
.admin-body .dash-pie{width:100%;max-width:290px;height:auto}
.admin-body .dash-pie-total{font-size:19px;font-weight:700;fill:#0f2f9e}
.admin-body .dash-pie-label{font-size:8px;fill:#66718a}
@media(max-width:800px){.admin-body .dash-comuni{grid-template-columns:minmax(0,1fr);gap:18px}.admin-body .dash-chart{order:-1}.admin-body .dash-pie{max-width:230px}}
@media(max-width:600px){.admin-body .dash-head{align-items:flex-start}.admin-body .dash-panel{padding:16px}.admin-body .dash-stat{padding:12px}.admin-body .dash-actions .btn{flex:1 1 130px}}
</style>

<div class="dash-head">
    <h1>Dashboard <?php if ($codice): ?><small><?= $codice ?></small><?php endif; ?></h1>
    <details class="dash-config">
        <summary>Personalizza</summary>
        <form method="post" action="<?= u('/dashboard.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <?php foreach (DASH_WIDGETS as $chiave => $etichetta): ?>
                <label>
                    <input type="checkbox" name="widget[]" value="<?= h($chiave) ?>" <?= $mostra($chiave) ? 'checked' : '' ?>>
                    <?= h($etichetta) ?>
                </label>
            <?php endforeach; ?>
            <button class="btn" type="submit">Salva</button>
        </form>
    </details>
</div>

<?php if (!$attivi): ?>
    <p class="note">Nessun widget selezionato: apri Personalizza per sceglierli.</p>
<?php endif; ?>

<?php if ($mostra('azioni')): ?>
<div class="dash-actions">
    <a class="btn" href="<?= u('/soci/create.php') ?>">+ Nuovo socio</a>
    <a class="btn btn-secondary" href="<?= u('/soci/list.php') ?>">Soci</a>
    <a class="btn btn-secondary" href="<?= u('/tesseramenti/list.php') ?>">Tesseramenti</a>
    <a class="btn btn-secondary" href="<?= u('/prima_nota/list.php') ?>">Prima nota</a>
    <a class="btn btn-secondary" href="<?= u('/messaggi/index.php') ?>">WhatsApp</a>
    <a class="btn btn-secondary" href="<?= u('/richieste/list.php') ?>">Richieste</a>
</div>
<?php endif; ?>

<div class="dash-grid">
<?php if ($mostra('soci')): ?>
    <section class="dash-panel" aria-labelledby="dash-soci">
        <h2 id="dash-soci">Soci e rinnovi</h2>
        <div class="dash-stats">
            <div class="dash-stat"><strong><?= $totale_soci ?></strong><span>Registrati</span></div>
            <div class="dash-stat"><strong><?= $soci_attivi_corrente ?></strong><span>Attivi <?= $codice ?: '(nessuna stagione)' ?></span></div>
            <div class="dash-stat"><strong><?= $soci_anagrafica_confermata ?></strong><span>Anagrafica confermata</span></div>
            <div class="dash-stat is-warn"><strong><?= $non_rinnovati ?></strong><span>Da rinnovare<?= $stagione_precedente ? ' (attivi in ' . h($stagione_precedente['codice_stagione']) . ')' : '' ?></span></div>
        </div>
    </section>
<?php endif; ?>

<?php if ($mostra('economia')): ?>
    <section class="dash-panel" aria-labelledby="dash-economia">
        <h2 id="dash-economia">Andamento economico</h2>
        <div class="dash-stats">
            <div class="dash-stat"><strong>&euro; <?= euro($incassato_stagione) ?></strong><span>Incassato</span></div>
            <div class="dash-stat <?= $soci_da_saldare > 0 ? 'is-warn' : '' ?>"><strong>&euro; <?= euro($residuo_previsto) ?></strong><span>Residuo (<?= $soci_da_saldare ?> da saldare)</span></div>
            <div class="dash-stat"><strong>&euro; <?= euro($saldi_conti['cassa']) ?></strong><span>Cassa</span></div>
            <div class="dash-stat"><strong>&euro; <?= euro($saldi_conti['banca']) ?></strong><span>Banca</span></div>
        </div>
    </section>
<?php endif; ?>

<?php if ($mostra('comuni')): ?>
    <section class="dash-panel is-wide" aria-labelledby="dash-comuni">
        <h2 id="dash-comuni">Soci per comune</h2>
        <?php if (empty($righe_comuni)): ?>
            <p class="note">Nessun dato disponibile.</p>
        <?php else: ?>
            <div class="dash-comuni">
                <div class="dash-list">
                    <table class="dash-table">
                        <thead><tr><th>Comune</th><th class="num">Soci</th><th class="num">%</th></tr></thead>
                        <tbody>
                        <?php foreach ($righe_comuni as $riga): ?>
                            <tr>
                                <td><span class="dash-dot" style="background:<?= h($riga['color']) ?>"></span><?= h($riga['label']) ?></td>
                                <td class="num"><?= (int)$riga['value'] ?></td>
                                <td class="num muted"><?= number_format($riga['value'] * 100 / max(1, $totale_comuni), 1, ',', '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="dash-chart"><?= dash_pie_svg($fette, $totale_comuni) ?></div>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
