<?php
/**
 * Funzioni di utilità generale - Gestionale Inter Club Brindisi
 */

/**
 * Escape sicuro per output HTML.
 */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// -------------------------------------------------------------------------
// Percorsi base (supporto installazione in sottocartella, es. XAMPP)
// -------------------------------------------------------------------------

/**
 * Percorso base dell'area pubblica.
 * - '' quando il DocumentRoot punta su /public
 * - '/ICBRmanaging/public' in installazione XAMPP in sottocartella
 */
function base_url(): string
{
    if (!defined('BASE_URL')) {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (str_contains($script, '/ICBRmanaging/')) {
            define('BASE_URL', '/ICBRmanaging/public');
        } else {
            define('BASE_URL', '');
        }
    }
    return rtrim(BASE_URL, '/');
}

/**
 * Prefissa un path assoluto ('/dashboard.php') con il base path reale.
 * I path relativi o URL completi vengono restituiti invariati.
 */
function base_path(string $url): string
{
    if ($url === '' || $url[0] !== '/' || str_starts_with($url, '//')) {
        return $url;
    }
    return base_url() . $url;
}

/**
 * Come base_path() ma con escape HTML: da usare negli attributi href/action.
 */
function u(string $url): string
{
    return h(base_path($url));
}

// -------------------------------------------------------------------------
// Alias CSRF (retrocompatibilità con le pagine che usano i nomi storici)
// -------------------------------------------------------------------------

/**
 * Alias di auth\csrf_token(): genera/recupera il token CSRF di sessione.
 */
function generate_csrf_token(): string
{
    return csrf_token();
}

/**
 * Alias di auth\csrf_verify(): verifica il token CSRF di un form.
 * A differenza di csrf_verify() (che restituisce un booleano), questa funzione
 * BLOCCA la richiesta se il token non è valido: le pagine che la invocano
 * come semplice istruzione devono essere protette davvero.
 */
function verify_csrf_token(?string $token): void
{
    if (!csrf_verify($token)) {
        http_response_code(403);
        die('Sessione scaduta o token non valido. Torna alla pagina precedente e riprova.');
    }
}

// -------------------------------------------------------------------------
// Formattazione importi
// -------------------------------------------------------------------------

/**
 * Formatta un importo in euro (12.5 -> "12,50").
 */
function euro(?float $importo): string
{
    return number_format((float)($importo ?? 0), 2, ',', '.');
}

// -------------------------------------------------------------------------
// Quote, versamenti e stato pagamento (logica centralizzata)
// -------------------------------------------------------------------------

/**
 * Quota dovuta per un tesseramento.
 * Priorità: snapshot quota_tesseramento (non retroattivo), poi quota tipologia.
 * Restituisce null se la quota non è nota (distinto da 0).
 */
function tess_quota(?array $t): ?float
{
    if ($t === null) {
        return null;
    }
    if (isset($t['quota_tesseramento']) && $t['quota_tesseramento'] !== null && (float)$t['quota_tesseramento'] > 0) {
        return (float)$t['quota_tesseramento'];
    }
    if (isset($t['quota_standard']) && $t['quota_standard'] !== null && (float)$t['quota_standard'] > 0) {
        return (float)$t['quota_standard'];
    }
    return null;
}

/**
 * Totale versato netto da un elenco di pagamenti.
 * I rimborsi vengono sottratti, le eccedenze non vengono nascoste.
 */
function pagamenti_netto(array $pagamenti): float
{
    $tot = 0.0;
    foreach ($pagamenti as $p) {
        $importo = (float)$p['importo'];
        $tot += ($p['tipo_pagamento'] === 'rimborso') ? -$importo : $importo;
    }
    return $tot;
}

/**
 * Stato economico di un tesseramento.
 *
 * Codici possibili:
 * - quota_sconosciuta : quota non impostata (non si può valutare il residuo)
 * - da_pagare         : nessun versamento registrato
 * - acconto            : versato > 0 ma inferiore alla quota ("solo acconto")
 * - saldato            : versato >= quota
 * - eccedenza          : versato > quota (resto a credito del socio)
 * - credito            : netto negativo (rimborsi superiori agli incassi: il club deve al socio)
 */
