<?php
/**
 * Importazione Inter Club
 * Step 2: leggi intestazioni file e mostra mapping colonne -> campi DB
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/xlsx_reader.php';

start_secure_session();
require_login();

// Percorso base
if (!defined('BASE_URL')) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    define('BASE_URL', str_contains($script, '/ICBRmanaging/') ? '/ICBRmanaging/public' : '');
}
$base = rtrim(BASE_URL, '/');

if (empty($_SESSION['import_file'])) {
    header('Location: ' . $base . '/importazioni/upload.php');
    exit;
}

$file = $_SESSION['import_file'];
$ext  = $_SESSION['import_ext'];

if (!file_exists($file)) {
    $_SESSION['import_error'] = 'File temporaneo non trovato: ' . basename($file)
        . '. La cartella storage/import/ deve essere scrivibile da PHP.';
    header('Location: ' . $base . '/importazioni/upload.php');
    exit;
}

$filename    = $_SESSION['import_filename'] ?? basename($file);
$id_stagione = (int)$_SESSION['import_stagione'];

$pdo      = get_db_connection();
$stagione = $pdo->prepare('SELECT codice_stagione FROM stagioni WHERE id_stagione = ?');
$stagione->execute([$id_stagione]);
$codice_stagione = $stagione->fetchColumn() ?: '—';

// Leggi intestazioni + campioni
try {
    $reader  = new XlsxReader($file, $ext);
    $headers = $reader->getHeaders();
    $samples = $reader->getSampleRows(3);
} catch (Exception $e) {
    $_SESSION['import_error'] = 'Impossibile leggere il file: ' . $e->getMessage();
    header('Location: ' . $base . '/importazioni/upload.php');
    exit;
}

if (empty($headers)) {
    $_SESSION['import_error'] = 'Il file sembra vuoto o non contiene intestazioni leggibili.';
    header('Location: ' . $base . '/importazioni/upload.php');
    exit;
}

// Campi DB disponibili
$campi_db = [
    ''                     => '— Non importare —',
    'nome'                 => 'Nome',
    'cognome'              => 'Cognome',
    'sesso'                => 'Sesso (M/F)',
    'data_nascita'         => 'Data di nascita',
    'codice_fiscale'       => 'Codice Fiscale',
    'luogo_nascita'        => 'Luogo di nascita',
    'paese'                => 'Paese / Nazionalità',
    'indirizzo'            => 'Indirizzo',
    'numero_civico'        => 'Numero civico',
    'cap'                  => 'CAP',
    'provincia'            => 'Provincia',
    'citta'                => 'Città / Comune',
    'telefono'             => 'Telefono',
    'email'                => 'Email',
    'numero_tessera'       => 'Numero tessera (Inter Club)',
    'tipologia_codice'     => 'Tipo (senior/junior/…)',
    'ruolo'                => 'Ruolo portale',
    'listino'              => 'Listino / Quota',
    'socio_plus'           => 'Socio PLUS (SI/NO)',
    'attivo_scorsa'        => 'Attivo scorsa stagione',
    'data_attivazione'     => 'Data/ora attivazione',
    'attivo_portale'       => 'Attivo portale (SI/NO)',
    'conferma_anagrafica'  => 'Conferma Anagrafica (SI/NO)',
    'tessera_fisica'       => 'Tessera Fisica (SI/NO)',
];

// Auto-mapping alias
$alias_map = [
    'nome'                => ['nome', 'first name', 'first_name', 'given name'],
    'cognome'             => ['cognome', 'surname', 'last name', 'last_name'],
    'sesso'               => ['sesso', 'gender', 'sex'],
    'data_nascita'        => ['data di nascita', 'data_nascita', 'birthdate', 'birth date', 'dob', 'date of birth'],
    'codice_fiscale'      => ['codice fiscale', 'codice_fiscale', 'cf', 'fiscal code', 'tax code', 'codicefiscale'],
    'luogo_nascita'       => ['comune nascita', 'comune_nascita', 'luogo nascita', 'luogo_nascita', 'birthplace'],
    'paese'               => ['nazionalita', 'nazionalità', 'paese', 'country', 'nazione'],
    'indirizzo'           => ['indirizzo', 'address', 'via', 'street'],
    'numero_civico'       => ['numero civico', 'numero_civico', 'civico', 'house number'],
    'cap'                 => ['cap', 'postal code', 'postcode', 'zip', 'zip code'],
    'provincia'           => ['provincia', 'province', 'prov'],
    'citta'               => ['comune', 'citta', 'città', 'city', 'town'],
    'telefono'            => ['telefono', 'phone', 'tel', 'mobile', 'cellulare', 'cell'],
    'email'               => ['email', 'e-mail', 'mail'],
    'numero_tessera'      => ['numero tessera', 'numero_tessera', 'tessera', 'card number', 'num tessera'],
    'tipologia_codice'    => ['tipo', 'tipo socio', 'tipologia', 'type'],
    'ruolo'               => ['ruolo', 'role'],
    'listino'             => ['listino', 'quota', 'listino/quota'],
    'socio_plus'          => ['socio plus', 'socio_plus', 'plus'],
    'attivo_scorsa'       => ['attivo scorsa stagione', 'attivo_scorsa_stagione', 'attivo scorsa', 'renewed'],
    'data_attivazione'    => ['data/ora attivazione', 'data attivazione', 'data_attivazione', 'activation date'],
    'attivo_portale'      => ['attivo', 'attivo portale', 'active'],
    'conferma_anagrafica' => ['conferma anagrafica', 'conferma_anagrafica', 'anagrafica confermata'],
    'tessera_fisica'      => ['tessera fisica', 'tessera_fisica', 'physical card'],
];

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

// Skip automatico se ha identificatore
$valori_auto = array_values($auto_map);
$ha_id_auto  = in_array('numero_tessera', $valori_auto)
               || in_array('codice_fiscale', $valori_auto)
               || (in_array('cognome', $valori_auto) && in_array('nome', $valori_auto));

if ($ha_id_auto && ($_GET['force'] ?? '') !== '1') {
    $_SESSION['import_mapping'] = $auto_map;
    header('Location: ' . $base . '/importazioni/process.php');
    exit;
}

// POST: mapping manuale
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mapping = [];
    foreach ($headers as $i => $h) {
        $campo = $_POST['map'][$i] ?? '';
        if ($campo !== '') $mapping[$i] = $campo;
    }
    $valori_mapping    = array_values($mapping);
    $ha_identificatore = in_array('numero_tessera', $valori_mapping)
                         || in_array('codice_fiscale', $valori_mapping)
                         || (in_array('cognome', $valori_mapping) && in_array('nome', $valori_mapping));

    if (!$ha_identificatore) {
        $error = 'Devi mappare almeno un identificatore: Numero tessera, Codice Fiscale, oppure Cognome + Nome.';
    } else {
        $_SESSION['import_mapping'] = $mapping;
        header('Location: ' . $base . '/importazioni/process.php');
        exit;
    }
}

$page_title = 'Importa — Mapping colonne';
require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Importazione Inter Club
    <small style="font-size:.6em;font-weight:400">Step 2 di 3 &mdash; Mapping colonne</small>
</h1>

<p style="margin-bottom:1rem;color:#555">
    File: <strong><?= h($filename) ?></strong> &nbsp;|&nbsp;
    Stagione: <strong><?= h($codice_stagione) ?></strong> &nbsp;|&nbsp;
    <?= count($headers) ?> colonne rilevate
    &nbsp;&mdash;&nbsp;
    <a href="?force=1" style="font-size:.85em">Rivedi mapping manualmente</a>
</p>

<?php if ($error): ?>
    <div style="background:#fdecea;border:1px solid #f5c6c6;color:#a12c2c;
                padding:.75rem 1rem;border-radius:.5rem;margin-bottom:1rem">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<form method="post">
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.9rem">
            <thead>
                <tr style="border-bottom:2px solid #ddd">
                    <th style="text-align:left;padding:.5rem">#</th>
                    <th style="text-align:left;padding:.5rem">Intestazione file</th>
                    <th style="text-align:left;padding:.5rem">Campo database</th>
                    <th style="text-align:left;padding:.5rem">Anteprima (prime 3 righe)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($headers as $i => $h): ?>
                    <tr style="border-bottom:1px solid #eee">
                        <td style="padding:.5rem;color:#999"><?= $i + 1 ?></td>
                        <td style="padding:.5rem"><strong><?= h($h) ?></strong></td>
                        <td style="padding:.5rem">
                            <select name="map[<?= $i ?>]"
                                    style="width:100%;min-width:180px;padding:.35rem .5rem;
                                           border:1px solid #ccc;border-radius:.375rem">
                                <?php foreach ($campi_db as $val => $label): ?>
                                    <option value="<?= h($val) ?>"
                                        <?= (($auto_map[$i] ?? '') === $val) ? 'selected' : '' ?>>
                                        <?= h($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td style="padding:.5rem;font-size:.85em;color:#666">
                            <?php foreach ($samples as $row): ?>
                                <div><?= h($row[$i] ?? '') ?></div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top:1.5rem;display:flex;gap:.75rem;align-items:center">
        <button type="submit"
                style="background:#1a3a6b;color:#fff;border:none;padding:.5rem 1.25rem;
                       border-radius:.375rem;cursor:pointer;font-weight:600">
            Avvia importazione &rarr;
        </button>
        <a href="<?= $base ?>/importazioni/upload.php"
           style="color:#555;text-decoration:none">
            &larr; Torna indietro
        </a>
    </div>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
