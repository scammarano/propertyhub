-- 2CAM Property Hub 0.6.3
-- Ciclo de vida seguro para tokens de registro y reconexión sin borrar instalaciones.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE bh_repo_registration_tokens
  ADD COLUMN token_purpose ENUM('initial_registration','relink') NOT NULL DEFAULT 'initial_registration' AFTER token_name,
  ADD COLUMN installation_id BIGINT UNSIGNED NULL AFTER allowed_installation_code,
  ADD COLUMN replaced_token_id BIGINT UNSIGNED NULL AFTER installation_id,
  ADD COLUMN revoked_at DATETIME NULL AFTER last_used_at,
  ADD COLUMN revoked_by BIGINT UNSIGNED NULL AFTER revoked_at,
  ADD COLUMN revocation_reason VARCHAR(255) NULL AFTER revoked_by,
  ADD KEY ix_bh_repo_token_installation (installation_id,token_purpose,status),
  ADD CONSTRAINT fk_bh_repo_token_installation FOREIGN KEY (installation_id) REFERENCES bh_repo_installations(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_bh_repo_token_replaced FOREIGN KEY (replaced_token_id) REFERENCES bh_repo_registration_tokens(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_bh_repo_token_revoked_by FOREIGN KEY (revoked_by) REFERENCES bh_local_users(id) ON DELETE SET NULL;

ALTER TABLE bh_repo_installations
  ADD COLUMN last_credentials_rotated_at DATETIME NULL AFTER last_manifest_at;

ALTER TABLE bh_repo_delivery_logs
  MODIFY COLUMN event_type ENUM(
    'registered','registration_token_reissued','credentials_rotated','heartbeat',
    'manifest_requested','package_requested','download_started','download_completed',
    'install_reported','rollback_reported','error'
  ) NOT NULL;

SET FOREIGN_KEY_CHECKS=1;
