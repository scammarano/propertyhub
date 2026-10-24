-- 2CAM Building Hub 0.4.0
-- Amplía el inventario tecnológico local sin almacenar secretos en texto plano.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE bh_connector_instances
  ADD COLUMN IF NOT EXISTS integration_mode VARCHAR(100) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS api_family VARCHAR(120) NULL AFTER integration_mode,
  ADD COLUMN IF NOT EXISTS api_version VARCHAR(60) NULL AFTER api_family,
  ADD COLUMN IF NOT EXISTS authentication_mode VARCHAR(80) NULL AFTER api_version,
  ADD COLUMN IF NOT EXISTS device_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER endpoint_url,
  ADD COLUMN IF NOT EXISTS capabilities_json JSON NULL AFTER configuration_encrypted,
  ADD COLUMN IF NOT EXISTS environment ENUM('production','test','sandbox') NOT NULL DEFAULT 'production' AFTER capabilities_json;

ALTER TABLE bh_devices
  ADD COLUMN IF NOT EXISTS firmware_version VARCHAR(100) NULL AFTER model,
  ADD COLUMN IF NOT EXISTS network_zone VARCHAR(100) NULL AFTER ip_address,
  ADD COLUMN IF NOT EXISTS managed_by VARCHAR(160) NULL AFTER network_zone;

CREATE TABLE IF NOT EXISTS bh_technology_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  profile_name VARCHAR(160) NOT NULL DEFAULT 'Perfil tecnológico principal',
  data_scope ENUM('metadata_only','aggregates','support_snapshot','operational_mirror') NOT NULL DEFAULT 'aggregates',
  share_aggregate_metrics TINYINT(1) NOT NULL DEFAULT 1,
  share_connector_inventory TINYINT(1) NOT NULL DEFAULT 1,
  remote_support_enabled TINYINT(1) NOT NULL DEFAULT 0,
  remote_support_requires_local_approval TINYINT(1) NOT NULL DEFAULT 1,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_technology_profile (installation_id),
  CONSTRAINT fk_bh_technology_profile_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
