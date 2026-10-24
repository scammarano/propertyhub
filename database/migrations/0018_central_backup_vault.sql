-- 2CAM Property Hub 0.10.6
-- Central Backup Vault: solicitudes remotas de respaldo y recuperación desde copias cifradas custodiadas por Central.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;


CREATE TABLE IF NOT EXISTS bh_repo_backup_vault_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  schedule_frequency ENUM('manual','daily','weekly') NOT NULL DEFAULT 'daily',
  schedule_time TIME NOT NULL DEFAULT '03:00:00',
  schedule_weekday TINYINT UNSIGNED NOT NULL DEFAULT 0,
  retention_per_installation INT UNSIGNED NOT NULL DEFAULT 30,
  last_scheduled_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bh_repo_backup_vault_settings (id,enabled,schedule_frequency,schedule_time,schedule_weekday,retention_per_installation)
VALUES (1,1,'daily','03:00:00',0,30)
ON DUPLICATE KEY UPDATE id=id;

CREATE TABLE IF NOT EXISTS bh_repo_backup_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_uuid CHAR(36) NOT NULL UNIQUE,
  installation_id BIGINT UNSIGNED NOT NULL,
  request_type ENUM('full_to_central') NOT NULL DEFAULT 'full_to_central',
  status ENUM('queued','delivered','running','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
  source_archive_id BIGINT UNSIGNED NULL,
  requested_by BIGINT UNSIGNED NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delivered_at DATETIME NULL,
  completed_at DATETIME NULL,
  message TEXT NULL,
  details_json LONGTEXT NULL,
  KEY ix_bh_repo_backup_request_status (installation_id,status,requested_at),
  KEY ix_bh_repo_backup_request_recent (requested_at),
  CONSTRAINT fk_bh_repo_backup_request_installation FOREIGN KEY (installation_id) REFERENCES bh_repo_installations(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_repo_backup_request_user FOREIGN KEY (requested_by) REFERENCES bh_local_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_repo_recovery_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL UNIQUE,
  archive_id BIGINT UNSIGNED NOT NULL,
  installation_uuid CHAR(36) NOT NULL,
  status ENUM('active','used','revoked','expired') NOT NULL DEFAULT 'active',
  expires_at DATETIME NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used_at DATETIME NULL,
  used_ip VARCHAR(64) NULL,
  KEY ix_bh_repo_recovery_archive (archive_id,status),
  KEY ix_bh_repo_recovery_expiry (status,expires_at),
  CONSTRAINT fk_bh_repo_recovery_user FOREIGN KEY (created_by) REFERENCES bh_local_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
