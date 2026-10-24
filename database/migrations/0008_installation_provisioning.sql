-- 2CAM Property Hub 0.5.2
-- Flujo central para preparar nuevas instalaciones antes de desplegar el Property Node.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS bh_repo_installation_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_uuid CHAR(36) NOT NULL UNIQUE,
  installation_code VARCHAR(60) NOT NULL UNIQUE,
  installation_name VARCHAR(190) NOT NULL,
  installation_type VARCHAR(40) NOT NULL DEFAULT 'mixed',
  planned_base_url VARCHAR(500) NULL,
  update_channel ENUM('development','beta','stable','pinned') NOT NULL DEFAULT 'stable',
  data_scope ENUM('metadata_only','aggregates','support_snapshot') NOT NULL DEFAULT 'aggregates',
  status ENUM('draft','ready','token_issued','registered','cancelled') NOT NULL DEFAULT 'ready',
  technology_json JSON NULL,
  notes TEXT NULL,
  registration_token_id BIGINT UNSIGNED NULL,
  registered_installation_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  registered_at DATETIME NULL,
  KEY ix_bh_repo_plan_status (status,created_at),
  CONSTRAINT fk_bh_repo_plan_token FOREIGN KEY (registration_token_id) REFERENCES bh_repo_registration_tokens(id) ON DELETE SET NULL,
  CONSTRAINT fk_bh_repo_plan_installation FOREIGN KEY (registered_installation_id) REFERENCES bh_repo_installations(id) ON DELETE SET NULL,
  CONSTRAINT fk_bh_repo_plan_user FOREIGN KEY (created_by) REFERENCES bh_local_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
