<?php
/**
 * Fase 4 — Import XLSX Inter Club
 * Step 2: leggi intestazioni file e mostra mapping colonne → campi DB
 *
 * Se il mapping automatico copre almeno un identificatore,
 * lo step viene saltato automaticamente (?force=1 per revisione manuale).
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/xlsx_reader.php';

start_secure_session();
require_login();

// Verifica sessione valida
if (empty($_SESSION['import_file'])) {
    header('Location: /importazioni/upload.php');
    exit;
}

$file = $_SESSION['import_file'];
$ext  = $_SESSION['import_ext'];

if (!file_exists($file)) {
    $_SESSION['import_error'] = 'File di import non trovato sul server ('
        . basename($file) . '). Verifica che la cartella storage/import/ '
        . 'esista e sia scrivibile da PHP (chmod 755).';
    header('Location: /importazioni/upload.php');
    exit;
}

$filename    = $_SESSION['import_filename'] ?? basename($file);
$id_stagione = (int)$_SESSION['import_stagione'];

$pdo      = get_db_connection();
$stagione = $pdo->prepare('SELECT codice_stagione FROM stagioni WHERE id_stagione = ?');
$stagione->execute([$id_stagione]);
$codice_stagione = $stagione->fetchColumn() ?: '—';

// ── Leggi intestazioni + prime 3 righe campione ───────────────────────────
try {
    $reader  = new XlsxReader($file, $ext);
    $headers = $reader->getHeaders();
    $samples = $reader->getSampleRows(3);
} catch (Exception $e) {
    $_SESSION['import_error'] = 'Impossibile leggere il file: ' . $e->getMessage();
    header('Location: /importazioni/upload.php');
    exit;
}

if (empty($headers)) {
    $_SESSION['import_error'] = 'Il file sembra vuoto o non contiene intestazioni leggibili.';
    header('Location: /importazioni/upload.php');
    exit;
}

// ── Campi disponibili nel DB (target del mapping) ─────────────────────────
$campi_db = [
    ''                     => '— Non importare —',
    'nome'                 => 'Nome',
    'cognome'              => 'Cognome',
    'sesso'                => 'Sesso (M/F)',
    'data_nascita'         => 'Data di nascita',
    'codice_fiscale'       => 'Codice Fiscale',
    'paese'                => 'Paese / Nazionalità',
    'indirizzo'            => 'Indirizzo',
    'numero_civico'        => 'Numero civico',
    'cap'                  => 'CAP',
    'provincia'            => 'Provincia',
    'citta'                => 'Città / Comune',
    'telefono'             => 'Telefono',
    'email'                => 'Email',
    'listino'              => 'Listino / Quota',
    'numero_tessera'       => 'Numero tessera (Inter Club)',
    'ruolo'                => 'Ruolo',
    'socio_plus'           => 'Socio PLUS (SI/NO)',
    'tipologia_codice'     => 'Tipo (senior/junior/…)',
    'luogo_nascita'        => 'Luogo di nascita',
    'attivo_scorsa'        => 'Attivo scorsa stagione',
    'data_attivazione'     => 'Data/ora attivazione',
    'attivo_portale'       => 'Attivo portale (SI/NO)',
    'conferma_anagrafica'  => 'Conferma Anagrafica (SI/NO)',
    'tessera_fisica'       => 'Tessera Fisica (SI/NO)',
];

// ── Tabella alias: intestazioni ESATTE del file Inter Club + varianti ─────
$alias_map = [
    'nome'                => ['nome', 'first name', 'first_name', 'given name'],
    'cognome'             => ['cognome', 'surname', 'last name', 'last_name'],
    'sesso'               => ['sesso', 'gender', 'sex'],
    'data_nascita'        => ['data di nascita', 'data_nascita', 'birthdate',
                              'birth date', 'dob', 'date of birth'],
    'codice_fiscale'      => ['codice fiscale', 'codice_fiscale', 'cf',
                              'fiscal code', 'tax code', 'codicefiscale'],
    'paese'               => ['nazionalita', 'nazionalità', 'paese',
                              'country', 'nazione', 'nation'],
    'indirizzo'           => ['indirizzo', 'address', 'via', 'street'],
    'numero_civico'       => ['numero civico', 'numero_civico', 'civico',
                              'house number', 'streetnumber'],
    'cap'                 => ['cap', 'postal code', 'postcode', 'zip', 'zip code'],
    'provincia'           => ['provincia', 'province', 'prov'],
    'citta'               => ['comune', 'citta', 'città', 'city', 'town'],
    'telefono'            => ['telefono', 'phone', 'tel', 'mobile',
                              'cellulare', 'cell'],
    'email'               => ['email', 'e-mail', 'mail'],
    'listino'             => ['listino', 'quota', 'membership type',
                              'listino/quota'],
    'numero_tessera'      => ['numero tessera', 'numero_tessera', 'tessera',
                              'card number', 'membership number', 'num tessera'],
    'ruolo'               => ['ruolo', 'role'],
    'socio_plus'          => ['socio plus', 'socio_plus', 'plus'],
    'tipologia_codice'    => ['tipo', 'tipo socio', 'tipologia', 'type'],
    'luogo_nascita'       => ['comune nascita', 'comune_nascita',
                              'luogo nascita', 'luogo_nascita',
                              'birthplace', 'city of birth'],
    'attivo_scorsa'       => ['attivo scorsa stagione',
                              'attivo_scorsa_stagione',
                              'attivo scorsa', 'renewed'],
    'data_attivazione'    => ['data/ora attivazione', 'data attivazione',
                              'data_attivazione', 'activation date'],
    'attivo_portale'      => ['attivo', 'attivo portale', 'active'],
    'conferma_anagrafica' => ['conferma anagrafica', 'conferma_anagrafica',
                              'anagrafica confermata'],
    'tessera_fisica'      => ['tessera fisica', 'tessera_fisica',
                              'physical card'],
];

// ── Auto-map ──────────────────────────────────────────────────────────────
$auto_map = [];
foreach ($headers as $i => $h) {
    $h_norm = strtolower(trim($h));
    if ($h_norm === '') continue;
    foreach ($alias_map as $campo => $aliases) {
        if (in_array($h_norm, $aliases, true)) {
            $auto_map[$i] = $campo;
            break;
        }
    }
}

// ── Skip automatico: basta avere almeno un identificatore mappato ─────────
// Non richiediamo più che TUTTE le colonne siano mappate:
// colonne sconosciute vengono semplicemente ignorate.
$valori_auto = array_values($auto_map);
$ha_id_auto  = in_array('numero_tessera', $valori_auto)
               || in_array('codice_fiscale', $valori_auto)
               || (in_array('cognome', $valori_auto) && in_array('nome', $valori_auto));

if ($ha_id_auto && ($_GET['force'] ?? '') !== '1') {
    $_SESSION['import_mapping'] = $auto_map;
    header('Location: /importazioni/process.php');
    exit;
}

// ── Gestione POST (revisione manuale) ────────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mapping = [];
    foreach ($headers as $i => $h) {
        $campo = $_POST['map'][$i] ?? '';
        if ($campo !== '') {
            $mapping[$i] = $campo;
        }
    }
    $valori_mapping    = array_values($mapping);
    $ha_identificatore = in_array('numero_tessera', $valori_mapping)
                         || in_array('codice_fiscale', $valori_mapping)
                         || (in_array('cognome', $valori_mapping)
                             && in_array('nome', $valori_mapping));

    if (!$ha_identificatore) {
        $error = 'Devi mappare almeno un identificatore: '
               . 'Numero tessera, oppure Codice Fiscale, oppure Cognome + Nome.';
    } else {
        $_SESSION['import_mapping'] = $mapping;
        header('Location: /importazioni/process.php');
        exit;
    }
}

$page_title = 'Importa XLSX — Mapping colonne';
require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Importazione Inter Club
    <small style="font-size:.6em;font-weight:400">Step 2 di 3 &mdash; Mapping colonne</small>
</h1>

<p class="note">
    File: <strong><?= h($filename) ?></strong> &nbsp;|&nbsp;
    Stagione: <strong><?= h($codice_stagione) ?></strong> &nbsp;|&nbsp;
    <?= count($headers) ?> colonne rilevate
    &nbsp;&mdash;&nbsp;
    <a href="?force=1" style="font-size:.85em">Rivedi mapping manualmente</a>
</p>

<?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<p class="note" style="color:var(--color-text-muted)">
    Devi mappare almeno un identificatore: <em>Numero tessera</em>,
    oppure <em>Codice Fiscale</em>, oppure <em>Cognome + Nome</em>.
</p>

<form method="post">
    <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Intestazione file</th>
                    <th>Campo database</th>
                    <th>Anteprima (prime 3 righe)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($headers as $i => $h): ?>
                    <tr>
                        <td style="color:var(--color-text-muted)"><?= $i + 1 ?></td>
                        <td><strong><?= h($h) ?></strong></td>
                        <td>
                            <select name="map[<?= $i ?>]">
                                <?php foreach ($campi_db as $val => $label): ?>
                                    <option value="<?= h($val) ?>"
                                        <?= (($auto_map[$i] ?? '') === $val) ? 'selected' : '' ?>>
                                        <?= h($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td style="font-size:.85em;color:var(--color-text-muted)">
                            <?php foreach ($samples as $row): ?>
                                <div><?= h($row[$i] ?? '') ?></div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions" style="margin-top:1.5rem">
        <button type="submit" class="btn">Avvia importazione &rarr;</button>
        <a href="/importazioni/upload.php" class="btn btn-secondary">&larr; Torna indietro</a>
    </div>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
