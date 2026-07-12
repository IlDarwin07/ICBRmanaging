<?php
/**
 * Fase 4 — Import XLSX Inter Club
 * Step 1: upload file e anteprima intestazioni
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

// Recupera stagioni per il selettore
$stagioni = $pdo->query(
    "SELECT id_stagione, codice_stagione FROM stagioni ORDER BY codice_stagione DESC"
)->fetchAll();

$error   = '';

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
            UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere il file sul server (permessi).',
            UPLOAD_ERR_EXTENSION  => 'Upload bloccato da un\'estensione PHP.',
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
            // Salva nella cartella storage/import/ del progetto
            // (evita problemi di permessi con sys_get_temp_dir() su XAMPP Windows)
            $dest_dir = dirname(__DIR__, 2) . '/storage/import';
            if (!is_dir($dest_dir)) {
                mkdir($dest_dir, 0755, true);
            }
            $dest = $dest_dir . '/' . session_id() . '_' . time() . '.' . $ext;

            if (!move_uploaded_file($tmp, $dest)) {
                $error = 'Impossibile salvare il file in ' . $dest_dir
                       . '. Verificare i permessi della cartella.';
            } else {
                $_SESSION['import_file']      = $dest;
                $_SESSION['import_ext']       = $ext;
                $_SESSION['import_stagione']  = $id_stagione;
                $_SESSION['import_filename']  = $nome;

                header('Location: /importazioni/mapping.php');
                exit;
            }
        }
    }
}

$page_title = 'Importa file XLSX';
require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Importazione Inter Club <small style="font-size:.6em;font-weight:400">Step 1 di 3 &mdash; Carica file</small></h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['import_error'])): ?>
    <div class="alert alert-error"><?= h($_SESSION['import_error']) ?></div>
    <?php unset($_SESSION['import_error']); ?>
<?php endif; ?>

<div class="card" style="max-width:560px">
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="id_stagione">Stagione di riferimento *</label>
            <select name="id_stagione" id="id_stagione" required>
                <option value="">— Seleziona —</option>
                <?php foreach ($stagioni as $s): ?>
                    <option value="<?= (int)$s['id_stagione'] ?>">
                        <?= h($s['codice_stagione']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($stagioni)): ?>
                <small class="note" style="color:var(--color-warning)">
                    &#9888; Nessuna stagione trovata.
                    <a href="/configurazione/stagioni.php">Crea una stagione</a>
                    prima di importare.
                </small>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="xlsx_file">File da importare *</label>
            <input type="file" name="xlsx_file" id="xlsx_file"
                   accept=".xlsx,.xls,.csv" required>
            <small class="note">Formati accettati: .xlsx, .xls, .csv (max 10 MB)</small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn"
                <?= empty($stagioni) ? 'disabled title="Crea prima una stagione"' : '' ?>>
                Continua &rarr;
            </button>
            <a href="/dashboard.php" class="btn btn-secondary">Annulla</a>
        </div>
    </form>
</div>

<div class="card" style="max-width:560px;margin-top:1.5rem">
    <h3 style="margin-bottom:.75rem">Storico importazioni</h3>
    <?php
    $imports = $pdo->query(
        "SELECT i.id_importazione, i.nome_file, i.data_importazione,
                s.codice_stagione,
                SUM(CASE WHEN ir.esito = 'inserita'   THEN 1 ELSE 0 END) AS inseriti,
                SUM(CASE WHEN ir.esito = 'aggiornata' THEN 1 ELSE 0 END) AS aggiornati,
                SUM(CASE WHEN ir.esito = 'duplicato'  THEN 1 ELSE 0 END) AS duplicati,
                SUM(CASE WHEN ir.esito = 'errore'     THEN 1 ELSE 0 END) AS errori,
                COUNT(ir.id_riga_import)                                   AS totale_righe
         FROM importazioni i
         JOIN stagioni s ON s.id_stagione = i.id_stagione
         LEFT JOIN righe_importazione ir ON ir.id_importazione = i.id_importazione
         GROUP BY i.id_importazione
         ORDER BY i.data_importazione DESC
         LIMIT 10"
    )->fetchAll();
    ?>
    <?php if (empty($imports)): ?>
        <p class="note">Nessuna importazione eseguita finora.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>File</th>
                    <th>Stagione</th>
                    <th style="text-align:right" title="Inseriti">&#x2795;</th>
                    <th style="text-align:right" title="Aggiornati">&#x21BB;</th>
                    <th style="text-align:right" title="Duplicati">&#x26A0;</th>
                    <th style="text-align:right" title="Errori">&#x274C;</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($imports as $im): ?>
                    <tr>
                        <td><?= h(date('d/m/Y H:i', strtotime($im['data_importazione']))) ?></td>
                        <td><?= h($im['nome_file']) ?></td>
                        <td><?= h($im['codice_stagione']) ?></td>
                        <td style="text-align:right"><?= (int)$im['inseriti'] ?></td>
                        <td style="text-align:right"><?= (int)$im['aggiornati'] ?></td>
                        <td style="text-align:right"><?= (int)$im['duplicati'] ?></td>
                        <td style="text-align:right"><?= (int)$im['errori'] ?></td>
                        <td><a href="/importazioni/log.php?id=<?= (int)$im['id_importazione'] ?>">Log</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
