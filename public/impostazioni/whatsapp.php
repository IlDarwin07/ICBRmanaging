<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/whatsapp_config.php';
start_secure_session();
require_role(['admin']);
$pdo = get_db_connection();
$ready = icbr_wa_settings_ready($pdo);
$errors = [];
$values = icbr_wa_read_settings($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    if (!$ready) {
        $errors[] = 'Applica prima la migrazione 015_impostazioni.sql.';
    } else {
        try {
            $values = icbr_wa_validate_settings($_POST);
            icbr_wa_save_settings($pdo, $values, (int)current_user()['id_utente']);
            audit_event('impostazioni', 'whatsapp_configurazione_modificata', 'impostazioni', 'whatsapp');
            redirect_with_message('/impostazioni/whatsapp.php', 'Configurazione salvata. Nessun messaggio inviato.');
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        } catch (PDOException $e) {
            $errors[] = 'Salvataggio non riuscito. Verifica il database; nessun invio è stato effettuato.';
        }
    }
}
$token = getenv('ICBR_WA_ACCESS_TOKEN');
$tokenPresent = is_string($token) && trim($token) !== '';
unset($token);
$csrf = generate_csrf_token();
$page_title = 'Configurazione WhatsApp';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header"><h1>Configurazione WhatsApp</h1><a class="btn btn-secondary" href="<?= u('/messaggi/index.php') ?>">Centro WhatsApp</a></div>
<div class="workflow-note">Solo l’amministratore può accedere. Questa pagina salva i dati di configurazione: non collega automaticamente l’account, non verifica le credenziali e non invia messaggi. L’invio API non è ancora disponibile.</div>
<?php if ($errors): ?><div class="flash flash-error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>
<?php if (!$ready): ?><div class="flash flash-error">Tabella impostazioni assente: applica la migrazione 015_impostazioni.sql prima di salvare.</div><?php endif; ?>
<form method="post" class="form-card">
<input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
<div class="form-group"><label for="phone_number_id">Phone Number ID Meta (non il numero di telefono)</label><input id="phone_number_id" name="phone_number_id" inputmode="numeric" maxlength="40" value="<?= h($values['phone_number_id']) ?>"></div>
<div class="form-group"><label for="business_account_id">WhatsApp Business Account ID</label><input id="business_account_id" name="business_account_id" inputmode="numeric" maxlength="40" value="<?= h($values['business_account_id']) ?>"></div>
<div class="form-group"><label for="graph_version">Versione Graph API supportata dall’app Meta</label><input id="graph_version" name="graph_version" maxlength="8" value="<?= h($values['graph_version']) ?>"><small>Inserisci la versione indicata nella console Meta. Non viene verificata online da questa pagina.</small></div>
<button class="btn" type="submit" <?= $ready ? '' : 'disabled' ?>>Salva configurazione</button>
</form>
<section class="admin-panel"><h2>Token di accesso</h2><p>Stato locale: <?= $tokenPresent ? 'presente sul server, validità non verificata' : 'non configurato' ?>.</p><p>Il token va configurato dall’amministratore del server nella variabile d’ambiente <code>ICBR_WA_ACCESS_TOKEN</code>. Non inserirlo su GitHub, in chat o in file pubblici. Questa pagina non lo mostra e non lo salva nel database.</p><p>Campi vuoti consentono di rimuovere gli identificativi salvati. Il numero resta da abilitare attraverso il percorso ufficiale Meta; salvare questi ID non completa l’attivazione.</p></section>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
