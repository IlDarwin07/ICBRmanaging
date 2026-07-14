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
    header('Location: ' . $url);
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
