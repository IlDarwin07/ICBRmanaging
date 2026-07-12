<?php
/**
 * Importazione Inter Club
 * Step 1: upload file e anteprima storico
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

// Recupera stagioni per il selettore
$stagioni = $pdo->query(
    'SELECT id_stagione, codice_stagione FROM stagioni ORDER BY codice_stagione DESC'
)->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_stagione = (int)($_POST['id_stagione'] ?? 0);

    if (!$id_stagione) {
        $error = 'Seleziona una stagione prima di procedere.';
    } elseif (!isset($_FILES['xlsx_file']) || $_FILES['xlsx_file']['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => 'File troppo grande (limite php.ini).',
            UPLOAD_ERR_FORM_SIZE  => 'File troppo grande (limite form).',
            UPLOAD_ERR_PARTIAL    => 'Caricamento parziale, riprova.',
            UPLOAD_ERR_NO_FILE    => 'Nessun file selezionato.',
            UPLOAD_ERR_NO_TMP_DIR => 'Cartella temporanea mancante sul server.',
            UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere il file sul server.',
            UPLOAD_ERR_EXTENSION  => "Upload bloccato da un'estensione PHP.",
        ];
        $err_code = $_FILES['xlsx_file']['error'] ?? -1;
        $error = $upload_errors[$err_code] ?? "Errore upload (codice: $err_code).";
    } else {
        $tmp  = $_FILES['xlsx_file']['tmp_name'];
        $nome = basename($_FILES['xlsx_file']['name']);
        $ext  = strtolower(pathinfo($nome, PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            $error = 'Formato non supportato. Carica un file .xlsx, .xls o .csv.';
        } else {
            // Crea cartella storage/import se non esiste
            $dest_dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage'
                      . DIRECTORY_SEPARATOR . 'import';
            if (!is_dir($dest_dir)) {
                if (!mkdir($dest_dir, 0755, true)) {
                    $error = 'Impossibile creare la cartella di import: ' . $dest_dir
                           . '. Crea manualmente la cartella "storage/import/" nella'
                           . ' root del progetto con permessi di scrittura.';
                    goto show_form;
                }
            }

            $dest = $dest_dir . DIRECTORY_SEPARATOR
                  . session_id() . '_' . time() . '.' . $ext;

            if (!move_uploaded_file($tmp, $dest)) {
                $error = 'Impossibile salvare il file. Verifica i permessi della'
                       . ' cartella: ' . $dest_dir;
            } else {
                $_SESSION['import_file']     = $dest;
                $_SESSION['import_ext']      = $ext;
                $_SESSION['import_stagione'] = $id_stagione;
                $_SESSION['import_filename'] = $nome;

                // Redirect con BASE_URL
                if (!defined('BASE_URL')) {
                    $script = $_SERVER['SCRIPT_NAME'] ?? '';
                    define('BASE_URL', str_contains($script, '/ICBRmanaging/')
                        ? '/ICBRmanaging/public' : '');
                }
                header('Location: ' . rtrim(BASE_URL, '/') . '/importazioni/mapping.php');
                exit;
            }
        }
    }
}

show_form:
$page_title = 'Importa file XLSX';
require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Importazione Inter Club
    <small style="font-size:.6em;font-weight:400">Step 1 di 3 &mdash; Carica file</small>
</h1>

<?php if ($error): ?>
    <div class="alert alert-error" style="
        background:#fdecea;border:1px solid #f5c6c6;color:#a12c2c;
        padding:.75rem 1rem;border-radius:.5rem;margin-bottom:1rem">
        <?= h($error) ?>
    </div>
<?php endif; ?>

<?php if (!empty($_SESSION['import_error'])): ?>
    <div class="alert alert-error" style="
        background:#fdecea;border:1px solid #f5c6c6;color:#a12c2c;
        padding:.75rem 1rem;border-radius:.5rem;margin-bottom:1rem">
        <?= h($_SESSION['import_error']) ?>
    </div>
    <?php unset($_SESSION['import_error']); ?>
<?php endif; ?>

<div style="background:#fff;border:1px solid #ddd;border-radius:.5rem;padding:1.5rem;max-width:560px;margin-bottom:1.5rem">
    <form method="post" enctype="multipart/form-data">
        <div style="margin-bottom:1rem">
            <label for="id_stagione" style="display:block;font-weight:600;margin-bottom:.35rem">
                Stagione di riferimento *
            </label>
            <select name="id_stagione" id="id_stagione" required
                    style="width:100%;padding:.45rem .6rem;border:1px solid #ccc;border-radius:.375rem">
                <option value="">&mdash; Seleziona &mdash;</option>
                <?php foreach ($stagioni as $s): ?>
                    <option value="<?= (int)$s['id_stagione'] ?>">
                        <?= h($s['codice_stagione']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($stagioni)): ?>
                <small style="color:#b45309;display:block;margin-top:.3rem">
                    &#9888; Nessuna stagione trovata.
                    <a href="<?= defined('BASE_URL') ? rtrim(BASE_URL,'/')  : '' ?>/stagioni/list.php">
                        Crea una stagione
                    </a> prima di importare.
                </small>
            <?php endif; ?>
        </div>

        <div style="margin-bottom:1.25rem">
            <label for="xlsx_file" style="display:block;font-weight:600;margin-bottom:.35rem">
                File da importare *
            </label>
            <input type="file" name="xlsx_file" id="xlsx_file"
                   accept=".xlsx,.xls,.csv" required>
            <small style="color:#666;display:block;margin-top:.3rem">
                Formati accettati: .xlsx, .xls, .csv (max 10 MB)
            </small>
        </div>

        <div>
            <button type="submit"
                    style="background:#1a3a6b;color:#fff;border:none;padding:.5rem 1.25rem;
                           border-radius:.375rem;cursor:pointer;font-weight:600"
                    <?= empty($stagioni) ? 'disabled title="Crea prima una stagione"' : '' ?>>
                Continua &rarr;
            </button>
            <a href="<?= defined('BASE_URL') ? rtrim(BASE_URL,'/')  : '' ?>/dashboard.php"
               style="margin-left:.75rem;color:#555;text-decoration:none">
                Annulla
            </a>
        </div>
    </form>
</div>

<div style="background:#fff;border:1px solid #ddd;border-radius:.5rem;padding:1.5rem;max-width:760px">
    <h3 style="margin:0 0 .75rem">Storico importazioni</h3>
    <?php
    try {
        $imports = $pdo->query(
            "SELECT i.id_importazione,
                    i.nome_file,
                    i.data_importazione,
                    COALESCE(s.codice_stagione, '—') AS codice_stagione,
                    SUM(CASE WHEN ri.esito = 'inserita'   THEN 1 ELSE 0 END) AS inseriti,
                    SUM(CASE WHEN ri.esito = 'aggiornata' THEN 1 ELSE 0 END) AS aggiornati,
                    SUM(CASE WHEN ri.esito = 'duplicato'  THEN 1 ELSE 0 END) AS duplicati,
                    SUM(CASE WHEN ri.esito = 'errore'     THEN 1 ELSE 0 END) AS errori,
                    COUNT(ri.id_riga_import)                                   AS totale_righe
             FROM importazioni i
             LEFT JOIN stagioni s ON s.id_stagione = i.id_stagione
             LEFT JOIN righe_importazione ri ON ri.id_importazione = i.id_importazione
             GROUP BY i.id_importazione
             ORDER BY i.data_importazione DESC
             LIMIT 10"
        )->fetchAll();
    } catch (PDOException $e) {
        $imports = [];
        echo '<p style="color:#b45309">Errore storico: ' . h($e->getMessage()) . '</p>';
    }
    ?>
    <?php if (empty($imports)): ?>
        <p style="color:#666">Nessuna importazione eseguita finora.</p>
    <?php else: ?>
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.9rem">
            <thead>
                <tr style="border-bottom:2px solid #ddd">
                    <th style="text-align:left;padding:.5rem">Data</th>
                    <th style="text-align:left;padding:.5rem">File</th>
                    <th style="text-align:left;padding:.5rem">Stagione</th>
                    <th style="text-align:right;padding:.5rem" title="Inseriti">&#x2795;</th>
                    <th style="text-align:right;padding:.5rem" title="Aggiornati">&#x21BB;</th>
                    <th style="text-align:right;padding:.5rem" title="Duplicati">&#x26A0;</th>
                    <th style="text-align:right;padding:.5rem" title="Errori">&#x274C;</th>
                    <th style="padding:.5rem"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($imports as $im): ?>
                    <tr style="border-bottom:1px solid #eee">
                        <td style="padding:.5rem"><?= h(date('d/m/Y H:i', strtotime($im['data_importazione']))) ?></td>
                        <td style="padding:.5rem"><?= h($im['nome_file']) ?></td>
                        <td style="padding:.5rem"><?= h($im['codice_stagione']) ?></td>
                        <td style="text-align:right;padding:.5rem"><?= (int)$im['inseriti'] ?></td>
                        <td style="text-align:right;padding:.5rem"><?= (int)$im['aggiornati'] ?></td>
                        <td style="text-align:right;padding:.5rem"><?= (int)$im['duplicati'] ?></td>
                        <td style="text-align:right;padding:.5rem"><?= (int)$im['errori'] ?></td>
                        <td style="padding:.5rem">
                            <a href="<?= defined('BASE_URL') ? rtrim(BASE_URL,'/')  : '' ?>/importazioni/log.php?id=<?= (int)$im['id_importazione'] ?>">
                                Log
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
