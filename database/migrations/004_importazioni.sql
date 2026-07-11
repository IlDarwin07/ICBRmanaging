-- ============================================================
-- Migrazione 004 - Tabella importazioni (Fase 4)
-- ============================================================

CREATE TABLE IF NOT EXISTS importazioni (
    id_importazione   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome_file         VARCHAR(255)  NOT NULL,
    hash_file         CHAR(64)      NOT NULL,
    data_importazione DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    righe_totali      INT UNSIGNED  NOT NULL DEFAULT 0,
    righe_inserite    INT UNSIGNED  NOT NULL DEFAULT 0,
    righe_aggiornate  INT UNSIGNED  NOT NULL DEFAULT 0,
    righe_scartate    INT UNSIGNED  NOT NULL DEFAULT 0,
    note_esito        TEXT          NULL,
    importato_da      INT UNSIGNED  NULL,
    PRIMARY KEY (id_importazione),
    INDEX idx_data   (data_importazione),
    INDEX idx_utente (importato_da)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
