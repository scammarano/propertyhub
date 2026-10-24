-- 2CAM Property Hub 0.7.0
-- Diseñador de estructura, ubicación fina de activos y trazabilidad de duplicaciones.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE bh_devices
  ADD COLUMN IF NOT EXISTS property_id BIGINT UNSIGNED NULL AFTER site_location_id,
  ADD COLUMN IF NOT EXISTS area_id BIGINT UNSIGNED NULL AFTER property_id,
  ADD COLUMN IF NOT EXISTS asset_stage ENUM('planned','installed','commissioned','retired') NOT NULL DEFAULT 'installed' AFTER asset_role;

ALTER TABLE bh_devices
  MODIFY COLUMN device_type ENUM(
    'access_controller','reader','lock','relay','camera','nvr','intercom','phone','speaker',
    'network_controller','network_switch','poe_switch','access_point','patch_panel','fiber_distributor','rack','ups','gateway',
    'elevator_controller','turnstile','barrier','door_operator','fire_panel','pump','meter','sensor','iot_actuator','other'
  ) NOT NULL;

CREATE INDEX IF NOT EXISTS ix_bh_device_property ON bh_devices (property_id);
CREATE INDEX IF NOT EXISTS ix_bh_device_area ON bh_devices (area_id);
CREATE INDEX IF NOT EXISTS ix_bh_device_stage ON bh_devices (installation_id,asset_stage,status);

ALTER TABLE bh_devices
  ADD CONSTRAINT fk_bh_device_property FOREIGN KEY (property_id) REFERENCES bh_properties(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_bh_device_area FOREIGN KEY (area_id) REFERENCES bh_areas(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS bh_structure_clone_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  public_uuid CHAR(36) NOT NULL UNIQUE,
  source_property_id BIGINT UNSIGNED NOT NULL,
  target_property_id BIGINT UNSIGNED NOT NULL,
  options_json JSON NOT NULL,
  summary_json JSON NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_bh_structure_clone_installation (installation_id,created_at),
  CONSTRAINT fk_bh_structure_clone_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_structure_clone_source FOREIGN KEY (source_property_id) REFERENCES bh_properties(id),
  CONSTRAINT fk_bh_structure_clone_target FOREIGN KEY (target_property_id) REFERENCES bh_properties(id),
  CONSTRAINT fk_bh_structure_clone_user FOREIGN KEY (created_by) REFERENCES bh_local_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
