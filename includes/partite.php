<?php
/**
 * Partite in sede e prenotazioni dei soci.
 * Funzioni condivise da gestionale (direttivo) e area soci.
 *
 * Ogni partita e' trasmessa in piu' sale. Per ogni sala il direttivo indica
 * l'intervallo di sedie (es. Sala Proiettore 1-20, Sala TV 21-32): la
 * numerazione e' unica per partita e la sala di una sedia deriva dal numero.
 */

require_once __DIR__ . '/functions.php';

const PARTITE_STATI = [
    'bozza'     => ['Bozza', 'badge-gray'],
    'aperta'    => ['Prenotazioni aperte', 'badge-green'],
    'chiusa'    => ['Prenotazioni chiuse', 'badge-orange'],
    'conclusa'  => ['Conclusa', 'badge-blue'],
    'annullata' => ['Annullata', 'badge-red'],
];

const PRENOTAZIONI_STATI = [
    'presente'  => ['Presente', 'badge-green'],
    'attesa'    => ['In lista d\'attesa', 'badge-orange'],
    'annullata' => ['Annullata', 'badge-gray'],
];

/** Transizioni di stato ammesse per una partita. */
const PARTITE_TRANSIZIONI = [
    'bozza'     => ['aperta', 'annullata'],
    'aperta'    => ['chiusa', 'annullata'],
    'chiusa'    => ['aperta', 'conclusa', 'annullata'],
    'conclusa'  => [],
    'annullata' => ['bozza'],
];

/** Numero massimo di sedia ammesso per una partita. */
const PARTITE_MAX_SEDIA = 500;

/** Capienza e nomi sale di una partita (alias p = partite, s = sale). Fallback: la sala principale. */
const PARTITE_SQL_CAPIENZA = "COALESCE((SELECT SUM(ps.posto_a - ps.posto_da + 1) FROM partite_sale ps WHERE ps.id_partita = p.id_partita), s.capienza)";
const PARTITE_SQL_SALE_NOME = "COALESCE((SELECT GROUP_CONCAT(sl.nome ORDER BY ps.posto_da SEPARATOR ' + ') FROM partite_sale ps JOIN sale sl ON sl.id_sala = ps.id_sala WHERE ps.id_partita = p.id_partita), s.nome)";

/** True se le migration 013 e 017 sono state applicate. */
function partite_ready(): bool
{
    static $ready = null;
    if ($ready === null) {
        $stmt = get_db_connection()->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('soci_account','sale','partite','prenotazioni','partite_sale')");
        $ready = (int)$stmt->fetchColumn() === 5;
    }
    return $ready;
}

function partite_require_ready(): void
{
    if (!partite_ready()) {
        render_error_page('Modulo partite non ancora installato', 'Applica le migrazioni database/migrations/013_partite_prenotazioni.sql e 017_partite_sale.sql e ricarica la pagina.', 503);
    }
}

function partita_stato_label(string $stato): string { return PARTITE_STATI[$stato][0] ?? $stato; }
function partita_stato_badge(string $stato): string { return PARTITE_STATI[$stato][1] ?? 'badge-gray'; }
function prenotazione_stato_label(string $stato): string { return PRENOTAZIONI_STATI[$stato][0] ?? $stato; }
function prenotazione_stato_badge(string $stato): string { return PRENOTAZIONI_STATI[$stato][1] ?? 'badge-gray'; }

function partita_data_label(?string $datetime): string
{
    if (!$datetime) return '—';
    $giorni = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];
    $ts = strtotime($datetime);
    return $giorni[(int)date('w', $ts)] . ' ' . date('d/m/Y \o\r\e H:i', $ts);
}

/** Converte il valore di un <input type="datetime-local"> in DATETIME SQL. */
function parse_datetime_local(?string $value): ?string
{
    $value = trim((string)$value);
    foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
        $d = DateTime::createFromFormat($format, $value);
        if ($d instanceof DateTime && $d->format($format) === $value) return $d->format('Y-m-d H:i:00');
    }
    return null;
}

