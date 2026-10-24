-- 2CAM Property Hub 0.13.0
-- Gateway de video Edge -> Media Relay público -> WebRTC/WHEP en Property App.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS bh_media_relay_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  relay_name VARCHAR(120) NOT NULL DEFAULT 'Property Hub Media Relay',
  webrtc_base_url VARCHAR(500) NULL,
  publish_rtsp_base_url VARCHAR(500) NULL,
  credentials_encrypted MEDIUMTEXT NULL,
  status ENUM('not_configured','configured','online','error','disabled') NOT NULL DEFAULT 'not_configured',
  last_test_at DATETIME NULL,
  last_error TEXT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_media_relay_installation (installation_id),
  CONSTRAINT fk_bh_media_relay_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_edge_media_gateway (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(40) NOT NULL DEFAULT 'mediamtx',
  provider_version VARCHAR(40) NULL,
  binary_path VARCHAR(500) NULL,
  config_path VARCHAR(500) NULL,
  local_rtsp_base_url VARCHAR(255) NOT NULL DEFAULT 'rtsp://127.0.0.1:8554',
  local_webrtc_base_url VARCHAR(255) NOT NULL DEFAULT 'http://127.0.0.1:8889',
  relay_configuration_encrypted MEDIUMTEXT NULL,
  status ENUM('not_installed','configured','running','stopped','error','disabled') NOT NULL DEFAULT 'not_installed',
  last_check_at DATETIME NULL,
  last_error TEXT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_edge_media_gateway_installation (installation_id),
  CONSTRAINT fk_bh_edge_media_gateway_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bh_edge_cameras
  ADD COLUMN IF NOT EXISTS webrtc_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER snapshot_url,
  ADD COLUMN IF NOT EXISTS webrtc_path VARCHAR(190) NULL AFTER webrtc_enabled,
  ADD COLUMN IF NOT EXISTS webrtc_codec VARCHAR(40) NULL AFTER webrtc_path,
  ADD COLUMN IF NOT EXISTS webrtc_status ENUM('disabled','pending','ready','incompatible','error') NOT NULL DEFAULT 'disabled' AFTER webrtc_codec,
  ADD COLUMN IF NOT EXISTS webrtc_last_test_at DATETIME NULL AFTER webrtc_status,
  ADD COLUMN IF NOT EXISTS webrtc_error TEXT NULL AFTER webrtc_last_test_at;

ALTER TABLE bh_cameras
  ADD COLUMN IF NOT EXISTS live_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER snapshot_at,
  ADD COLUMN IF NOT EXISTS live_transport ENUM('none','webrtc','hls') NOT NULL DEFAULT 'none' AFTER live_enabled,
  ADD COLUMN IF NOT EXISTS live_path VARCHAR(190) NULL AFTER live_transport,
  ADD COLUMN IF NOT EXISTS live_codec VARCHAR(40) NULL AFTER live_path,
  ADD COLUMN IF NOT EXISTS live_status ENUM('disabled','pending','ready','incompatible','offline','error') NOT NULL DEFAULT 'disabled' AFTER live_codec,
  ADD COLUMN IF NOT EXISTS live_updated_at DATETIME NULL AFTER live_status;

SET FOREIGN_KEY_CHECKS=1;