function stato_quota(?float $quota, float $versato_netto): array
{
    if ($quota === null || $quota <= 0) {
        return ['code' => 'quota_sconosciuta', 'label' => 'Quota n/d', 'badge' => 'badge-gray'];
    }
    if ($versato_netto < -0.004) {
        return ['code' => 'credito', 'label' => 'A credito', 'badge' => 'badge-blue'];
    }
    if ($versato_netto <= 0 && $versato_netto >= -0.004) {
        return ['code' => 'da_pagare', 'label' => 'Da saldare', 'badge' => 'badge-red'];
    }
    if ($versato_netto < $quota - 0.004) {
        return ['code' => 'acconto', 'label' => 'Solo acconto', 'badge' => 'badge-orange'];
    }
    if ($versato_netto > $quota + 0.004) {
        return ['code' => 'eccedenza', 'label' => 'Eccedenza', 'badge' => 'badge-blue'];
    }
    return ['code' => 'saldato', 'label' => 'Saldato', 'badge' => 'badge-green'];
}

/**
 * Residuo dovuto: max(0, quota - versato). Null se quota sconosciuta.
 */
function tess_residuo(?float $quota, float $versato_netto): ?float
{
    if ($quota === null || $quota <= 0) {
        return null;
    }
    return round(max(0.0, $quota - $versato_netto), 2);
}

// -------------------------------------------------------------------------
// Messaggi WhatsApp assistiti: placeholder
// -------------------------------------------------------------------------

/**
 * Placeholder riconosciuti nei template WhatsApp.
 */
const WHATSAPP_PLACEHOLDER = [
    '{{nome}}'           => 'Nome del socio',
    '{{cognome}}'        => 'Cognome del socio',
    '{{nome_completo}}'  => 'Nome e cognome',
    '{{telefono}}'       => 'Telefono del socio',
    '{{quota}}'          => 'Quota dovuta della stagione',
    '{{pagato}}'         => 'Totale già versato',
    '{{residuo}}'        => 'Residuo da saldare',
    '{{stagione}}'       => 'Codice stagione (es. 2026/27)',
    '{{data}}'           => 'Data odierna',
];

/**
 * Elenca i placeholder presenti in un testo che NON sono riconosciuti.
 */
function placeholder_sconosciuti(string $testo): array
{
    preg_match_all('/\{\{[^}]*\}\}/', $testo, $m);
    $trovati = array_unique($m[0] ?? []);
    return array_values(array_filter($trovati, fn($p) => !isset(WHATSAPP_PLACEHOLDER[$p])));
}

/**
 * Sostituisce i placeholder con i valori del socio/tesseramento.
 * I placeholder sconosciuti restano invariati nel testo.
 */
function render_messaggio(string $testo, array $vars): string
{
    $map = [];
    foreach ($vars as $k => $v) {
        $map['{{' . $k . '}}'] = (string)$v;
    }
    return strtr($testo, $map);
}

/**
 * Normalizza un numero di telefono per il link wa.me:
 * - toglie spazi, punti, trattini, parentesi
 * - +39 / 0039 gestiti (0039 diventa 39)
 * Restituisce null se il numero non è valido (troppo corto).
 */
function wa_phone(?string $phone): ?string
{
    if ($phone === null || trim($phone) === '') {
        return null;
    }
    $p = preg_replace('/[\s.\-()\/]/', '', trim($phone));
    if (str_starts_with($p, '00')) {
        $p = '+' . substr($p, 2);
    }
    if (str_starts_with($p, '+')) {
        $digits = substr($p, 1);
    } else {
        $digits = $p; // numero italiano senza prefisso
    }
    if (!preg_match('/^\d{8,15}$/', $digits)) {
        return null;
    }
    return $digits;
}

/**
 * Trim + normalizzazione spazi multipli.
 */
function clean_text(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $value = trim(preg_replace('/\s+/', ' ', $value));
    return $value === '' ? null : $value;
}

/**
 * Normalizza un numero di telefono: rimuove spazi superflui ma conserva
 * il prefisso internazionale (+39, 0039, ecc.) come richiesto dalla
 * mappatura import (03-mappatura-import-file-inter-club).
 */
function clean_phone(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    // Rimuove tutti gli spazi, mantenendo cifre, + e parentesi eventuali.
    $value = preg_replace('/\s+/', '', $value);
    return $value === '' ? null : $value;
}

function clean_email(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $value = strtolower(trim($value));
    return $value === '' ? null : $value;
}

function clean_codice_fiscale(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $value = strtoupper(trim($value));
    return $value === '' ? null : $value;
}

/**
 * Converte una data in formato gg-mm-aaaa (o simili) in formato SQL Y-m-d.
 * Restituisce null se il valore non è interpretabile.
 */
function parse_date_to_sql(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    $formats = ['d-m-Y', 'd/m/Y', 'Y-m-d', 'd.m.Y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d');
        }
    }

    return null;
}

