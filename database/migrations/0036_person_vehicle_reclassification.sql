-- 0.14.24 — normalización de nombres y reclasificación segura persona ↔ vehículo.
-- Vehicle es una identidad propia: la placa puede ser desconocida en una adopción RFID y no debe inventarse.

ALTER TABLE bh_vehicles
  MODIFY COLUMN plate VARCHAR(40) NULL;

ALTER TABLE bh_vehicles
  ADD COLUMN IF NOT EXISTS display_name VARCHAR(160) NULL AFTER person_id;

CREATE INDEX IF NOT EXISTS ix_bh_vehicle_display_name ON bh_vehicles (installation_id,display_name);
