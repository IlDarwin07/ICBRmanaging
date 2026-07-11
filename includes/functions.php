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