/**
 * Converte i valori SI/NO del portale in booleano (1/0), come da
 * regole di pulizia dati definite nella mappatura import.
 */
function si_no_to_bool(?string $value): int
{
    $value = strtoupper(trim((string)$value));
    return $value === 'SI' || $value === 'SÌ' || $value === 'S' || $value === '1' ? 1 : 0;
}

/**
 * Redirect helper con messaggio flash opzionale in sessione.
 */
function redirect_with_message(string $url, string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    header('Location: ' . base_path($url));
    exit;
}

function get_flash_message(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// -------------------------------------------------------------------------
// Tessera fisica
// -------------------------------------------------------------------------

/**
 * Valori ENUM validi per il campo tesseramenti.tessera_fisica.
 * Rispecchia la definizione nel database.
 */
const TESSERA_FISICA_STATI = [
    'non_richiesta'  => 'Non richiesta',
    'non_consegnata' => 'Non consegnata',
    'consegnata'     => 'Consegnata',
];

/**
 * Restituisce la label leggibile dello stato tessera fisica.
 * Usare nei template HTML: h(tessera_fisica_label($row['tessera_fisica']))
 *
 * @param  string|null $stato  Valore ENUM dal database
 * @return string              Label in italiano
 */
function tessera_fisica_label(?string $stato): string
{
    return TESSERA_FISICA_STATI[$stato] ?? 'Non richiesta';
}

/**
 * CSS badge class Bootstrap 5 per lo stato tessera fisica.
 *
 * @param  string|null $stato
 * @return string  classe Bootstrap badge (es. 'bg-secondary', 'bg-warning', 'bg-success')
 */
function tessera_fisica_badge(?string $stato): string
{
    return match ($stato) {
        'non_consegnata' => 'bg-warning text-dark',
        'consegnata'     => 'bg-success',
        default          => 'bg-secondary',  // non_richiesta o null
    };
}

// -------------------------------------------------------------------------
// Tipologie tesseramento 2026/27
// -------------------------------------------------------------------------

/**
 * Mappa tipo → descrizione leggibile per le tipologie campagna 2026/27.
 * Corrisponde ai dati in tipologie_tesseramento popolati dalla migration 007.
 *
 * Struttura: tipo => [label, quota_nuovo_socio, quota_rinnovo|null]
 * Le quote rinnovo si applicano solo se rinnovato entro 30/06/2026.
 * Dopo tale data il rinnovo equivale alla quota nuovo socio.
 */
const TIPOLOGIE_2027 = [
    'fuori_sede'          => ['label' => 'Senior Fuori Sede',    'quota' => 50.00, 'quota_rinnovo' => 45.00],
    'sostenitore'         => ['label' => 'Senior Sostenitore',   'quota' => 55.00, 'quota_rinnovo' => 50.00],
    'fedelissimo'         => ['label' => 'Senior Fedelissimo',   'quota' => 95.00, 'quota_rinnovo' => 90.00],
    'family'              => ['label' => 'Senior Family',         'quota' => 50.00, 'quota_rinnovo' => 45.00],
    'junior'              => ['label' => 'Junior',               'quota' => 25.00, 'quota_rinnovo' => 25.00],
    'fuori_sede_rinnovo'  => ['label' => 'Senior Fuori Sede (Rinnovo ≤30/06/26)',   'quota' => 45.00, 'quota_rinnovo' => null],
    'sostenitore_rinnovo' => ['label' => 'Senior Sostenitore (Rinnovo ≤30/06/26)',  'quota' => 50.00, 'quota_rinnovo' => null],
    'fedelissimo_rinnovo' => ['label' => 'Senior Fedelissimo (Rinnovo ≤30/06/26)',  'quota' => 90.00, 'quota_rinnovo' => null],
    'family_rinnovo'      => ['label' => 'Senior Family (Rinnovo ≤30/06/26)',        'quota' => 45.00, 'quota_rinnovo' => null],
    'junior_rinnovo'      => ['label' => 'Junior (Rinnovo ≤30/06/26)',               'quota' => 25.00, 'quota_rinnovo' => null],
    'quota_plus'          => ['label' => 'Quota Plus (Upgrade Senior)',              'quota' => 35.00, 'quota_rinnovo' => null],
];

/**
 * Restituisce la label leggibile di una tipologia.
 */
function tipologia_label(?string $tipo): string
{
    return TIPOLOGIE_2027[$tipo]['label'] ?? ($tipo ?? '—');
}
