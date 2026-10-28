-- Galleria fotografica degli articoli (elenco JSON di file e didascalie).
-- Se la colonna esiste già, ignora l'errore: il gestionale la crea comunque da solo.
ALTER TABLE articoli_eventi ADD COLUMN galleria TEXT NULL AFTER copertina_alt;
