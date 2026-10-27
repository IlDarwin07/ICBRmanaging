<?php
require_once __DIR__ . '/functions.php';

/** Formule del listino locale; nessuna connessione pubblica al database. */
function site_membership_types(): array
{
    return [
        1=>['tipo'=>'fedelissimo','nome'=>'Fedelissimo','rinnovo'=>'90 €'],
        2=>['tipo'=>'sostenitore','nome'=>'Sostenitore','rinnovo'=>'50 €'],
        3=>['tipo'=>'fuori_sede','nome'=>'Fuori sede','rinnovo'=>'45 €'],
        4=>['tipo'=>'family','nome'=>'Family','rinnovo'=>'45 €'],
        5=>['tipo'=>'junior','nome'=>'Junior','rinnovo'=>'25 €'],
        6=>['tipo'=>'quota_plus','nome'=>'Quota Plus','rinnovo'=>null],
    ];
}

function site_store_dir(): string
{
    return __DIR__ . '/../storage/site';
}

function site_image_valid(string $name): bool
{
    return preg_match('/\A[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)\z/', $name) === 1
        && is_file(__DIR__ . '/../public/assets/images/' . $name);
}

/** Aggiorna soltanto vecchi testi standard, senza sovrascrivere personalizzazioni. */
function site_upgrade_content(array $content): array
{
    $defaults = require __DIR__ . '/../config/site_defaults.php';
    $previous = [
        'hero_testo' => ['La partita sullo schermo, gli amici accanto. A Brindisi la viviamo così. Se ti va di esserci, c’è posto anche per te.', 'La partita sullo schermo, gli amici accanto e un coro che ci unisce. A Brindisi l’Inter si vive insieme. Il prossimo coro, cantiamolo insieme.', 'La partita sullo schermo, gli amici accanto e un coro che ci unisce. A Brindisi l’Inter si vive così.'],
        'hero_pulsante' => ['Vieni a conoscerci', 'Entra nella famiglia nerazzurra'],
        'tesseramento_titolo' => "Nuova stagione.\nStessa voglia di esserci.",
        'tessera_1_testo' => ['Il club è già un po’ casa tua? Scrivici e scopri come vivere insieme la nuova stagione.', 'Le partite, gli amici, il tuo posto al club. Vivi la stagione da protagonista, insieme a noi.'],
        'tessera_2_testo' => ['Hai voglia di far parte del gruppo e dare il tuo sostegno? Ti raccontiamo come funziona.', 'La tua passione dà forza al club. Entra nel gruppo e sostieni la nostra famiglia nerazzurra.'],
        'sede_titolo' => ["Qui ci sentiamo\na casa.", 'Ci vediamo al club.'],
        'sede_testo' => ['Siamo in via San Benedetto, nel centro di Brindisi. Vuoi passare a salutarci? Scrivici prima, così ci mettiamo d’accordo.', 'Una partita, due chiacchiere e gli stessi colori nel cuore. Vieni a conoscere la tua famiglia nerazzurra in via San Benedetto, nel centro di Brindisi. Contatta il direttivo prima di passare: ci mettiamo d’accordo e ci vediamo in sede.', 'Una partita, due chiacchiere e gli stessi colori nel cuore, nel centro di Brindisi. Scrivi al direttivo prima di passare: le aperture si concordano insieme.'],
        'contatti_titolo' => "Due chiacchiere,\npoi si parte.",
        'contatti_testo' => ['Tesseramento, rinnovo o semplicemente voglia di conoscerci? Scrivici qui. Ti risponde il direttivo, non un bot.', 'L’Inter ci unisce, Brindisi ci fa incontrare. Contatta il direttivo per scoprire come tesserarti, conoscere il club o passare in sede: la prossima partita possiamo viverla insieme.'],
        'tessera_3_testo' => ['Lontano da Brindisi, ma sempre dei nostri. La distanza non cambia i colori.', 'Abiti fuori dal Comune di Brindisi? C’è un posto anche per te nella nostra famiglia nerazzurra.'],
        'tessera_3_dettagli' => ['Vivi lontano da Brindisi e vuoi restare vicino al nostro club? Chiedici della formula Fuori sede: verifichiamo insieme i requisiti e le modalità di partecipazione previste, senza dare per scontati gli stessi servizi delle altre formule.', 'Fuori sede è la formula riservata a chi risiede fuori dal Comune di Brindisi, anche in un comune vicino. Conta la residenza, non la distanza dalla sede: scrivici per conoscere le modalità di adesione e i servizi previsti.'],
        'tessera_4_testo' => ['Gli stessi colori, in famiglia. La passione nerazzurra si tramanda.', 'La passione si vive in famiglia, insieme a un socio Fedelissimo.'],
        'tessera_4_dettagli' => ['Family è la voce del listino dedicata alle adesioni familiari. Prima di iscrivere più persone, verifica con il direttivo abbinamenti e requisiti: la quota indicata non va considerata automaticamente un prezzo per tutto il nucleo familiare.', 'La tessera Family è attivabile in abbinamento a un tesseramento Fedelissimo. Gli stessi colori, anche in famiglia: scrivici per organizzare le adesioni. La quota Family si riferisce alla singola tessera, non all’intero nucleo familiare.'],
        'tessera_5_testo' => ['Piccoli tifosi, grande passione. Il nerazzurro comincia presto.', 'Per tutti i soci under 14. Piccoli tifosi, grande passione.'],
        'tessera_5_dettagli' => ['La formula Junior è dedicata ai soci più giovani. Ti aiutiamo a verificare la fascia d’età ammessa per la stagione e ciò che serve per iscrivere un minore: scrivici senza inviare dati o documenti del bambino nel modulo pubblico.', 'Tutti i soci under 14, cioè con meno di 14 anni, possono scegliere la tessera Junior. Il nerazzurro comincia presto: ti aiutiamo a organizzare l’iscrizione di un minore. Scrivici senza inviare dati o documenti del bambino nel modulo pubblico.'],
        'tessera_quote_nota' => ['Le quote mostrate sono i riferimenti del listino ICBR 2026/27 per i nuovi soci; Plus è un’aggiunta alla quota Senior. Conferma sempre importo, requisiti e servizi inclusi con il direttivo prima di versare. Da questa pagina puoi chiedere informazioni, non acquistare una tessera.', 'Quote del listino ICBR 2026/27 per i nuovi soci. Importo, requisiti e servizi inclusi si confermano con il direttivo prima di versare: da qui chiedi informazioni, non acquisti la tessera.'],
        'club_testo' => "C’è chi arriva in anticipo, chi si tiene il posto di sempre e chi commenta ogni pallone. Poi comincia la partita e siamo tutti dalla stessa parte.\n\nSiamo l’Inter Club Javier Zanetti di Brindisi. Dal 2003 condividiamo questi colori e tutto quello che ci gira intorno: le serate in sede, le trasferte, le amicizie che restano anche dopo il fischio finale.",
        'tessera_1_dettagli' => 'Fedelissimo è la formula del nostro listino per chi vuole vivere e sostenere da vicino la vita del club durante la stagione. Prima di aderire ti confermiamo accesso alla sede, attività e servizi compresi.',
        'tessera_2_dettagli' => 'Sostenitore è una delle formule di adesione ICBR per entrare nel gruppo e contribuire alla vita del club. Ti aiutiamo a capire se è quella adatta a te, spiegandoti attività e servizi previsti prima dell’iscrizione.',
        'tessera_6_dettagli' => 'La Quota Plus si aggiunge a un tesseramento Senior: non sostituisce la quota della tessera scelta. Se ti interessa, chiedici come abbinarla alla tua iscrizione e quali condizioni sono previste.',
    ];
    foreach ($previous as $key => $values) {
        $current = $content[$key] ?? null;
        if (is_string($current) && in_array(str_replace(["\r\n", "\r"], "\n", $current), (array) $values, true)) {
            $content[$key] = $defaults[$key];
        }
    }
    return $content;
}

