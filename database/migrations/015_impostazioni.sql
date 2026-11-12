-- =====================================================================
-- 015 - Impostazioni modificabili dal portale (solo admin)
-- Per ora: indirizzo mittente delle email di sistema.
-- La password per le app di Gmail NON va qui: resta nella configurazione di Apache.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS impostazioni (
    chiave VARCHAR(60) NOT NULL PRIMARY KEY,
    valore VARCHAR(255) NOT NULL,
    aggiornata_da INT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_impostazioni_utente FOREIGN KEY (aggiornata_da) REFERENCES utenti_admin(id_utente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indirizzo provvisorio: l'admin lo cambia da Gestione > Email di sistema.
INSERT INTO impostazioni (chiave, valore) VALUES ('mail_mittente', 'mailprimadidaniele@gmail.com')
ON DUPLICATE KEY UPDATE chiave = chiave;
