-- =====================================================================
-- Migration 007 - Inter Club Brindisi Gestionale
-- Autore: ICBRmanaging
-- Data:   2026-07-14
--
-- Modifiche:
--   1. tesseramenti.tessera_fisica: TINYINT(1) → ENUM a 3 stati
--        non_richiesta  = il socio non ha ancora richiesto la tessera fisica
--        non_consegnata = richiesta registrata, tessera in attesa di consegna
--        consegnata     = tessera fisica consegnata al socio
--   2. Aggiunta KEY idx_tessera_fisica per query filtrate per stato
--   3. tipologie_tesseramento: popolate tutte le tipologie campagna 2026/27
--        (nuovo socio + rinnovo entro 30/06/2026 + quota plus)
-- =====================================================================

-- Step 1: modifica colonna tessera_fisica
-- I vecchi valori 0 → 'non_richiesta', 1 → 'consegnata'
ALTER TABLE tesseramenti
    MODIFY COLUMN tessera_fisica ENUM('non_richiesta','non_consegnata','consegnata')
        NOT NULL DEFAULT 'non_richiesta'
        COMMENT 'Stato della tessera fisica: non richiesta / richiesta ma non consegnata / consegnata';

-- Aggiorna righe preesistenti (da TINYINT era già stringa '0'/'1')
UPDATE tesseramenti SET tessera_fisica = 'non_richiesta' WHERE tessera_fisica = '0';
UPDATE tesseramenti SET tessera_fisica = 'consegnata'    WHERE tessera_fisica = '1';

-- Step 2: index per ricerche/filtri per stato tessera
ALTER TABLE tesseramenti
    ADD KEY idx_tessera_fisica (tessera_fisica);

-- Step 3: tipologie tesseramento campagna 2026/27
INSERT INTO tipologie_tesseramento (tipo, listino_label, quota_standard, attiva) VALUES
    -- Nuovi soci
    ('fuori_sede',           'FUORI SEDE - Nuovo Socio 2026/27',              50.00, 1),
    ('sostenitore',          'SOSTENITORE - Nuovo Socio 2026/27',             55.00, 1),
    ('fedelissimo',          'FEDELISSIMO - Nuovo Socio 2026/27',             95.00, 1),
    ('family',               'FAMILY - Nuovo Socio 2026/27',                  50.00, 1),
    ('junior',               'JUNIOR - Nuovo Socio 2026/27',                  25.00, 1),
    -- Rinnovi (entro 30/06/2026 — scadenza già passata, valgono quote nuovo socio)
    ('fuori_sede_rinnovo',   'FUORI SEDE - Rinnovo entro 30/06/2026',         45.00, 1),
    ('sostenitore_rinnovo',  'SOSTENITORE - Rinnovo entro 30/06/2026',        50.00, 1),
    ('fedelissimo_rinnovo',  'FEDELISSIMO - Rinnovo entro 30/06/2026',        90.00, 1),
    ('family_rinnovo',       'FAMILY - Rinnovo entro 30/06/2026',             45.00, 1),
    ('junior_rinnovo',       'JUNIOR - Rinnovo entro 30/06/2026',             25.00, 1),
    -- Quota Plus
    ('quota_plus',           'QUOTA PLUS - Upgrade Senior 2026/27',           35.00, 1)
ON DUPLICATE KEY UPDATE listino_label = VALUES(listino_label), quota_standard = VALUES(quota_standard);
