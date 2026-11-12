<?php
// Prenotazioni e area soci. Solo database di prova: tutto viene annullato con ROLLBACK.
// Uso: ICBR_DB_NAME=icbr_test php tests/partite_test.php  (migration 013 applicata)
if (PHP_SAPI !== 'cli' || !preg_match('/_(preview|test)$/', getenv('ICBR_DB_NAME') ?: '')) exit("Usare ICBR_DB_NAME terminante in _preview o _test.\n");
require __DIR__ . '/../includes/auth_soci.php';
$pdo = get_db_connection();
function check(bool $value, string $label): void { if (!$value) throw new RuntimeException($label); echo "OK $label\n"; }
function expect_domain(callable $fn, string $label): void { try { $fn(); } catch (DomainException $e) { check(true, $label); return; } throw new RuntimeException($label); }

check(partite_ready(), 'migration 013 applicata');
check(parse_datetime_local('2026-11-20T20:45') === '2026-11-20 20:45:00' && parse_datetime_local('20/11/2026') === null, 'parsing data e ora');

$pdo->beginTransaction();
try {
    $soci = [];
    for ($i = 1; $i <= 4; $i++) {
        $pdo->prepare('INSERT INTO soci (nome, cognome) VALUES (?, ?)')->execute(["Test$i", 'Prenotazioni']);
        $soci[] = (int)$pdo->lastInsertId();
    }
    $pdo->prepare("INSERT INTO sale (nome, capienza, posti_per_fila) VALUES ('Sala test " . bin2hex(random_bytes(3)) . "', 2, 2)")->execute();
    $sala = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO partite (titolo, data_ora, id_sala, prenotazioni_chiudono, solo_tesserati, stato)
                   VALUES ('Inter - Test', NOW() + INTERVAL 2 DAY, ?, NOW() + INTERVAL 1 DAY, 0, 'aperta')")->execute([$sala]);
    $partita = (int)$pdo->lastInsertId();

    check(prenotazione_conferma($partita, $soci[0]) === 'presente', 'primo socio presente');
    check(prenotazione_conferma($partita, $soci[0]) === 'presente', 'doppia conferma senza duplicati');
    check(prenotazione_conferma($partita, $soci[1]) === 'presente', 'secondo socio presente');
    check(prenotazione_conferma($partita, $soci[2]) === 'attesa', 'oltre capienza: lista d\'attesa');

    prenotazione_assegna_posto($partita, $soci[0], 1);
    expect_domain(fn() => prenotazione_assegna_posto($partita, $soci[1], 1), 'stessa sedia rifiutata');
    expect_domain(fn() => prenotazione_assegna_posto($partita, $soci[1], 3), 'sedia oltre capienza rifiutata');
    expect_domain(fn() => prenotazione_assegna_posto($partita, $soci[2], 2), 'niente sedia a chi è in attesa');

    check(prenotazione_annulla($partita, $soci[1]) === $soci[2], 'annullamento promuove il primo in attesa');
    check(prenotazioni_assegna_automatico($partita) === 1, 'assegnazione automatica delle sedie libere');
    $posti = array_column(array_filter(prenotazioni_partita($partita), fn($r) => $r['stato'] === 'presente'), 'numero_posto');
    sort($posti);
    check($posti === [1, 2], 'sedie 1 e 2 assegnate senza doppioni');

    $pdo->prepare('UPDATE partite SET prenotazioni_chiudono = NOW() - INTERVAL 1 MINUTE WHERE id_partita = ?')->execute([$partita]);
    expect_domain(fn() => prenotazione_conferma($partita, $soci[3]), 'prenotazioni chiuse per il socio');
    check(prenotazione_conferma($partita, $soci[3], true) === 'attesa', 'il direttivo può aggiungere a prenotazioni chiuse');

    $pdo->prepare('UPDATE partite SET solo_tesserati = 1, prenotazioni_chiudono = NOW() + INTERVAL 1 DAY WHERE id_partita = ?')->execute([$partita]);
    if (!socio_tesserato($soci[1])) expect_domain(fn() => prenotazione_conferma($partita, $soci[1]), 'partita riservata ai tesserati');

    // Account e token
    $email = 'test-' . bin2hex(random_bytes(4)) . '@example.invalid';
    $token = soci_account_invite($soci[0], $email);
    $row = $pdo->query('SELECT * FROM soci_account WHERE id_socio = ' . $soci[0])->fetch();
    check($row['token_hash'] === hash('sha256', $token) && !str_contains(json_encode($row), $token), 'token salvato solo come hash');
    try { soci_account_invite($soci[1], strtoupper($email)); throw new LogicException('email duplicata accettata'); }
    catch (InvalidArgumentException $e) { check(true, 'email già usata da un altro socio rifiutata'); }
    $account = soci_account_by_token($token);
    check($account && (int)$account['id_socio'] === $soci[0], 'token valido trovato');
    soci_account_set_password((int)$account['id_account'], 'password-di-prova-2026');
    check(soci_account_by_token($token) === null, 'token monouso');
    check(socio_password_errors('corta', 'corta') !== [] && socio_password_errors('abbastanza-lunga', 'diversa-lunga') !== [], 'regole password');
    $last = $pdo->query("SELECT dettagli_json FROM registro_attivita ORDER BY id_evento DESC LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
    check(!str_contains(implode(' ', array_filter($last)), 'password-di-prova'), 'nessuna password nel registro');

    // Separazione delle sessioni
    $_SESSION = ['socio' => ['id_socio' => $soci[0], 'id_account' => (int)$account['id_account']]];
    check(!is_logged_in() && current_user() === null, 'un socio non risulta collegato al gestionale');
    $_SESSION = [];
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
echo "Tutti i controlli superati.\n";
