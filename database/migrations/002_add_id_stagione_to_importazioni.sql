-- Migrazione 002: aggiunge id_stagione a importazioni (se non presente)
-- Eseguire su icbr_gestionale dopo aver importato schema.sql

ALTER TABLE importazioni
    ADD COLUMN IF NOT EXISTS id_stagione INT NULL
        AFTER hash_file,
    ADD CONSTRAINT fk_importazioni_stagione
        FOREIGN KEY (id_stagione)
        REFERENCES stagioni(id_stagione)
        ON DELETE SET NULL;
