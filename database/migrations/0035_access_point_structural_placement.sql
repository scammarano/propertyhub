-- Property Hub 0.14.22 · Ubicación estructural de accesos y lados físicos de una puerta.
-- Una puerta puede ubicarse directamente en edificio/torre/nivel aun sin área específica,
-- y opcionalmente describir los dos espacios que separa.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS bh_access_point_sides (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  access_point_id BIGINT UNSIGNED NOT NULL,
  side_key ENUM('A','B') NOT NULL,
  property_id BIGINT UNSIGNED NULL,
  area_id BIGINT UNSIGNED NULL,
  label VARCHAR(160) NULL,
  direction_hint ENUM('entry','exit','bidirectional','unknown') NOT NULL DEFAULT 'unknown',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_access_point_side (access_point_id,side_key),
  KEY ix_bh_access_side_location (installation_id,property_id,area_id),
  CONSTRAINT fk_bh_access_side_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_access_side_access FOREIGN KEY (access_point_id) REFERENCES bh_access_points(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_access_side_property FOREIGN KEY (property_id) REFERENCES bh_properties(id) ON DELETE SET NULL,
  CONSTRAINT fk_bh_access_side_area FOREIGN KEY (area_id) REFERENCES bh_areas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
