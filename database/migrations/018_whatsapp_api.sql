-- WhatsApp API storage. No consent is inferred from existing telephone numbers.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS impostazioni (
 chiave VARCHAR(60) NOT NULL PRIMARY KEY, valore VARCHAR(255) NOT NULL,
 aggiornata_da INT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_impostazioni_utente FOREIGN KEY (aggiornata_da) REFERENCES utenti_admin(id_utente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wa_consensi (
 id_socio INT NOT NULL PRIMARY KEY, telefono VARCHAR(15) NOT NULL,
 stato ENUM('concesso','revocato') NOT NULL, fonte VARCHAR(255) NOT NULL,
 registrato_da INT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_wa_consenso_socio FOREIGN KEY (id_socio) REFERENCES soci(id_socio) ON DELETE CASCADE,
 CONSTRAINT fk_wa_consenso_autore FOREIGN KEY (registrato_da) REFERENCES utenti_admin(id_utente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wa_consensi_eventi (
 id_evento BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, id_socio INT NOT NULL, telefono VARCHAR(15) NOT NULL,
 stato ENUM('concesso','revocato') NOT NULL, fonte VARCHAR(255) NOT NULL, registrato_da INT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_wa_consensi_storico (id_socio, id_evento),
 CONSTRAINT fk_wa_consenso_evento_socio FOREIGN KEY (id_socio) REFERENCES soci(id_socio) ON DELETE CASCADE,
 CONSTRAINT fk_wa_consenso_evento_autore FOREIGN KEY (registrato_da) REFERENCES utenti_admin(id_utente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wa_modelli (
 id_modello INT AUTO_INCREMENT PRIMARY KEY, nome_meta VARCHAR(512) NOT NULL, lingua VARCHAR(10) NOT NULL,
 testo_anteprima TEXT NOT NULL, parametri_json JSON NOT NULL,
 attivo TINYINT(1) NOT NULL DEFAULT 0, verificato_da INT NULL, verificato_il DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_wa_modello_verificatore FOREIGN KEY (verificato_da) REFERENCES utenti_admin(id_utente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wa_lotti (
 id_lotto BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, token_hash CHAR(64) NOT NULL,
 id_utente INT NOT NULL, id_stagione INT NOT NULL, id_modello INT NOT NULL,
 stato ENUM('anteprima','confermato','annullato') NOT NULL DEFAULT 'anteprima',
 modello_json JSON NOT NULL, filtri_json JSON NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, scade_il DATETIME NOT NULL, confermato_il DATETIME NULL,
 UNIQUE KEY uq_wa_lotto_token (token_hash),
 CONSTRAINT fk_wa_lotto_utente FOREIGN KEY (id_utente) REFERENCES utenti_admin(id_utente),
 CONSTRAINT fk_wa_lotto_stagione FOREIGN KEY (id_stagione) REFERENCES stagioni(id_stagione),
 CONSTRAINT fk_wa_lotto_modello FOREIGN KEY (id_modello) REFERENCES wa_modelli(id_modello)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wa_inviati (
 id_invio BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, id_lotto BIGINT UNSIGNED NOT NULL, id_socio INT NULL,
 id_tesseramento INT NULL, telefono VARCHAR(15) NOT NULL, payload_json JSON NOT NULL, testo_anteprima TEXT NOT NULL,
 stato ENUM('preparato','in_corso','accettato','consegnato','letto','fallito','incerto','escluso') NOT NULL DEFAULT 'preparato',
 meta_message_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
 http_status SMALLINT UNSIGNED NULL, errore_codice INT NULL, motivo VARCHAR(255) NULL,
 tentato_il DATETIME NULL, stato_meta_il BIGINT UNSIGNED NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_wa_invio_socio (id_lotto, id_socio),
 UNIQUE KEY uq_wa_invio_telefono (id_lotto, telefono),
 UNIQUE KEY uq_wa_meta_message (meta_message_id),
 CONSTRAINT fk_wa_invio_lotto FOREIGN KEY (id_lotto) REFERENCES wa_lotti(id_lotto),
 CONSTRAINT fk_wa_invio_socio FOREIGN KEY (id_socio) REFERENCES soci(id_socio) ON DELETE SET NULL,
 CONSTRAINT fk_wa_invio_tesseramento FOREIGN KEY (id_tesseramento) REFERENCES tesseramenti(id_tesseramento) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wa_webhook_eventi (
 hash_evento CHAR(64) NOT NULL PRIMARY KEY, meta_message_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 stato VARCHAR(20) NOT NULL, timestamp_meta BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_wa_webhook_message (meta_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
