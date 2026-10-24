<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

// ---------------------------------------------------------------------
// Filtri: conto, tipo, categorie, periodo
// ---------------------------------------------------------------------
$f_conto     = $_GET['conto'] ?? '';
$f_tipo      = $_GET['tipo_movimento'] ?? '';
$f_categoria = trim($_GET['categoria'] ?? '');
$f_da        = parse_date_to_sql($_GET['da'] ?? '') ?? '';
$f_a         = parse_date_to_sql($_GET['a'] ?? '') ?? '';

if (!in_array($f_conto, ['cassa', 'banca', 'altro'], true)) $f_conto = '';
if (!in_array($f_tipo, ['entrata', 'uscita'], true)) $f_tipo = '';

$sql = 'SELECT pn.*, s.cognome, s.nome
        FROM prima_nota pn
        LEFT JOIN soci s ON s.id_socio = pn.id_socio
        WHERE 1=1';
$params = [];

if ($f_conto !== '') { $sql .= ' AND pn.conto = :conto'; $params['conto'] = $f_conto; }
if ($f_tipo !== '') { $sql .= ' AND pn.tipo_movimento = :tipo'; $params['tipo'] = $f_tipo; }
if ($f_categoria !== '') { $sql .= ' AND pn.categoria = :cat'; $params['cat'] = $f_categoria; }
if ($f_da !== '') { $sql .= ' AND pn.data_movimento >= :da'; $params['da'] = $f_da; }
if ($f_a !== '') { $sql .= ' AND pn.data_movimento <= :a'; $params['a'] = $f_a; }

$sql .= ' ORDER BY pn.data_movimento DESC, pn.id_movimento DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimenti = $stmt->fetchAll();

// Saldi COMPLESSIVI per conto (non limitati ai filtri): cassa / banca / altro
$saldi_conti = ['cassa' => 0.0, 'banca' => 0.0, 'altro' => 0.0];
foreach ($pdo->query('SELECT conto, SUM(CASE WHEN tipo_movimento = \'entrata\' THEN importo ELSE -importo END) AS saldo FROM prima_nota GROUP BY conto') as $r) {
    $saldi_conti[$r['conto']] = (float)$r['saldo'];
}

// Totali del PERIODO FILTRATO: query aggregata sull'intero insieme
// filtrato (NON limitata alle prime 200 righe mostrate in tabella)
$tot_periodo_entrata = 0.0;
$tot_periodo_uscita = 0.0;
$sql_tot = 'SELECT tipo_movimento, COALESCE(SUM(importo),0) AS tot
            FROM prima_nota WHERE 1=1';
$params_tot = [];
if ($f_conto !== '') { $sql_tot .= ' AND conto = :conto'; $params_tot['conto'] = $f_conto; }
if ($f_tipo !== '') { $sql_tot .= ' AND tipo_movimento = :tipo'; $params_tot['tipo'] = $f_tipo; }
if ($f_categoria !== '') { $sql_tot .= ' AND categoria = :cat'; $params_tot['cat'] = $f_categoria; }
if ($f_da !== '') { $sql_tot .= ' AND data_movimento >= :da'; $params_tot['da'] = $f_da; }
if ($f_a !== '') { $sql_tot .= ' AND data_movimento <= :a'; $params_tot['a'] = $f_a; }
$sql_tot .= ' GROUP BY tipo_movimento';
$stmt_tot = $pdo->prepare($sql_tot);
$stmt_tot->execute($params_tot);
foreach ($stmt_tot->fetchAll() as $r) {
    if ($r['tipo_movimento'] === 'entrata') $tot_periodo_entrata = (float)$r['tot'];
    else $tot_periodo_uscita = (float)$r['tot'];
}

// Categorie esistenti per il datalist
$categorie = $pdo->query('SELECT DISTINCT categoria FROM prima_nota WHERE categoria IS NOT NULL AND categoria <> \'\' ORDER BY categoria')->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'Prima nota';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Prima nota</h1>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a class="btn" href="<?= u('/prima_nota/create.php') ?>">+ Nuovo movimento</a>
        <a class="btn btn-secondary" href="<?= u('/prima_nota/export.php') ?><?= $_SERVER['QUERY_STRING'] ? '?' . h($_SERVER['QUERY_STRING']) : '' ?>">⬇ Esporta CSV</a>
    </div>
</div>

