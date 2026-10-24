-- 2CAM Property Hub 0.10.0
-- Respaldo, recuperación, retención y copias externas cifradas.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS bh_backup_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  schedule_frequency ENUM('manual','daily','weekly') NOT NULL DEFAULT 'daily',
  schedule_time TIME NOT NULL DEFAULT '02:00:00',
  schedule_weekday TINYINT UNSIGNED NOT NULL DEFAULT 0,
  retention_daily INT UNSIGNED NOT NULL DEFAULT 7,
  retention_weekly INT UNSIGNED NOT NULL DEFAULT 4,
  retention_monthly INT UNSIGNED NOT NULL DEFAULT 6,
  local_path VARCHAR(500) NULL,
  network_path VARCHAR(500) NULL,
  central_copy_enabled TINYINT(1) NOT NULL DEFAULT 0,
  last_backup_at DATETIME NULL,
  last_status ENUM('never','ok','warning','failed') NOT NULL DEFAULT 'never',
  last_message TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_backup_settings_installation (installation_id),
  CONSTRAINT fk_bh_backup_settings_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_backup_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_uuid CHAR(36) NOT NULL UNIQUE,
  installation_id BIGINT UNSIGNED NOT NULL,
  backup_type ENUM('manual','scheduled','pre_update','pre_restore') NOT NULL DEFAULT 'manual',
  status ENUM('running','completed','warning','failed','deleted') NOT NULL DEFAULT 'running',
  filename VARCHAR(255) NULL,
  local_path VARCHAR(500) NULL,
  size_bytes BIGINT UNSIGNED NULL,
  sha256 CHAR(64) NULL,
  encrypted TINYINT(1) NOT NULL DEFAULT 1,
  destinations_json JSON NULL,
  manifest_json LONGTEXT NULL,
  error_message TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  KEY ix_bh_backup_runs_latest (installation_id,started_at),
  CONSTRAINT fk_bh_backup_runs_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_backup_runs_user FOREIGN KEY (created_by) REFERENCES bh_local_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_restore_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_uuid CHAR(36) NOT NULL UNIQUE,
  installation_id BIGINT UNSIGNED NULL,
  restore_mode ENUM('same_installation','safe_clone') NOT NULL,
  status ENUM('running','completed','warning','failed') NOT NULL DEFAULT 'running',
  source_filename VARCHAR(255) NOT NULL,
  source_sha256 CHAR(64) NULL,
  source_manifest_json LONGTEXT NULL,
  reconciliation_json LONGTEXT NULL,
  error_message TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  KEY ix_bh_restore_runs_latest (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existe en ambos perfiles para mantener una única migración. Solo el Central la utiliza.
CREATE TABLE IF NOT EXISTS bh_backup_offsite_archives (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_uuid CHAR(36) NOT NULL,
  installation_code VARCHAR(40) NOT NULL,
  filename VARCHAR(255) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  sha256 CHAR(64) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  backup_created_at DATETIME NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('stored','corrupt','deleted') NOT NULL DEFAULT 'stored',
  metadata_json JSON NULL,
  KEY ix_bh_offsite_installation (installation_uuid,received_at),
  KEY ix_bh_offsite_code (installation_code,received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bh_backup_settings (installation_id,enabled,schedule_frequency,schedule_time,retention_daily,retention_weekly,retention_monthly,local_path)
SELECT id,1,'daily','02:00:00',7,4,6,'storage/backups'
FROM bh_installations i
WHERE NOT EXISTS (SELECT 1 FROM bh_backup_settings s WHERE s.installation_id=i.id);

SET FOREIGN_KEY_CHECKS=1;
