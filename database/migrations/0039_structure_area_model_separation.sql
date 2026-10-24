-- Property Hub 0.14.44 — separación explícita entre estructura física, áreas funcionales y ubicación de cámaras.
-- Estructura responde DÓNDE; Área/Espacio responde QUÉ uso/ámbito tiene ese lugar; los recursos pueden ubicarse
-- directamente en estructura y opcionalmente precisar un área.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE bh_areas
  ADD COLUMN IF NOT EXISTS scope_type ENUM('common','private','technical','service','restricted','public') NOT NULL DEFAULT 'common' AFTER area_type,
  ADD COLUMN IF NOT EXISTS usage_type VARCHAR(60) NOT NULL DEFAULT 'general' AFTER scope_type;

-- Backfill conservador desde la clasificación histórica. No borra area_type, que se conserva por compatibilidad.
UPDATE bh_areas SET scope_type='technical' WHERE area_type='technical' AND scope_type='common';
UPDATE bh_areas SET scope_type='restricted' WHERE area_type='security' AND scope_type='common';
UPDATE bh_areas SET usage_type='circulation' WHERE area_type='circulation' AND usage_type='general';
UPDATE bh_areas SET usage_type='parking' WHERE area_type='parking' AND usage_type='general';
UPDATE bh_areas SET usage_type='office' WHERE area_type='office' AND usage_type='general';
UPDATE bh_areas SET usage_type='commercial' WHERE area_type='commercial' AND usage_type='general';
UPDATE bh_areas SET usage_type='technical' WHERE area_type='technical' AND usage_type='general';
UPDATE bh_areas SET usage_type='security' WHERE area_type='security' AND usage_type='general';
UPDATE bh_areas SET usage_type='reservable' WHERE area_type='reservable' AND usage_type='general';

ALTER TABLE bh_cameras
  ADD COLUMN IF NOT EXISTS property_id BIGINT UNSIGNED NULL AFTER installation_id;

CREATE INDEX IF NOT EXISTS ix_bh_camera_property ON bh_cameras (installation_id,property_id);

ALTER TABLE bh_cameras
  ADD CONSTRAINT fk_bh_camera_property FOREIGN KEY (property_id) REFERENCES bh_properties(id) ON DELETE SET NULL;

-- Las cámaras ya ubicadas por área heredan la ubicación estructural de esa área.
UPDATE bh_cameras c
JOIN bh_areas a ON a.id=c.area_id
SET c.property_id=a.property_id
WHERE c.property_id IS NULL AND a.property_id IS NOT NULL;

SET FOREIGN_KEY_CHECKS=1;
