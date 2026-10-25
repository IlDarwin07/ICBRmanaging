<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

// Stessi filtri della lista
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
$sql .= ' ORDER BY pn.data_movimento ASC, pn.id_movimento ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimenti = $stmt->fetchAll();
audit_event('prima_nota','esportazione_csv');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="prima_nota_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM per Excel
fputcsv($out, ['Data', 'Conto', 'Tipo', 'Categoria', 'Causale', 'Importo (EUR)', 'Socio', 'Riferimento', 'Note'], ';');
foreach ($movimenti as $m) {
    fputcsv($out, [
        $m['data_movimento'],
        $m['conto'],
        $m['tipo_movimento'],
        $m['categoria'] ?? '',
        $m['causale'] ?? '',
        number_format((float)$m['importo'], 2, ',', ''),
        $m['id_socio'] ? trim($m['cognome'] . ' ' . $m['nome']) : '',
        $m['riferimento_documento'] ?? '',
        $m['note'] ?? '',
    ], ';');
}
fclose($out);
exit;
