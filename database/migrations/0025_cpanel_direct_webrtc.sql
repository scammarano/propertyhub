-- 2CAM Property Hub 0.13.5
-- WebRTC directo Edge -> navegador para laboratorio cPanel sin Media Relay/VPS.

ALTER TABLE bh_media_relay_settings
  MODIFY COLUMN media_mode ENUM('cpanel_test','edge_direct_webrtc','external_webrtc') NOT NULL DEFAULT 'cpanel_test';

CREATE TABLE IF NOT EXISTS bh_edge_media_endpoints (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  edge_agent_id BIGINT UNSIGNED NULL,
  agent_code VARCHAR(100) NOT NULL,
  endpoint_type ENUM('quick_tunnel','named_tunnel','manual') NOT NULL DEFAULT 'quick_tunnel',
  public_base_url VARCHAR(500) NOT NULL,
  status ENUM('online','offline','error') NOT NULL DEFAULT 'online',
  last_seen_at DATETIME NULL,
  last_error TEXT NULL,
  metadata_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_edge_media_endpoint_agent (installation_id,agent_code),
  KEY ix_bh_edge_media_endpoint_agent_id (edge_agent_id),
  CONSTRAINT fk_bh_edge_media_endpoint_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bh_edge_media_gateway
  ADD COLUMN IF NOT EXISTS tunnel_provider VARCHAR(40) NULL AFTER status,
  ADD COLUMN IF NOT EXISTS tunnel_public_url VARCHAR(500) NULL AFTER tunnel_provider,
  ADD COLUMN IF NOT EXISTS tunnel_status ENUM('not_configured','starting','online','offline','error') NOT NULL DEFAULT 'not_configured' AFTER tunnel_public_url,
  ADD COLUMN IF NOT EXISTS tunnel_last_started_at DATETIME NULL AFTER tunnel_status;
