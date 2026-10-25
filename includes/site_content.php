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
        'tessera_3_testo' => 'Lontano da Brindisi, ma sempre dei nostri. La distanza non cambia i colori.',
        'tessera_3_dettagli' => 'Vivi lontano da Brindisi e vuoi restare vicino al nostro club? Chiedici della formula Fuori sede: verifichiamo insieme i requisiti e le modalità di partecipazione previste, senza dare per scontati gli stessi servizi delle altre formule.',
        'tessera_4_testo' => 'Gli stessi colori, in famiglia. La passione nerazzurra si tramanda.',
        'tessera_4_dettagli' => 'Family è la voce del listino dedicata alle adesioni familiari. Prima di iscrivere più persone, verifica con il direttivo abbinamenti e requisiti: la quota indicata non va considerata automaticamente un prezzo per tutto il nucleo familiare.',
        'tessera_5_testo' => 'Piccoli tifosi, grande passione. Il nerazzurro comincia presto.',
        'tessera_5_dettagli' => 'La formula Junior è dedicata ai soci più giovani. Ti aiutiamo a verificare la fascia d’età ammessa per la stagione e ciò che serve per iscrivere un minore: scrivici senza inviare dati o documenti del bambino nel modulo pubblico.',
    ];
    foreach ($previous as $key => $value) {
        if (($content[$key] ?? null) === $value) $content[$key] = $defaults[$key];
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

function site_image_credit(string $filename): string
{
    return in_array($filename, ['club-sede.jpg','club-interno.jpg','club-ricordi.jpg'], true)
        ? 'Foto d’archivio: Agenda Brindisi, 2022'
        : 'Foto caricata dal direttivo';
}
