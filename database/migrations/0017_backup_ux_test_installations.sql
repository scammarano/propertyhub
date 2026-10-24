-- 2CAM Property Hub 0.10.5
-- UX de respaldo y ciclo de vida seguro de instalaciones de prueba en Property Hub Central.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE bh_repo_installations
  ADD COLUMN IF NOT EXISTS environment_type ENUM('production','test','lab') NOT NULL DEFAULT 'production' AFTER is_demo,
  ADD COLUMN IF NOT EXISTS clone_source_code VARCHAR(60) NULL AFTER environment_type;

ALTER TABLE bh_repo_installation_plans
  ADD COLUMN IF NOT EXISTS environment_type ENUM('production','test','lab') NOT NULL DEFAULT 'production' AFTER data_scope,
  ADD COLUMN IF NOT EXISTS clone_source_code VARCHAR(60) NULL AFTER environment_type;

UPDATE bh_repo_installations
SET environment_type='test'
WHERE is_demo=1;

UPDATE bh_repo_installations
SET environment_type='test'
WHERE environment_type='production'
  AND (
    LOWER(COALESCE(base_url,'')) LIKE '%localhost%'
    OR UPPER(installation_code) REGEXP '(^|[-_])(TEST|PRUEBA)([-_]|$)'
  );

UPDATE bh_repo_installations
SET environment_type='lab'
WHERE environment_type='production'
  AND UPPER(installation_code) REGEXP '(^|[-_])LAB([-_]|$)';

SET FOREIGN_KEY_CHECKS=1;
