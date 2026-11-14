-- FatecPoll — cadastro de professores com verificação de e-mail (idempotente)
USE fatecpoll;

-- Professores usam a mesma tabela de acesso ao painel (admins), com papel próprio.
-- Professor recém-cadastrado fica inativo (active = 0) até confirmar o código enviado por e-mail.
ALTER TABLE admins ADD COLUMN IF NOT EXISTS role ENUM('admin','teacher') NOT NULL DEFAULT 'admin' AFTER email;
ALTER TABLE admins ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER active;

CREATE TABLE IF NOT EXISTS email_verifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id    INT UNSIGNED NOT NULL,
  code_hash   CHAR(64) NOT NULL,                 -- SHA-256 do código; o código em si nunca é salvo
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at  DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
  INDEX idx_ev_admin (admin_id, created_at)
) ENGINE=InnoDB;