function site_content(): array
{
    $defaults = require __DIR__ . '/../config/site_defaults.php';
    $path = site_store_dir() . '/content.json';
    if (!is_file($path)) return $defaults;
    $raw = @file_get_contents($path);
    $data = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($data)) return $defaults;
    foreach ($defaults as $key => $default) {
        if (isset($data[$key]) && is_string($data[$key])) {
            if (str_ends_with($key, '_foto') && !site_image_valid($data[$key])) continue;
            $defaults[$key] = $data[$key];
        }
    }
    return site_upgrade_content($defaults);
}

function site_revision(): string
{
    $path = site_store_dir() . '/content.json';
    $hash = is_file($path) ? @hash_file('sha256', $path) : false;
    return is_string($hash) ? $hash : hash('sha256', '');
}

function site_save(array $content, string $revision): void
{
    $dir = site_store_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('La cartella storage/site non è scrivibile. Controlla i permessi del server.');
    }
    $lock = @fopen($dir . '/.lock', 'c');
    if (!$lock) throw new RuntimeException('Non è possibile aprire l’archivio dei contenuti.');
    $temp = false;
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('Archivio occupato. Riprova.');
        clearstatcache();
        if (!hash_equals(site_revision(), $revision)) {
            throw new RuntimeException('Un altro amministratore ha aggiornato il sito. Ricarica la pagina prima di salvare; le tue modifiche non sono state pubblicate.');
        }
        $temp = tempnam($dir, '.content-');
        if ($temp === false || file_put_contents($temp, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Salvataggio non riuscito. I contenuti pubblici non sono stati modificati.');
        }
        chmod($temp, 0640);
        if (!rename($temp, $dir . '/content.json')) throw new RuntimeException('Impossibile pubblicare i contenuti. Controlla i permessi di storage/site.');
    } finally {
        if ($temp && is_file($temp)) unlink($temp);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function site_social_valid(string $key, string $value): bool
{
    if ($value === '') return true;
    $hosts = [
        'instagram' => ['instagram.com','www.instagram.com'],
        'facebook' => ['facebook.com','www.facebook.com','m.facebook.com','fb.com','www.fb.com'],
        'youtube' => ['youtube.com','www.youtube.com','youtu.be'],
        'tiktok' => ['tiktok.com','www.tiktok.com'],
    ];
    $parts = parse_url($value);
    return filter_var($value, FILTER_VALIDATE_URL) !== false
        && ($parts['scheme'] ?? '') === 'https'
        && in_array(strtolower($parts['host'] ?? ''), $hosts[$key] ?? [], true)
        && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port'])
        && trim($parts['path'] ?? '', '/') !== '';
}

/**
 * Immagine con variante WebP opzionale in assets/images/webp/.
 * Senza foto caricata mostra il logo del club come segnaposto.
 */
function site_picture(string $filename, string $alt, int $width, int $height, bool $priority = false): string
{
    $loading = $priority ? 'fetchpriority="high"' : 'loading="lazy"';
    if ($filename === '' || !site_image_valid($filename)) {
        return '<img class="photo-placeholder" src="' . h(base_path('/assets/images/logo-inter-club-brindisi.jpg')) . '" width="400" height="400" ' . $loading . ' decoding="async" alt="Logo Inter Club Javier Zanetti Brindisi">';
    }
    $src = base_path('/assets/images/' . $filename);
    $webp = 'webp/' . pathinfo($filename, PATHINFO_FILENAME) . '.webp';
    $img = '<img src="' . h($src) . '" width="' . $width . '" height="' . $height . '" ' . $loading . ' decoding="async" alt="' . h($alt) . '">';
    if (!is_file(__DIR__ . '/../public/assets/images/' . $webp)) return $img;
    return '<picture><source srcset="' . h(base_path('/assets/images/' . $webp)) . '" type="image/webp">' . $img . '</picture>';
}
