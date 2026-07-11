<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

start_secure_session();
require_login();

$pdo = get_db_connection();

$id_socio = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id_socio <= 0) {
    http_response_code(404);
    die('Socio non trovato.');
}

$stmt = $pdo->prepare('SELECT * FROM soci WHERE id_socio = :id');
$stmt->execute(['id' => $id_socio]);
$socio = $stmt->fetch();

if (!$socio) {
    http_response_code(404);
    die('Socio non trovato.');
}

$errori = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errori[] = 'Sessione scaduta, ricarica la pagina e riprova.';
    } else {
        $campi = [
            'nome', 'cognome', 'sesso', 'data_nascita', 'codice_fiscale', 'nazionalita',
            'comune_nascita', 'indirizzo', 'numero_civico', 'cap', 'provincia', 'comune',
            'telefono', 'email',
        ];
        foreach ($campi as $campo) {
            $socio[$campo] = trim($_POST[$campo] ?? '');
        }
        $attivo_record = isset($_POST['attivo_record']) ? 1 : 0;

        if ($socio['nome'] === '' || $socio['cognome'] === '') {
            $errori[] = 'Nome e cognome sono obbligatori.';
        }

        $codice_fiscale = clean_codice_fiscale($socio['codice_fiscale']);
        if ($codice_fiscale !== null && strlen($codice_fiscale) !== 16) {
            $errori[] = 'Il codice fiscale, se inserito, deve avere 16 caratteri.';
        }

        $data_nascita = $socio['data_nascita'] !== '' ? parse_date_to_sql($socio['data_nascita']) : null;
        if ($socio['data_nascita'] !== '' && $data_nascita === null) {
            // potrebbe già essere in formato Y-m-d se non modificato
            $data_nascita = DateTime::createFromFormat('Y-m-d', $socio['data_nascita']) ? $socio['data_nascita'] : null;
            if ($data_nascita === null) {
                $errori[] = 'Data di nascita non valida.';
            }
        }

        $email = clean_email($socio['email']);
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errori[] = 'Email non valida.';
        }

        if (empty($errori) && $codice_fiscale !== null) {
            $check = $pdo->prepare('SELECT id_socio FROM soci WHERE codice_fiscale = :cf AND id_socio <> :id');
            $check->execute(['cf' => $codice_fiscale, 'id' => $id_socio]);
            if ($check->fetch()) {
                $errori[] = 'Esiste già un altro socio con questo codice fiscale.';
            }
        }

        if (empty($errori)) {
            $update = $pdo->prepare(
                'UPDATE soci SET
                    nome = :nome, cognome = :cognome, sesso = :sesso, data_nascita = :data_nascita,
                    codice_fiscale = :codice_fiscale, nazionalita = :nazionalita, comune_nascita = :comune_nascita,
                    indirizzo = :indirizzo, numero_civico = :numero_civico, cap = :cap, provincia = :provincia,
                    comune = :comune, telefono = :telefono, email = :email, attivo_record = :attivo_record
                 WHERE id_socio = :id'
            );
            $update->execute([
                'nome'           => clean_text($socio['nome']),
                'cognome'        => clean_text($socio['cognome']),
                'sesso'          => $socio['sesso'] !== '' ? strtoupper(substr($socio['sesso'], 0, 1)) : null,
                'data_nascita'   => $data_nascita,
                'codice_fiscale' => $codice_fiscale,
                'nazionalita'    => clean_text($socio['nazionalita']),
                'comune_nascita' => clean_text($socio['comune_nascita']),
                'indirizzo'      => clean_text($socio['indirizzo']),
                'numero_civico'  => clean_text($socio['numero_civico']),
                'cap'            => clean_text($socio['cap']),
                'provincia'      => $socio['provincia'] !== '' ? strtoupper(clean_text($socio['provincia'])) : null,
                'comune'         => clean_text($socio['comune']),
                'telefono'       => clean_phone($socio['telefono']),
                'email'          => $email,
                'attivo_record'  => $attivo_record,
                'id'             => $id_socio,
            ]);

            redirect_with_message('/soci/view.php?id=' . $id_socio, 'Socio aggiornato correttamente.');
        }
    }
}

$csrf = csrf_token();
$page_title = 'Modifica socio';
require __DIR__ . '/../../includes/layout_header.php';
?>
<h1>Modifica socio: <?= h($socio['nome'] . ' ' . $socio['cognome']) ?></h1>

<?php if (!empty($errori)): ?>
    <div class="flash flash-error">
        <ul><?php foreach ($errori as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="/soci/edit.php?id=<?= $id_socio ?>" class="record-form">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="id" value="<?= $id_socio ?>">

    <div class="form-row">
        <label>Nome *<input type="text" name="nome" value="<?= h($socio['nome']) ?>" required></label>
        <label>Cognome *<input type="text" name="cognome" value="<?= h($socio['cognome']) ?>" required></label>
    </div>

    <div class="form-row">
        <label>Sesso
            <select name="sesso">
                <option value="">-</option>
                <option value="M" <?= $socio['sesso'] === 'M' ? 'selected' : '' ?>>M</option>
                <option value="F" <?= $socio['sesso'] === 'F' ? 'selected' : '' ?>>F</option>
            </select>
        </label>
        <label>Data di nascita<input type="text" name="data_nascita" placeholder="gg-mm-aaaa" value="<?= h($socio['data_nascita']) ?>"></label>
        <label>Codice fiscale<input type="text" name="codice_fiscale" maxlength="16" value="<?= h($socio['codice_fiscale']) ?>"></label>
    </div>

    <div class="form-row">
        <label>Nazionalità<input type="text" name="nazionalita" value="<?= h($socio['nazionalita']) ?>"></label>
        <label>Comune di nascita<input type="text" name="comune_nascita" value="<?= h($socio['comune_nascita']) ?>"></label>
    </div>

    <div class="form-row">
        <label>Indirizzo<input type="text" name="indirizzo" value="<?= h($socio['indirizzo']) ?>"></label>
        <label>Civico<input type="text" name="numero_civico" value="<?= h($socio['numero_civico']) ?>"></label>
    </div>

    <div class="form-row">
        <label>CAP<input type="text" name="cap" value="<?= h($socio['cap']) ?>"></label>
        <label>Provincia<input type="text" name="provincia" maxlength="10" value="<?= h($socio['provincia']) ?>"></label>
        <label>Comune<input type="text" name="comune" value="<?= h($socio['comune']) ?>"></label>
    </div>

    <div class="form-row">
        <label>Telefono<input type="text" name="telefono" value="<?= h($socio['telefono']) ?>"></label>
        <label>Email<input type="email" name="email" value="<?= h($socio['email']) ?>"></label>
    </div>

    <label class="checkbox-inline">
        <input type="checkbox" name="attivo_record" value="1" <?= $socio['attivo_record'] ? 'checked' : '' ?>>
        Record attivo
    </label>

    <button type="submit" class="btn">Salva modifiche</button>
    <a class="btn btn-secondary" href="/soci/view.php?id=<?= $id_socio ?>">Annulla</a>
</form>

<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
