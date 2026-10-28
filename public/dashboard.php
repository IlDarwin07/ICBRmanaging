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
    'soci'     => 'Soci e rinnovi',
    'economia' => 'Andamento economico',
    'comuni'   => 'Soci per comune',
    'azioni'   => 'Azioni rapide',
];
const DASH_COOKIE = 'icbr_dashboard';

function dash_selected(): array
{
    if (!isset($_COOKIE[DASH_COOKIE])) {
        return array_keys(DASH_WIDGETS);
    }
    $chosen = array_filter(explode(',', (string)$_COOKIE[DASH_COOKIE]), fn($k) => isset(DASH_WIDGETS[$k]));
    return array_values($chosen);
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
    $soci_per_comune = $pdo->query(
        "SELECT COALESCE(NULLIF(TRIM(comune), ''), 'Non specificato') AS comune, COUNT(*) AS totale
         FROM soci WHERE attivo_record = 1 GROUP BY 1 ORDER BY totale DESC, comune ASC"
    )->fetchAll();
}

$page_title = 'Dashboard';
require __DIR__ . '/../includes/layout_header.php';
?>
<h1>Dashboard <?php if ($codice): ?><small><?= $codice ?></small><?php endif; ?></h1>

<details class="dash-config">
    <summary>Personalizza dashboard</summary>
    <form method="post" action="<?= u('/dashboard.php') ?>">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <?php foreach (DASH_WIDGETS as $chiave => $etichetta): ?>
            <label style="display:inline-block;margin-right:1rem">
                <input type="checkbox" name="widget[]" value="<?= h($chiave) ?>" <?= $mostra($chiave) ? 'checked' : '' ?>>
                <?= h($etichetta) ?>
            </label>
        <?php endforeach; ?>
        <button class="btn btn-secondary" type="submit">Salva</button>
    </form>
</details>

<?php if (!$attivi): ?>
    <p class="note">Nessun widget selezionato: usa \"Personalizza dashboard\" per sceglierli.</p>
<?php endif; ?>

<?php if ($mostra('azioni')): ?>
<div class="quick-links">
    <a class="btn" href="<?= u('/soci/list.php') ?>">Soci</a>
    <a class="btn btn-secondary" href="<?= u('/soci/create.php') ?>">+ Nuovo socio</a>
    <a class="btn btn-secondary" href="<?= u('/tesseramenti/list.php') ?>">Tesseramenti</a>
    <a class="btn btn-secondary" href="<?= u('/prima_nota/list.php') ?>">Prima nota</a>
    <a class="btn btn-secondary" href="<?= u('/richieste/list.php') ?>">Richieste dal sito</a>
    <a class="btn btn-secondary" href="<?= u('/messaggi/index.php') ?>">WhatsApp</a>
</div>
<?php endif; ?>

<?php if ($mostra('soci')): ?>
<h2>Soci e rinnovi</h2>
<div class="cards-grid">
    <div class="card"><span class="card-value"><?= $totale_soci ?></span><span class="card-label">Registrati</span></div>
    <div class="card"><span class="card-value"><?= $soci_attivi_corrente ?></span><span class="card-label">Attivi <?= $codice ?: '(nessuna stagione)' ?></span></div>
    <div class="card"><span class="card-value"><?= $soci_anagrafica_confermata ?></span><span class="card-label">Anagrafica confermata</span></div>
    <div class="card card-warning"><span class="card-value"><?= $non_rinnovati ?></span><span class="card-label">Da rinnovare<?= $stagione_precedente ? ' (attivi in ' . h($stagione_precedente['codice_stagione']) . ')' : '' ?></span></div>
</div>
<?php endif; ?>

<?php if ($mostra('economia')): ?>
<h2>Andamento economico</h2>
<div class="cards-grid">
    <div class="card"><span class="card-value">&euro; <?= euro($incassato_stagione) ?></span><span class="card-label">Incassato</span></div>
    <div class="card <?= $soci_da_saldare > 0 ? 'card-warning' : '' ?>"><span class="card-value">&euro; <?= euro($residuo_previsto) ?></span><span class="card-label">Residuo (<?= $soci_da_saldare ?> da saldare)</span></div>
    <div class="card"><span class="card-value">&euro; <?= euro($saldi_conti['cassa']) ?></span><span class="card-label">Cassa</span></div>
    <div class="card"><span class="card-value">&euro; <?= euro($saldi_conti['banca']) ?></span><span class="card-label">Banca</span></div>
</div>
<?php endif; ?>

<?php if ($mostra('comuni')): ?>
<h2>Soci per comune</h2>
<?php if (empty($soci_per_comune)): ?>
    <p class="note">Nessun dato disponibile.</p>
<?php else: ?>
    <table class="data-table" style="max-width:420px">
        <thead><tr><th>Comune</th><th style="text-align:right">N. soci</th></tr></thead>
        <tbody>
        <?php foreach ($soci_per_comune as $row): ?>
            <tr><td><?= h($row['comune']) ?></td><td style="text-align:right"><?= (int)$row['totale'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
