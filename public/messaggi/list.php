<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$csrf = generate_csrf_token();

// Azioni (POST + CSRF): segna come inviato / elimina
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_msg = (int)($_POST['id_messaggio'] ?? 0);

    if (($_POST['azione'] ?? '') === 'inviato') {
        $pdo->prepare("UPDATE messaggi_whatsapp SET stato_messaggio = 'inviato', data_invio = COALESCE(data_invio,NOW()) WHERE id_messaggio = :id")
            ->execute(['id' => $id_msg]);
        redirect_with_message('/messaggi/list.php', 'Messaggio segnato come inviato.');
    }
    if (in_array($_POST['azione'] ?? '', ['fallito','riprova'], true)) {
        $newState = $_POST['azione'] === 'fallito' ? 'fallito' : 'preparato';
        $pdo->prepare('UPDATE messaggi_whatsapp SET stato_messaggio = ?, data_invio = NULL WHERE id_messaggio = ?')->execute([$newState, $id_msg]);
        redirect_with_message('/messaggi/list.php', 'Stato aggiornato manualmente.');
    }
    if (($_POST['azione'] ?? '') === 'elimina') {
        $pdo->prepare('DELETE FROM messaggi_whatsapp WHERE id_messaggio = :id')
            ->execute(['id' => $id_msg]);
        redirect_with_message('/messaggi/list.php', 'Messaggio eliminato.');
    }
}

$f_stato = $_GET['stato'] ?? '';
if (!in_array($f_stato, ['bozza', 'preparato', 'inviato', 'fallito'], true)) $f_stato = '';

$contactColumn = $pdo->query("SHOW COLUMNS FROM messaggi_whatsapp LIKE 'id_richiesta'")->fetch();
$sql = 'SELECT m.*, s.cognome, s.nome, t.nome_template' . ($contactColumn ? ', r.nome AS nome_contatto' : ', NULL AS nome_contatto') . '
        FROM messaggi_whatsapp m
        LEFT JOIN soci s ON s.id_socio = m.id_socio
        LEFT JOIN template_whatsapp t ON t.id_template = m.id_template' . ($contactColumn ? ' LEFT JOIN richieste_contatto r ON r.id_richiesta = m.id_richiesta' : '');
$params = [];
if ($f_stato !== '') { $sql .= ' WHERE m.stato_messaggio = :stato'; $params['stato'] = $f_stato; }
$sql .= ' ORDER BY m.data_creazione DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messaggi = $stmt->fetchAll();

$contatori = ['preparato' => 0, 'inviato' => 0, 'fallito' => 0, 'bozza' => 0];
foreach ($pdo->query('SELECT stato_messaggio, COUNT(*) AS c FROM messaggi_whatsapp GROUP BY stato_messaggio') as $r) {
    $contatori[$r['stato_messaggio']] = (int)$r['c'];
}

$page_title = 'Messaggi generati';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <h1>Coda e storico WhatsApp</h1>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a class="btn" href="<?= u('/messaggi/compose.php') ?>">✎ Componi invio</a>
        <a class="btn btn-secondary" href="<?= u('/messaggi/direct.php') ?>">Messaggio singolo</a>
        <a class="btn btn-secondary" href="<?= u('/messaggi/index.php') ?>">Centro WhatsApp</a>
        <a class="btn btn-secondary" href="<?= u('/messaggi/template_list.php') ?>">Template</a>
    </div>
</div>

<div class="stats-bar">
    <span>Preparati: <strong><?= $contatori['preparato'] ?></strong></span>
    <span>Inviati (confermati manualmente): <strong><?= $contatori['inviato'] ?></strong></span>
    <span>Falliti: <strong><?= $contatori['fallito'] ?></strong></span>
</div>

