-- Property Hub 0.14.57 · Signed deployment file and property-scoped Edge preparation.
-- Operational data is Central-only. The migration is deliberately safe on Node/Edge:
-- when the Central repository tables are absent every statement becomes a no-op. This
-- lets the existing 0.14.56 updater apply 0045 automatically on all package roles.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

SET @bh_has_edge_deployments := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_repo_edge_deployments');
SET @bh_has_property_code := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_repo_edge_deployments' AND COLUMN_NAME='property_code');
SET @bh_sql := IF(@bh_has_edge_deployments=0,'SELECT 1',IF(@bh_has_property_code=0,'ALTER TABLE bh_repo_edge_deployments MODIFY COLUMN parent_installation_id BIGINT UNSIGNED NULL, ADD COLUMN property_code VARCHAR(60) NULL AFTER parent_installation_id','ALTER TABLE bh_repo_edge_deployments MODIFY COLUMN parent_installation_id BIGINT UNSIGNED NULL'));
PREPARE bh_stmt FROM @bh_sql;
EXECUTE bh_stmt;
DEALLOCATE PREPARE bh_stmt;

SET @bh_has_installations := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_repo_installations');
SET @bh_sql := IF(@bh_has_edge_deployments=1 AND @bh_has_installations=1,'UPDATE bh_repo_edge_deployments d JOIN bh_repo_installations i ON i.id=d.parent_installation_id SET d.property_code=i.installation_code WHERE d.property_code IS NULL','SELECT 1');
PREPARE bh_stmt FROM @bh_sql;
EXECUTE bh_stmt;
DEALLOCATE PREPARE bh_stmt;

SET @bh_has_ix := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_repo_edge_deployments' AND INDEX_NAME='ix_bh_repo_edge_deployment_property');
SET @bh_sql := IF(@bh_has_edge_deployments=1 AND @bh_has_ix=0,'CREATE INDEX ix_bh_repo_edge_deployment_property ON bh_repo_edge_deployments (property_code,status,expires_at)','SELECT 1');
PREPARE bh_stmt FROM @bh_sql;
EXECUTE bh_stmt;
DEALLOCATE PREPARE bh_stmt;

SET @bh_has_uq := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_repo_edge_deployments' AND INDEX_NAME='uq_bh_repo_edge_deployment_property_code');
SET @bh_sql := IF(@bh_has_edge_deployments=1 AND @bh_has_uq=0,'CREATE UNIQUE INDEX uq_bh_repo_edge_deployment_property_code ON bh_repo_edge_deployments (property_code,edge_code)','SELECT 1');
PREPARE bh_stmt FROM @bh_sql;
EXECUTE bh_stmt;
DEALLOCATE PREPARE bh_stmt;

SET FOREIGN_KEY_CHECKS=1;
