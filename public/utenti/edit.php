<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login();
if (!security_ready()) render_error_page('Aggiornamento necessario','La gestione del direttivo richiede la migrazione 010. Nessuna modifica automatica al database.',503);
$pdo = get_db_connection();
$id = max(0,(int)($_GET['id'] ?? 0));
$user = ['nome'=>'','cognome'=>'','username'=>'','etichetta_ruolo'=>'Membro del direttivo','ruolo'=>'segreteria','attivo'=>1,'permessi'=>[],'versione_sessione'=>0];
if ($id) {
    $stmt = $pdo->prepare('SELECT u.*,a.etichetta_ruolo,a.permessi_json,a.versione_sessione FROM utenti_admin u LEFT JOIN utenti_accessi a ON a.id_utente=u.id_utente WHERE u.id_utente=?');
    $stmt->execute([$id]); $user = $stmt->fetch();
    if (!$user) render_error_page('Account non trovato','Controlla l’elenco del direttivo.');
    $decoded = json_decode($user['permessi_json'] ?? '[]',true);
    $user['permessi'] = normalise_permissions(is_array($decoded) ? $decoded : []);
    $user['versione_sessione'] = (int)($user['versione_sessione'] ?? 0);
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $candidate = $user;
    foreach (['nome','cognome','username','etichetta_ruolo'] as $key) $candidate[$key] = trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : '');
    $candidate['ruolo'] = isset($_POST['amministratore']) ? 'admin' : 'segreteria';
    $candidate['attivo'] = isset($_POST['attivo']) ? 1 : 0;
    $candidate['permessi'] = normalise_permissions(is_array($_POST['permessi'] ?? null) ? $_POST['permessi'] : []);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $version = filter_var($_POST['versione'] ?? null,FILTER_VALIDATE_INT);
    foreach (['nome'=>'Nome','cognome'=>'Cognome','etichetta_ruolo'=>'Etichetta ruolo'] as $key=>$label) {
        if ($candidate[$key] === '' || mb_strlen($candidate[$key]) > 100) $errors[] = "$label: inserisci da 1 a 100 caratteri.";
    }
    if (!preg_match('/\A[a-zA-Z0-9._-]{3,80}\z/',$candidate['username'])) $errors[] = 'Username: da 3 a 80 caratteri, solo lettere, numeri, punto, trattino e underscore.';
    if ((!$id || $password !== '') && (mb_strlen($password) < 12 || strlen($password) > 72)) $errors[] = 'La password deve contenere almeno 12 caratteri e non superare 72 byte.';
    if (!$candidate['attivo'] && $id === current_user()['id_utente']) $errors[] = 'Non puoi disattivare il tuo stesso account.';
    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $all = $pdo->query('SELECT id_utente,ruolo,attivo FROM utenti_admin ORDER BY id_utente FOR UPDATE')->fetchAll();
            $actor = array_filter($all,fn($u)=>(int)$u['id_utente']===current_user()['id_utente'] && $u['ruolo']==='admin' && $u['attivo']);
            if (!$actor) throw new RuntimeException('Il tuo accesso amministratore non è più valido. Accedi di nuovo.');
            $admins = count(array_filter($all,fn($u)=>$u['ruolo']==='admin' && (int)$u['attivo']===1));
            $fresh = null;
            if ($id) {
                $stmt = $pdo->prepare('SELECT u.ruolo,u.attivo,a.versione_sessione FROM utenti_admin u LEFT JOIN utenti_accessi a ON a.id_utente=u.id_utente WHERE u.id_utente=?');
                $stmt->execute([$id]); $fresh = $stmt->fetch();
                if (!$fresh || (int)($fresh['versione_sessione'] ?? 0) !== $version) throw new RuntimeException('Questo account è stato modificato nel frattempo. Ricarica la pagina prima di salvare.');
                if ($fresh['ruolo']==='admin' && $fresh['attivo'] && $admins<=1 && ($candidate['ruolo']!=='admin' || !$candidate['attivo'])) throw new RuntimeException('Deve restare almeno un amministratore attivo.');
            }
            $params = [$candidate['nome'],$candidate['cognome'],$candidate['username'],$candidate['ruolo'],$candidate['attivo']];
            if ($id) {
                $sql = 'UPDATE utenti_admin SET nome=?,cognome=?,username=?,ruolo=?,attivo=?';
                if ($password !== '') { $sql .= ',password_hash=?'; $params[] = password_hash($password,PASSWORD_DEFAULT); }
                $sql .= ' WHERE id_utente=?'; $params[] = $id;
                $pdo->prepare($sql)->execute($params);
            } else {
                $pdo->prepare('INSERT INTO utenti_admin (nome,cognome,username,ruolo,attivo,password_hash) VALUES (?,?,?,?,?,?)')->execute([...$params,password_hash($password,PASSWORD_DEFAULT)]);
                $id = (int)$pdo->lastInsertId();
            }
            $newVersion = ((int)($fresh['versione_sessione'] ?? 0)) + 1;
            $pdo->prepare('INSERT INTO utenti_accessi (id_utente,etichetta_ruolo,permessi_json,versione_sessione) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE etichetta_ruolo=VALUES(etichetta_ruolo),permessi_json=VALUES(permessi_json),versione_sessione=VALUES(versione_sessione)')
                ->execute([$id,$candidate['etichetta_ruolo'],json_encode($candidate['permessi'],JSON_THROW_ON_ERROR),$newVersion]);
            $pdo->commit();
            if ($id === current_user()['id_utente']) {
                $_SESSION['user']['versione_sessione'] = $newVersion;
                $_SESSION['user']['ruolo'] = $candidate['ruolo'];
            }
            redirect_with_message('/utenti/list.php','Account salvato. Le sessioni precedenti della persona sono state revocate.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e instanceof PDOException && $e->getCode()==='23000' ? 'Questo username è già utilizzato.' : ($e instanceof RuntimeException ? $e->getMessage() : 'Salvataggio non riuscito.');
        }
    }
    $user = $candidate;
}
$page_title = $id ? 'Gestisci account' : 'Nuovo account';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header"><div><span class="admin-eyebrow">DIRETTIVO / ACCESSI PERSONALI</span><h1><?= h($page_title) ?></h1><p class="muted">Ogni persona vede e gestisce solo ciò che le assegni.</p></div><a class="btn btn-secondary" href="<?= u('/utenti/list.php') ?>">Torna al direttivo</a></div>
<?php if ($errors): ?><div class="flash flash-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="account-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="versione" value="<?= (int)$user['versione_sessione'] ?>">
<section class="admin-panel"><h2>La persona</h2><div class="account-grid">
<?php foreach (['nome'=>'Nome','cognome'=>'Cognome','username'=>'Username','etichetta_ruolo'=>'Etichetta ruolo'] as $key=>$label): ?><div><label for="<?= $key ?>"><?= $label ?></label><input id="<?= $key ?>" name="<?= $key ?>" value="<?= h($user[$key]) ?>" maxlength="<?= $key==='username' ? 80 : 100 ?>" required autocomplete="<?= $key==='username' ? 'off' : 'on' ?>"></div><?php endforeach; ?>
<div class="account-wide"><label for="password"><?= $id ? 'Nuova password, solo se vuoi cambiarla' : 'Password iniziale' ?></label><input id="password" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" <?= $id ? '' : 'required' ?>><p class="note">Almeno 12 caratteri. Comunicala con un canale sicuro e individuale: non verrà mostrata nell’elenco o nei log.</p></div>
</div><label class="permission-check"><input type="checkbox" name="attivo" value="1" <?= $user['attivo'] ? 'checked' : '' ?>>Account attivo</label><label class="permission-check admin-grant"><input id="amministratore" type="checkbox" name="amministratore" value="1" <?= $user['ruolo']==='admin' ? 'checked' : '' ?>><span>Amministratore completo<small>Può gestire anche utenti e permessi. Non assegnarlo se non necessario.</small></span></label></section>
<section class="admin-panel"><span class="admin-eyebrow">AUTORIZZAZIONI</span><h2>Cosa può fare</h2><p class="muted">La dashboard è sempre accessibile, senza mostrare dati di moduli non abilitati. Il ruolo scritto sopra non aggiunge permessi automaticamente.</p>
<div class="permission-grid"><?php foreach (permission_catalog() as $module=>[$label,$actions]): ?><fieldset class="permission-module"><legend><?= h($label) ?></legend><?php foreach ($actions as $action=>$text): $permission="$module.$action"; ?><label class="permission-check"><input type="checkbox" name="permessi[]" value="<?= $permission ?>" <?= in_array($permission,$user['permessi'],true) ? 'checked' : '' ?>><span><?= h($text) ?></span></label><?php endforeach; ?></fieldset><?php endforeach; ?></div></section>
<div class="cms-save"><p>Il salvataggio aggiorna i permessi e revoca gli accessi precedenti.</p><button class="btn" type="submit">Salva account e permessi</button></div>
</form>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
