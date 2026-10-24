-- Property Hub 0.14.6 · verified email, password recovery and granular resident permissions.
ALTER TABLE bh_local_users
  ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER status;

-- Preserve access for accounts that were already active before email verification existed.
UPDATE bh_local_users SET email_verified_at=COALESCE(email_verified_at,NOW()) WHERE status='active';

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

CREATE TABLE IF NOT EXISTS bh_membership_camera_grants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  membership_id BIGINT UNSIGNED NOT NULL,
  camera_id BIGINT UNSIGNED NOT NULL,
  can_view TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_membership_camera (membership_id,camera_id),
  KEY ix_bh_camera_grant_camera (camera_id),
  CONSTRAINT fk_bh_camera_grant_membership FOREIGN KEY (membership_id) REFERENCES bh_property_memberships(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_camera_grant_camera FOREIGN KEY (camera_id) REFERENCES bh_cameras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_membership_access_grants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  membership_id BIGINT UNSIGNED NOT NULL,
  access_point_id BIGINT UNSIGNED NOT NULL,
  can_operate TINYINT(1) NOT NULL DEFAULT 1,
  days_of_week VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5,6,7',
  start_time TIME NOT NULL DEFAULT '00:00:00',
  end_time TIME NOT NULL DEFAULT '23:59:59',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_membership_access (membership_id,access_point_id),
  KEY ix_bh_access_grant_access (access_point_id),
  CONSTRAINT fk_bh_access_grant_membership FOREIGN KEY (membership_id) REFERENCES bh_property_memberships(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_access_grant_access FOREIGN KEY (access_point_id) REFERENCES bh_access_points(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
