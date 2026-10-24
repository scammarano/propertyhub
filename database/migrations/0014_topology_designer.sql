-- 2CAM Property Hub 0.8.0
-- Diseñador topológico: niveles especiales, zonas de estacionamiento/maleteros,
-- áreas ubicadas y circulación vertical con paradas por torre.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE bh_properties
  MODIFY COLUMN property_type ENUM(
    'site','urbanization','sector','street','block','parcel','building','tower',
    'floor','ground_floor','basement','mezzanine','terrace','roof','technical_floor',
    'apartment','house','townhouse','office','commercial_unit','warehouse','storage',
    'parking','parking_zone','garage','guard_post','park','common','technical','other'
  ) NOT NULL;

ALTER TABLE bh_areas
  ADD COLUMN IF NOT EXISTS metadata_json JSON NULL AFTER rules_text;

ALTER TABLE bh_access_points
  ADD COLUMN IF NOT EXISTS property_id BIGINT UNSIGNED NULL AFTER installation_id;

CREATE INDEX IF NOT EXISTS ix_bh_access_point_property ON bh_access_points (property_id);

ALTER TABLE bh_access_points
  ADD CONSTRAINT fk_bh_access_point_property FOREIGN KEY (property_id) REFERENCES bh_properties(id) ON DELETE SET NULL;

ALTER TABLE bh_structure_wizard_items
  MODIFY COLUMN entity_type ENUM('property','area','vertical_transport','access_point') NOT NULL;

CREATE TABLE IF NOT EXISTS bh_vertical_transports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  public_uuid CHAR(36) NOT NULL UNIQUE,
  code VARCHAR(60) NOT NULL,
  name VARCHAR(160) NOT NULL,
  transport_type ENUM('passenger_elevator','service_elevator','freight_elevator','escalator','stair','other') NOT NULL DEFAULT 'passenger_elevator',
  status ENUM('planned','active','maintenance','inactive') NOT NULL DEFAULT 'planned',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_vertical_transport_code (installation_id,code),
  KEY ix_bh_vertical_transport_installation (installation_id,status),
  CONSTRAINT fk_bh_vertical_transport_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_vertical_transport_towers (
  vertical_transport_id BIGINT UNSIGNED NOT NULL,
  tower_property_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (vertical_transport_id,tower_property_id),
  CONSTRAINT fk_bh_vertical_tower_transport FOREIGN KEY (vertical_transport_id) REFERENCES bh_vertical_transports(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_vertical_tower_property FOREIGN KEY (tower_property_id) REFERENCES bh_properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_vertical_transport_stops (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vertical_transport_id BIGINT UNSIGNED NOT NULL,
  level_property_id BIGINT UNSIGNED NOT NULL,
  access_point_id BIGINT UNSIGNED NULL,
  stop_order INT NOT NULL DEFAULT 0,
  is_restricted TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_vertical_transport_stop (vertical_transport_id,level_property_id),
  KEY ix_bh_vertical_stop_level (level_property_id),
  CONSTRAINT fk_bh_vertical_stop_transport FOREIGN KEY (vertical_transport_id) REFERENCES bh_vertical_transports(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_vertical_stop_level FOREIGN KEY (level_property_id) REFERENCES bh_properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_vertical_stop_access FOREIGN KEY (access_point_id) REFERENCES bh_access_points(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
