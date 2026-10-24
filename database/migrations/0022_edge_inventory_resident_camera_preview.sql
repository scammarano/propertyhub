-- 2CAM Property Hub 0.12.0
-- Conexiones dinámicas Node/Edge en BD + sincronización Edge -> Node + snapshot seguro para Resident App.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS bh_remote_connections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  connection_type ENUM('central','parent_node') NOT NULL,
  remote_code VARCHAR(80) NULL,
  endpoint_url VARCHAR(500) NOT NULL,
  credential_encrypted MEDIUMTEXT NULL,
  status ENUM('configured','online','offline','error','disabled') NOT NULL DEFAULT 'configured',
  provisioned_by ENUM('installer','self','property_node','legacy_env','manual_recovery') NOT NULL DEFAULT 'self',
  last_ok_at DATETIME NULL,
  last_error TEXT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_remote_connection (installation_id,connection_type),
  KEY ix_bh_remote_connection_status (installation_id,status,updated_at),
  CONSTRAINT fk_bh_remote_connection_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bh_edge_cameras
  ADD COLUMN IF NOT EXISTS last_synced_at DATETIME NULL AFTER last_test_at,
  ADD COLUMN IF NOT EXISTS last_snapshot_synced_at DATETIME NULL AFTER last_synced_at,
  ADD COLUMN IF NOT EXISTS sync_status ENUM('pending','synced','warning','error') NOT NULL DEFAULT 'pending' AFTER last_snapshot_synced_at,
  ADD COLUMN IF NOT EXISTS sync_error TEXT NULL AFTER sync_status;

ALTER TABLE bh_devices
  ADD COLUMN IF NOT EXISTS source_scope ENUM('node','edge') NOT NULL DEFAULT 'node' AFTER managed_by,
  ADD COLUMN IF NOT EXISTS edge_agent_id BIGINT UNSIGNED NULL AFTER source_scope,
  ADD COLUMN IF NOT EXISTS source_uuid CHAR(36) NULL AFTER edge_agent_id,
  ADD COLUMN IF NOT EXISTS source_updated_at DATETIME NULL AFTER source_uuid;

ALTER TABLE bh_cameras
  ADD COLUMN IF NOT EXISTS source_scope ENUM('node','edge') NOT NULL DEFAULT 'node' AFTER status,
  ADD COLUMN IF NOT EXISTS edge_agent_id BIGINT UNSIGNED NULL AFTER source_scope,
  ADD COLUMN IF NOT EXISTS edge_camera_uuid CHAR(36) NULL AFTER edge_agent_id,
  ADD COLUMN IF NOT EXISTS edge_last_seen_at DATETIME NULL AFTER edge_camera_uuid,
  ADD COLUMN IF NOT EXISTS snapshot_path VARCHAR(500) NULL AFTER edge_last_seen_at,
  ADD COLUMN IF NOT EXISTS snapshot_mime VARCHAR(100) NULL AFTER snapshot_path,
  ADD COLUMN IF NOT EXISTS snapshot_at DATETIME NULL AFTER snapshot_mime,
  ADD COLUMN IF NOT EXISTS source_metadata_json JSON NULL AFTER snapshot_at;

SET FOREIGN_KEY_CHECKS=1;
