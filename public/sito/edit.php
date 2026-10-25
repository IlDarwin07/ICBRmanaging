<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/site_content.php';
require_login();
$values = site_content();
$revision = site_revision();
$errors = [];
$groups = [
    'Il primo impatto' => [
        'stagione'=>['Stagione',30], 'hero_titolo'=>['Titolo principale',120],
        'hero_testo'=>['Testo introduttivo',600], 'hero_pulsante'=>['Testo del pulsante',60],
    ],
    'La voce del club' => [
        'club_titolo'=>['Titolo del club',150], 'club_intro'=>['Frase introduttiva',180],
        'club_testo'=>['Il racconto del club',2400],
    ],
    'Tesseramento' => [
        'tesseramento_titolo'=>['Titolo tesseramento',150],
        'tessera_quote_nota'=>['Nota sulle quote e sulla conferma con il direttivo',1000],
    ],
    'La sede e i contatti' => [
        'sede_titolo'=>['Titolo sede',150], 'sede_testo'=>['Come venire a trovarci',600],
        'indirizzo'=>['Indirizzo',160], 'citta'=>['CAP e città',100],
        'mappe'=>['Collegamento Google Maps (HTTPS)',500],
        'contatti_titolo'=>['Titolo contatti',150], 'contatti_testo'=>['Invito a scriverci',600],
    ],
    'I vostri social' => [
        'instagram'=>['Instagram: URL del profilo',500], 'facebook'=>['Facebook: URL della pagina',500],
        'youtube'=>['YouTube: URL del canale',500], 'tiktok'=>['TikTok: URL del profilo',500],
    ],
];
$membershipGroups = [];
foreach (site_membership_types() as $i=>$membership) {
    $membershipGroups['Tessera '.$membership['nome']] = [
        "tessera_{$i}_titolo"=>['Nome della formula',40],
        "tessera_{$i}_testo"=>['Presentazione breve',400],
        "tessera_{$i}_quota"=>['Quota di riferimento, compreso il simbolo €',40],
        "tessera_{$i}_dettagli"=>['Spiegazione, requisiti e servizi da confermare',1200],
    ];
}
$groups = array_merge(array_slice($groups,0,3,true),$membershipGroups,array_slice($groups,3,null,true));
$photos = ['hero_foto'=>'Foto principale','sede_foto'=>'Foto della sede','ricordi_foto'=>'Foto del racconto'];
$availablePhotos = array_values(array_filter(array_map('basename', glob(__DIR__ . '/../assets/images/*') ?: []),
    fn($name) => site_image_valid($name) && $name !== 'logo-inter-club-brindisi.jpg'));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $postedRevision = is_string($_POST['revision'] ?? null) ? $_POST['revision'] : '';
    $reset = ($_POST['azione'] ?? '') === 'ripristina';
    $candidate = $reset ? require __DIR__ . '/../../config/site_defaults.php' : $values;
    if (!$reset) {
        foreach ($groups as $fields) foreach ($fields as $key=>[$label,$limit]) {
            $text = trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : '');
            $candidate[$key] = $text;
            if (mb_strlen($text) > $limit) $errors[] = "$label: massimo $limit caratteri.";
            if (!in_array($key,['instagram','facebook','youtube','tiktok'],true) && $text === '') $errors[] = "$label: il campo è obbligatorio.";
        }
        foreach (['instagram','facebook','youtube','tiktok'] as $key) {
            if (!site_social_valid($key, $candidate[$key])) $errors[] = ucfirst($key) . ': inserisci l’URL HTTPS di un profilo della piattaforma corretta, oppure lascia vuoto.';
        }
        $map = parse_url($candidate['mappe']);
        if (!filter_var($candidate['mappe'], FILTER_VALIDATE_URL) || ($map['scheme'] ?? '') !== 'https'
            || !in_array(strtolower($map['host'] ?? ''),['google.com','www.google.com','maps.google.com','maps.app.goo.gl','goo.gl'],true)
            || isset($map['user']) || isset($map['pass']) || isset($map['port'])) $errors[] = 'Usa un collegamento Google Maps HTTPS valido.';
        foreach ($photos as $key=>$label) {
            $photo = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
            if (!in_array($photo,$availablePhotos,true)) $errors[] = "$label: scegli una foto presente nell’archivio.";
            else $candidate[$key] = $photo;
        }
    }
    $newFiles = [];
    if (!$reset && !$errors) foreach ($photos as $key=>$label) {
        $upload = $_FILES[$key . '_upload'] ?? null;
        if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if (!is_array($upload) || !is_int($upload['error'] ?? null) || $upload['error'] !== UPLOAD_ERR_OK
            || !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])
            || !is_int($upload['size'] ?? null) || $upload['size'] <= 0 || $upload['size'] > 6 * 1024 * 1024) {
            $errors[] = "$label: carica un’immagine valida, massimo 6 MB."; continue;
        }
        $info = @getimagesize($upload['tmp_name']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!$info || !isset($extensions[$mime]) || ($info['mime'] ?? '') !== $mime
            || $info[0] < 640 || $info[1] < 360 || $info[0] * $info[1] > 24000000) {
            $errors[] = "$label: usa JPEG, PNG o WebP, almeno 640×360 px e massimo 24 megapixel. SVG e altri file non sono accettati."; continue;
        }
        $name = 'club-upload-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
        $path = __DIR__ . '/../assets/images/' . $name;
        if (!move_uploaded_file($upload['tmp_name'],$path)) { $errors[] = "$label: cartella foto non scrivibile. Controlla i permessi."; continue; }
        chmod($path,0644);
        $newFiles[] = $path;
        $candidate[$key] = $name;
    }
    if (!$errors) {
        try {
            site_save($candidate,$postedRevision);
            $newFiles = []; // Le foto sono già referenziate dal file pubblicato: non rimuoverle.
            try { audit_event('sito',$reset ? 'ripristino_vetrina' : 'pubblicazione_vetrina'); }
            catch (Throwable $auditError) {
                error_log('ICBR: vetrina salvata, registro attività non disponibile.');
                $_SESSION['flash'] = ['type'=>'error','message'=>'Il sito è stato aggiornato, ma non è stato possibile scrivere il registro attività. Segnala il problema a un amministratore prima di proseguire.'];
                header('Location: ' . u('/sito/edit.php'),true,303); exit;
            }
            $_SESSION['flash'] = ['type'=>'success','message'=>$reset ? 'Contenuti iniziali ripristinati.' : 'Sito aggiornato. Le modifiche sono già visibili nella vetrina.'];
            header('Location: ' . u('/sito/edit.php'),true,303); exit;
        } catch (Throwable $e) { $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Non è stato possibile salvare. Riprova.'; }
    }
    foreach ($newFiles as $file) if (is_file($file)) {
        unlink($file);
        foreach ($photos as $key=>$label) if (($candidate[$key] ?? '') === basename($file)) $candidate[$key] = $values[$key];
    }
    $values = $candidate;
    // In caso di conflitto non aggiornare la revisione: prima occorre ricaricare.
    $revision = $postedRevision;
}
$page_title = 'La vetrina del club';
require __DIR__ . '/../../includes/layout_header.php';
?>
<div class="page-header"><div><span class="admin-eyebrow">SITO PUBBLICO / CONTENUTI</span><h1>La vetrina del club</h1><p class="muted">La vostra voce, senza mettere mano al codice.</p></div><a class="btn btn-secondary" href="<?= u('/index.php') ?>" target="_blank" rel="noopener">Apri il sito ↗</a></div>
<?php if ($errors): ?><div class="flash flash-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="cms-layout">
<aside class="admin-panel cms-guide"><span class="admin-eyebrow">PICCOLA GUIDA</span><h2>Parlate come al club.</h2><p>Frasi semplici, informazioni utili e un po’ della vostra passione. Per andare a capo nei titoli, premi Invio.</p><p>Il salvataggio aggiorna subito il sito pubblico. I social lasciati vuoti non compaiono nel footer.</p><p>Per ogni tessera puoi aggiornare nome, presentazione, quota e spiegazione. Le quote della vetrina non modificano i listini del gestionale: tienili coerenti quando cambiano gli importi.</p><p>Le foto restano sul server, senza dipendere da Instagram. Caricate solo immagini che avete il diritto di utilizzare.</p><p class="muted">Niente HTML o codice: i testi sono mostrati come testo, non eseguiti.</p><a class="text-link" href="#tesseramento-sito">Vai alle tessere ↓</a><a class="text-link" href="#foto-sito">Vai alle fotografie ↓</a></aside>
<form method="post" enctype="multipart/form-data" class="cms-form">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="revision" value="<?= h($revision) ?>">
<?php foreach ($groups as $title=>$fields): ?>
<section class="admin-panel cms-section" <?= $title==='Tesseramento' ? 'id="tesseramento-sito"' : '' ?>><h2><?= h($title) ?></h2><div class="cms-fields">
<?php foreach ($fields as $key=>[$label,$limit]): ?><div class="cms-field"><label for="<?= h($key) ?>"><?= h($label) ?></label>
<?php if ((str_ends_with($key,'titolo') && !preg_match('/^tessera_\d+_titolo$/',$key)) || str_ends_with($key,'testo') || str_ends_with($key,'dettagli') || $key === 'tessera_quote_nota' || $key === 'club_intro'): ?>
<textarea id="<?= h($key) ?>" name="<?= h($key) ?>" rows="<?= $key === 'club_testo' ? 7 : (str_ends_with($key,'dettagli') ? 6 : 3) ?>" maxlength="<?= $limit ?>" required><?= h($values[$key]) ?></textarea>
<?php else: ?><input id="<?= h($key) ?>" name="<?= h($key) ?>" type="<?= in_array($key,['instagram','facebook','youtube','tiktok','mappe'],true) ? 'url' : 'text' ?>" maxlength="<?= $limit ?>" value="<?= h($values[$key]) ?>" <?= in_array($key,['instagram','facebook','youtube','tiktok'],true) ? '' : 'required' ?>><?php endif; ?>
</div><?php endforeach; ?></div></section><?php endforeach; ?>
<section class="admin-panel cms-section" id="foto-sito"><h2>Le fotografie</h2><p class="muted">Scegli dall’archivio o carica una foto nuova. JPEG, PNG o WebP, massimo 6 MB, almeno 640×360 px.</p>
<?php foreach ($photos as $key=>$label): ?><div class="cms-photo"><img src="<?= u('/assets/images/' . (site_image_valid($values[$key]) ? $values[$key] : 'club-sede.jpg')) ?>" alt="Anteprima <?= h(mb_strtolower($label)) ?>" width="320" height="180"><div><label for="<?= h($key) ?>"><?= h($label) ?></label><select id="<?= h($key) ?>" name="<?= h($key) ?>"><?php foreach ($availablePhotos as $photo): ?><option value="<?= h($photo) ?>" <?= $values[$key] === $photo ? 'selected' : '' ?>><?= h($photo) ?></option><?php endforeach; ?></select><label for="<?= h($key) ?>_upload">Oppure carica una nuova foto</label><input id="<?= h($key) ?>_upload" name="<?= h($key) ?>_upload" type="file" accept="image/jpeg,image/png,image/webp"></div></div><?php endforeach; ?>
</section>
<div class="cms-save"><p>Il salvataggio rende pubblici testi, social e foto.</p><button class="btn" type="submit" name="azione" value="salva">Salva e aggiorna il sito</button></div>
</form>
</div>
<form method="post" class="cms-reset" data-confirm="Ripristinare tutti i testi, i social e le foto iniziali? Le personalizzazioni verranno sostituite."><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="revision" value="<?= h($revision) ?>"><button class="btn btn-secondary" name="azione" value="ripristina">Ripristina i contenuti iniziali</button></form>
<?php require __DIR__ . '/../../includes/layout_footer.php'; ?>
