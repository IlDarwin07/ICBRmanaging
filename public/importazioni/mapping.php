<?php
/**
 * Fase 4 — Import XLSX Inter Club
 * Step 2: leggi intestazioni file e mostra mapping colonne → campi DB
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/xlsx_reader.php';

start_secure_session();
require_login();

if (empty($_SESSION['import_file']) || !file_exists($_SESSION['import_file'])) {
    header('Location: /importazioni/upload.php');
    exit;
}

$file     = $_SESSION['import_file'];
$ext      = $_SESSION['import_ext'];
$filename = $_SESSION['import_filename'] ?? basename($file);
$id_stagione = (int)$_SESSION['import_stagione'];

$pdo = get_db_connection();
$stagione = $pdo->prepare('SELECT codice_stagione FROM stagioni WHERE id_stagione = ?');
$stagione->execute([$id_stagione]);
$codice_stagione = $stagione->fetchColumn() ?: '—';

// Leggi solo la prima riga (intestazioni) + prime 3 righe campione
try {
    $reader = new XlsxReader($file, $ext);
    $headers = $reader->getHeaders();      // array di stringhe
    $samples = $reader->getSampleRows(3); // array di array
} catch (Exception $e) {
    $_SESSION['import_error'] = 'Impossibile leggere il file: ' . $e->getMessage();
    header('Location: /importazioni/upload.php');
    exit;
}

// Campi disponibili nel DB (target)
$campi_db = [
    ''                     => '— Non importare —',
    // Anagrafica
    'cognome'              => 'Cognome',
    'nome'                 => 'Nome',
    'codice_fiscale'       => 'Codice Fiscale',
    'data_nascita'         => 'Data di nascita',
    'luogo_nascita'        => 'Luogo di nascita',
    'paese'                => 'Paese',
    'sesso'                => 'Sesso (M/F)',
    'email'                => 'Email',
    'telefono'             => 'Telefono',
    'indirizzo'            => 'Indirizzo',
    'cap'                  => 'CAP',
    'citta'                => 'Città',
    'provincia'            => 'Provincia',
    // Tesseramento
    'numero_tessera'       => 'Numero tessera (Inter Club)',
    'tipologia_codice'     => 'Tipologia (codice, es. SENIOR)',
    'data_iscrizione'      => 'Data iscrizione',
    'attivo_portale'       => 'Attivo portale (0/1)',
    'conferma_anagrafica'  => 'Anagrafica confermata (0/1)',
    'note_tesseramento'    => 'Note tesseramento',
];

// Mapping automatico euristica (case-insensitive, trim)
$auto_map = [];
foreach ($headers as $i => $h) {
    $h_norm = strtolower(trim($h));
    foreach (array_keys($campi_db) as $campo) {
        if ($campo === '') continue;
        if ($h_norm === strtolower($campo)) {
            $auto_map[$i] = $campo;
            break;
        }
        // Alias comuni
        $aliases = [
            'cognome'         => ['surname', 'last name', 'last_name'],
            'nome'            => ['first name', 'first_name', 'given name'],
            'codice_fiscale'  => ['cf', 'fiscal code', 'tax code'],
            'data_nascita'    => ['birthdate', 'birth date', 'dob', 'data di nascita'],
            'paese'           => ['country', 'nazione', 'nation'],
            'email'           => ['e-mail', 'mail'],
            'telefono'        => ['phone', 'tel', 'mobile', 'cellulare'],
            'numero_tessera'  => ['tessera', 'card number', 'membership number', 'num tessera'],
        ];
        if (isset($aliases[$campo]) && in_array($h_norm, $aliases[$campo])) {
            $auto_map[$i] = $campo;
            break;
        }
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Salva mapping in sessione e vai allo step 3
    $mapping = [];
    foreach ($headers as $i => $h) {
        $campo = $_POST['map'][$i] ?? '';
        if ($campo !== '') {
            $mapping[$i] = $campo;
        }
    }
    // Verifica che almeno cognome+nome o numero_tessera siano mappati
    $valori_mapping = array_values($mapping);
    $ha_identificatore = in_array('numero_tessera', $valori_mapping)
        || (in_array('cognome', $valori_mapping) && in_array('nome', $valori_mapping))
        || in_array('codice_fiscale', $valori_mapping);

    if (!$ha_identificatore) {
        $error = 'Devi mappare almeno un identificatore: Numero tessera, oppure Codice Fiscale, oppure Cognome + Nome.';
    } else {
        $_SESSION['import_mapping'] = $mapping;
        header('Location: /importazioni/process.php');
        exit;
    }
}

$page_title = 'Importa XLSX — Mapping colonne';
require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Importazione Inter Club <small style="font-size:.6em;font-weight:400">Step 2 di 3 — Mapping colonne</small></h1>

<p class="note">
    File: <strong><?= h($filename) ?></strong> &nbsp;|&nbsp;
    Stagione: <strong><?= h($codice_stagione) ?></strong> &nbsp;|&nbsp;
    <?= count($headers) ?> colonne rilevate
</p>

<?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

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
