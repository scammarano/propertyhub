-- 2CAM Building Hub 0.3.0
-- Fundamentos de instalación, migraciones, componentes y actualizaciones locales.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS bh_schema_migrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(190) NOT NULL UNIQUE,
  checksum_sha256 CHAR(64) NOT NULL,
  batch INT UNSIGNED NOT NULL DEFAULT 1,
  executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_deployment_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  instance_role ENUM('central_repository','building_node') NOT NULL,
  installation_uuid CHAR(36) NOT NULL,
  repository_url VARCHAR(500) NULL,
  repository_installation_key_hash CHAR(64) NULL,
  repository_status ENUM('not_configured','pending','registered','offline','error') NOT NULL DEFAULT 'not_configured',
  repository_public_key TEXT NULL,
  update_channel ENUM('development','beta','stable','pinned') NOT NULL DEFAULT 'stable',
  allow_offline TINYINT(1) NOT NULL DEFAULT 1,
  auto_check_updates TINYINT(1) NOT NULL DEFAULT 1,
  auto_download_updates TINYINT(1) NOT NULL DEFAULT 0,
  auto_install_updates TINYINT(1) NOT NULL DEFAULT 0,
  last_repository_check_at DATETIME NULL,
  last_heartbeat_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_deployment_installation (installation_id),
  UNIQUE KEY uq_bh_deployment_uuid (installation_uuid),
  CONSTRAINT fk_bh_deployment_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_installed_components (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  component_type ENUM('core','ui','module','connector','api','bridge','video_gateway','language','report','other') NOT NULL,
  component_code VARCHAR(120) NOT NULL,
  display_name VARCHAR(190) NOT NULL,
  installed_version VARCHAR(50) NOT NULL,
  api_compatibility VARCHAR(100) NULL,
  channel VARCHAR(30) NOT NULL DEFAULT 'stable',
  status ENUM('active','disabled','pending','broken','removed') NOT NULL DEFAULT 'active',
  source VARCHAR(500) NULL,
  installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_component (installation_id,component_type,component_code),
  CONSTRAINT fk_bh_component_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_update_sources (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  source_type ENUM('central_repository','manual_package','local_mirror') NOT NULL DEFAULT 'central_repository',
  source_url VARCHAR(500) NULL,
  public_key TEXT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  priority INT NOT NULL DEFAULT 100,
  last_checked_at DATETIME NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_update_source_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_update_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_uuid CHAR(36) NOT NULL UNIQUE,
  installation_id BIGINT UNSIGNED NOT NULL,
  source_id BIGINT UNSIGNED NULL,
  release_code VARCHAR(120) NOT NULL,
  component_code VARCHAR(120) NOT NULL,
  from_version VARCHAR(50) NULL,
  to_version VARCHAR(50) NOT NULL,
  manifest_json JSON NULL,
  package_path VARCHAR(500) NULL,
  package_hash_sha256 CHAR(64) NULL,
  signature TEXT NULL,
  backup_path VARCHAR(500) NULL,
  status ENUM('queued','checking','downloading','downloaded','validated','backing_up','installing','installed','rolling_back','rolled_back','failed','cancelled') NOT NULL DEFAULT 'queued',
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  details TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  KEY ix_bh_update_job_status (installation_id,status,created_at),
  CONSTRAINT fk_bh_update_job_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_update_job_source FOREIGN KEY (source_id) REFERENCES bh_update_sources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_installation_health (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  app_version VARCHAR(50) NOT NULL,
  api_version VARCHAR(30) NOT NULL,
  php_version VARCHAR(30) NULL,
  database_version VARCHAR(100) NULL,
  bridge_version VARCHAR(50) NULL,
  connector_summary_json JSON NULL,
  disk_free_bytes BIGINT UNSIGNED NULL,
  queue_depth INT UNSIGNED NOT NULL DEFAULT 0,
  internet_status ENUM('online','offline','unknown') NOT NULL DEFAULT 'unknown',
  overall_status ENUM('healthy','warning','critical','offline','unknown') NOT NULL DEFAULT 'unknown',
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_bh_health_latest (installation_id,checked_at),
  CONSTRAINT fk_bh_health_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
