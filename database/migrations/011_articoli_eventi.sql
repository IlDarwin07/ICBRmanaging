-- Articoli sugli eventi organizzati dal club.
-- Applicabile anche in produzione: usa CREATE TABLE IF NOT EXISTS.
CREATE TABLE IF NOT EXISTS articoli_eventi (
    id_articolo INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(160) NOT NULL,
    titolo VARCHAR(160) NOT NULL,
    sommario VARCHAR(300) NOT NULL,
    testo MEDIUMTEXT NOT NULL,
    data_evento DATE NOT NULL,
    luogo VARCHAR(160) NULL,
    copertina VARCHAR(120) NULL,
    copertina_alt VARCHAR(200) NULL,
    pubblicato TINYINT(1) NOT NULL DEFAULT 0,
    id_autore INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_articolo_slug (slug),
    KEY idx_articoli_pubblico (pubblicato, data_evento),
    CONSTRAINT fk_articoli_autore FOREIGN KEY (id_autore) REFERENCES utenti_admin(id_utente) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
