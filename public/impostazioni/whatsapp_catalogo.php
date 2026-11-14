<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/whatsapp_payload.php';
start_secure_session();
require_role(['admin']);
$pdo = get_db_connection();
function wa_catalog_text(array $input, string $key): string {
    $v = $input[$key] ?? '';
    if (!is_string($v)) throw new InvalidArgumentException('Campo non valido: ' . $key);
    return trim($v);
}
function wa_catalog_revision($row): string { return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR)); }
$tab = ($_GET['tab'] ?? '') === 'modelli' ? 'modelli' : 'consensi';
$id = max(0, (int)filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT));
$ready = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('wa_consensi','wa_consensi_eventi','wa_modelli')")->fetchColumn() === 3;
$errors = [];
$_SESSION['wa_catalog_nonce'] ??= bin2hex(random_bytes(24));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    try {
        if (!$ready) throw new InvalidArgumentException('Applica prima la migrazione 018_whatsapp_api.sql.');
        if (!hash_equals($_SESSION['wa_catalog_nonce'], wa_catalog_text($_POST, 'nonce'))) throw new InvalidArgumentException('Modulo già usato o scaduto: ricarica la pagina.');
        $action = wa_catalog_text($_POST, 'azione');
        $actor = (int)current_user()['id_utente'];
        if ($action === 'consenso') {
            $id = (int)filter_var($_POST['id_socio'] ?? 0, FILTER_VALIDATE_INT);
            $state = wa_catalog_text($_POST, 'stato');
            $source = wa_catalog_text($_POST, 'fonte');
            if ($id < 1 || !in_array($state, ['concesso','revocato'], true) || $source === '' || strlen($source) > 255) throw new InvalidArgumentException('Seleziona un socio, uno stato e una fonte entro 255 byte.');
            if ($state === 'concesso' && ($_POST['conferma'] ?? '') !== '1') throw new InvalidArgumentException('Conferma di avere riscontro del consenso per questo numero.');
            $pdo->beginTransaction();
            $s = $pdo->prepare('SELECT telefono FROM soci WHERE id_socio=? FOR UPDATE');
            $s->execute([$id]);
            $person = $s->fetch(PDO::FETCH_ASSOC);
            if (!$person) throw new InvalidArgumentException('Socio non trovato.');
            $phone = wa_phone($person['telefono']);
            if (($phone ?? '') !== wa_catalog_text($_POST, 'telefono_snapshot')) throw new InvalidArgumentException('Il numero del socio è cambiato: ricarica prima di salvare.');
            $s = $pdo->prepare('SELECT telefono,stato,fonte,updated_at FROM wa_consensi WHERE id_socio=? FOR UPDATE');
            $s->execute([$id]);
            $old = $s->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!hash_equals(wa_catalog_revision($old), wa_catalog_text($_POST, 'revisione'))) throw new InvalidArgumentException('Consenso modificato da un altro operatore: ricarica la pagina.');
            if ($state === 'revocato') $phone = $old['telefono'] ?? $phone;
            if (!$phone) throw new InvalidArgumentException('Numero non valido: correggi l’anagrafica prima di registrare un consenso.');
            if (!$old || $old['telefono'] !== $phone || $old['stato'] !== $state || $old['fonte'] !== $source) {
                $s = $pdo->prepare('INSERT INTO wa_consensi (id_socio,telefono,stato,fonte,registrato_da) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE telefono=VALUES(telefono),stato=VALUES(stato),fonte=VALUES(fonte),registrato_da=VALUES(registrato_da)');
                $s->execute([$id,$phone,$state,$source,$actor]);
                $pdo->prepare('INSERT INTO wa_consensi_eventi (id_socio,telefono,stato,fonte,registrato_da) VALUES (?,?,?,?,?)')->execute([$id,$phone,$state,$source,$actor]);
                audit_event('whatsapp','consenso_aggiornato','soci',(string)$id,['stato'=>$state]);
            }
            $pdo->commit();
            $target = '/impostazioni/whatsapp_catalogo.php?tab=consensi&id=' . $id;
        } elseif ($action === 'modello') {
            $name = wa_catalog_text($_POST, 'nome_meta');
            $language = wa_catalog_text($_POST, 'lingua');
            $body = wa_catalog_text($_POST, 'testo');
            $raw = wa_catalog_text($_POST, 'parametri');
            $mapping = $raw === '' ? [] : array_map('trim', explode(',', $raw));
            $allowed = ['nome','cognome','nome_completo','telefono','quota','pagato','residuo','stagione','data'];
            if ($body === '' || strlen($body) > 4096 || preg_match('//u', $body) !== 1 || count($mapping) > 20 || array_diff($mapping, $allowed)) throw new InvalidArgumentException('Testo o parametri non validi: usa i campi indicati e massimo 4096 byte di testo.');
            icbr_wa_template_payload('393331234567', $name, $language, array_fill(0, count($mapping), 'esempio'));
            preg_match_all('/\{\{([1-9][0-9]*)\}\}/', $body, $matches);
            $positions = array_values(array_unique(array_map('intval', $matches[1])));
            sort($positions);
            $expected = $mapping ? range(1, count($mapping)) : [];
            $without = preg_replace('/\{\{([1-9][0-9]*)\}\}/', '', $body);
            if ($positions !== $expected || str_contains($without, '{{') || str_contains($without, '}}')) throw new InvalidArgumentException('I segnaposto devono essere {{1}}, {{2}}, ecc., senza salti e coerenti con i parametri.');
            $active = ($_POST['attivo'] ?? '') === '1';
            if ($active && ($_POST['verificato_meta'] ?? '') !== '1') throw new InvalidArgumentException('Per attivare il modello devi confermare la verifica manuale su Meta.');
            $pdo->beginTransaction();
            $s = $pdo->prepare('INSERT INTO wa_modelli (nome_meta,lingua,testo_anteprima,parametri_json,attivo,verificato_da,verificato_il) VALUES (?,?,?,?,?,?,?)');
            $s->execute([$name,$language,$body,json_encode($mapping, JSON_THROW_ON_ERROR),(int)$active,$active ? $actor : null,$active ? date('Y-m-d H:i:s') : null]);
            audit_event('whatsapp','modello_creato','wa_modelli',(string)$pdo->lastInsertId(),['attivo'=>$active]);
            $pdo->commit();
            $target = '/impostazioni/whatsapp_catalogo.php?tab=modelli';
        } elseif ($action === 'disattiva') {
            $modelId = (int)filter_var($_POST['id_modello'] ?? 0, FILTER_VALIDATE_INT);
            if ($modelId < 1) throw new InvalidArgumentException('Modello non valido.');
            $pdo->beginTransaction();
            $s = $pdo->prepare('UPDATE wa_modelli SET attivo=0 WHERE id_modello=? AND attivo=1');
            $s->execute([$modelId]);
            if ($s->rowCount()) audit_event('whatsapp','modello_disattivato','wa_modelli',(string)$modelId);
            $pdo->commit();
            $target = '/impostazioni/whatsapp_catalogo.php?tab=modelli';
        } else { throw new InvalidArgumentException('Operazione non valida.'); }
        $_SESSION['wa_catalog_nonce'] = bin2hex(random_bytes(24));
        redirect_with_message($target, 'Salvataggio completato. Nessun messaggio inviato.');
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = 'Operazione non riuscita. Verifica il database e ricarica prima di riprovare.';
    }
}
$csrf = generate_csrf_token();
$nonce = $_SESSION['wa_catalog_nonce'];
$page_title = 'Consensi e modelli WhatsApp';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header"><h1>Consensi e modelli Meta</h1><a class="btn btn-secondary" href="<?= u('/messaggi/index.php') ?>">Centro WhatsApp</a></div>
<nav><a class="btn btn-secondary" href="?tab=consensi">Consensi soci</a> <a class="btn btn-secondary" href="?tab=modelli">Modelli Meta</a> <a class="btn btn-secondary" href="<?= u('/impostazioni/whatsapp.php') ?>">Configurazione Meta</a></nav>
<p>Sezione riservata all’admin. Nessuna operazione in questa pagina invia messaggi.</p>
<?php foreach ($errors as $error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endforeach; ?>
<?php if (!$ready): ?>
<div class="flash flash-error">Applica la migrazione 018_whatsapp_api.sql prima di utilizzare questa sezione.</div>
<?php elseif ($tab === 'consensi'): ?>
<?php
$q = is_string($_GET['q'] ?? null) ? substr(trim($_GET['q']), 0, 100) : '';
$page = max(1, min(100000, (int)filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT)));
$offset = ($page - 1) * 50;
$s = $pdo->prepare('SELECT s.id_socio,s.nome,s.cognome,s.telefono,c.telefono AS telefono_consenso,c.stato FROM soci s LEFT JOIN wa_consensi c ON c.id_socio=s.id_socio WHERE s.attivo_record=1 AND (s.nome LIKE ? OR s.cognome LIKE ?) ORDER BY s.cognome,s.nome,s.id_socio LIMIT 51 OFFSET ' . $offset);
$s->execute(['%' . $q . '%','%' . $q . '%']);
$people = $s->fetchAll(PDO::FETCH_ASSOC);
$more = count($people) > 50;
$people = array_slice($people, 0, 50);
?>
<form method="get" class="form-card"><input type="hidden" name="tab" value="consensi"><label for="q">Cerca nome o cognome</label><input id="q" name="q" value="<?= h($q) ?>" maxlength="100"><button class="btn">Cerca</button></form>
<table class="data-table"><thead><tr><th>Socio</th><th>Telefono</th><th>Consenso registrato</th><th>Azione</th></tr></thead><tbody>
<?php foreach ($people as $p): $match = wa_phone($p['telefono']) === ($p['telefono_consenso'] ?? null); ?>
<tr><td><?= h($p['cognome'] . ' ' . $p['nome']) ?></td><td><?= h($p['telefono']) ?></td><td><?= h($p['stato'] ?? 'non registrato') ?><?= $p['stato'] && !$match ? ' — numero diverso: consenso non riutilizzabile' : '' ?></td><td><a href="?tab=consensi&amp;id=<?= (int)$p['id_socio'] ?>">Gestisci</a></td></tr>
<?php endforeach; ?>
</tbody></table>
<p>Pagina <?= $page ?>, massimo 50 soci per pagina. <?php if ($page > 1): ?><a href="?tab=consensi&amp;q=<?= rawurlencode($q) ?>&amp;page=<?= $page-1 ?>">Precedente</a><?php endif; ?> <?php if ($more): ?><a href="?tab=consensi&amp;q=<?= rawurlencode($q) ?>&amp;page=<?= $page+1 ?>">Successiva</a><?php endif; ?></p>
<?php if ($id > 0):
$s = $pdo->prepare('SELECT id_socio,nome,cognome,telefono FROM soci WHERE id_socio=?'); $s->execute([$id]); $person = $s->fetch(PDO::FETCH_ASSOC);
if ($person):
$s = $pdo->prepare('SELECT telefono,stato,fonte,updated_at FROM wa_consensi WHERE id_socio=?'); $s->execute([$id]); $consent = $s->fetch(PDO::FETCH_ASSOC) ?: null;
$s = $pdo->prepare('SELECT telefono,stato,fonte,created_at,registrato_da FROM wa_consensi_eventi WHERE id_socio=? ORDER BY id_evento DESC LIMIT 50'); $s->execute([$id]); $history = $s->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="admin-panel"><h2><?= h($person['cognome'] . ' ' . $person['nome']) ?></h2><p>Numero attuale normalizzato: <?= h(wa_phone($person['telefono']) ?? 'non valido') ?>. La presenza del telefono non equivale al consenso. Registra solo riscontri effettivamente acquisiti.</p>
<form method="post" class="form-card"><input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="nonce" value="<?= h($nonce) ?>"><input type="hidden" name="azione" value="consenso"><input type="hidden" name="id_socio" value="<?= $id ?>"><input type="hidden" name="telefono_snapshot" value="<?= h(wa_phone($person['telefono']) ?? '') ?>"><input type="hidden" name="revisione" value="<?= h(wa_catalog_revision($consent)) ?>">
<div class="form-group"><label for="stato">Nuovo stato</label><select id="stato" name="stato" required><option value="">Scegli esplicitamente</option><option value="concesso">Consenso concesso sul numero attuale</option><option value="revocato">Consenso revocato sul numero precedentemente registrato</option></select></div>
<div class="form-group"><label for="fonte">Fonte e riferimento del riscontro o della revoca</label><input id="fonte" name="fonte" maxlength="255" required><small>Indica un riferimento rintracciabile e la data del riscontro. Non inserire dati sensibili o credenziali.</small></div>
<label><input type="checkbox" name="conferma" value="1"> Confermo un riscontro del consenso per questo socio e numero (obbligatorio per concederlo).</label><div class="form-actions"><button class="btn">Registra stato</button></div></form>
<h3>Ultimi 50 eventi</h3><table class="data-table"><thead><tr><th>Data</th><th>Numero</th><th>Stato</th><th>Fonte</th><th>ID operatore</th></tr></thead><tbody><?php foreach ($history as $event): ?><tr><td><?= h($event['created_at']) ?></td><td><?= h($event['telefono']) ?></td><td><?= h($event['stato']) ?></td><td><?= h($event['fonte']) ?></td><td><?= h((string)$event['registrato_da']) ?></td></tr><?php endforeach; ?></tbody></table></section>
<?php else: ?><p>Socio non trovato.</p><?php endif; endif; ?>
<?php else: ?>
<section class="admin-panel"><h2>Registra un modello Meta</h2><p>Solo modelli con corpo testuale e parametri posizionali, senza header o pulsanti. Questi modelli sono separati dai template assistiti. Copia esattamente il corpo approvato da Meta: questa pagina non crea né verifica modelli su Meta.</p>
<form method="post" class="form-card"><input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="nonce" value="<?= h($nonce) ?>"><input type="hidden" name="azione" value="modello">
<div class="form-group"><label for="nome_meta">Nome esatto del modello Meta</label><input id="nome_meta" name="nome_meta" maxlength="512" pattern="[a-z0-9_]+" required></div>
<div class="form-group"><label for="lingua">Codice lingua esatto su Meta</label><input id="lingua" name="lingua" maxlength="10" placeholder="it" required></div>
<div class="form-group"><label for="testo">Corpo testuale con {{1}}, {{2}}, ecc.</label><textarea id="testo" name="testo" rows="5" maxlength="4096" required></textarea></div>
<div class="form-group"><label for="parametri">Campi nell’ordine dei parametri, separati da virgola</label><input id="parametri" name="parametri" placeholder="nome, residuo, stagione"><small>Ammessi: nome, cognome, nome_completo, telefono, quota, pagato, residuo, stagione, data. Vuoto per modelli senza parametri.</small></div>
<label><input type="checkbox" name="attivo" value="1"> Rendi selezionabile nel gestionale</label><br><label><input type="checkbox" name="verificato_meta" value="1"> Ho verificato manualmente approvazione, account Meta, lingua, corpo e ordine dei parametri.</label>
<p>Ogni salvataggio crea una nuova versione locale. Per correggere un modello registra una nuova versione e disattiva quella precedente; non si modificano i modelli già usati nei lotti.</p><button class="btn">Registra modello</button></form></section>
<?php $models = $pdo->query('SELECT * FROM wa_modelli ORDER BY id_modello DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC); ?>
<section class="admin-panel"><h2>Ultime 100 versioni locali</h2><table class="data-table"><thead><tr><th>ID e modello</th><th>Corpo e parametri</th><th>Stato locale</th><th>Azione</th></tr></thead><tbody><?php foreach ($models as $m): ?><tr><td>#<?= (int)$m['id_modello'] ?> <?= h($m['nome_meta']) ?> (<?= h($m['lingua']) ?>)</td><td><pre style="white-space:pre-wrap"><?= h($m['testo_anteprima']) ?></pre><code><?= h($m['parametri_json']) ?></code></td><td><?= $m['attivo'] ? 'Selezionabile; verifica Meta manuale' : 'Inattivo' ?><?php if ($m['verificato_il']): ?><br><?= h($m['verificato_il']) ?>, operatore #<?= (int)$m['verificato_da'] ?><?php endif; ?></td><td><?php if ($m['attivo']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="nonce" value="<?= h($nonce) ?>"><input type="hidden" name="azione" value="disattiva"><input type="hidden" name="id_modello" value="<?= (int)$m['id_modello'] ?>"><button class="btn btn-secondary">Disattiva</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
