-- 2CAM Property Hub 0.14.17
-- Puente operativo ZKBio: inventario Node, adopción de puertas, apertura móvil,
-- provisión de personas/visitantes/vehículos RFID y estado de sincronización.
-- Incluye autorreparación del esquema histórico de bh_external_mappings (0001 vs 0029).
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- ---------------------------------------------------------------------------
-- Compatibilidad histórica de mappings.
-- 0001 creó entity_type/external_entity_id; 0029 creó el contrato multimarca
-- solo si la tabla no existía. Esta migración completa ambas formas sin borrar datos.
-- ---------------------------------------------------------------------------
ALTER TABLE bh_external_mappings
  ADD COLUMN IF NOT EXISTS installation_id BIGINT UNSIGNED NULL AFTER id,
  ADD COLUMN IF NOT EXISTS local_entity_type VARCHAR(40) NULL AFTER connector_instance_id,
  ADD COLUMN IF NOT EXISTS external_object_type VARCHAR(40) NULL AFTER local_entity_id,
  ADD COLUMN IF NOT EXISTS external_id VARCHAR(190) NULL AFTER external_object_type,
  ADD COLUMN IF NOT EXISTS external_name VARCHAR(255) NULL AFTER external_id,
  ADD COLUMN IF NOT EXISTS authority VARCHAR(30) NOT NULL DEFAULT 'property_hub' AFTER external_name;

-- Permite que las columnas legacy de 0001 coexistan sin bloquear inserts del contrato nuevo.
ALTER TABLE bh_external_mappings
  MODIFY COLUMN entity_type VARCHAR(80) NULL,
  MODIFY COLUMN external_entity_id VARCHAR(255) NULL,
  MODIFY COLUMN sync_status ENUM('pending','queued','sent','confirmed','synced','warning','error','conflict','revoked','disabled') NOT NULL DEFAULT 'pending';

UPDATE bh_external_mappings m
LEFT JOIN bh_connector_instances ci ON ci.id=m.connector_instance_id
SET m.installation_id=COALESCE(m.installation_id,ci.installation_id),
    m.local_entity_type=COALESCE(NULLIF(m.local_entity_type,''),NULLIF(m.entity_type,''),'other'),
    m.external_object_type=COALESCE(NULLIF(m.external_object_type,''),NULLIF(m.entity_type,''),'other'),
    m.external_id=COALESCE(NULLIF(m.external_id,''),NULLIF(m.external_entity_id,'')),
    m.external_name=COALESCE(m.external_name,m.external_entity_id),
    m.authority=COALESCE(NULLIF(m.authority,''),'shared'),
    m.sync_status=CASE m.sync_status WHEN 'confirmed' THEN 'synced' WHEN 'sent' THEN 'pending' WHEN 'queued' THEN 'pending' ELSE m.sync_status END;

CREATE INDEX IF NOT EXISTS ix_bh_external_mapping_installation ON bh_external_mappings (installation_id);
CREATE UNIQUE INDEX IF NOT EXISTS uq_bh_external_mapping_local_v2 ON bh_external_mappings (connector_instance_id,local_entity_type,local_entity_id);
CREATE UNIQUE INDEX IF NOT EXISTS uq_bh_external_mapping_external_v2 ON bh_external_mappings (connector_instance_id,external_object_type,external_id);

ALTER TABLE bh_invitations
  ADD COLUMN IF NOT EXISTS external_provider VARCHAR(40) NULL AFTER status,
  ADD COLUMN IF NOT EXISTS external_sync_status ENUM('not_requested','pending','synced','warning','error') NOT NULL DEFAULT 'not_requested' AFTER external_provider,
  ADD COLUMN IF NOT EXISTS external_id VARCHAR(190) NULL AFTER external_sync_status,
  ADD COLUMN IF NOT EXISTS external_command_uuid CHAR(36) NULL AFTER external_id,
  ADD COLUMN IF NOT EXISTS external_error TEXT NULL AFTER external_command_uuid,
  ADD COLUMN IF NOT EXISTS external_response_json MEDIUMTEXT NULL AFTER external_error;
CREATE INDEX IF NOT EXISTS ix_bh_invitation_external_sync ON bh_invitations (installation_id,external_provider,external_sync_status);

CREATE TABLE IF NOT EXISTS bh_vehicles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_uuid CHAR(36) NOT NULL UNIQUE,
  installation_id BIGINT UNSIGNED NOT NULL,
  property_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NULL,
  plate VARCHAR(40) NOT NULL,
  make VARCHAR(80) NULL,
  model VARCHAR(100) NULL,
  color VARCHAR(60) NULL,
  notes VARCHAR(255) NULL,
  zk_access_level_ids TEXT NULL,
  status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
  external_provider VARCHAR(40) NULL,
  external_sync_status ENUM('not_requested','pending','synced','warning','error') NOT NULL DEFAULT 'not_requested',
  external_id VARCHAR(190) NULL,
  external_command_uuid CHAR(36) NULL,
  external_error TEXT NULL,
  external_response_json MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_vehicle_plate (installation_id,plate),
  KEY ix_bh_vehicle_property (property_id,status),
  KEY ix_bh_vehicle_person (person_id,status),
  KEY ix_bh_vehicle_external_sync (installation_id,external_provider,external_sync_status),
  CONSTRAINT fk_bh_vehicle_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_vehicle_property FOREIGN KEY (property_id) REFERENCES bh_properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_vehicle_person FOREIGN KEY (person_id) REFERENCES bh_people(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE bh_vehicles ADD COLUMN IF NOT EXISTS zk_access_level_ids TEXT NULL AFTER notes;

-- Un vehículo no es su placa. Puede tener RFID/card tag, LPR, QR, app, etc.
CREATE TABLE IF NOT EXISTS bh_vehicle_credentials (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_uuid CHAR(36) NOT NULL UNIQUE,
  installation_id BIGINT UNSIGNED NOT NULL,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  credential_type ENUM('rfid','card','plate_lpr','qr','mobile','other') NOT NULL,
  credential_value VARCHAR(190) NOT NULL,
  provider VARCHAR(40) NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive','revoked','error') NOT NULL DEFAULT 'active',
  external_id VARCHAR(190) NULL,
  external_sync_status ENUM('not_requested','pending','synced','warning','error') NOT NULL DEFAULT 'not_requested',
  external_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_vehicle_credential (installation_id,provider,credential_type,credential_value),
  KEY ix_bh_vehicle_credentials_vehicle (vehicle_id,status),
  CONSTRAINT fk_bh_vehicle_credential_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_vehicle_credential_vehicle FOREIGN KEY (vehicle_id) REFERENCES bh_vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS ix_bh_edge_commands_type_status ON bh_edge_commands (command_type,status,queued_at);
CREATE INDEX IF NOT EXISTS ix_bh_access_commands_correlation_status ON bh_access_commands (correlation_uuid,status,requested_at);

SET FOREIGN_KEY_CHECKS=1;
