-- 2CAM Property Hub 0.14.15
-- Laboratorio ZKBio API + cola segura Node -> Edge para pruebas remotas de solo lectura.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS bh_connector_api_tests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  connector_instance_id BIGINT UNSIGNED NULL,
  edge_agent_id BIGINT UNSIGNED NULL,
  origin ENUM('edge_local','node_remote') NOT NULL DEFAULT 'edge_local',
  operation_code VARCHAR(120) NOT NULL,
  http_method VARCHAR(12) NOT NULL,
  request_path VARCHAR(500) NOT NULL,
  request_json MEDIUMTEXT NULL,
  response_json MEDIUMTEXT NULL,
  http_status SMALLINT UNSIGNED NULL,
  vendor_code VARCHAR(80) NULL,
  status ENUM('success','error') NOT NULL,
  latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
  error_message TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_bh_connector_api_tests_recent (installation_id,created_at),
  KEY ix_bh_connector_api_tests_connector (connector_instance_id,created_at),
  KEY ix_bh_connector_api_tests_agent (edge_agent_id,created_at),
  CONSTRAINT fk_bh_connector_api_tests_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_connector_api_tests_connector FOREIGN KEY (connector_instance_id) REFERENCES bh_connector_instances(id) ON DELETE SET NULL,
  CONSTRAINT fk_bh_connector_api_tests_agent FOREIGN KEY (edge_agent_id) REFERENCES bh_edge_agents(id) ON DELETE SET NULL,
  CONSTRAINT fk_bh_connector_api_tests_user FOREIGN KEY (created_by) REFERENCES bh_local_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_edge_commands (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  command_uuid CHAR(36) NOT NULL UNIQUE,
  agent_id BIGINT UNSIGNED NOT NULL,
  command_type VARCHAR(120) NOT NULL,
  payload_json MEDIUMTEXT NULL,
  status ENUM('queued','claimed','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
  queued_by BIGINT UNSIGNED NULL,
  queued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  claimed_at DATETIME NULL,
  completed_at DATETIME NULL,
  result_json MEDIUMTEXT NULL,
  error_message TEXT NULL,
  KEY ix_bh_edge_commands_queue (agent_id,status,queued_at),
  KEY ix_bh_edge_commands_recent (agent_id,queued_at),
  CONSTRAINT fk_bh_edge_commands_agent FOREIGN KEY (agent_id) REFERENCES bh_edge_agents(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_edge_commands_user FOREIGN KEY (queued_by) REFERENCES bh_local_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
