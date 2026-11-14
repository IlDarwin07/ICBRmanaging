<?php
declare(strict_types=1);
if (getenv('ICBR_WA_DB_TEST') !== '1') { fwrite(STDERR, 'Test database disabilitato: usare solo un database temporaneo CI.' . PHP_EOL); exit(1); }
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ((int)$pdo->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = 'icbr_gestionale'")->fetchColumn() !== 0) throw new RuntimeException('Database già presente: test interrotto per sicurezza');
$pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$sql = file_get_contents(__DIR__ . '/../database/migrations/018_whatsapp_api.sql');
$pdo->exec($sql);
$pdo->exec($sql);
$pdo->beginTransaction();
try {
    $pdo->exec("INSERT INTO utenti_admin (nome,cognome,username,password_hash) VALUES ('Test','CI','wa_ci','not-a-login-hash')");
    $actor = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO soci (nome,cognome,telefono) VALUES ('Test','Socio','393331234567')");
    $socio = (int)$pdo->lastInsertId();
    if ((int)$pdo->query('SELECT COUNT(*) FROM wa_consensi')->fetchColumn() !== 0) throw new RuntimeException('Consenso creato implicitamente');
    $pdo->exec("INSERT INTO stagioni (codice_stagione) VALUES ('WA-CI')");
    $season = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO wa_modelli (nome_meta,lingua,testo_anteprima,parametri_json) VALUES ('test_ci','it','Avviso','[]')");
    $model = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO wa_lotti (token_hash,id_utente,id_stagione,id_modello,modello_json,filtri_json,scade_il) VALUES (?,?,?,?, '{}','{}',DATE_ADD(NOW(),INTERVAL 10 MINUTE))");
    $stmt->execute([hash('sha256','unit-test-token'),$actor,$season,$model]);
    $batch = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO wa_inviati (id_lotto,id_socio,telefono,payload_json,testo_anteprima) VALUES (?,?,?,'{}','Avviso')");
    $stmt->execute([$batch,$socio,'393331234567']);
    $blocked = false;
    try { $stmt->execute([$batch,$socio,'393331234567']); } catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) !== 1062) throw $e; $blocked = true; }
    if (!$blocked) throw new RuntimeException('Duplicato non bloccato');
    $claim = $pdo->prepare("UPDATE wa_inviati SET stato='in_corso',tentato_il=NOW() WHERE id_lotto=? AND stato='preparato'");
    $claim->execute([$batch]);
    if ($claim->rowCount() !== 1) throw new RuntimeException('Primo claim non acquisito');
    $claim->execute([$batch]);
    if ($claim->rowCount() !== 0) throw new RuntimeException('Doppio claim consentito');
} finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
echo 'OK: migrazione ripetibile, nessun consenso implicito, duplicati e doppio claim bloccati.' . PHP_EOL;
