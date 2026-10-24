-- Property Hub 0.14.41 — nombres mostrables y orden de presentación para recursos de residentes.
-- El nombre técnico/administrativo permanece separado del nombre amigable de Property App.

ALTER TABLE bh_cameras
  ADD COLUMN IF NOT EXISTS resident_name VARCHAR(160) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS display_order INT NOT NULL DEFAULT 100 AFTER resident_name;

ALTER TABLE bh_access_points
  ADD COLUMN IF NOT EXISTS resident_name VARCHAR(160) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS display_order INT NOT NULL DEFAULT 100 AFTER resident_name;

CREATE INDEX IF NOT EXISTS ix_bh_camera_resident_order
  ON bh_cameras (installation_id,area_id,display_order,resident_name);

CREATE INDEX IF NOT EXISTS ix_bh_access_resident_order
  ON bh_access_points (installation_id,area_id,display_order,resident_name);
