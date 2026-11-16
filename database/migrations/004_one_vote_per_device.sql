-- FatecPoll — voto único por dispositivo (idempotente)
USE fatecpoll;

-- Sinais antifraude da participação: só hashes (HMAC); IP e fingerprint em texto puro nunca são salvos.
-- risk = 1 quando um dispositivo "novo" repete o mesmo fingerprint + rede de uma participação anterior.
ALTER TABLE participations ADD COLUMN IF NOT EXISTS ip_hash CHAR(64) NULL;
ALTER TABLE participations ADD COLUMN IF NOT EXISTS fp_hash CHAR(64) NULL;
ALTER TABLE participations ADD COLUMN IF NOT EXISTS risk TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE participations ADD INDEX IF NOT EXISTS idx_part_signal (poll_id, fp_hash, ip_hash);

-- Uma linha por (enquete, identificador de dispositivo). A restrição UNIQUE é a trava definitiva:
-- se duas requisições simultâneas tentarem registrar o mesmo dispositivo, o banco recusa a segunda.
CREATE TABLE IF NOT EXISTS participation_devices (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  poll_id          INT UNSIGNED NOT NULL,
  participation_id INT UNSIGNED NOT NULL,
  device_hash      CHAR(64) NOT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (poll_id) REFERENCES polls(id),
  FOREIGN KEY (participation_id) REFERENCES participations(id),
  UNIQUE KEY uq_pd_poll_device (poll_id, device_hash)
) ENGINE=InnoDB;
