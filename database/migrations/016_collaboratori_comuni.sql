-- ============================================================
-- Migrazione 016 - Collaboratori limitati per comune
-- Aggiunge a utenti_accessi l'elenco (JSON) dei comuni consentiti.
-- Vuoto/NULL = nessun limite. Da eseguire una sola volta.
-- ============================================================

ALTER TABLE utenti_accessi
    ADD COLUMN comuni_json TEXT NULL AFTER permessi_json;
