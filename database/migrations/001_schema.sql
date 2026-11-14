-- FatecPoll — schema inicial (MariaDB 10.6+ / InnoDB / utf8mb4)
CREATE DATABASE IF NOT EXISTS fatecpoll CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fatecpoll;

CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS polls (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title           VARCHAR(190) NOT NULL,
  slug            VARCHAR(120) NOT NULL UNIQUE,
  description     TEXT NULL,
  type            ENUM('catalog','options') NOT NULL,
  question        VARCHAR(255) NULL,                 -- usada em enquetes de opções
  status          ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
  results_public  TINYINT(1) NOT NULL DEFAULT 0,
  use_tokens      TINYINT(1) NOT NULL DEFAULT 0,     -- credenciais individuais
  starts_at       DATETIME NULL,
  ends_at         DATETIME NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_polls_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS games (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  poll_id     INT UNSIGNED NOT NULL,
  name        VARCHAR(160) NOT NULL,
  description TEXT NULL,
  cover_path  VARCHAR(255) NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  active      TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (poll_id) REFERENCES polls(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS game_members (
  id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  game_id INT UNSIGNED NOT NULL,
  name    VARCHAR(120) NOT NULL,
  FOREIGN KEY (game_id) REFERENCES games(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS poll_options (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  poll_id    INT UNSIGNED NOT NULL,
  label      VARCHAR(190) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active     TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (poll_id) REFERENCES polls(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS participations (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  poll_id    INT UNSIGNED NOT NULL,
  token_id   INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (poll_id) REFERENCES polls(id),
  INDEX idx_part_poll (poll_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS participation_tokens (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  poll_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,              -- SHA-256 do token; o token em si nunca é salvo
  status     ENUM('active','used','revoked') NOT NULL DEFAULT 'active',
  expires_at DATETIME NULL,
  used_at    DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (poll_id) REFERENCES polls(id)
) ENGINE=InnoDB;

ALTER TABLE participations
  ADD CONSTRAINT fk_part_token FOREIGN KEY (token_id) REFERENCES participation_tokens(id),
  ADD UNIQUE KEY uq_part_token (token_id);

CREATE TABLE IF NOT EXISTS game_ratings (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participation_id INT UNSIGNED NOT NULL,
  game_id          INT UNSIGNED NOT NULL,
  q_time           TINYINT UNSIGNED NOT NULL,   -- tempo de jogo adequado?
  q_fun            TINYINT UNSIGNED NOT NULL,   -- foi divertido?
  q_replay         TINYINT UNSIGNED NOT NULL,   -- jogaria novamente?
  FOREIGN KEY (participation_id) REFERENCES participations(id),
  FOREIGN KEY (game_id) REFERENCES games(id),
  UNIQUE KEY uq_rating (participation_id, game_id),
  CONSTRAINT chk_q_time   CHECK (q_time   BETWEEN 1 AND 5),
  CONSTRAINT chk_q_fun    CHECK (q_fun    BETWEEN 1 AND 5),
  CONSTRAINT chk_q_replay CHECK (q_replay BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS option_votes (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participation_id INT UNSIGNED NOT NULL,
  option_id        INT UNSIGNED NOT NULL,
  FOREIGN KEY (participation_id) REFERENCES participations(id),
  FOREIGN KEY (option_id) REFERENCES poll_options(id),
  UNIQUE KEY uq_vote (participation_id, option_id)
) ENGINE=InnoDB;
