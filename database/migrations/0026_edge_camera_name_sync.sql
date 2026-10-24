-- 2CAM Property Hub 0.14.1
-- Separa el nombre técnico recibido del Edge del nombre visible administrado en el Node.
-- Por defecto, las cámaras Edge existentes siguen el nombre del Agent; el Node puede
-- desactivar esa sincronización cuando necesite un nombre público personalizado.
SET NAMES utf8mb4;

ALTER TABLE bh_cameras
  ADD COLUMN IF NOT EXISTS edge_name VARCHAR(190) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS name_sync_mode ENUM('edge','node') NOT NULL DEFAULT 'node' AFTER edge_name;

UPDATE bh_cameras
SET edge_name = name,
    name_sync_mode = 'edge'
WHERE source_scope='edge'
  AND (edge_name IS NULL OR TRIM(edge_name)='');

UPDATE bh_cameras
SET name_sync_mode='node'
WHERE source_scope<>'edge';
