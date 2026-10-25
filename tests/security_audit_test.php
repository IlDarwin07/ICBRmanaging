<?php
// Solo database di prova esplicitamente selezionati. Ogni fixture è annullata.
if (PHP_SAPI !== 'cli' || !preg_match('/_(preview|test)$/',getenv('ICBR_DB_NAME') ?: '')) exit("Usare ICBR_DB_NAME terminante in _preview o _test.\n");
require __DIR__.'/../includes/auth.php';
$pdo=get_db_connection();
function check(bool $value,string $label): void { if (!$value) throw new RuntimeException($label); echo "OK $label\n"; }
check(security_ready(),'schema sicurezza disponibile');
check(normalise_permissions(['utenti.manage','evil','soci.write',['bad']])===['soci.write','soci.view'],'whitelist e dipendenze permessi');
$before=(int)$pdo->query('SELECT COUNT(*) FROM registro_attivita')->fetchColumn();
$pdo->beginTransaction();
for ($i=0;$i<5;$i++) audit_event('sicurezza','test_transazione');
$username='test_'.bin2hex(random_bytes(5));
$pdo->prepare('INSERT INTO utenti_admin(nome,cognome,username,password_hash,ruolo) VALUES (?,?,?,?,?)')->execute(['Test','Rollback',$username,password_hash('Non-salvare-nei-log-2026!',PASSWORD_DEFAULT),'segreteria']);
$id=(int)$pdo->lastInsertId();
$stmt=$pdo->prepare('SELECT id_utente FROM utenti_admin WHERE username=?');$stmt->execute([$username]);
check($id===(int)$stmt->fetchColumn(),'lastInsertId conserva id dominio dopo audit');
$count=(int)$pdo->query('SELECT COUNT(*) FROM registro_attivita')->fetchColumn();
$pdo->prepare('UPDATE utenti_admin SET username=username WHERE id_utente=?')->execute([$id]);
check((int)$pdo->query('SELECT COUNT(*) FROM registro_attivita')->fetchColumn()===$count,'nessun evento per update senza modifiche');
$last=$pdo->query('SELECT * FROM registro_attivita ORDER BY id_evento DESC LIMIT 1')->fetch();
check($last['id_entita']===(string)$id && !str_contains(json_encode($last),'Non-salvare') && !str_contains(json_encode($last),'$2y$'),'riferimento corretto e assenza password');
$pdo->rollBack();
check((int)$pdo->query('SELECT COUNT(*) FROM registro_attivita')->fetchColumn()===$before,'rollback annulla anche eventi audit');
$stmt->execute([$username]);check(!$stmt->fetch(),'rollback annulla account');
$pdo->beginTransaction();
$pdo->prepare('INSERT INTO utenti_admin(nome,cognome,username,password_hash,ruolo) VALUES (?,?,?,?,?)')->execute(['Test','Rollback',$username,'test-hash','segreteria']);
try { $pdo->record(str_repeat('x',100),'errore_simulato'); } catch (PDOException $expected) {}
try { $pdo->commit(); throw new LogicException('Il commit doveva fallire.'); }
catch (RuntimeException $expected) { check(!$pdo->inTransaction(),'errore audit impedisce commit'); }
$stmt->execute([$username]);check(!$stmt->fetch(),'errore audit annulla modifica al dominio');
check((int)$pdo->query('SELECT COUNT(*) FROM registro_attivita')->fetchColumn()===$before,'nessun evento parziale dopo errore');
echo "Tutti i controlli superati.\n";
