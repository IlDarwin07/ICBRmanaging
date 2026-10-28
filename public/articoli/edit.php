<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/articoli.php';
require_login();

$ready = articoli_table_ready();
$id = filter_var($_GET['id'] ?? $_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$article = ($ready && $id > 0) ? articoli_admin_find($id) : null;
if ($ready && $id > 0 && !$article) {
    render_error_page('Articolo non trovato', 'L’articolo richiesto non esiste più.', 404);
}
$values = $article ?: [
    'titolo' => '', 'sommario' => '', 'testo' => '', 'data_evento' => date('Y-m-d'),
    'luogo' => '', 'copertina' => '', 'copertina_alt' => '', 'pubblicato' => 0,
];
$currentGallery = articoli_gallery_decode($article['galleria'] ?? null);
$errors = [];
$uploaded = [];

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $field = fn(string $key) => trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : '');
    $values['titolo'] = $field('titolo');
    $values['sommario'] = $field('sommario');
    $values['testo'] = str_replace(["\r\n", "\r"], "\n", $field('testo'));
    $values['data_evento'] = $field('data_evento');
    $values['luogo'] = $field('luogo');
    $values['copertina_alt'] = $field('copertina_alt');
    $values['pubblicato'] = !empty($_POST['pubblicato']) ? 1 : 0;
    $removeCover = !empty($_POST['rimuovi_copertina']);

    $limits = ['titolo' => ['Titolo', 160, true], 'sommario' => ['Frase di presentazione', 300, true], 'testo' => ['Il racconto', 12000, true], 'luogo' => ['Luogo', 160, false], 'copertina_alt' => ['Descrizione della foto', 200, false]];
    foreach ($limits as $key => [$label, $max, $required]) {
        if ($required && $values[$key] === '') $errors[] = "$label: il campo è obbligatorio.";
        if (mb_strlen($values[$key]) > $max) $errors[] = "$label: massimo $max caratteri.";
    }
    $date = DateTime::createFromFormat('Y-m-d', $values['data_evento']);
    if (!$date || $date->format('Y-m-d') !== $values['data_evento']) $errors[] = 'Data evento: inserisci una data valida.';

    $removeList = array_filter((array)($_POST['galleria_rimuovi'] ?? []), 'is_string');
    $removedFiles = [];
    $gallery = [];
    foreach ($currentGallery as $item) {
        if (in_array($item['file'], $removeList, true)) { $removedFiles[] = $item['file']; continue; }
        $caption = $_POST['galleria_alt'][$item['file']] ?? '';
        $item['alt'] = mb_substr(trim(is_string($caption) ? $caption : ''), 0, 200);
        $gallery[] = $item;
    }

    $newCover = null;
    if (!$errors) {
        $newCover = articoli_save_cover($_FILES['copertina_upload'] ?? null, $errors);
        if ($newCover !== null) $uploaded[] = $newCover;
    }
    if (!$errors) {
        for ($i = 0; $i < ARTICOLI_NEW_PHOTO_SLOTS; $i++) {
            $slot = $_FILES['galleria_nuova_' . $i] ?? null;
            if (!$slot || ($slot['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            if (count($gallery) >= ARTICOLI_GALLERY_MAX) {
                $errors[] = 'Foto nell’articolo: massimo ' . ARTICOLI_GALLERY_MAX . ' immagini.';
                break;
            }
            $name = articoli_save_cover($slot, $errors, 'Foto ' . ($i + 1));
            if ($name === null) continue;
            $uploaded[] = $name;
            $caption = $_POST['galleria_nuova_alt_' . $i] ?? '';
            $gallery[] = ['file' => $name, 'alt' => mb_substr(trim(is_string($caption) ? $caption : ''), 0, 200)];
        }
    }

    if (!$errors) {
        $cover = $removeCover ? '' : (string)($values['copertina'] ?? '');
        if ($newCover !== null) $cover = $newCover;
        try {
            $pdo = get_db_connection();
            $params = [
                'titolo' => $values['titolo'], 'sommario' => $values['sommario'], 'testo' => $values['testo'],
                'data' => $values['data_evento'], 'luogo' => $values['luogo'] !== '' ? $values['luogo'] : null,
                'copertina' => $cover !== '' ? $cover : null,
                'alt' => $values['copertina_alt'] !== '' ? $values['copertina_alt'] : null,
                'galleria' => articoli_gallery_encode($gallery),
                'pubblicato' => $values['pubblicato'],
            ];
            if ($article) {
                $stmt = $pdo->prepare('UPDATE articoli_eventi SET titolo = :titolo, sommario = :sommario, testo = :testo, data_evento = :data, luogo = :luogo, copertina = :copertina, copertina_alt = :alt, galleria = :galleria, pubblicato = :pubblicato WHERE id_articolo = :id');
                $stmt->execute($params + ['id' => $article['id_articolo']]);
                $savedId = (int)$article['id_articolo'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO articoli_eventi (slug, titolo, sommario, testo, data_evento, luogo, copertina, copertina_alt, galleria, pubblicato, id_autore) VALUES (:slug, :titolo, :sommario, :testo, :data, :luogo, :copertina, :alt, :galleria, :pubblicato, :autore)');
                $stmt->execute($params + ['slug' => articoli_unique_slug(articoli_slug($values['titolo'])), 'autore' => current_user()['id_utente'] ?? null]);
                $savedId = (int)$pdo->lastInsertId();
            }
            foreach ($removedFiles as $file) {
                if (str_starts_with($file, 'evento-') && is_file(__DIR__ . '/../assets/images/' . $file)) @unlink(__DIR__ . '/../assets/images/' . $file);
            }
            try {
                audit_event('articoli', $article ? 'modifica' : 'creazione', 'articolo', (string)$savedId, ['titolo' => $values['titolo'], 'pubblicato' => $values['pubblicato']]);
            } catch (Throwable $auditError) {
                error_log('ICBR: articolo salvato, registro attività non disponibile.');
            }
            redirect_with_message('/articoli/list.php', $values['pubblicato'] ? 'Articolo salvato e pubblicato.' : 'Articolo salvato come bozza.');
        } catch (Throwable $e) {
            $errors[] = 'Non è stato possibile salvare. Riprova.';
        }
    }
    if ($errors) {
        foreach ($uploaded as $file) {
            if (is_file(__DIR__ . '/../assets/images/' . $file)) @unlink(__DIR__ . '/../assets/images/' . $file);
        }
    }
}

$freeSlots = max(0, ARTICOLI_GALLERY_MAX - count($currentGallery));
$page_title = $article ? 'Modifica articolo' : 'Nuovo articolo';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header">
    <div>
        <span class="admin-eyebrow">SITO PUBBLICO / EVENTI</span>
        <h1><?= $article ? 'Modifica articolo' : 'Nuovo articolo' ?></h1>
    </div>
    <a class="btn btn-secondary" href="<?= u('/articoli/list.php') ?>">Torna all’elenco</a>
</div>
<?php if (!$ready): ?><div class="flash flash-error" role="alert">Tabella articoli non disponibile: applica la migrazione 011 e ricarica.</div><?php endif; ?>
<?php if ($errors): ?><div class="flash flash-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="cms-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <section class="admin-panel cms-section">
        <h2>Il racconto</h2>
        <div class="cms-fields">
            <div class="cms-field"><label for="titolo">Titolo</label><input id="titolo" name="titolo" maxlength="160" required value="<?= h($values['titolo']) ?>"></div>
            <div class="cms-field"><label for="data_evento">Data dell’evento</label><input id="data_evento" name="data_evento" type="date" required value="<?= h($values['data_evento']) ?>"></div>
            <div class="cms-field"><label for="luogo">Luogo <span class="muted">(facoltativo)</span></label><input id="luogo" name="luogo" maxlength="160" value="<?= h($values['luogo'] ?? '') ?>"></div>
            <div class="cms-field"><label for="sommario">Frase di presentazione <span class="muted">(compare nell’elenco e su Google)</span></label><textarea id="sommario" name="sommario" rows="3" maxlength="300" required><?= h($values['sommario']) ?></textarea></div>
            <div class="cms-field"><label for="testo">Testo</label><textarea id="testo" name="testo" rows="14" maxlength="12000" required><?= h($values['testo']) ?></textarea><p class="note">Testo semplice: lascia una riga vuota per iniziare un nuovo paragrafo. Niente HTML o codice.</p></div>
        </div>
    </section>
    <section class="admin-panel cms-section">
        <h2>Foto di copertina</h2>
        <p class="muted">JPEG, PNG o WebP, massimo 6 MB, almeno 640×360 px. Carica solo foto del club o di cui avete i diritti: niente immagini di altri siti.</p>
        <?php if (!empty($values['copertina']) && site_image_valid($values['copertina'])): ?>
            <div class="cms-photo"><img src="<?= u('/assets/images/' . $values['copertina']) ?>" alt="Copertina attuale" width="320" height="180"><div><label><input type="checkbox" name="rimuovi_copertina" value="1"> Rimuovi la copertina</label></div></div>
        <?php endif; ?>
        <div class="cms-fields">
            <div class="cms-field"><label for="copertina_upload">Carica una nuova copertina</label><input id="copertina_upload" name="copertina_upload" type="file" accept="image/jpeg,image/png,image/webp"></div>
            <div class="cms-field"><label for="copertina_alt">Descrizione della foto <span class="muted">(per chi non la vede)</span></label><input id="copertina_alt" name="copertina_alt" maxlength="200" value="<?= h($values['copertina_alt'] ?? '') ?>"></div>
        </div>
    </section>
    <section class="admin-panel cms-section">
        <h2>Foto nell’articolo</h2>
        <p class="muted">Fino a <?= ARTICOLI_GALLERY_MAX ?> foto, mostrate sotto il testo. La didascalia compare sotto la foto. Ne puoi aggiungere <?= ARTICOLI_NEW_PHOTO_SLOTS ?> per volta.</p>
        <?php foreach ($currentGallery as $photo): ?>
            <div class="cms-photo">
                <img src="<?= u('/assets/images/' . $photo['file']) ?>" alt="Foto dell’articolo" width="320" height="180">
                <div>
                    <label for="alt-<?= h($photo['file']) ?>">Didascalia</label>
                    <input id="alt-<?= h($photo['file']) ?>" name="galleria_alt[<?= h($photo['file']) ?>]" maxlength="200" value="<?= h($photo['alt']) ?>">
                    <label><input type="checkbox" name="galleria_rimuovi[]" value="<?= h($photo['file']) ?>"> Rimuovi questa foto</label>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($freeSlots > 0): ?>
            <div class="cms-fields">
            <?php for ($i = 0; $i < min($freeSlots, ARTICOLI_NEW_PHOTO_SLOTS); $i++): ?>
                <div class="cms-field">
                    <label for="galleria_nuova_<?= $i ?>">Nuova foto <?= $i + 1 ?></label>
                    <input id="galleria_nuova_<?= $i ?>" name="galleria_nuova_<?= $i ?>" type="file" accept="image/jpeg,image/png,image/webp">
                    <input name="galleria_nuova_alt_<?= $i ?>" maxlength="200" placeholder="Didascalia (facoltativa)" aria-label="Didascalia della nuova foto <?= $i + 1 ?>">
                </div>
            <?php endfor; ?>
            </div>
        <?php else: ?>
            <p class="note">Hai raggiunto il massimo di <?= ARTICOLI_GALLERY_MAX ?> foto: rimuovine una per aggiungerne altre.</p>
        <?php endif; ?>
    </section>
    <div class="cms-save">
        <label><input type="checkbox" name="pubblicato" value="1" <?= !empty($values['pubblicato']) ? 'checked' : '' ?>> Pubblica sul sito</label>
        <button class="btn" type="submit">Salva articolo</button>
    </div>
</form>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
