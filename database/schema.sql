CREATE DATABASE IF NOT EXISTS icbr_gestionale
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE icbr_gestionale;
-- =====================================================================
-- Schema database - Gestionale Web Inter Club Javier Zanetti Brindisi
-- Basato su: 02-schema-database-inter-club.pdf
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. utenti_admin (creata per prima: è referenziata da altre tabelle)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS utenti_admin (
    id_utente INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    ruolo ENUM('admin','segreteria','tesoriere','presidente') NOT NULL DEFAULT 'admin',
    attivo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_accesso DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 2. soci
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS soci (
    id_socio INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    sesso CHAR(1) NULL,
    data_nascita DATE NULL,
    codice_fiscale VARCHAR(16) NULL,
    nazionalita VARCHAR(10) NULL,
    comune_nascita VARCHAR(100) NULL,
    indirizzo VARCHAR(150) NULL,
    numero_civico VARCHAR(20) NULL,
    cap VARCHAR(10) NULL,
    provincia VARCHAR(10) NULL,
    comune VARCHAR(100) NULL,
    telefono VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    attivo_record TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_codice_fiscale (codice_fiscale),
    KEY idx_cognome (cognome),
    KEY idx_telefono (telefono),
    KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. stagioni
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stagioni (
    id_stagione INT AUTO_INCREMENT PRIMARY KEY,
    codice_stagione VARCHAR(20) NOT NULL UNIQUE,
    descrizione VARCHAR(100) NULL,
    attiva TINYINT(1) NOT NULL DEFAULT 0,
    data_inizio DATE NULL,
    data_fine DATE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4. tipologie_tesseramento
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tipologie_tesseramento (
    id_tipologia INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    listino_label VARCHAR(100) NULL,
    quota_standard DECIMAL(10,2) NULL,
    attiva TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5. importazioni (deve precedere tesseramenti per la FK)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS importazioni (
    id_importazione INT AUTO_INCREMENT PRIMARY KEY,
    nome_file VARCHAR(255) NOT NULL,
    hash_file VARCHAR(64) NULL,
    data_importazione DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    righe_totali INT NOT NULL DEFAULT 0,
    righe_inserite INT NOT NULL DEFAULT 0,
    righe_aggiornate INT NOT NULL DEFAULT 0,
    righe_scartate INT NOT NULL DEFAULT 0,
    note_esito TEXT NULL,
    importato_da INT NULL,
    CONSTRAINT fk_importazioni_utente FOREIGN KEY (importato_da) REFERENCES utenti_admin(id_utente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6. tesseramenti
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tesseramenti (
    id_tesseramento INT AUTO_INCREMENT PRIMARY KEY,
    id_socio INT NOT NULL,
    id_stagione INT NOT NULL,
    id_tipologia INT NULL,
    listino_originale VARCHAR(120) NULL,
    numero_tessera VARCHAR(50) NULL,
    ruolo_portale VARCHAR(100) NULL,
    socio_plus TINYINT(1) NOT NULL DEFAULT 0,
    tipo_portale VARCHAR(50) NULL,
    attivo_scorsa_stagione TINYINT(1) NOT NULL DEFAULT 0,
    data_ora_attivazione DATETIME NULL,
    attivo_portale TINYINT(1) NOT NULL DEFAULT 0,
    conferma_anagrafica TINYINT(1) NOT NULL DEFAULT 0,
    -- tessera_fisica: stato consegna tessera fisica Inter Club
    -- non_richiesta = il socio non ha ancora richiesto la tessera
    -- non_consegnata = richiesta registrata, in attesa di consegna
    -- consegnata     = tessera fisica consegnata al socio
    tessera_fisica ENUM('non_richiesta','non_consegnata','consegnata') NOT NULL DEFAULT 'non_richiesta',
    fonte_inserimento ENUM('portale','manuale','misto') NOT NULL DEFAULT 'portale',
    id_importazione INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_socio_stagione (id_socio, id_stagione),
    KEY idx_numero_tessera (numero_tessera),
    KEY idx_attivo_portale (attivo_portale),
    KEY idx_tessera_fisica (tessera_fisica),
    CONSTRAINT fk_tesseramenti_socio FOREIGN KEY (id_socio) REFERENCES soci(id_socio) ON DELETE CASCADE,
    CONSTRAINT fk_tesseramenti_stagione FOREIGN KEY (id_stagione) REFERENCES stagioni(id_stagione) ON DELETE RESTRICT,
    CONSTRAINT fk_tesseramenti_tipologia FOREIGN KEY (id_tipologia) REFERENCES tipologie_tesseramento(id_tipologia) ON DELETE SET NULL,
    CONSTRAINT fk_tesseramenti_importazione FOREIGN KEY (id_importazione) REFERENCES importazioni(id_importazione) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7. pagamenti_tesseramenti
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagamenti_tesseramenti (
    id_pagamento INT AUTO_INCREMENT PRIMARY KEY,
    id_tesseramento INT NOT NULL,
    data_pagamento DATE NOT NULL,
    tipo_pagamento ENUM('acconto','saldo','integrazione','rimborso') NOT NULL,
    importo DECIMAL(10,2) NOT NULL,
    metodo_pagamento ENUM('contanti','bonifico','pos','altro') NOT NULL DEFAULT 'contanti',
    riferimento VARCHAR(100) NULL,
    note TEXT NULL,
    registrato_da INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pagamenti_tesseramento FOREIGN KEY (id_tesseramento) REFERENCES tesseramenti(id_tesseramento) ON DELETE CASCADE,
    CONSTRAINT fk_pagamenti_utente FOREIGN KEY (registrato_da) REFERENCES utenti_admin(id_utente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 8. prima_nota
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prima_nota (
    id_movimento INT AUTO_INCREMENT PRIMARY KEY,
    data_movimento DATE NOT NULL,
    tipo_movimento ENUM('entrata','uscita') NOT NULL,
    conto ENUM('cassa','banca','altro') NOT NULL DEFAULT 'cassa',
    categoria VARCHAR(80) NULL,
    causale VARCHAR(255) NULL,
    importo DECIMAL(10,2) NOT NULL,
    id_socio INT NULL,
    id_tesseramento INT NULL,
    id_pagamento INT NULL,
    riferimento_documento VARCHAR(100) NULL,
    note TEXT NULL,
    registrato_da INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_primanota_socio FOREIGN KEY (id_socio) REFERENCES soci(id_socio) ON DELETE SET NULL,
    CONSTRAINT fk_primanota_tesseramento FOREIGN KEY (id_tesseramento) REFERENCES tesseramenti(id_tesseramento) ON DELETE SET NULL,
    CONSTRAINT fk_primanota_pagamento FOREIGN KEY (id_pagamento) REFERENCES pagamenti_tesseramenti(id_pagamento) ON DELETE SET NULL,
    CONSTRAINT fk_primanota_utente FOREIGN KEY (registrato_da) REFERENCES utenti_admin(id_utente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 9. righe_importazione
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS righe_importazione (
    id_riga_import INT AUTO_INCREMENT PRIMARY KEY,
    id_importazione INT NOT NULL,
    numero_riga INT NOT NULL,
    payload_json JSON NULL,
    esito ENUM('inserita','aggiornata','duplicato','errore') NOT NULL,
    messaggio_esito VARCHAR(255) NULL,
    id_socio INT NULL,
    id_tesseramento INT NULL,
    CONSTRAINT fk_righeimport_importazione FOREIGN KEY (id_importazione) REFERENCES importazioni(id_importazione) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 10. template_whatsapp
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS template_whatsapp (
    id_template INT AUTO_INCREMENT PRIMARY KEY,
    nome_template VARCHAR(100) NOT NULL,
    categoria VARCHAR(50) NULL,
    testo_template TEXT NOT NULL,
    attivo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 11. messaggi_whatsapp
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS messaggi_whatsapp (
    id_messaggio INT AUTO_INCREMENT PRIMARY KEY,
    id_socio INT NULL,
    id_tesseramento INT NULL,
    id_template INT NULL,
    telefono_destinatario VARCHAR(30) NOT NULL,
    testo_finale TEXT NOT NULL,
    stato_messaggio ENUM('bozza','preparato','inviato','fallito') NOT NULL DEFAULT 'bozza',
    data_creazione DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_invio DATETIME NULL,
    risposta_api TEXT NULL,
    CONSTRAINT fk_msg_socio FOREIGN KEY (id_socio) REFERENCES soci(id_socio) ON DELETE SET NULL,
    CONSTRAINT fk_msg_tesseramento FOREIGN KEY (id_tesseramento) REFERENCES tesseramenti(id_tesseramento) ON DELETE SET NULL,
    CONSTRAINT fk_msg_template FOREIGN KEY (id_template) REFERENCES template_whatsapp(id_template) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Dati iniziali di comodo
-- ---------------------------------------------------------------------

-- NOTA: l'utente amministratore NON viene creato da questo script SQL,
-- perché l'hash della password deve essere generato da PHP con
-- password_hash() e non può essere scritto in modo sicuro qui.
-- Dopo aver importato questo schema, creare il primo utente admin con:
--   php bin/reset_admin_password.php admin "PasswordSicura123!"

-- ---------------------------------------------------------------------
-- Tipologie tesseramento - Campagna 2026/27
-- Fonte: locandina ufficiale Inter Club Brindisi
-- Nuovo socio / Rinnovo (entro 30/06/2026) / Quota Plus
-- ---------------------------------------------------------------------
INSERT INTO tipologie_tesseramento (tipo, listino_label, quota_standard, attiva) VALUES
    -- Nuovi soci 2026/27
    ('fuori_sede',           'FUORI SEDE - Nuovo Socio 2026/27',              50.00, 1),
    ('sostenitore',          'SOSTENITORE - Nuovo Socio 2026/27',             55.00, 1),
    ('fedelissimo',          'FEDELISSIMO - Nuovo Socio 2026/27',             95.00, 1),
    ('family',               'FAMILY - Nuovo Socio 2026/27',                  50.00, 1),
    ('junior',               'JUNIOR - Nuovo Socio 2026/27',                  25.00, 1),
    -- Rinnovi (entro 30/06/2026)
    ('fuori_sede_rinnovo',   'FUORI SEDE - Rinnovo entro 30/06/2026',         45.00, 1),
    ('sostenitore_rinnovo',  'SOSTENITORE - Rinnovo entro 30/06/2026',        50.00, 1),
    ('fedelissimo_rinnovo',  'FEDELISSIMO - Rinnovo entro 30/06/2026',        90.00, 1),
    ('family_rinnovo',       'FAMILY - Rinnovo entro 30/06/2026',             45.00, 1),
    ('junior_rinnovo',       'JUNIOR - Rinnovo entro 30/06/2026',             25.00, 1),
    -- Quota Plus (upgrade big match - attivabile da ogni Socio Senior)
    ('quota_plus',           'QUOTA PLUS - Upgrade Senior 2026/27',           35.00, 1)
ON DUPLICATE KEY UPDATE tipo = tipo;
