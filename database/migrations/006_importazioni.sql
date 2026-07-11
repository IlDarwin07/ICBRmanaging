-- Fase 4: tabelle per il modulo di importazione XLSX

CREATE TABLE IF NOT EXISTS importazioni (
    id_importazione INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_stagione     INT UNSIGNED NOT NULL,
    nome_file       VARCHAR(255) NOT NULL,
    data_import     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_stagione) REFERENCES stagioni(id_stagione) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS importazioni_righe (
    id_riga          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_importazione  INT UNSIGNED NOT NULL,
    numero_riga      INT UNSIGNED NOT NULL,
    esito            ENUM('inserito','aggiornato','duplicato','errore') NOT NULL,
    messaggio        TEXT,
    dati_json        JSON,
    FOREIGN KEY (id_importazione) REFERENCES importazioni(id_importazione) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_imp_righe_esito ON importazioni_righe (id_importazione, esito);
