-- Property Hub 0.14.8 · Gemelo funcional: activos lógicos + inventario físico + bindings + capacidades.
-- La estructura Property Hub define QUÉ existe. Edge/Conectores descubren QUÉ está instalado.
-- Ambos mundos pueden crearse en cualquier orden y se vinculan explícitamente.

ALTER TABLE bh_access_points
  ADD COLUMN IF NOT EXISTS logical_state ENUM('planned','unmapped','mapped','partial','offline','disabled') NOT NULL DEFAULT 'unmapped' AFTER status,
  ADD COLUMN IF NOT EXISTS capabilities_json MEDIUMTEXT NULL AFTER logical_state;

ALTER TABLE bh_cameras
  ADD COLUMN IF NOT EXISTS logical_state ENUM('planned','unmapped','mapped','partial','offline','disabled') NOT NULL DEFAULT 'unmapped' AFTER status,
  ADD COLUMN IF NOT EXISTS capabilities_json MEDIUMTEXT NULL AFTER logical_state;

CREATE TABLE IF NOT EXISTS bh_asset_bindings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  logical_type ENUM('access_point','camera','sensor','light','intercom','elevator','other') NOT NULL,
  logical_id BIGINT UNSIGNED NOT NULL,
  binding_role ENUM('primary','secondary','authenticator','actuator','stream','telemetry') NOT NULL DEFAULT 'primary',
  connector_instance_id BIGINT UNSIGNED NULL,
  external_object_type VARCHAR(50) NULL,
  external_id VARCHAR(190) NULL,
  external_name VARCHAR(255) NULL,
  local_device_id BIGINT UNSIGNED NULL,
  capability_override_json MEDIUMTEXT NULL,
  execution_mode ENUM('provisioned','realtime','hybrid','passive') NOT NULL DEFAULT 'realtime',
  priority SMALLINT NOT NULL DEFAULT 100,
  status ENUM('pending','mapped','online','offline','warning','disabled') NOT NULL DEFAULT 'pending',
  last_seen_at DATETIME NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_asset_binding_external (installation_id,logical_type,logical_id,binding_role,connector_instance_id,external_object_type,external_id),
  KEY ix_bh_asset_binding_logical (installation_id,logical_type,logical_id,status),
  KEY ix_bh_asset_binding_external_lookup (connector_instance_id,external_object_type,external_id),
  CONSTRAINT fk_bh_asset_binding_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_asset_binding_connector FOREIGN KEY (connector_instance_id) REFERENCES bh_connector_instances(id) ON DELETE SET NULL,
  CONSTRAINT fk_bh_asset_binding_device FOREIGN KEY (local_device_id) REFERENCES bh_devices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_access_authenticators (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  access_point_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  authenticator_type ENUM('property_app','qr','card','nfc','pin','face','fingerprint','lpr','intercom','button','other') NOT NULL,
  validation_owner ENUM('property_hub','vendor','shared') NOT NULL DEFAULT 'property_hub',
  connector_instance_id BIGINT UNSIGNED NULL,
  external_object_type VARCHAR(50) NULL,
  external_id VARCHAR(190) NULL,
  capabilities_json MEDIUMTEXT NULL,
  allow_residents TINYINT(1) NOT NULL DEFAULT 1,
  allow_visitors TINYINT(1) NOT NULL DEFAULT 0,
  allow_temporary_credentials TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('planned','unmapped','online','offline','disabled') NOT NULL DEFAULT 'planned',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_bh_auth_access (installation_id,access_point_id,status),
  CONSTRAINT fk_bh_auth_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_auth_access FOREIGN KEY (access_point_id) REFERENCES bh_access_points(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_auth_connector FOREIGN KEY (connector_instance_id) REFERENCES bh_connector_instances(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_asset_capabilities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  logical_type ENUM('access_point','camera','sensor','light','intercom','elevator','other') NOT NULL,
  logical_id BIGINT UNSIGNED NOT NULL,
  capability_code VARCHAR(80) NOT NULL,
  capability_source ENUM('declared','discovered','inherited','override','effective') NOT NULL DEFAULT 'declared',
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  details_json MEDIUMTEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_asset_capability (installation_id,logical_type,logical_id,capability_code,capability_source),
  KEY ix_bh_asset_capability_effective (installation_id,logical_type,logical_id,enabled),
  CONSTRAINT fk_bh_asset_capability_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Las cámaras ya sincronizadas desde Edge quedan mapeadas; las creadas sólo en Property permanecen sin mapear.
UPDATE bh_cameras SET logical_state=CASE WHEN source_scope='edge' THEN 'mapped' WHEN device_id IS NOT NULL THEN 'mapped' ELSE 'unmapped' END WHERE logical_state='unmapped';
UPDATE bh_access_points SET logical_state=CASE WHEN device_id IS NOT NULL THEN 'mapped' ELSE 'unmapped' END WHERE logical_state='unmapped';
