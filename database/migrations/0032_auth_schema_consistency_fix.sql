-- Property Hub 0.14.10 · Auth schema consistency guard.
-- Repairs installations where 0.14.9 files became active while the 0.14.6
-- authentication schema was not yet present (for example after a manual/cPanel
-- file replacement or an interrupted cumulative update).
ALTER TABLE bh_local_users
  ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER status;

-- Accounts that already existed and were active before email verification
-- must remain usable after the column is introduced.
UPDATE bh_local_users
SET email_verified_at = COALESCE(email_verified_at, NOW())
WHERE status = 'active';

CREATE TABLE IF NOT EXISTS bh_password_reset_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  local_user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_password_reset_token (token_hash),
  KEY ix_bh_password_reset_user (installation_id,local_user_id,expires_at),
  CONSTRAINT fk_bh_password_reset_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_password_reset_user FOREIGN KEY (local_user_id) REFERENCES bh_local_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_email_verification_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  local_user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_email_verification_token (token_hash),
  KEY ix_bh_email_verification_user (installation_id,local_user_id,expires_at),
  CONSTRAINT fk_bh_email_verification_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_email_verification_user FOREIGN KEY (local_user_id) REFERENCES bh_local_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