<!-- Saldi complessivi per conto -->
<div class="cards-grid">
    <div class="card">
        <div class="card-value">€ <?= euro($saldi_conti['cassa']) ?></div>
        <div class="card-label">Saldo cassa (complessivo)</div>
    </div>
    <div class="card">
        <div class="card-value">€ <?= euro($saldi_conti['banca']) ?></div>
        <div class="card-label">Saldo banca (complessivo)</div>
    </div>
    <div class="card">
        <div class="card-value">€ <?= euro($saldi_conti['altro']) ?></div>
        <div class="card-label">Saldo altro conto (complessivo)</div>
    </div>
    <div class="card <?= ($tot_periodo_entrata - $tot_periodo_uscita) < 0 ? 'card-warning' : '' ?>">
        <div class="card-value">€ <?= euro($tot_periodo_entrata - $tot_periodo_uscita) ?></div>
        <div class="card-label">Risultato periodo filtrato (E <?= euro($tot_periodo_entrata) ?> / U <?= euro($tot_periodo_uscita) ?>)</div>
    </div>
</div>

<!-- Filtri -->
<form method="get" class="search-form">
    <select name="conto">
        <option value="">— Conto —</option>
        <option value="cassa" <?= $f_conto === 'cassa' ? 'selected' : '' ?>>Cassa</option>
        <option value="banca" <?= $f_conto === 'banca' ? 'selected' : '' ?>>Banca</option>
        <option value="altro" <?= $f_conto === 'altro' ? 'selected' : '' ?>>Altro</option>
    </select>
    <select name="tipo_movimento">
        <option value="">— Tipo —</option>
        <option value="entrata" <?= $f_tipo === 'entrata' ? 'selected' : '' ?>>Entrata</option>
        <option value="uscita" <?= $f_tipo === 'uscita' ? 'selected' : '' ?>>Uscita</option>
    </select>
    <input type="text" name="categoria" value="<?= h($f_categoria) ?>" list="categorie_pn" placeholder="Categoria">
    <datalist id="categorie_pn">
        <?php foreach ($categorie as $c): ?><option value="<?= h($c) ?>"></option><?php endforeach; ?>
    </datalist>
    <input type="date" name="da" value="<?= h($f_da) ?>" title="Dal">
    <input type="date" name="a" value="<?= h($f_a) ?>" title="Al">
    <button type="submit" class="btn">Filtra</button>
    <a class="btn btn-secondary" href="<?= u('/prima_nota/list.php') ?>">Reset</a>
</form>

<table class="data-table">
    <thead>
    <tr>
        <th>Data</th>
        <th>Conto</th>
        <th>Tipo</th>
        <th>Categoria</th>
        <th>Causale</th>
        <th>Importo</th>
        <th>Socio</th>
        <th>Rif. doc</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($movimenti)): ?>
        <tr><td colspan="9">Nessun movimento trovato.</td></tr>
    <?php endif; ?>
    <?php foreach ($movimenti as $m): ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($m['data_movimento'])) ?></td>
            <td><?= h($m['conto']) ?></td>
            <td><?= $m['tipo_movimento'] === 'entrata'
                    ? '<span class="badge badge-green">Entrata</span>'
                    : '<span class="badge badge-red">Uscita</span>' ?></td>
            <td><?= h($m['categoria']) ?: '—' ?></td>
            <td><?= h($m['causale']) ?></td>
            <td class="amount"><?= $m['tipo_movimento'] === 'entrata' ? '+' : '−' ?>€ <?= euro((float)$m['importo']) ?></td>
            <td><?= $m['id_socio'] ? h($m['cognome'] . ' ' . $m['nome']) : '—' ?></td>
            <td><?= h($m['riferimento_documento']) ?: '—' ?></td>
            <td>
                <?php if ($m['id_pagamento']): ?>
                    <span class="badge badge-blue" title="Movimento generato dalla registrazione di un pagamento: importo e tipo non modificabili">da pagamento</span>
                <?php else: ?>
                    <a href="<?= u('/prima_nota/edit.php') ?>?id=<?= (int)$m['id_movimento'] ?>">Modifica</a>
                    ·
                    <a href="<?= u('/prima_nota/delete.php') ?>?id=<?= (int)$m['id_movimento'] ?>">Elimina</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if (count($movimenti) >= 200): ?>
<p style="color:#888;font-size:.85rem;">Mostrati gli ultimi 200 movimenti: restringi il periodo o usa l'export CSV.</p>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
