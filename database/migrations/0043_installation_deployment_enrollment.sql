-- Property Hub 0.14.53 · Installation Deployment Center & Central-issued Edge enrollment.
-- Central remains source of truth while Edge traffic still terminates at the Property Node.
-- The table is safe to create on every role so the monolithic updater can apply this migration automatically; only Central uses it.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS bh_repo_edge_deployments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_installation_id BIGINT UNSIGNED NOT NULL,
  edge_code VARCHAR(80) NOT NULL,
  edge_name VARCHAR(190) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  status ENUM('pending','claimed','revoked','expired') NOT NULL DEFAULT 'pending',
  expires_at DATETIME NOT NULL,
  claimed_at DATETIME NULL,
  claimed_agent_uuid CHAR(36) NULL,
  claimed_base_url VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_repo_edge_deployment_code (parent_installation_id,edge_code),
  KEY ix_bh_repo_edge_deployment_status (parent_installation_id,status,expires_at),
  CONSTRAINT fk_bh_repo_edge_deployment_user FOREIGN KEY (created_by) REFERENCES bh_local_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
