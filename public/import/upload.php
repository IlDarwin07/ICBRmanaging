<?php
/**
 * Fase 4 – Upload file Inter Club
 * Accetta un file CSV (o XLSX convertito in CSV dal client) e lo processa.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

// Stagioni disponibili per scelta durante l'import
$stagioni = $pdo->query('SELECT * FROM stagioni ORDER BY attiva DESC, codice_stagione DESC')->fetchAll();

// Tipologie disponibili
$tipologie = $pdo->query('SELECT * FROM tipologie_tesseramento WHERE attiva = 1 ORDER BY listino_label')->fetchAll();

$page_title = 'Import file Inter Club';
require __DIR__ . '/../../includes/layout_header.php';
?>

<div class="page-header">
    <h1>Import file Inter Club</h1>
    <a class="btn btn-secondary" href="/import/log.php">Storico importazioni</a>
</div>

<div class="import-info">
    <h2>Istruzioni</h2>
    <p>Carica il file esportato dal portale Inter Club (formato <strong>CSV</strong> o <strong>XLSX</strong>).
    Il sistema riconosce automaticamente le colonne, normalizza i dati e associa ogni riga
    al socio corrispondente tramite codice fiscale, numero tessera o dati anagrafici.</p>
    <ul>
        <li>Formato accettato: <strong>CSV</strong> (sep. virgola o punto-e-virgola) · <strong>XLS / XLSX</strong> (prima colonna = intestazioni)</li>
        <li>Dimensione massima: <strong>5 MB</strong></li>
        <li>Le righe duplicate (stessa stagione + stesso socio) vengono <strong>aggiornate</strong>, non duplicate</li>
        <li>Le righe non abbinabili vengono <strong>segnalate nel log</strong> come scartate</li>
    </ul>
</div>

<form method="post" action="/import/process.php" enctype="multipart/form-data" class="record-form import-form">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">

    <div class="form-row">
        <label>
            File Inter Club *
            <input type="file" name="import_file" accept=".csv,.xls,.xlsx,.txt" required>
            <span class="field-hint">CSV o XLSX esportato dal portale Inter Club</span>
        </label>
    </div>

    <div class="form-row">
        <label>
            Stagione di destinazione *
            <select name="id_stagione" required>
                <option value="">— Seleziona stagione —</option>
                <?php foreach ($stagioni as $s): ?>
                    <option value="<?= (int)$s['id_stagione'] ?>" <?= $s['attiva'] ? 'selected' : '' ?>>
                        <?= h($s['codice_stagione']) ?> <?= $s['attiva'] ? '(attiva)' : '' ?> <?= $s['descrizione'] ? '— ' . h($s['descrizione']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Tipologia default (se non rilevata dal file)
            <select name="id_tipologia_default">
                <option value="">— Nessuna / da file —</option>
                <?php foreach ($tipologie as $t): ?>
                    <option value="<?= (int)$t['id_tipologia'] ?>"><?= h($t['listino_label'] ?: $t['tipo']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <div class="form-row">
        <label>
            Modalità import
            <select name="modalita">
                <option value="upsert">Aggiorna se esiste, inserisce se nuovo (consigliato)</option>
                <option value="insert_only">Inserisci solo i nuovi, salta i duplicati</option>
                <option value="update_only">Aggiorna solo i già esistenti, scarta i nuovi</option>
            </select>
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Avvia importazione</button>
        <a href="/dashboard.php" class="btn btn-secondary">Annulla</a>
    </div>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
