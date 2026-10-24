-- 2CAM Building Hub 0.4.0
-- Portafolio central, telemetría agregada, catálogo de APIs de fabricantes y privacidad.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE bh_repo_installations
  ADD COLUMN IF NOT EXISTS is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN IF NOT EXISTS data_scope ENUM('metadata_only','aggregates','support_snapshot','operational_mirror') NOT NULL DEFAULT 'aggregates' AFTER is_demo,
  ADD COLUMN IF NOT EXISTS health_status ENUM('healthy','warning','critical','offline','unknown') NOT NULL DEFAULT 'unknown' AFTER data_scope,
  ADD COLUMN IF NOT EXISTS last_error_summary VARCHAR(500) NULL AFTER health_status;

CREATE TABLE IF NOT EXISTS bh_repo_installation_metrics (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  reported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_count INT UNSIGNED NOT NULL DEFAULT 0,
  people_count INT UNSIGNED NOT NULL DEFAULT 0,
  unit_count INT UNSIGNED NOT NULL DEFAULT 0,
  area_count INT UNSIGNED NOT NULL DEFAULT 0,
  active_visitor_count INT UNSIGNED NOT NULL DEFAULT 0,
  pending_reservation_count INT UNSIGNED NOT NULL DEFAULT 0,
  camera_count INT UNSIGNED NOT NULL DEFAULT 0,
  access_point_count INT UNSIGNED NOT NULL DEFAULT 0,
  connector_count INT UNSIGNED NOT NULL DEFAULT 0,
  device_count INT UNSIGNED NOT NULL DEFAULT 0,
  online_device_count INT UNSIGNED NOT NULL DEFAULT 0,
  warning_device_count INT UNSIGNED NOT NULL DEFAULT 0,
  queue_depth INT UNSIGNED NOT NULL DEFAULT 0,
  critical_alert_count INT UNSIGNED NOT NULL DEFAULT 0,
  disk_free_bytes BIGINT UNSIGNED NULL,
  summary_json JSON NULL,
  KEY ix_bh_repo_metrics_latest (installation_id,reported_at),
  CONSTRAINT fk_bh_repo_metrics_installation FOREIGN KEY (installation_id) REFERENCES bh_repo_installations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_repo_connector_inventory (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  connector_code VARCHAR(120) NOT NULL,
  display_name VARCHAR(190) NOT NULL,
  vendor_name VARCHAR(120) NULL,
  domain VARCHAR(80) NOT NULL,
  product_family VARCHAR(190) NULL,
  connector_version VARCHAR(50) NULL,
  api_family VARCHAR(120) NULL,
  api_version VARCHAR(60) NULL,
  integration_mode VARCHAR(120) NULL,
  status ENUM('configured','online','offline','warning','error','disabled','planned') NOT NULL DEFAULT 'configured',
  device_count INT UNSIGNED NOT NULL DEFAULT 0,
  capabilities_json JSON NULL,
  metadata_json JSON NULL,
  last_seen_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_repo_connector_inventory (installation_id,connector_code,display_name),
  CONSTRAINT fk_bh_repo_connector_inventory_installation FOREIGN KEY (installation_id) REFERENCES bh_repo_installations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_repo_vendor_apis (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vendor_code VARCHAR(80) NOT NULL,
  vendor_name VARCHAR(160) NOT NULL,
  api_code VARCHAR(120) NOT NULL,
  display_name VARCHAR(190) NOT NULL,
  domain VARCHAR(100) NOT NULL,
  api_type ENUM('rest','openapi','sdk','protocol','cloud','local','database','other') NOT NULL DEFAULT 'other',
  current_version VARCHAR(80) NULL,
  lifecycle_status ENUM('research','planned','development','beta','stable','conditional','deprecated') NOT NULL DEFAULT 'research',
  connector_code VARCHAR(120) NULL,
  capabilities_text TEXT NULL,
  documentation_reference VARCHAR(500) NULL,
  compatibility_notes TEXT NULL,
  secrets_location ENUM('node_only','central_allowed','not_applicable') NOT NULL DEFAULT 'node_only',
  last_reviewed_at DATE NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_repo_vendor_api (vendor_code,api_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_repo_privacy_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_scope VARCHAR(80) NOT NULL DEFAULT 'global',
  default_data_scope ENUM('metadata_only','aggregates','support_snapshot','operational_mirror') NOT NULL DEFAULT 'aggregates',
  receive_personal_data TINYINT(1) NOT NULL DEFAULT 0,
  receive_operational_messages TINYINT(1) NOT NULL DEFAULT 0,
  retain_metrics_days INT UNSIGNED NOT NULL DEFAULT 365,
  remote_support_enabled TINYINT(1) NOT NULL DEFAULT 0,
  remote_support_requires_local_approval TINYINT(1) NOT NULL DEFAULT 1,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_repo_privacy_scope (setting_scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_repo_support_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  public_uuid CHAR(36) NOT NULL UNIQUE,
  requested_by BIGINT UNSIGNED NULL,
  scope ENUM('diagnostics','logs','configuration_snapshot','operational_readonly') NOT NULL DEFAULT 'diagnostics',
  status ENUM('requested','approved','active','expired','revoked','rejected') NOT NULL DEFAULT 'requested',
  justification VARCHAR(500) NOT NULL,
  approved_by_local VARCHAR(190) NULL,
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_repo_support_installation FOREIGN KEY (installation_id) REFERENCES bh_repo_installations(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_repo_support_user FOREIGN KEY (requested_by) REFERENCES bh_local_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bh_repo_privacy_settings (setting_scope,default_data_scope,receive_personal_data,receive_operational_messages,retain_metrics_days,remote_support_enabled,remote_support_requires_local_approval,notes)
VALUES ('global','aggregates',0,0,365,0,1,'El Central Hub recibe metadatos técnicos y métricas agregadas. No recibe nombres, documentos, visitantes ni mensajes operativos por defecto.')
ON DUPLICATE KEY UPDATE setting_scope=VALUES(setting_scope);

SET FOREIGN_KEY_CHECKS=1;
