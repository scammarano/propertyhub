-- 2CAM Property Hub 0.11.0 - Central registry para Property Nodes y Edge Agents.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE bh_repo_installations
  ADD COLUMN IF NOT EXISTS instance_role ENUM('building_node','edge_agent') NOT NULL DEFAULT 'building_node' AFTER installation_type,
  ADD COLUMN IF NOT EXISTS parent_installation_code VARCHAR(60) NULL AFTER instance_role;

ALTER TABLE bh_backup_offsite_archives
  ADD COLUMN IF NOT EXISTS instance_role ENUM('building_node','edge_agent') NOT NULL DEFAULT 'building_node' AFTER installation_code,
  ADD COLUMN IF NOT EXISTS parent_installation_code VARCHAR(60) NULL AFTER instance_role;

SET FOREIGN_KEY_CHECKS=1;
