-- 2CAM Property Hub 0.11.2
-- Emparejamiento Edge con un solo PHEDGE y catálogo técnico de cámaras ONVIF/RTSP.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE bh_edge_pairing_tokens
  ADD COLUMN IF NOT EXISTS central_bootstrap_encrypted TEXT NULL AFTER allowed_agent_code,
  ADD COLUMN IF NOT EXISTS central_repository_url VARCHAR(500) NULL AFTER central_bootstrap_encrypted;

CREATE TABLE IF NOT EXISTS bh_edge_cameras (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  camera_uuid CHAR(36) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  host VARCHAR(255) NOT NULL,
  onvif_url VARCHAR(500) NOT NULL,
  media_service_url VARCHAR(500) NULL,
  rtsp_url VARCHAR(1000) NULL,
  snapshot_url VARCHAR(1000) NULL,
  configuration_encrypted TEXT NULL,
  manufacturer VARCHAR(120) NULL,
  model VARCHAR(160) NULL,
  firmware_version VARCHAR(120) NULL,
  serial_number VARCHAR(160) NULL,
  hardware_id VARCHAR(160) NULL,
  profile_count INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('configured','online','offline','warning','error','disabled') NOT NULL DEFAULT 'configured',
  last_test_at DATETIME NULL,
  last_error TEXT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_edge_camera_installation_name (installation_id,name),
  KEY ix_bh_edge_cameras_status (installation_id,status,last_test_at),
  CONSTRAINT fk_bh_edge_camera_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
