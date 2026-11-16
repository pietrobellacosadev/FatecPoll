-- FatecPoll — dono da enquete (idempotente)
USE fatecpoll;

-- Professor enxerga e edita só as enquetes em que owner_id é o dele; administrador enxerga todas.
-- Enquetes antigas ficam com owner_id NULL (sem dono): só o administrador as vê.
ALTER TABLE polls ADD COLUMN IF NOT EXISTS owner_id INT UNSIGNED NULL AFTER id;
ALTER TABLE polls ADD INDEX IF NOT EXISTS idx_polls_owner (owner_id);
