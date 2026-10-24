-- 2CAM Property Hub 0.11.0
-- Rol Edge Agent, emparejamiento Agent -> Property Node y custodia local de respaldos del Agent.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE bh_deployment_settings
  MODIFY COLUMN instance_role ENUM('central_repository','building_node','edge_agent') NOT NULL;

ALTER TABLE bh_backup_settings
  ADD COLUMN IF NOT EXISTS node_copy_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER central_copy_enabled;

CREATE TABLE IF NOT EXISTS bh_edge_pairing_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL UNIQUE,
  token_name VARCHAR(160) NOT NULL,
  allowed_agent_code VARCHAR(60) NULL,
  status ENUM('active','used','revoked','expired') NOT NULL DEFAULT 'active',
  expires_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  used_at DATETIME NULL,
  KEY ix_bh_edge_pairing_status (status,expires_at),
  CONSTRAINT fk_bh_edge_pairing_user FOREIGN KEY (created_by) REFERENCES bh_local_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_edge_agents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_uuid CHAR(36) NOT NULL UNIQUE,
  agent_code VARCHAR(60) NOT NULL UNIQUE,
  agent_name VARCHAR(190) NOT NULL,
  base_url VARCHAR(500) NULL,
  agent_key_hash CHAR(64) NOT NULL,
  app_version VARCHAR(50) NOT NULL DEFAULT '0.0.0',
  status ENUM('active','offline','warning','disabled','retired') NOT NULL DEFAULT 'active',
  last_seen_at DATETIME NULL,
  last_error TEXT NULL,
  metadata_json JSON NULL,
  registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_bh_edge_agents_status (status,last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Política de custodia por defecto de la arquitectura 0.11.0:
-- Node -> Central; Edge -> Property Node + Central. El usuario puede desactivarlo después.
UPDATE bh_backup_settings b
JOIN bh_deployment_settings d ON d.installation_id=b.installation_id
SET b.central_copy_enabled=1
WHERE d.instance_role IN ('building_node','edge_agent');

UPDATE bh_backup_settings b
JOIN bh_deployment_settings d ON d.installation_id=b.installation_id
SET b.node_copy_enabled=1
WHERE d.instance_role='edge_agent';

CREATE TABLE IF NOT EXISTS bh_edge_backup_archives (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agent_id BIGINT UNSIGNED NOT NULL,
  backup_uuid CHAR(36) NULL,
  filename VARCHAR(255) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  sha256 CHAR(64) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  backup_created_at DATETIME NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('stored','corrupt','deleted') NOT NULL DEFAULT 'stored',
  metadata_json JSON NULL,
  KEY ix_bh_edge_backup_agent (agent_id,received_at),
  CONSTRAINT fk_bh_edge_backup_agent FOREIGN KEY (agent_id) REFERENCES bh_edge_agents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
