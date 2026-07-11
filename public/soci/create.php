<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();
$errori = [];
$dati = [
    'nome' => '', 'cognome' => '', 'sesso' => '', 'data_nascita' => '',
    'codice_fiscale' => '', 'nazionalita' => '', 'comune_nascita' => '',
    'indirizzo' => '', 'numero_civico' => '', 'cap' => '', 'provincia' => '',
    'comune' => '', 'telefono' => '', 'email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errori[] = 'Sessione scaduta, ricarica la pagina e riprova.';
    } else {
        foreach ($dati as $campo => $default) {
            $dati[$campo] = trim($_POST[$campo] ?? '');
        }

        if ($dati['nome'] === '' || $dati['cognome'] === '') {
            $errori[] = 'Nome e cognome sono obbligatori.';
        }

        $codice_fiscale = clean_codice_fiscale($dati['codice_fiscale']);
        if ($codice_fiscale !== null && strlen($codice_fiscale) !== 16) {
            $errori[] = 'Il codice fiscale, se inserito, deve avere 16 caratteri.';
        }

        $data_nascita = $dati['data_nascita'] !== '' ? parse_date_to_sql($dati['data_nascita']) : null;
        if ($dati['data_nascita'] !== '' && $data_nascita === null) {
            $errori[] = 'Data di nascita non valida (usa formato gg-mm-aaaa).';
        }

        $email = clean_email($dati['email']);
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errori[] = 'Email non valida.';
        }

        if (empty($errori)) {
            // Controllo duplicati su codice fiscale, coerente con le regole
            // di matching descritte nel documento di mappatura import.
            if ($codice_fiscale !== null) {
                $check = $pdo->prepare('SELECT id_socio FROM soci WHERE codice_fiscale = :cf');
                $check->execute(['cf' => $codice_fiscale]);
                if ($check->fetch()) {
                    $errori[] = 'Esiste già un socio con questo codice fiscale.';
                }
            }
        }

        if (empty($errori)) {
            $stmt = $pdo->prepare(
                'INSERT INTO soci
                 (nome, cognome, sesso, data_nascita, codice_fiscale, nazionalita, comune_nascita,
                  indirizzo, numero_civico, cap, provincia, comune, telefono, email, attivo_record)
                 VALUES
                 (:nome, :cognome, :sesso, :data_nascita, :codice_fiscale, :nazionalita, :comune_nascita,
                  :indirizzo, :numero_civico, :cap, :provincia, :comune, :telefono, :email, 1)'
            );
            $stmt->execute([
                'nome'           => clean_text($dati['nome']),
                'cognome'        => clean_text($dati['cognome']),
                'sesso'          => $dati['sesso'] !== '' ? strtoupper(substr($dati['sesso'], 0, 1)) : null,
                'data_nascita'   => $data_nascita,
                'codice_fiscale' => $codice_fiscale,
                'nazionalita'    => clean_text($dati['nazionalita']),
                'comune_nascita' => clean_text($dati['comune_nascita']),
                'indirizzo'      => clean_text($dati['indirizzo']),
                'numero_civico'  => clean_text($dati['numero_civico']),
                'cap'            => clean_text($dati['cap']),
                'provincia'      => $dati['provincia'] !== '' ? strtoupper(clean_text($dati['provincia'])) : null,
                'comune'         => clean_text($dati['comune']),
                'telefono'       => clean_phone($dati['telefono']),
                'email'          => $email,
            ]);

            $id_socio = (int)$pdo->lastInsertId();
            redirect_with_message('/soci/view.php?id=' . $id_socio, 'Socio creato correttamente.');
        }
    }
}

$csrf = csrf_token();
$page_title = 'Nuovo socio';
require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Nuovo socio</h1>

<?php if (!empty($errori)): ?>
    <div class="flash flash-error">
        <ul><?php foreach ($errori as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="/soci/create.php" class="record-form">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <div class="form-row">
        <label>Nome *<input type="text" name="nome" value="<?= h($dati['nome']) ?>" required></label>
        <label>Cognome *<input type="text" name="cognome" value="<?= h($dati['cognome']) ?>" required></label>
    </div>

    <div class="form-row">
        <label>Sesso
            <select name="sesso">
                <option value="">-</option>
                <option value="M" <?= $dati['sesso'] === 'M' ? 'selected' : '' ?>>M</option>
                <option value="F" <?= $dati['sesso'] === 'F' ? 'selected' : '' ?>>F</option>
            </select>
        </label>
        <label>Data di nascita<input type="text" name="data_nascita" placeholder="gg-mm-aaaa" value="<?= h($dati['data_nascita']) ?>"></label>
        <label>Codice fiscale<input type="text" name="codice_fiscale" maxlength="16" value="<?= h($dati['codice_fiscale']) ?>"></label>
    </div>

    <div class="form-row">
        <label>Nazionalità<input type="text" name="nazionalita" value="<?= h($dati['nazionalita']) ?>"></label>
        <label>Comune di nascita<input type="text" name="comune_nascita" value="<?= h($dati['comune_nascita']) ?>"></label>
    </div>

    <div class="form-row">
        <label>Indirizzo<input type="text" name="indirizzo" value="<?= h($dati['indirizzo']) ?>"></label>
        <label>Civico<input type="text" name="numero_civico" value="<?= h($dati['numero_civico']) ?>"></label>
    </div>

    <div class="form-row">
        <label>CAP<input type="text" name="cap" value="<?= h($dati['cap']) ?>"></label>
        <label>Provincia<input type="text" name="provincia" maxlength="10" value="<?= h($dati['provincia']) ?>"></label>
        <label>Comune<input type="text" name="comune" value="<?= h($dati['comune']) ?>"></label>
    </div>

    <div class="form-row">
        <label>Telefono<input type="text" name="telefono" value="<?= h($dati['telefono']) ?>"></label>
        <label>Email<input type="email" name="email" value="<?= h($dati['email']) ?>"></label>
    </div>

    <button type="submit" class="btn">Salva socio</button>
    <a class="btn btn-secondary" href="/soci/list.php">Annulla</a>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
