<?php
/**
 * Uso da riga di comando:
 *   php bin/reset_admin_password.php admin NuovaPasswordSicura
 *
 * Crea l'utente se non esiste, altrimenti aggiorna la password.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/database.php';

if ($argc < 3) {
    fwrite(STDERR, "Uso: php bin/reset_admin_password.php <username> <password>\n");
    exit(1);
}

[$script, $username, $password] = $argv;

$pdo = get_db_connection();
$pdo->beginTransaction();
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('SELECT id_utente,ruolo FROM utenti_admin WHERE username = :u');
$stmt->execute(['u' => $username]);
$esistente = $stmt->fetch();

if ($esistente) {
    $update = $pdo->prepare('UPDATE utenti_admin SET password_hash = :hash, attivo = 1 WHERE username = :u');
    $update->execute(['hash' => $hash, 'u' => $username]);
    $message = "Password aggiornata per l'utente '{$username}'.\n";
} else {
    $insert = $pdo->prepare(
        'INSERT INTO utenti_admin (nome, cognome, username, password_hash, ruolo, attivo)
         VALUES (:nome, :cognome, :username, :hash, :ruolo, 1)'
    );
    $insert->execute([
        'nome'     => 'Admin',
        'cognome'  => 'Sistema',
        'username' => $username,
        'hash'     => $hash,
        'ruolo'    => 'admin',
    ]);
    $message = "Utente '{$username}' creato con ruolo admin.\n";
}

if ($pdo->securityReady()) {
    $id = $esistente ? (int)$esistente['id_utente'] : (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO utenti_accessi (id_utente,etichetta_ruolo,permessi_json,versione_sessione) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE versione_sessione=versione_sessione+1')
        ->execute([$id,ucfirst($esistente['ruolo'] ?? 'admin'),'[]']);
}
$pdo->commit();
echo $message;
