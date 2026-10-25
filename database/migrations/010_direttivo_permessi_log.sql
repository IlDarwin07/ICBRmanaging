-- Backup prima dell'applicazione. Richiede lo schema esistente; non altera dati storici.
CREATE TABLE IF NOT EXISTS utenti_accessi (
    id_utente INT PRIMARY KEY,
    etichetta_ruolo VARCHAR(100) NOT NULL,
    permessi_json TEXT NOT NULL,
    versione_sessione INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_accessi_utente FOREIGN KEY (id_utente) REFERENCES utenti_admin(id_utente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS registro_attivita (
    id_evento BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_evento DATETIME NOT NULL,
    id_utente INT NULL,
    autore VARCHAR(180) NOT NULL,
    modulo VARCHAR(40) NOT NULL,
    azione VARCHAR(60) NOT NULL,
    entita VARCHAR(80) NULL,
    id_entita VARCHAR(80) NULL,
    dettagli_json TEXT NULL,
    richiesta VARCHAR(32) NOT NULL,
    KEY idx_attivita_data (data_evento, id_evento),
    KEY idx_attivita_utente (id_utente, data_evento),
    KEY idx_attivita_modulo (modulo, data_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Preservare eventuali personalizzazioni se la migrazione viene rieseguita.
INSERT IGNORE INTO utenti_accessi (id_utente, etichetta_ruolo, permessi_json)
SELECT id_utente, CONCAT(UPPER(LEFT(ruolo,1)),SUBSTRING(ruolo,2)),
    CASE ruolo
        WHEN 'admin' THEN '[]'
        WHEN 'tesoriere' THEN '["soci.view","tesseramenti.view","pagamenti.write","pagamenti.delete","prima_nota.view","prima_nota.write","prima_nota.delete","prima_nota.export"]'
        WHEN 'segreteria' THEN '["soci.view","soci.write","tesseramenti.view","tesseramenti.write","pagamenti.write","whatsapp.view","whatsapp.write","richieste.view","richieste.write","importazioni.write"]'
        ELSE '["soci.view","tesseramenti.view","prima_nota.view","whatsapp.view","richieste.view","configurazione.view","attivita.view"]'
    END
FROM utenti_admin;
