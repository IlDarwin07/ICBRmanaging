<?php
/**
 * Invio email dell'area soci (attivazione account e recupero password).
 *
 * Le credenziali si leggono SOLO da variabili d'ambiente, mai dal repository.
 * Esempio per Apache (VirtualHost o .htaccess, se consentito):
 *   SetEnv ICBR_SMTP_HOST smtp.gmail.com
 *   SetEnv ICBR_SMTP_PORT 587
 *   SetEnv ICBR_SMTP_SECURE tls        # tls (STARTTLS, porta 587) oppure ssl (porta 465)
 *   SetEnv ICBR_SMTP_USER segreteria@esempio.it
 *   SetEnv ICBR_SMTP_PASS "password-per-app"
 *   SetEnv ICBR_MAIL_FROM segreteria@esempio.it
 *   SetEnv ICBR_PUBLIC_URL https://www.esempio.it
 *
 * Se ICBR_SMTP_HOST è vuoto l'invio è disattivato: il gestionale mostra al
 * direttivo il link di attivazione da inoltrare a mano (es. WhatsApp).
 */
return [
    'host'      => getenv('ICBR_SMTP_HOST') ?: '',
    'port'      => (int)(getenv('ICBR_SMTP_PORT') ?: 587),
    'secure'    => strtolower(getenv('ICBR_SMTP_SECURE') ?: 'tls'),
    'user'      => getenv('ICBR_SMTP_USER') ?: '',
    'pass'      => getenv('ICBR_SMTP_PASS') ?: '',
    'from'      => getenv('ICBR_MAIL_FROM') ?: (getenv('ICBR_SMTP_USER') ?: ''),
    'from_name' => getenv('ICBR_MAIL_FROM_NAME') ?: 'Inter Club Brindisi',
    // URL pubblico del sito, usato nei link delle email (senza "/" finale).
    'public_url' => rtrim(getenv('ICBR_PUBLIC_URL') ?: '', '/'),
    'timeout'   => 15,
];
