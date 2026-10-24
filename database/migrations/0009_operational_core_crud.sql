-- 2CAM Property Hub 0.6.0
-- Perfil real de la instalación y soporte del primer núcleo CRUD persistente.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE bh_installations
  ADD COLUMN IF NOT EXISTS address_line VARCHAR(255) NULL AFTER api_version,
  ADD COLUMN IF NOT EXISTS city VARCHAR(120) NULL AFTER address_line,
  ADD COLUMN IF NOT EXISTS state_region VARCHAR(120) NULL AFTER city,
  ADD COLUMN IF NOT EXISTS country_code CHAR(2) NULL DEFAULT 'VE' AFTER state_region,
  ADD COLUMN IF NOT EXISTS contact_name VARCHAR(160) NULL AFTER country_code,
  ADD COLUMN IF NOT EXISTS contact_email VARCHAR(190) NULL AFTER contact_name,
  ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(60) NULL AFTER contact_email,
  ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER contact_phone;

SET FOREIGN_KEY_CHECKS=1;