<form method="get" class="search-form">
    <select name="stato">
        <option value="">— Tutti gli stati —</option>
        <?php foreach (['preparato' => 'Preparati', 'inviato' => 'Inviati', 'fallito' => 'Falliti', 'bozza' => 'Bozze'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= $f_stato === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn">Filtra</button>
    <a class="btn btn-secondary" href="<?= u('/messaggi/list.php') ?>">Reset</a>
</form>

<table class="data-table">
    <thead>
    <tr>
        <th>Creazione</th>
        <th>Destinatario</th>
        <th>Telefono</th>
        <th>Template</th>
        <th>Messaggio</th>
        <th>Stato</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($messaggi)): ?>
        <tr><td colspan="7">Nessun messaggio. Componi un invio dal menu "Componi invio".</td></tr>
    <?php endif; ?>
    <?php foreach ($messaggi as $m): ?>
        <?php $wa = wa_phone($m['telefono_destinatario']); ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($m['data_creazione'])) ?></td>
            <td><?= h($m['nome_contatto'] ?? trim(($m['cognome'] ?? '') . ' ' . ($m['nome'] ?? ''))) ?: 'Contatto singolo' ?>
                <?php if (!empty($m['id_richiesta'])): ?><br><a href="<?= u('/richieste/view.php') ?>?id=<?= (int)$m['id_richiesta'] ?>">Richiesta #<?= (int)$m['id_richiesta'] ?></a><?php endif; ?>
            </td>
            <td><?= h($m['telefono_destinatario']) ?><?= $wa ? '' : ' <span class="badge badge-red" title="Numero non valido per wa.me">!</span>' ?></td>
            <td><?= h($m['nome_template'] ?? '') ?: '—' ?></td>
            <td style="max-width:420px;">
                <textarea readonly rows="2" class="msg-preview" data-copy><?= h($m['testo_finale']) ?></textarea>
                <div style="margin-top:.3rem;display:flex;gap:.4rem;flex-wrap:wrap;">
                    <button type="button" class="btn btn-sm btn-secondary js-copia">Copia testo</button>
                    <?php if ($wa && $m['stato_messaggio'] !== 'inviato'): ?>
                    <a class="btn btn-sm" target="_blank" rel="noopener"
                       href="https://wa.me/<?= h($wa) ?>?text=<?= rawurlencode($m['testo_finale']) ?>">Apri WhatsApp</a>
                    <?php endif; ?>
                </div>
            </td>
            <td>
                <?php if ($m['stato_messaggio'] === 'inviato'): ?>
                    <span class="badge badge-green">Inviato <?= $m['data_invio'] ? date('d/m/Y H:i', strtotime($m['data_invio'])) : '' ?></span>
                <?php elseif ($m['stato_messaggio'] === 'preparato'): ?>
                    <span class="badge badge-orange">Preparato</span>
                <?php elseif ($m['stato_messaggio'] === 'fallito'): ?>
                    <span class="badge badge-red">Fallito</span>
                <?php else: ?>
                    <span class="badge badge-gray">Bozza</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($m['stato_messaggio'] !== 'inviato'): ?>
                <form method="post" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="id_messaggio" value="<?= (int)$m['id_messaggio'] ?>">
                    <input type="hidden" name="azione" value="inviato">
                    <button type="submit" class="btn btn-sm" title="Segna come inviato dopo averlo inviato manualmente">Conferma inviato</button>
                </form>
                <a class="btn btn-sm btn-secondary" href="<?= u('/messaggi/edit.php') ?>?id=<?= (int)$m['id_messaggio'] ?>">Modifica</a>
                <?php endif; ?>
                <form method="post" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="id_messaggio" value="<?= (int)$m['id_messaggio'] ?>">
                    <input type="hidden" name="azione" value="<?= $m['stato_messaggio'] === 'fallito' ? 'riprova' : 'fallito' ?>">
                    <button class="btn btn-sm btn-secondary"><?= $m['stato_messaggio'] === 'fallito' ? 'Rimetti in coda' : 'Segna problema' ?></button>
                </form>
                <form method="post" style="display:inline" data-confirm="Eliminare questo messaggio dallo storico?">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="id_messaggio" value="<?= (int)$m['id_messaggio'] ?>">
                    <input type="hidden" name="azione" value="elimina">
                    <button type="submit" class="btn btn-sm btn-danger" title="Elimina messaggio">Elimina</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p style="color:#888;font-size:.82rem;">
    Nota: l'apertura di WhatsApp NON segna automaticamente il messaggio come inviato;
    usa il pulsante "Conferma inviato" dopo averlo effettivamente inviato. L'invio automatico
    via WhatsApp Business API (fase 8 della roadmap) richiede configurazione Meta esterna.
</p>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
