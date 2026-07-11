<?php
/**
 * Configurazione connessione database - Gestionale Inter Club Brindisi
 *
 * In produzione, valorizzare queste costanti con le credenziali reali
 * fornite dal provider di hosting, oppure meglio ancora leggerle da
 * variabili d'ambiente (getenv) per non tenerle in chiaro nel repository.
 */

define('DB_HOST', getenv('ICBR_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('ICBR_DB_NAME') ?: 'icbr_gestionale');
define('DB_USER', getenv('ICBR_DB_USER') ?: 'root');
define('DB_PASS', getenv('ICBR_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Restituisce una connessione PDO condivisa (singleton) al database.
 *
 * @return PDO
 */
function get_db_connection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Non esporre mai i dettagli di connessione all'utente finale.
            error_log('Errore connessione database: ' . $e->getMessage());
            http_response_code(500);
            die('Errore di connessione al database. Contattare l\'amministratore.');
        }
    }

    return $pdo;
}
