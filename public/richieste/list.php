<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
start_secure_session();
require_login();
$pdo = get_db_connection();
$stato = $_GET['stato'] ?? '';
if (!in_array($stato, ['nuova','in_gestione','chiusa'], true)) $stato = '';
$params = [];
$sql = 'SELECT * FROM richieste_contatto';
if ($stato !== '') { $sql .= ' WHERE stato = ?'; $params[] = $stato; }
$sql .= ' ORDER BY data_creazione DESC, id_richiesta DESC LIMIT 200';
$ready = true;
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
    $counts = ['nuova' => 0, 'in_gestione' => 0, 'chiusa' => 0];
    foreach ($pdo->query('SELECT stato, COUNT(*) n FROM richieste_contatto GROUP BY stato') as $r) $counts[$r['stato']] = (int)$r['n'];
} catch (PDOException $e) {
    if ($e->getCode() !== '42S02') throw $e;
    $ready = false; $requests = [];
}
$labels = ['nuova'=>'Nuova','in_gestione'=>'In gestione','chiusa'=>'Chiusa'];
$page_title = 'Richieste dal sito';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header"><h1>Richieste dal sito</h1><a class="btn btn-secondary" href="<?= u('/index.php') ?>#contatti">Vedi modulo pubblico ↗</a></div>
<?php if (!$ready): ?>
<div class="flash flash-info">Per attivare le richieste applica la migrazione 009 sul database, dopo la 008. La vetrina resta consultabile; il modulo non conferma invii se la tabella non è disponibile.</div>
<?php else: ?>
<div class="stats-bar"><span>Nuove: <strong><?= $counts['nuova'] ?></strong></span><span>In gestione: <strong><?= $counts['in_gestione'] ?></strong></span><span>Chiuse: <strong><?= $counts['chiusa'] ?></strong></span></div>
<form method="get" class="search-form"><select name="stato" aria-label="Stato richiesta"><option value="">Tutte le richieste</option><?php foreach ($labels as $key=>$label): ?><option value="<?= $key ?>" <?= $stato === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><button class="btn">Filtra</button><a class="btn btn-secondary" href="<?= u('/richieste/list.php') ?>">Reset</a></form>
<table class="data-table"><thead><tr><th>Data</th><th>Nome</th><th>Argomento</th><th>Email</th><th>Telefono</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>
<?php if (!$requests): ?><tr><td colspan="7">Nessuna richiesta con questi filtri. I messaggi della vetrina compariranno qui.</td></tr><?php endif; ?>
<?php foreach ($requests as $r): ?><tr><td><?= h(date('d/m/Y H:i', strtotime($r['data_creazione']))) ?></td><td><?= h($r['nome']) ?></td><td><?= h($r['oggetto']) ?></td><td><?= h($r['email']) ?></td><td><?= h($r['telefono'] ?: 'Non indicato') ?></td><td><span class="badge <?= $r['stato']==='nuova' ? 'badge-blue' : 'badge-gray' ?>"><?= h($labels[$r['stato']]) ?></span></td><td><a class="btn btn-sm" href="<?= u('/richieste/view.php') ?>?id=<?= (int)$r['id_richiesta'] ?>">Apri richiesta</a></td></tr><?php endforeach; ?>
</tbody></table>
<p class="note">Mostrate le 200 richieste più recenti per filtro. Elimina le richieste non più necessarie dalla relativa scheda; la cancellazione rimuove anche le risposte WhatsApp collegate.</p>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
