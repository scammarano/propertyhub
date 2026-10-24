-- 2CAM Property Hub 0.5.0
-- Amplía el portafolio central para urbanizaciones y otros tipos de propiedad.

SET NAMES utf8mb4;

ALTER TABLE bh_repo_installations
  MODIFY COLUMN installation_type ENUM(
    'residential','urbanization','residential_complex','office','office_building',
    'commercial','shopping_center','mixed','campus','industrial','other'
  ) NOT NULL DEFAULT 'mixed';
