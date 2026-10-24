-- =====================================================================
-- Migration 008 - Completamento MVP: quota snapshot, prima nota, unicità
-- Gestionale Inter Club Javier Zanetti Brindisi
--
-- 1. tesseramenti.quota_tesseramento
--    Snapshot della quota dovuta AL MOMENTO della creazione del
--    tesseramento. Evita che modifiche future ai listini (tipologie)
--    cambino retroattivamente i residui delle stagioni passate.
--    Se NULL, il gestionale usa in fallback la quota corrente della
--    tipologia (vedi includes/functions.php::tess_quota()).
--
-- 2. prima_nota: chiave unica su id_pagamento
--    Rapporto 1:1 movimento-pagamento (NON rende idempotente il reinvio
--    del form di registrazione pagamento, che crea un pagamento distinto)
--
-- 3. tipologie_tesseramento: chiave unica su tipo
--    Rende effettivo l'ON DUPLICATE KEY UPDATE già presente nello
--    schema ed evita listini duplicati se lo schema viene rieseguito.
-- =====================================================================

USE icbr_gestionale;

-- ---------------------------------------------------------------------
-- 1. Snapshot quota per tesseramento
-- ---------------------------------------------------------------------
ALTER TABLE tesseramenti
    ADD COLUMN quota_tesseramento DECIMAL(10,2) NULL AFTER id_tipologia;

-- Backfill: congela la quota corrente della tipologia sui record esistenti
UPDATE tesseramenti t
JOIN tipologie_tesseramento tip ON tip.id_tipologia = t.id_tipologia
SET t.quota_tesseramento = tip.quota_standard
WHERE t.quota_tesseramento IS NULL
  AND tip.quota_standard IS NOT NULL;

-- ---------------------------------------------------------------------
-- 2. Unicità movimento prima nota <-> pagamento
-- ---------------------------------------------------------------------
ALTER TABLE prima_nota
    ADD UNIQUE KEY uq_primanota_pagamento (id_pagamento);

-- ---------------------------------------------------------------------
-- 3. Unicità tipologia di tesseramento
--    Prima di eliminare i duplicati, i riferimenti dei tesseramenti
--    vengono spostati sulla tipologia canonica (id MINIMO per `tipo`,
--    scelto in modo deterministico): così nessun tesseramento perde la
--    tipologia (la FK è ON DELETE SET NULL e il legame andrebbe perso).
-- ---------------------------------------------------------------------
UPDATE tesseramenti t
JOIN tipologie_tesseramento dup ON dup.id_tipologia = t.id_tipologia
JOIN (
    SELECT tipo, MIN(id_tipologia) AS id_conservato
    FROM tipologie_tesseramento
    GROUP BY tipo
) canon ON canon.tipo = dup.tipo
SET t.id_tipologia = canon.id_conservato
WHERE t.id_tipologia <> canon.id_conservato;

DELETE t1 FROM tipologie_tesseramento t1
JOIN tipologie_tesseramento t2
  ON t1.tipo = t2.tipo
 AND t1.id_tipologia > t2.id_tipologia;

ALTER TABLE tipologie_tesseramento
    ADD UNIQUE KEY uq_tipo (tipo);
