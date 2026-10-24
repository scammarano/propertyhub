-- 2CAM Property Hub 0.13.1
-- Ciclo de vida editable de fuentes de video Edge + baja sincronizada + canales ONVIF NVR/DVR.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE bh_edge_cameras
  ADD COLUMN IF NOT EXISTS source_kind ENUM('camera','nvr','dvr','encoder','unknown') NOT NULL DEFAULT 'unknown' AFTER name,
  ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER sync_error,
  ADD COLUMN IF NOT EXISTS deleted_synced_at DATETIME NULL AFTER deleted_at,
  ADD COLUMN IF NOT EXISTS last_channel_scan_at DATETIME NULL AFTER deleted_synced_at;

CREATE TABLE IF NOT EXISTS bh_edge_video_channels (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  edge_camera_id BIGINT UNSIGNED NOT NULL,
  channel_uuid CHAR(36) NOT NULL,
  source_token VARCHAR(255) NOT NULL,
  channel_index INT UNSIGNED NOT NULL DEFAULT 1,
  channel_name VARCHAR(190) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  selected_profile_token VARCHAR(255) NULL,
  profile_name VARCHAR(190) NULL,
  encoding VARCHAR(40) NULL,
  resolution VARCHAR(60) NULL,
  stream_uri VARCHAR(1000) NULL,
  snapshot_uri VARCHAR(1000) NULL,
  status ENUM('detected','online','offline','disabled','error') NOT NULL DEFAULT 'detected',
  webrtc_enabled TINYINT(1) NOT NULL DEFAULT 0,
  webrtc_path VARCHAR(190) NULL,
  webrtc_codec VARCHAR(40) NULL,
  webrtc_status ENUM('disabled','pending','ready','incompatible','error') NOT NULL DEFAULT 'disabled',
  webrtc_last_test_at DATETIME NULL,
  webrtc_error TEXT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_edge_video_channel_uuid (channel_uuid),
  UNIQUE KEY uq_bh_edge_video_channel_source (edge_camera_id,source_token),
  KEY ix_bh_edge_video_channel_enabled (installation_id,enabled,status),
  CONSTRAINT fk_bh_edge_video_channel_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_edge_video_channel_camera FOREIGN KEY (edge_camera_id) REFERENCES bh_edge_cameras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bh_cameras
  ADD COLUMN IF NOT EXISTS edge_source_uuid CHAR(36) NULL AFTER edge_camera_uuid,
  ADD COLUMN IF NOT EXISTS edge_channel_key VARCHAR(255) NULL AFTER edge_source_uuid,
  ADD COLUMN IF NOT EXISTS source_deleted_at DATETIME NULL AFTER source_metadata_json;

ALTER TABLE bh_devices
  ADD COLUMN IF NOT EXISTS source_deleted_at DATETIME NULL AFTER source_updated_at;

ALTER TABLE bh_media_relay_settings
  ADD COLUMN IF NOT EXISTS media_mode ENUM('cpanel_test','external_webrtc') NOT NULL DEFAULT 'cpanel_test' AFTER relay_name;

SET FOREIGN_KEY_CHECKS=1;
