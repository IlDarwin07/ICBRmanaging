<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login();
$page_title = 'Il direttivo';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header"><div><span class="admin-eyebrow">PERSONE / ACCESSI</span><h1>Il direttivo</h1><p class="muted">Un account per persona. Il ruolo racconta il compito, i permessi definiscono l’accesso.</p></div><a class="btn" href="<?= u('/utenti/edit.php') ?>">Crea un account</a></div>
<?php if (!security_ready()): ?><div class="flash flash-error">Prima di gestire gli account è necessaria la migrazione 010. Nessuna modifica automatica al database.</div><?php else:
$users = get_db_connection()->query('SELECT u.id_utente,u.nome,u.cognome,u.username,u.ruolo,u.attivo,u.ultimo_accesso,a.etichetta_ruolo,a.permessi_json FROM utenti_admin u LEFT JOIN utenti_accessi a ON a.id_utente=u.id_utente ORDER BY u.attivo DESC,u.cognome,u.nome')->fetchAll();
?>
<table class="data-table"><thead><tr><th>Persona</th><th>Username</th><th>Ruolo nel direttivo</th><th>Accesso</th><th>Ultimo accesso</th><th></th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr><td><?= h($user['cognome'].' '.$user['nome']) ?></td><td><?= h($user['username']) ?></td><td><?= h($user['etichetta_ruolo'] ?: ucfirst($user['ruolo'])) ?></td><td><span class="badge <?= $user['attivo'] ? 'badge-success' : 'badge-warning' ?>"><?= !$user['attivo'] ? 'Disattivato' : ($user['ruolo'] === 'admin' ? 'Amministratore completo' : 'Permessi personalizzati') ?></span></td><td><?= h($user['ultimo_accesso'] ?: 'Mai') ?></td><td><a class="btn btn-secondary" href="<?= u('/utenti/edit.php?id='.(int)$user['id_utente']) ?>">Gestisci</a></td></tr><?php endforeach; ?>
</tbody></table><p class="note">Gli account si disattivano, non si cancellano: le attività restano attribuite alla persona che le ha eseguite.</p>
<?php endif; require __DIR__ . '/../../includes/layout_footer.php'; ?>
