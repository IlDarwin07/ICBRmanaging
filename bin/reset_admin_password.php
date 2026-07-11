<?php
/**
 * Uso da riga di comando:
 *   php bin/reset_admin_password.php admin NuovaPasswordSicura
 *
 * Crea l'utente se non esiste, altrimenti aggiorna la password.
 */

require_once __DIR__ . '/../config/database.php';

if ($argc < 3) {
    fwrite(STDERR, "Uso: php bin/reset_admin_password.php <username> <password>\n");
    exit(1);
}

[$script, $username, $password] = $argv;

$pdo = get_db_connection();
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('SELECT id_utente FROM utenti_admin WHERE username = :u');
$stmt->execute(['u' => $username]);
$esistente = $stmt->fetch();

if ($esistente) {
    $update = $pdo->prepare('UPDATE utenti_admin SET password_hash = :hash, attivo = 1 WHERE username = :u');
    $update->execute(['hash' => $hash, 'u' => $username]);
    echo "Password aggiornata per l'utente '{$username}'.\n";
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
    echo "Utente '{$username}' creato con ruolo admin.\n";
}
