<?php
/**
 * Famiglia (prenotare per i familiari) e quota dei soci sostenitori.
 * Richiede la migrazione 014_famiglia_sostenitori.sql.
 */
require_once __DIR__ . '/partite.php';

// Euro a partita, dovuti in sede solo dai soci sostenitori.
const PARTITA_QUOTA_SOSTENITORE = 3.00;

function famiglia_ready(): bool
{
    static $ready = null;
    if ($ready === null) {
        $n = (int)get_db_connection()->query(
            "SELECT (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'soci_famiglia')
                  + (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'prenotazioni' AND column_name = 'prenotata_da')"
        )->fetchColumn();
        $ready = $n === 2;
    }
    return $ready;
}

/** Soci per cui $id_titolare puo prenotare. */
function famiglia_familiari(int $id_titolare): array
{
    if (!famiglia_ready()) return [];
    $stmt = get_db_connection()->prepare(
        'SELECT s.id_socio, s.nome, s.cognome FROM soci_famiglia f
         JOIN soci s ON s.id_socio = f.id_familiare
         WHERE f.id_titolare = ? AND s.attivo_record = 1 ORDER BY s.cognome, s.nome'
    );
    $stmt->execute([$id_titolare]);
    return $stmt->fetchAll();
}

function famiglia_puo_prenotare_per(int $id_titolare, int $id_familiare): bool
{
    if (!famiglia_ready() || $id_titolare === $id_familiare) return false;
    $stmt = get_db_connection()->prepare(
        'SELECT 1 FROM soci_famiglia f JOIN soci s ON s.id_socio = f.id_familiare
         WHERE f.id_titolare = ? AND f.id_familiare = ? AND s.attivo_record = 1'
    );
    $stmt->execute([$id_titolare, $id_familiare]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Prenotazioni attive (presente/attesa) dei soci indicati, per le partite indicate.
 * Risultato: [id_partita][id_socio] => riga con stato, numero_posto, prenotata_da, da_nome.
 */
function famiglia_prenotazioni(array $ids_soci, array $ids_partite): array
{
    $ids_soci = array_values(array_unique(array_map('intval', $ids_soci)));
    $ids_partite = array_values(array_unique(array_map('intval', $ids_partite)));
    if (!famiglia_ready() || !$ids_soci || !$ids_partite) return [];
    $ps = implode(',', array_fill(0, count($ids_soci), '?'));
    $pp = implode(',', array_fill(0, count($ids_partite), '?'));
    $stmt = get_db_connection()->prepare(
        "SELECT r.id_partita, r.id_socio, r.stato, r.numero_posto, r.prenotata_da, CONCAT(b.nome, ' ', b.cognome) AS da_nome
         FROM prenotazioni r LEFT JOIN soci b ON b.id_socio = r.prenotata_da
         WHERE r.id_socio IN ($ps) AND r.id_partita IN ($pp) AND r.stato IN ('presente','attesa')"
    );
    $stmt->execute(array_merge($ids_soci, $ids_partite));
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[(int)$r['id_partita']][(int)$r['id_socio']] = $r;
    }
    return $out;
}

/**
 * Conferma la presenza di $id_socio. Se $da e valorizzato la prenotazione e
 * fatta da quel socio per conto di un familiare (altrimenti $da = null).
 * Restituisce 'presente' oppure 'attesa'.
 * @throws DomainException
 */
function prenotazione_conferma_da(int $id_partita, int $id_socio, ?int $da = null): string
{
    return partite_transaction(function (PDO $pdo) use ($id_partita, $id_socio, $da) {
        $stmt = $pdo->prepare('SELECT stato FROM prenotazioni WHERE id_partita = ? AND id_socio = ?');
        $stmt->execute([$id_partita, $id_socio]);
        $prima = $stmt->fetchColumn();
        $stato = prenotazione_conferma($id_partita, $id_socio);
        if (famiglia_ready() && !in_array($prima, ['presente', 'attesa'], true)) {
            $pdo->prepare('UPDATE prenotazioni SET prenotata_da = ? WHERE id_partita = ? AND id_socio = ?')
                ->execute([$da, $id_partita, $id_socio]);
        }
        return $stato;
    });
}

/** Soci sostenitori della stagione corrente tra quelli indicati: [id_socio => true]. */
function soci_sostenitori(array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $stagione = stagione_corrente();
    if (!$ids || !$stagione) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = get_db_connection()->prepare(
        "SELECT DISTINCT t.id_socio FROM tesseramenti t
         JOIN tipologie_tesseramento tip ON tip.id_tipologia = t.id_tipologia
         WHERE t.id_stagione = ? AND tip.tipo LIKE 'sostenitore%' AND t.id_socio IN ($ph)"
    );
    $stmt->execute(array_merge([$stagione['id_stagione']], $ids));
    return array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
}

/** Soci sostenitori con presenza confermata: sono gli unici che pagano la partita. */
function partita_sostenitori_da_pagare(int $id_partita): array
{
    $stagione = stagione_corrente();
    if (!$stagione) return [];
    $pronta = famiglia_ready();
    $campi = $pronta
        ? ", r.prenotata_da, CONCAT(b.nome, ' ', b.cognome) AS prenotata_da_nome"
        : ', NULL AS prenotata_da, NULL AS prenotata_da_nome';
    $join = $pronta ? ' LEFT JOIN soci b ON b.id_socio = r.prenotata_da' : '';
    $stmt = get_db_connection()->prepare(
        "SELECT s.id_socio, s.nome, s.cognome, s.telefono, t.numero_tessera, r.numero_posto $campi
         FROM prenotazioni r
         JOIN soci s ON s.id_socio = r.id_socio
         JOIN tesseramenti t ON t.id_socio = s.id_socio AND t.id_stagione = ?
         JOIN tipologie_tesseramento tip ON tip.id_tipologia = t.id_tipologia
         $join
         WHERE r.id_partita = ? AND r.stato = 'presente' AND tip.tipo LIKE 'sostenitore%'
         ORDER BY s.cognome, s.nome"
    );
    $stmt->execute([$stagione['id_stagione'], $id_partita]);
    return $stmt->fetchAll();
}
