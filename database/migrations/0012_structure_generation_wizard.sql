-- 2CAM Property Hub 0.6.4
-- Historial y trazabilidad del asistente de generación masiva de estructura.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS bh_structure_wizard_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  public_uuid CHAR(36) NOT NULL UNIQUE,
  root_property_id BIGINT UNSIGNED NULL,
  run_name VARCHAR(180) NOT NULL,
  status ENUM('completed','rolled_back','rollback_blocked','failed') NOT NULL DEFAULT 'completed',
  configuration_json JSON NOT NULL,
  summary_json JSON NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  rolled_back_at DATETIME NULL,
  KEY ix_bh_structure_wizard_installation (installation_id,status,created_at),
  CONSTRAINT fk_bh_structure_wizard_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_structure_wizard_root FOREIGN KEY (root_property_id) REFERENCES bh_properties(id) ON DELETE SET NULL,
  CONSTRAINT fk_bh_structure_wizard_user FOREIGN KEY (created_by) REFERENCES bh_local_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_structure_wizard_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  entity_type ENUM('property','area') NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  entity_code VARCHAR(60) NOT NULL,
  hierarchy_depth SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_structure_wizard_item (run_id,entity_type,entity_id),
  KEY ix_bh_structure_wizard_entity (entity_type,entity_id),
  CONSTRAINT fk_bh_structure_wizard_item_run FOREIGN KEY (run_id) REFERENCES bh_structure_wizard_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
