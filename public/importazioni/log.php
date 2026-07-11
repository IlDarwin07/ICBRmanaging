<?php
/**
 * Fase 4 — Log dettagliato di una singola importazione
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$id_import = (int)($_GET['id'] ?? 0);

$import = null;
if ($id_import) {
    $stmt = $pdo->prepare(
        'SELECT i.*, s.codice_stagione
         FROM importazioni i
         JOIN stagioni s ON s.id_stagione = i.id_stagione
         WHERE i.id_importazione = ?'
    );
    $stmt->execute([$id_import]);
    $import = $stmt->fetch();
}

if (!$import) {
    header('Location: /importazioni/upload.php');
    exit;
}

$righe = $pdo->prepare(
    'SELECT * FROM importazioni_righe WHERE id_importazione = ? ORDER BY numero_riga ASC'
);
$righe->execute([$id_import]);
$righe = $righe->fetchAll();

$cnt = ['inserito' => 0, 'aggiornato' => 0, 'duplicato' => 0, 'errore' => 0];
foreach ($righe as $r) {
    $cnt[$r['esito']] = ($cnt[$r['esito']] ?? 0) + 1;
}

$filtro = $_GET['filtro'] ?? '';
if ($filtro && in_array($filtro, ['inserito','aggiornato','duplicato','errore'])) {
    $righe = array_filter($righe, fn($r) => $r['esito'] === $filtro);
}

$page_title = 'Log importazione #' . $id_import;
require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Log importazione <small style="font-size:.6em;font-weight:400">#<?= $id_import ?></small></h1>

<p class="note">
    File: <strong><?= h($import['nome_file']) ?></strong> &nbsp;|&nbsp;
    Stagione: <strong><?= h($import['codice_stagione']) ?></strong> &nbsp;|&nbsp;
    Data: <strong><?= h(date('d/m/Y H:i', strtotime($import['data_import']))) ?></strong>
</p>

<div class="cards-grid" style="margin-bottom:1rem">
    <?php foreach (['inserito'=>'Inseriti','aggiornato'=>'Aggiornati','duplicato'=>'Ambigui','errore'=>'Errori'] as $k => $label): ?>
        <div class="card<?= $k === 'errore' || $k === 'duplicato' ? ' card-warning' : '' ?>">
            <span class="card-value"><?= $cnt[$k] ?></span>
            <span class="card-label"><?= $label ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="quick-links" style="margin-bottom:1rem">
    <?php foreach ([''=>'Tutti','inserito'=>'Inseriti','aggiornato'=>'Aggiornati','duplicato'=>'Ambigui','errore'=>'Errori'] as $k => $label): ?>
        <a href="?id=<?= $id_import ?>&filtro=<?= $k ?>"
           class="btn btn-secondary<?= ($filtro === $k) ? ' btn-active' : '' ?>">
            <?= $label ?>
        </a>
    <?php endforeach; ?>
</div>

<div style="overflow-x:auto">
    <table class="data-table">
        <thead>
            <tr>
                <th>Riga</th>
                <th>Esito</th>
                <th>Messaggio</th>
                <th>Dati importati</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($righe as $r):
                $cls = match($r['esito']) {
                    'inserito'   => 'color:var(--color-success)',
                    'aggiornato' => '',
                    'duplicato'  => 'color:var(--color-warning)',
                    'errore'     => 'color:var(--color-error)',
                    default      => '',
                };
                $dati_json = $r['dati_json'] ? json_decode($r['dati_json'], true) : [];
                $preview = implode(' | ', array_map(
                    fn($k, $v) => "$k: $v",
                    array_keys($dati_json),
                    array_values($dati_json)
                ));
            ?>
                <tr>
                    <td><?= (int)$r['numero_riga'] ?></td>
                    <td style="<?= $cls ?>;font-weight:600"><?= h(ucfirst($r['esito'])) ?></td>
                    <td><?= h($r['messaggio']) ?></td>
                    <td style="font-size:.8em;color:var(--color-text-muted)"><?= h(substr($preview, 0, 160)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="form-actions" style="margin-top:1.5rem">
    <a href="/importazioni/upload.php" class="btn btn-secondary">&larr; Storico importazioni</a>
</div>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
