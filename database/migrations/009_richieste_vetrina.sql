-- Vetrina pubblica: richieste di contatto e collegamento risposte WhatsApp.
-- Eseguire DOPO 008 su un database già esistente, con backup preventivo.
-- Per installazione nuova usare solo database/schema.sql.
CREATE TABLE IF NOT EXISTS richieste_contatto (
    id_richiesta INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    telefono VARCHAR(30) NULL,
    oggetto VARCHAR(60) NOT NULL,
    messaggio TEXT NOT NULL,
    stato ENUM('nuova','in_gestione','chiusa') NOT NULL DEFAULT 'nuova',
    data_creazione DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_aggiornamento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    privacy_versione VARCHAR(20) NOT NULL DEFAULT '2026-10',
    INDEX idx_richieste_stato_data (stato, data_creazione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE messaggi_whatsapp
    ADD COLUMN id_richiesta INT NULL AFTER id_template,
    ADD CONSTRAINT fk_msg_richiesta FOREIGN KEY (id_richiesta)
        REFERENCES richieste_contatto(id_richiesta) ON DELETE SET NULL;
