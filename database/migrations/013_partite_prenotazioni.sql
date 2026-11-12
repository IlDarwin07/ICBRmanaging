-- =====================================================================
-- 013 - Area soci: account, sale, partite e prenotazioni in sede
-- Backup prima dell'applicazione. Non modifica dati esistenti.
-- =====================================================================

SET NAMES utf8mb4;

-- Account dell'area soci: separato da utenti_admin, nessun accesso al gestionale.
CREATE TABLE IF NOT EXISTS soci_account (
    id_account INT AUTO_INCREMENT PRIMARY KEY,
    id_socio INT NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NULL,
    -- token monouso (attivazione o recupero): si salva solo l'hash SHA-256
    token_hash CHAR(64) NULL,
    token_scadenza DATETIME NULL,
    attivo TINYINT(1) NOT NULL DEFAULT 1,
    tentativi_falliti TINYINT UNSIGNED NOT NULL DEFAULT 0,
    bloccato_fino DATETIME NULL,
    ultimo_accesso DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_account_socio (id_socio),
    UNIQUE KEY uq_account_email (email),
    UNIQUE KEY uq_account_token (token_hash),
    CONSTRAINT fk_account_socio FOREIGN KEY (id_socio) REFERENCES soci(id_socio) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sale della sede. Le sedie sono numerate da 1 a capienza.
CREATE TABLE IF NOT EXISTS sale (
    id_sala INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(60) NOT NULL,
    capienza SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    posti_per_fila TINYINT UNSIGNED NOT NULL DEFAULT 10,
    attiva TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_sala_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Capienze provvisorie: il direttivo le corregge da Partite > Sale.
INSERT INTO sale (nome, capienza, posti_per_fila) VALUES
    ('Sala Proiettore', 40, 10),
    ('Sala TV', 20, 10)
ON DUPLICATE KEY UPDATE nome = nome;

CREATE TABLE IF NOT EXISTS partite (
    id_partita INT AUTO_INCREMENT PRIMARY KEY,
    titolo VARCHAR(150) NOT NULL,
    competizione VARCHAR(80) NULL,
    data_ora DATETIME NOT NULL,
    id_sala INT NOT NULL,
    prenotazioni_chiudono DATETIME NOT NULL,
    solo_tesserati TINYINT(1) NOT NULL DEFAULT 1,
    note VARCHAR(500) NULL,
    stato ENUM('bozza','aperta','chiusa','conclusa','annullata') NOT NULL DEFAULT 'bozza',
    creata_da INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_partite_stato_data (stato, data_ora),
    CONSTRAINT fk_partite_sala FOREIGN KEY (id_sala) REFERENCES sale(id_sala) ON DELETE RESTRICT,
    CONSTRAINT fk_partite_autore FOREIGN KEY (creata_da) REFERENCES utenti_admin(id_utente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Una riga per socio e partita. La sedia la assegna solo il direttivo.
-- La UNIQUE su (id_partita, numero_posto) impedisce la doppia assegnazione
-- (più valori NULL sono ammessi: posto non ancora assegnato).
CREATE TABLE IF NOT EXISTS prenotazioni (
    id_prenotazione INT AUTO_INCREMENT PRIMARY KEY,
    id_partita INT NOT NULL,
    id_socio INT NOT NULL,
    stato ENUM('presente','attesa','annullata') NOT NULL DEFAULT 'presente',
    numero_posto SMALLINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_prenotazione_socio (id_partita, id_socio),
    UNIQUE KEY uq_prenotazione_posto (id_partita, numero_posto),
    KEY idx_prenotazioni_stato (id_partita, stato, created_at),
    CONSTRAINT fk_prenotazioni_partita FOREIGN KEY (id_partita) REFERENCES partite(id_partita) ON DELETE CASCADE,
    CONSTRAINT fk_prenotazioni_socio FOREIGN KEY (id_socio) REFERENCES soci(id_socio) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
