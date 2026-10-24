-- Property Hub 0.14.54 · Property-first portfolio and durable Edge enrollment token history.
-- Central-only migration. Nodes and Edge Agents do not create repository portfolio tables.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS bh_repo_edge_enrollment_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  edge_deployment_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  status ENUM('active','used','revoked','expired') NOT NULL DEFAULT 'active',
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  revoked_at DATETIME NULL,
  revocation_reason VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_bh_repo_edge_token_edge (edge_deployment_id,created_at),
  KEY ix_bh_repo_edge_token_status (status,expires_at),
  CONSTRAINT fk_bh_repo_edge_token_deployment FOREIGN KEY (edge_deployment_id) REFERENCES bh_repo_edge_deployments(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_repo_edge_token_user FOREIGN KEY (created_by) REFERENCES bh_local_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve the token state introduced by 0.14.53 before future regenerations
-- start writing a complete history row for every enrollment credential.
INSERT IGNORE INTO bh_repo_edge_enrollment_tokens
  (edge_deployment_id,token_hash,status,expires_at,used_at,revoked_at,revocation_reason,created_by,created_at)
SELECT
  d.id,
  d.token_hash,
  CASE d.status WHEN 'pending' THEN 'active' WHEN 'claimed' THEN 'used' WHEN 'revoked' THEN 'revoked' ELSE 'expired' END,
  d.expires_at,
  CASE WHEN d.status='claimed' THEN d.claimed_at ELSE NULL END,
  CASE WHEN d.status='revoked' THEN d.updated_at ELSE NULL END,
  CASE WHEN d.status='revoked' THEN 'Estado migrado desde 0.14.53' ELSE NULL END,
  d.created_by,
  d.created_at
FROM bh_repo_edge_deployments d;

-- Historical registration tokens remain queryable by allowed_installation_code;
-- future successful registrations also persist installation_id directly.

SET FOREIGN_KEY_CHECKS=1;
