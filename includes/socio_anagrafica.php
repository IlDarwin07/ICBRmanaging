<?php
/** Area soci: dati personali e invito INTERISTA. */
const INTERISTA_URL = 'https://loyalty.inter.it/it';

function socio_anagrafica(int $id_socio): array
{
    $stmt = get_db_connection()->prepare('SELECT * FROM soci WHERE id_socio = ? AND attivo_record = 1');
    $stmt->execute([$id_socio]);
    return $stmt->fetch() ?: [];
}

function socio_ultima_tessera(int $id_socio): ?array
{
    $stmt = get_db_connection()->prepare(
        'SELECT t.numero_tessera, t.tessera_fisica, t.conferma_anagrafica, st.codice_stagione, tp.listino_label
         FROM tesseramenti t
         JOIN stagioni st ON st.id_stagione = t.id_stagione
         LEFT JOIN tipologie_tesseramento tp ON tp.id_tipologia = t.id_tipologia
         WHERE t.id_socio = ?
         ORDER BY st.data_inizio DESC, t.id_tesseramento DESC LIMIT 1'
    );
    $stmt->execute([$id_socio]);
    return $stmt->fetch() ?: null;
}

function socio_data_it(?string $d): string
{
    if (!$d || $d === '0000-00-00') return '';
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : '';
}

function socio_anagrafica_righe(array $a, ?array $tessera): array
{
    $sesso = ['M' => 'Maschio', 'F' => 'Femmina'][strtoupper((string)($a['sesso'] ?? ''))] ?? (string)($a['sesso'] ?? '');
    $via = trim(($a['indirizzo'] ?? '') . ' ' . ($a['numero_civico'] ?? ''));
    $citta = trim(($a['cap'] ?? '') . ' ' . ($a['comune'] ?? '') . (!empty($a['provincia']) ? ' (' . $a['provincia'] . ')' : ''));
    $righe = [
        'Nome' => $a['nome'] ?? '',
        'Cognome' => $a['cognome'] ?? '',
        'Sesso' => $sesso,
        'Data di nascita' => socio_data_it($a['data_nascita'] ?? null),
        'Comune di nascita' => $a['comune_nascita'] ?? '',
        'Nazionalità' => $a['nazionalita'] ?? '',
        'Codice fiscale' => $a['codice_fiscale'] ?? '',
        'Indirizzo' => $via,
        'CAP, comune e provincia' => $citta,
        'Telefono' => $a['telefono'] ?? '',
        'Email' => $a['email'] ?? '',
    ];
    if ($tessera) {
        $fisica = ['non_richiesta' => 'Non richiesta', 'non_consegnata' => 'Richiesta, in attesa di consegna', 'consegnata' => 'Consegnata'];
        $righe['Ultima tessera'] = trim(($tessera['listino_label'] ?? '') . ' · ' . $tessera['codice_stagione'], ' ·');
        $righe['Numero tessera'] = $tessera['numero_tessera'] ?? '';
        $righe['Tessera fisica'] = $fisica[$tessera['tessera_fisica']] ?? '';
    }
    $righe['Iscritto dal'] = socio_data_it($a['created_at'] ?? null);
    return $righe;
}

function render_socio_anagrafica(array $a, ?array $tessera): void
{
    $confermata = $tessera !== null && (int)($tessera['conferma_anagrafica'] ?? 0) === 1;
    ?>
    <div class="sa-registry-status" style="margin:0 0 18px;padding:14px 16px;border-radius:12px;border:1px solid <?= $confermata ? '#a7d9b6' : '#efb4b4' ?>;background:<?= $confermata ? '#e7f6ec' : '#fff0f0' ?>;color:<?= $confermata ? '#14532d' : '#8f1d1d' ?>">
        <strong><?= $confermata ? 'Anagrafica confermata' : 'Anagrafica non confermata' ?></strong>
        <?php if ($tessera): ?>
            <span style="display:block;font-size:.85rem;margin-top:4px">Stato relativo alla tessera <?= h($tessera['codice_stagione']) ?>.</span>
        <?php else: ?>
            <span style="display:block;font-size:.85rem;margin-top:4px">Nessun tesseramento registrato: conferma non disponibile.</span>
        <?php endif; ?>
        <?php if (!$confermata): ?><span style="display:block;font-size:.85rem;margin-top:4px">Per verificare i dati e la conferma, contatta il direttivo.</span><?php endif; ?>
    </div>
    <dl class="sa-dl">
        <?php foreach (socio_anagrafica_righe($a, $tessera) as $label => $valore): ?>
        <div><dt><?= h($label) ?></dt><dd><?= trim((string)$valore) !== '' ? h($valore) : '—' ?></dd></div>
        <?php endforeach; ?>
    </dl>
    <?php
}

function render_invito_interista(?array $tessera): void
{
    ?>
    <div class="sa-card sa-loyalty">
        <h3>Iscriviti a INTERISTA</h3>
        <p>INTERISTA è il programma loyalty gratuito dell’Inter: premi e vantaggi esclusivi per i tifosi più fedeli. Se non l’hai ancora fatto, iscriviti: è gratis e ci metti pochi minuti.</p>
        <?php if ($tessera && !empty($tessera['numero_tessera'])): ?>
            <p class="sa-small">Tieni a portata di mano il numero della tua tessera Inter Club: <strong><?= h($tessera['numero_tessera']) ?></strong>.</p>
        <?php endif; ?>
        <p><a class="sa-btn" href="<?= h(INTERISTA_URL) ?>" target="_blank" rel="noopener noreferrer">Iscriviti a INTERISTA</a></p>
        <p class="sa-small">Ti portiamo sul sito ufficiale dell’Inter: l’iscrizione è personale e non condividiamo i tuoi dati con terzi.</p>
    </div>
    <?php
}