function datetime_local_value(?string $sql): string
{
    return $sql ? date('Y-m-d\TH:i', strtotime($sql)) : '';
}

// -------------------------------------------------------------------------
// Sale
// -------------------------------------------------------------------------

function sale_list(bool $solo_attive = false): array
{
    $sql = 'SELECT * FROM sale' . ($solo_attive ? ' WHERE attiva = 1' : '') . ' ORDER BY nome';
    return get_db_connection()->query($sql)->fetchAll();
}

/**
 * Sale di una partita con intervallo di sedie, in ordine di numerazione.
 * Ogni riga: id_sala, sala_nome, posti_per_fila, posto_da, posto_a.
 * Se la partita non ha righe in partite_sale usa la sala principale (1..capienza).
 */
function partita_settori(int $id_partita): array
{
    $pdo = get_db_connection();
    $stmt = $pdo->prepare(
        'SELECT ps.id_sala, sl.nome AS sala_nome, sl.posti_per_fila, ps.posto_da, ps.posto_a
         FROM partite_sale ps JOIN sale sl ON sl.id_sala = ps.id_sala
         WHERE ps.id_partita = ? ORDER BY ps.posto_da'
    );
    $stmt->execute([$id_partita]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        $stmt = $pdo->prepare('SELECT s.id_sala, s.nome AS sala_nome, s.posti_per_fila, 1 AS posto_da, GREATEST(1, s.capienza) AS posto_a FROM partite p JOIN sale s ON s.id_sala = p.id_sala WHERE p.id_partita = ?');
        $stmt->execute([$id_partita]);
        $rows = $stmt->fetchAll();
    }
    foreach ($rows as &$r) {
        $r['id_sala'] = (int)$r['id_sala'];
        $r['posti_per_fila'] = max(1, (int)$r['posti_per_fila']);
        $r['posto_da'] = (int)$r['posto_da'];
        $r['posto_a'] = (int)$r['posto_a'];
    }
    unset($r);
    return $rows;
}

/** Tutti i numeri di sedia disponibili, in ordine. */
function partita_posti(array $settori): array
{
    $out = [];
    foreach ($settori as $s) {
        for ($n = (int)$s['posto_da']; $n <= (int)$s['posto_a']; $n++) $out[] = $n;
    }
    return $out;
}

/** Sala a cui appartiene la sedia $n, o null se il numero non esiste. */
function partita_settore_di_posto(array $settori, int $n): ?array
{
    foreach ($settori as $s) {
        if ($n >= (int)$s['posto_da'] && $n <= (int)$s['posto_a']) return $s;
    }
    return null;
}

function partita_nome_sala_posto(array $settori, int $n): string
{
    $s = partita_settore_di_posto($settori, $n);
    return $s ? (string)$s['sala_nome'] : '';
}

/** Es. "Sala Proiettore (sedie 1-20) · Sala TV (sedie 21-32)". */
function partita_settori_label(array $settori): string
{
    return implode(' · ', array_map(fn($s) => $s['sala_nome'] . ' (sedie ' . (int)$s['posto_da'] . '-' . (int)$s['posto_a'] . ')', $settori));
}

/**
 * Salva le sale della partita (sostituisce le precedenti) e libera le sedie
 * assegnate che non esistono piu'. Restituisce quante sedie sono state liberate.
 */
function partita_salva_settori(int $id_partita, array $settori): int
{
    return partite_transaction(function (PDO $pdo) use ($id_partita, $settori): int {
        $pdo->prepare('DELETE FROM partite_sale WHERE id_partita = ?')->execute([$id_partita]);
        $ins = $pdo->prepare('INSERT INTO partite_sale (id_partita, id_sala, posto_da, posto_a) VALUES (?, ?, ?, ?)');
        foreach ($settori as $s) {
            $ins->execute([$id_partita, (int)$s['id_sala'], (int)$s['posto_da'], (int)$s['posto_a']]);
        }
        $validi = array_flip(partita_posti($settori));
        $stmt = $pdo->prepare('SELECT id_prenotazione, numero_posto FROM prenotazioni WHERE id_partita = ? AND numero_posto IS NOT NULL');
        $stmt->execute([$id_partita]);
        $libera = $pdo->prepare('UPDATE prenotazioni SET numero_posto = NULL WHERE id_prenotazione = ?');
        $n = 0;
        foreach ($stmt->fetchAll() as $r) {
            if (!isset($validi[(int)$r['numero_posto']])) {
                $libera->execute([(int)$r['id_prenotazione']]);
                $n++;
            }
        }
        return $n;
    });
}

