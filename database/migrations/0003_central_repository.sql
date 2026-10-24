-- 2CAM Building Hub 0.3.0
-- Tablas exclusivas de la instalación CENTRAL_REPOSITORY.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS bh_repo_installations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_uuid CHAR(36) NOT NULL UNIQUE,
  installation_code VARCHAR(60) NOT NULL UNIQUE,
  installation_name VARCHAR(190) NOT NULL,
  installation_type VARCHAR(40) NOT NULL DEFAULT 'mixed',
  base_url VARCHAR(500) NULL,
  app_version VARCHAR(50) NOT NULL DEFAULT '0.0.0',
  api_version VARCHAR(30) NOT NULL DEFAULT 'v1',
  ui_version VARCHAR(50) NULL,
  update_channel ENUM('development','beta','stable','pinned') NOT NULL DEFAULT 'stable',
  installation_key_hash CHAR(64) NOT NULL,
  public_key TEXT NULL,
  status ENUM('pending','active','maintenance','suspended','offline','retired') NOT NULL DEFAULT 'pending',
  metadata_json JSON NULL,
  registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NULL,
  last_manifest_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_repo_registration_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_name VARCHAR(160) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  allowed_installation_code VARCHAR(60) NULL,
  allowed_channel ENUM('development','beta','stable','pinned') NULL,
  max_uses INT UNSIGNED NOT NULL DEFAULT 1,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  status ENUM('active','revoked','expired') NOT NULL DEFAULT 'active',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  CONSTRAINT fk_bh_repo_token_user FOREIGN KEY (created_by) REFERENCES bh_local_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_repo_api_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  api_code VARCHAR(100) NOT NULL,
  version VARCHAR(50) NOT NULL,
  status ENUM('draft','development','beta','stable','deprecated','retired') NOT NULL DEFAULT 'draft',
  minimum_app_version VARCHAR(50) NULL,
  maximum_app_version VARCHAR(50) NULL,
  documentation_path VARCHAR(500) NULL,
  changelog TEXT NULL,
  released_at DATETIME NULL,
  support_ends_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_repo_api_version (api_code,version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_repo_releases (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  release_code VARCHAR(120) NOT NULL UNIQUE,
  component_type ENUM('core','ui','module','connector','api','bridge','video_gateway','language','report','other') NOT NULL,
  component_code VARCHAR(120) NOT NULL,
  display_name VARCHAR(190) NOT NULL,
  version VARCHAR(50) NOT NULL,
  channel ENUM('development','beta','stable','pinned') NOT NULL DEFAULT 'stable',
  status ENUM('draft','testing','published','withdrawn','archived') NOT NULL DEFAULT 'draft',
  min_core_version VARCHAR(50) NULL,
  max_core_version VARCHAR(50) NULL,
  compatible_api_versions_json JSON NULL,
  target_roles_json JSON NULL,
  release_notes TEXT NULL,
  manifest_path VARCHAR(500) NULL,
  package_path VARCHAR(500) NULL,
  package_size_bytes BIGINT UNSIGNED NULL,
  package_hash_sha256 CHAR(64) NULL,
  signature TEXT NULL,
  published_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_bh_repo_release_lookup (component_code,channel,status,version),
  CONSTRAINT fk_bh_repo_release_user FOREIGN KEY (created_by) REFERENCES bh_local_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_repo_release_dependencies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  release_id BIGINT UNSIGNED NOT NULL,
  dependency_component_code VARCHAR(120) NOT NULL,
  version_constraint VARCHAR(100) NOT NULL,
  required TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_bh_repo_dependency_release FOREIGN KEY (release_id) REFERENCES bh_repo_releases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_repo_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  release_id BIGINT UNSIGNED NOT NULL,
  installation_id BIGINT UNSIGNED NULL,
  installation_code VARCHAR(60) NULL,
  assignment_type ENUM('all_channel','installation','installation_type','version_range','manual') NOT NULL DEFAULT 'manual',
  criteria_json JSON NULL,
  mandatory TINYINT(1) NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_repo_assignment_release FOREIGN KEY (release_id) REFERENCES bh_repo_releases(id),
  CONSTRAINT fk_bh_repo_assignment_installation FOREIGN KEY (installation_id) REFERENCES bh_repo_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_repo_delivery_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  release_id BIGINT UNSIGNED NULL,
  event_type ENUM('registered','heartbeat','manifest_requested','package_requested','download_started','download_completed','install_reported','rollback_reported','error') NOT NULL,
  status VARCHAR(50) NULL,
  remote_ip VARCHAR(64) NULL,
  details_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_bh_repo_delivery_installation (installation_id,created_at),
  CONSTRAINT fk_bh_repo_delivery_installation FOREIGN KEY (installation_id) REFERENCES bh_repo_installations(id),
  CONSTRAINT fk_bh_repo_delivery_release FOREIGN KEY (release_id) REFERENCES bh_repo_releases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
