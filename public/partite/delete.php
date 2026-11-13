<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/partite.php';
start_secure_session();
require_role(['admin']);
partite_require_ready();
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
$input = $method === 'POST' ? $_POST : $_GET;
$id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) redirect_with_message('/partite/list.php', 'Partita non valida.', 'error');
if ($method === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    if (($_POST['conferma'] ?? '') !== 'elimina') redirect_with_message('/partite/list.php', 'Conferma richiesta.', 'error');
    try {
        partite_transaction(function (PDO $pdo) use ($id): void {
            $stmt = $pdo->prepare('SELECT titolo, stato FROM partite WHERE id_partita = ? FOR UPDATE');
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            if (!$p) throw new DomainException('Partita non trovata o già eliminata.');
            if ($p['stato'] !== 'annullata') throw new DomainException('Puoi eliminare solo una partita annullata.');
            $delete = $pdo->prepare("DELETE FROM partite WHERE id_partita = ? AND stato = 'annullata'");
            $delete->execute([$id]);
            if ($delete->rowCount() !== 1) throw new DomainException('Partita non eliminata.');
            audit_event('partite', 'elimina', 'partite', (string)$id, ['titolo' => $p['titolo'], 'stato' => 'annullata']);
        });
    } catch (DomainException $e) {
        redirect_with_message('/partite/list.php', $e->getMessage(), 'error');
    } catch (Throwable $e) {
        error_log('Eliminazione partita fallita: ' . $e->getMessage());
        redirect_with_message('/partite/list.php', 'Eliminazione non riuscita. Nessuna modifica salvata.', 'error');
    }
    redirect_with_message('/partite/list.php', 'Partita annullata eliminata definitivamente.');
}
$p = partita_get($id);
if (!$p) redirect_with_message('/partite/list.php', 'Partita non trovata.', 'error');
if ($p['stato'] !== 'annullata') redirect_with_message('/partite/view.php?id=' . $id, 'Puoi eliminare solo una partita annullata.', 'error');
$stmt = get_db_connection()->prepare('SELECT COUNT(*) FROM prenotazioni WHERE id_partita = ?');
$stmt->execute([$id]);
$prenotazioni = (int)$stmt->fetchColumn();
$page_title = 'Elimina partita annullata';
require __DIR__ . '/../../includes/layout_header.php';
?>
<section class="admin-panel">
<h1>Elimina partita annullata</h1>
<p><strong><?= h($p['titolo']) ?></strong> · <?= h(partita_data_label($p['data_ora'])) ?></p>
<p role="note">Questa operazione è irreversibile. Verranno eliminate la partita, tutte le <?= $prenotazioni ?> prenotazioni collegate (anche quelle annullate o in attesa) e le relative assegnazioni di posti e sale. Le anagrafiche dei soci e le sale della sede non vengono cancellate.</p>
<form method="post" action="<?= u('/partite/delete.php') ?>">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
<input type="hidden" name="id" value="<?= (int)$id ?>">
<label><input type="checkbox" name="conferma" value="elimina" required> Confermo la cancellazione definitiva della partita e delle prenotazioni collegate.</label>
<p><button type="submit" class="btn" style="background:#b42318;border-color:#b42318;color:#fff">Elimina definitivamente</button> <a class="btn btn-secondary" href="<?= u('/partite/view.php?id=' . (int)$id) ?>">Non eliminare</a></p>
</form>
</section>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