// -------------------------------------------------------------------------
// Tesseramento: chi può prenotare
// -------------------------------------------------------------------------

/** Stagione corrente: quella marcata attiva, altrimenti la più recente. */
function stagione_corrente(): ?array
{
    static $stagione = false;
    if ($stagione === false) {
        $stagione = get_db_connection()->query(
            'SELECT id_stagione, codice_stagione FROM stagioni ORDER BY attiva DESC, codice_stagione DESC LIMIT 1'
        )->fetch() ?: null;
    }
    return $stagione;
}

function socio_tesserato(int $id_socio): bool
{
    $stagione = stagione_corrente();
    if (!$stagione) return false;
    $stmt = get_db_connection()->prepare('SELECT 1 FROM tesseramenti WHERE id_socio = ? AND id_stagione = ? LIMIT 1');
    $stmt->execute([$id_socio, $stagione['id_stagione']]);
    return (bool)$stmt->fetchColumn();
}

// -------------------------------------------------------------------------
// Partite
// -------------------------------------------------------------------------

/** Dati partita. capienza e sala_nome sommano tutte le sale; 'settori' elenca gli intervalli di sedie. */
function partita_get(int $id, bool $for_update = false): ?array
{
    $stmt = get_db_connection()->prepare(
        'SELECT p.*, ' . PARTITE_SQL_SALE_NOME . ' AS sala_nome, ' . PARTITE_SQL_CAPIENZA . ' AS capienza, s.posti_per_fila,
                (SELECT COUNT(*) FROM prenotazioni r WHERE r.id_partita = p.id_partita AND r.stato = \'presente\') AS presenti,
                (SELECT COUNT(*) FROM prenotazioni r WHERE r.id_partita = p.id_partita AND r.stato = \'attesa\') AS in_attesa
         FROM partite p JOIN sale s ON s.id_sala = p.id_sala WHERE p.id_partita = ?' . ($for_update ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if (!$p) return null;
    $p['settori'] = partita_settori($id);
    return $p;
}

function partite_admin_list(string $filtro = 'prossime'): array
{
    $where = $filtro === 'passate' ? 'p.data_ora < NOW() - INTERVAL 4 HOUR' : 'p.data_ora >= NOW() - INTERVAL 4 HOUR';
    $order = $filtro === 'passate' ? 'DESC' : 'ASC';
    return get_db_connection()->query(
        'SELECT p.*, ' . PARTITE_SQL_SALE_NOME . ' AS sala_nome, ' . PARTITE_SQL_CAPIENZA . " AS capienza,
                SUM(r.stato = 'presente') AS presenti,
                SUM(r.stato = 'attesa') AS in_attesa,
                SUM(r.stato = 'presente' AND r.numero_posto IS NOT NULL) AS assegnati
         FROM partite p JOIN sale s ON s.id_sala = p.id_sala
         LEFT JOIN prenotazioni r ON r.id_partita = p.id_partita
         WHERE $where GROUP BY p.id_partita ORDER BY p.data_ora $order LIMIT 100"
    )->fetchAll();
}

/** Partite visibili al socio, con la sua eventuale prenotazione. Nessun dato di altri soci. */
function partite_per_socio(int $id_socio): array
{
    $stmt = get_db_connection()->prepare(
        'SELECT p.id_partita, p.titolo, p.competizione, p.data_ora, p.prenotazioni_chiudono, p.stato,
                p.solo_tesserati, p.note, ' . PARTITE_SQL_SALE_NOME . ' AS sala_nome, ' . PARTITE_SQL_CAPIENZA . " AS capienza,
                (SELECT COUNT(*) FROM prenotazioni x WHERE x.id_partita = p.id_partita AND x.stato = 'presente') AS presenti,
                r.stato AS mia_stato, r.numero_posto AS mio_posto
         FROM partite p JOIN sale s ON s.id_sala = p.id_sala
         LEFT JOIN prenotazioni r ON r.id_partita = p.id_partita AND r.id_socio = ?
         WHERE p.stato IN ('aperta','chiusa','annullata') AND p.data_ora >= NOW() - INTERVAL 4 HOUR
           AND (p.stato <> 'annullata' OR r.id_prenotazione IS NOT NULL)
         ORDER BY p.data_ora ASC"
    );
    $stmt->execute([$id_socio]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) $r['settori'] = partita_settori((int)$r['id_partita']);
    unset($r);
    return $rows;
}

function partita_prenotabile(array $p): bool
{
    return $p['stato'] === 'aperta' && strtotime($p['prenotazioni_chiudono']) > time();
}

/**
 * Valida i dati del form partita. Restituisce [dati puliti, errori].
 * Le sale arrivano come sala_da[id_sala] / sala_a[id_sala]; sala vuota = non usata.
 */
function partita_validate(array $in): array
{
    $errors = [];
    $data = [
        'titolo' => clean_text($in['titolo'] ?? '') ?? '',
        'competizione' => clean_text($in['competizione'] ?? ''),
        'data_ora' => parse_datetime_local($in['data_ora'] ?? ''),
        'prenotazioni_chiudono' => parse_datetime_local($in['prenotazioni_chiudono'] ?? ''),
        'solo_tesserati' => !empty($in['solo_tesserati']) ? 1 : 0,
        'note' => clean_text($in['note'] ?? ''),
    ];
    if ($data['titolo'] === '') $errors[] = 'Indica la partita (es. Inter - Milan).';
    if (mb_strlen($data['titolo']) > 150) $errors[] = 'Il titolo è troppo lungo (massimo 150 caratteri).';
    if ($data['competizione'] !== null && mb_strlen($data['competizione']) > 80) $errors[] = 'La competizione è troppo lunga.';
    if ($data['note'] !== null && mb_strlen($data['note']) > 500) $errors[] = 'Le note superano i 500 caratteri.';
    if (!$data['data_ora']) $errors[] = 'Data e ora della partita non valide.';
    if (!$data['prenotazioni_chiudono'] && $data['data_ora']) {
        // Default: le prenotazioni chiudono 2 ore prima del calcio d'inizio.
        $data['prenotazioni_chiudono'] = date('Y-m-d H:i:00', strtotime($data['data_ora']) - 7200);
    }
    if ($data['data_ora'] && $data['prenotazioni_chiudono'] && $data['prenotazioni_chiudono'] > $data['data_ora']) {
        $errors[] = 'La chiusura delle prenotazioni deve precedere l\'inizio della partita.';
    }

    $da_in = is_array($in['sala_da'] ?? null) ? $in['sala_da'] : [];
    $a_in = is_array($in['sala_a'] ?? null) ? $in['sala_a'] : [];
    $settori = [];
    foreach (sale_list(true) as $s) {
        $k = (int)$s['id_sala'];
        $da = is_scalar($da_in[$k] ?? null) ? trim((string)$da_in[$k]) : '';
        $a = is_scalar($a_in[$k] ?? null) ? trim((string)$a_in[$k]) : '';
        if ($da === '' && $a === '') continue;
        $nd = filter_var($da, FILTER_VALIDATE_INT);
        $na = filter_var($a, FILTER_VALIDATE_INT);
        if ($nd === false || $na === false || $nd < 1 || $na < $nd || $na > PARTITE_MAX_SEDIA) {
            $errors[] = $s['nome'] . ': indica la prima e l\'ultima sedia (numeri da 1 a ' . PARTITE_MAX_SEDIA . ', la seconda non minore della prima).';
            continue;
        }
        $settori[] = ['id_sala' => $k, 'sala_nome' => $s['nome'], 'posti_per_fila' => max(1, (int)$s['posti_per_fila']), 'posto_da' => $nd, 'posto_a' => $na];
    }
    usort($settori, fn($x, $y) => $x['posto_da'] <=> $y['posto_da']);
    for ($i = 1; $i < count($settori); $i++) {
        if ($settori[$i]['posto_da'] <= $settori[$i - 1]['posto_a']) {
            $errors[] = 'Le sedie di ' . $settori[$i - 1]['sala_nome'] . ' e ' . $settori[$i]['sala_nome'] . ' si sovrappongono: i numeri non possono ripetersi tra sale.';
            break;
        }
    }
    if (!$settori && !array_filter($errors, fn($e) => str_contains($e, 'sedia'))) $errors[] = 'Indica le sedie di almeno una sala.';
    $data['settori'] = $settori;
    $data['id_sala'] = $settori[0]['id_sala'] ?? 0;
    return [$data, $errors];
}

/**
 * Esegue $fn in una transazione; se una transazione è già aperta
 * (es. nei test o dentro un'altra operazione) partecipa a quella.
 */
function partite_transaction(callable $fn): mixed
{
    $pdo = get_db_connection();
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        if ($own) $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// -------------------------------------------------------------------------
// Prenotazioni (lato socio)
// -------------------------------------------------------------------------

/**
 * Conferma la presenza. Restituisce 'presente' oppure 'attesa'.
 * @throws DomainException con messaggio leggibile dal socio
 */
function prenotazione_conferma(int $id_partita, int $id_socio, bool $forza = false): string
{
    return partite_transaction(function (PDO $pdo) use ($id_partita, $id_socio, $forza) {
        $p = partita_get($id_partita, true); // blocca la riga: niente overbooking concorrente
        if (!$p) throw new DomainException('Partita non trovata.');
        if (!$forza) {
            if (!partita_prenotabile($p)) throw new DomainException('Le prenotazioni per questa partita sono chiuse.');
            if ((int)$p['solo_tesserati'] === 1 && !socio_tesserato($id_socio)) {
                throw new DomainException('Per questa partita serve il tesseramento della stagione in corso. Rinnova la tessera con il direttivo.');
            }
        } elseif (in_array($p['stato'], ['conclusa', 'annullata'], true)) {
            throw new DomainException('La partita è ' . strtolower(partita_stato_label($p['stato'])) . '.');
        }
        $stmt = $pdo->prepare('SELECT stato FROM prenotazioni WHERE id_partita = ? AND id_socio = ? FOR UPDATE');
        $stmt->execute([$id_partita, $id_socio]);
        $attuale = $stmt->fetchColumn();
        if ($attuale === 'presente' || $attuale === 'attesa') {
            return $attuale;
        }
        $stato = (int)$p['presenti'] < (int)$p['capienza'] ? 'presente' : 'attesa';
        // Se il socio aveva annullato, torna in coda dall'ultimo posto.
        $pdo->prepare(
            'INSERT INTO prenotazioni (id_partita, id_socio, stato) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE stato = VALUES(stato), numero_posto = NULL, created_at = NOW()'
        )->execute([$id_partita, $id_socio, $stato]);
        return $stato;
    });
}

/**
 * Annulla la presenza; se si libera un posto, promuove il primo in attesa.
 * Restituisce l'id_socio promosso (o null).
 */
function prenotazione_annulla(int $id_partita, int $id_socio, bool $forza = false): ?int
{
    return partite_transaction(function (PDO $pdo) use ($id_partita, $id_socio, $forza) {
        $p = partita_get($id_partita, true);
        if (!$p) throw new DomainException('Partita non trovata.');
        if (!$forza && !partita_prenotabile($p)) {
            throw new DomainException('Le prenotazioni sono chiuse: per annullare contatta il direttivo.');
        }
        $stmt = $pdo->prepare('SELECT stato FROM prenotazioni WHERE id_partita = ? AND id_socio = ? FOR UPDATE');
        $stmt->execute([$id_partita, $id_socio]);
        $attuale = $stmt->fetchColumn();
        if ($attuale === false || $attuale === 'annullata') {
            return null;
        }
        $pdo->prepare("UPDATE prenotazioni SET stato = 'annullata', numero_posto = NULL WHERE id_partita = ? AND id_socio = ?")
            ->execute([$id_partita, $id_socio]);
        $promosso = $attuale === 'presente' ? prenotazioni_promuovi_attesa($id_partita) : null;
        return $promosso;
    });
}

/** Promuove il primo socio in attesa se c'è posto. Da chiamare dentro una transazione. */
function prenotazioni_promuovi_attesa(int $id_partita): ?int
{
    $pdo = get_db_connection();
    $p = partita_get($id_partita);
    if (!$p || (int)$p['presenti'] >= (int)$p['capienza']) return null;
    $stmt = $pdo->prepare("SELECT id_socio FROM prenotazioni WHERE id_partita = ? AND stato = 'attesa' ORDER BY created_at, id_prenotazione LIMIT 1 FOR UPDATE");
    $stmt->execute([$id_partita]);
    $id_socio = $stmt->fetchColumn();
    if ($id_socio === false) return null;
    $pdo->prepare("UPDATE prenotazioni SET stato = 'presente' WHERE id_partita = ? AND id_socio = ?")->execute([$id_partita, $id_socio]);
    return (int)$id_socio;
}

// -------------------------------------------------------------------------
// Prenotazioni (lato direttivo)
// -------------------------------------------------------------------------

function prenotazioni_partita(int $id_partita): array
{
    $stmt = get_db_connection()->prepare(
        "SELECT r.*, s.nome, s.cognome, s.telefono, s.email
         FROM prenotazioni r JOIN soci s ON s.id_socio = r.id_socio
         WHERE r.id_partita = ?
         ORDER BY FIELD(r.stato,'presente','attesa','annullata'), r.numero_posto IS NULL, r.numero_posto, r.created_at, r.id_prenotazione"
    );
    $stmt->execute([$id_partita]);
    return $stmt->fetchAll();
}

/**
 * Assegna (o libera, con $numero = null) la sedia di un socio presente.
 * Il numero deve rientrare in una delle sale della partita; la sala e' quella del numero.
 * @throws DomainException
 */
function prenotazione_assegna_posto(int $id_partita, int $id_socio, ?int $numero): void
{
    try {
        partite_transaction(function (PDO $pdo) use ($id_partita, $id_socio, $numero): void {
            $p = partita_get($id_partita, true);
            if (!$p) throw new DomainException('Partita non trovata.');
            if (in_array($p['stato'], ['conclusa', 'annullata'], true)) throw new DomainException('La partita non è più modificabile.');
            if ($numero !== null && !partita_settore_di_posto($p['settori'], $numero)) {
                throw new DomainException('La sedia ' . $numero . ' non esiste per questa partita. Sedie disponibili: ' . partita_settori_label($p['settori']) . '.');
            }
            $stmt = $pdo->prepare("SELECT stato FROM prenotazioni WHERE id_partita = ? AND id_socio = ? FOR UPDATE");
            $stmt->execute([$id_partita, $id_socio]);
            if ($stmt->fetchColumn() !== 'presente') throw new DomainException('Si assegna la sedia solo ai soci con presenza confermata.');
            if ($numero !== null) {
                $stmt = $pdo->prepare('SELECT s.cognome FROM prenotazioni r JOIN soci s ON s.id_socio = r.id_socio WHERE r.id_partita = ? AND r.numero_posto = ? AND r.id_socio <> ?');
                $stmt->execute([$id_partita, $numero, $id_socio]);
                $occupante = $stmt->fetchColumn();
                if ($occupante !== false) throw new DomainException("La sedia $numero è già assegnata ($occupante). Liberala prima di riassegnarla.");
            }
            $pdo->prepare('UPDATE prenotazioni SET numero_posto = ? WHERE id_partita = ? AND id_socio = ?')->execute([$numero, $id_partita, $id_socio]);
        });
    } catch (PDOException $e) {
        // Due operatori sulla stessa sedia nello stesso istante: vince il primo.
        if ($e->getCode() === '23000') throw new DomainException('Sedia appena assegnata a un altro socio: ricarica la pagina.');
        throw $e;
    }
}

/** Assegna in ordine di prenotazione le sedie libere ai presenti senza posto. Restituisce quante. */
function prenotazioni_assegna_automatico(int $id_partita): int
{
    return partite_transaction(function (PDO $pdo) use ($id_partita) {
        $p = partita_get($id_partita, true);
        if (!$p || in_array($p['stato'], ['conclusa', 'annullata'], true)) throw new DomainException('La partita non è modificabile.');
        $stmt = $pdo->prepare('SELECT numero_posto FROM prenotazioni WHERE id_partita = ? AND numero_posto IS NOT NULL');
        $stmt->execute([$id_partita]);
        $occupati = array_flip(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        $liberi = array_values(array_filter(partita_posti($p['settori']), fn($n) => !isset($occupati[$n])));
        $stmt = $pdo->prepare("SELECT id_socio FROM prenotazioni WHERE id_partita = ? AND stato = 'presente' AND numero_posto IS NULL ORDER BY created_at, id_prenotazione");
        $stmt->execute([$id_partita]);
        $update = $pdo->prepare('UPDATE prenotazioni SET numero_posto = ? WHERE id_partita = ? AND id_socio = ?');
        $n = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id_socio) {
            if (!$liberi) break;
            $update->execute([array_shift($liberi), $id_partita, $id_socio]);
            $n++;
        }
        return $n;
    });
}

/** Testo WhatsApp con la sedia assegnata e la sala in cui si trova. */
function partita_messaggio_posto(array $partita, array $prenotazione): string
{
    $dove = $partita['sala_nome'];
    if ($prenotazione['numero_posto'] !== null) {
        $posto = 'la sedia n. ' . (int)$prenotazione['numero_posto'];
        $sala = partita_nome_sala_posto($partita['settori'] ?? [], (int)$prenotazione['numero_posto']);
        if ($sala !== '') $dove = $sala;
    } else {
        $posto = 'un posto (numero in arrivo)';
    }
    return 'Ciao ' . $prenotazione['nome'] . ', per ' . $partita['titolo'] . ' di ' . partita_data_label($partita['data_ora'])
        . ' in ' . $dove . ' ti abbiamo riservato ' . $posto . '. Ti aspettiamo in sede! Forza Inter 🖤💙';
}

/** Email al socio passato dalla lista d'attesa ai presenti (se l'invio è configurato). */
function partita_notifica_promozione(int $id_partita, int $id_socio): void
{
    require_once __DIR__ . '/mailer.php';
    if (!mail_enabled()) return;
    $p = partita_get($id_partita);
    $stmt = get_db_connection()->prepare('SELECT s.nome, a.email FROM soci s JOIN soci_account a ON a.id_socio = s.id_socio WHERE s.id_socio = ? AND a.attivo = 1');
    $stmt->execute([$id_socio]);
    $s = $stmt->fetch();
    if (!$p || !$s) return;
    send_mail($s['email'], 'Si è liberato un posto: ' . $p['titolo'],
        "Ciao {$s['nome']},\n\nsi è liberato un posto per {$p['titolo']} di " . partita_data_label($p['data_ora'])
        . " in {$p['sala_nome']}: la tua presenza ora è confermata.\n\nSe non puoi più venire, annulla dall'area soci:\n"
        . absolute_url('/soci-area/index.php') . "\n\nInter Club Javier Zanetti Brindisi\n");
}
