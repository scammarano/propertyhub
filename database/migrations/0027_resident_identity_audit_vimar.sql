-- Property Hub 0.14.6 · Resident identity, visible audit, presence, email invitations and Vimar lab.
ALTER TABLE bh_local_users
  ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL AFTER last_login_at,
  ADD COLUMN IF NOT EXISTS last_ip_address VARCHAR(64) NULL AFTER last_seen_at;

CREATE TABLE IF NOT EXISTS bh_account_invitations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NOT NULL,
  local_user_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  accepted_at DATETIME NULL,
  sent_at DATETIME NULL,
  status ENUM('pending','sent','accepted','expired','revoked','error') NOT NULL DEFAULT 'pending',
  last_error TEXT NULL,
  created_by_local_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_account_invite_token (token_hash),
  KEY ix_bh_account_invite_person (installation_id,person_id,status),
  CONSTRAINT fk_bh_account_invite_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_account_invite_person FOREIGN KEY (person_id) REFERENCES bh_people(id),
  CONSTRAINT fk_bh_account_invite_user FOREIGN KEY (local_user_id) REFERENCES bh_local_users(id),
  CONSTRAINT fk_bh_account_invite_creator FOREIGN KEY (created_by_local_user_id) REFERENCES bh_local_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_mail_settings (
  installation_id BIGINT UNSIGNED PRIMARY KEY,
  transport ENUM('smtp','php_mail') NOT NULL DEFAULT 'smtp',
  smtp_host VARCHAR(190) NULL,
  smtp_port INT UNSIGNED NOT NULL DEFAULT 587,
  smtp_security ENUM('none','tls','ssl') NOT NULL DEFAULT 'tls',
  smtp_username VARCHAR(190) NULL,
  smtp_secret_encrypted MEDIUMTEXT NULL,
  from_email VARCHAR(190) NULL,
  from_name VARCHAR(190) NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_mail_settings_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_vimar_edge_config (
  installation_id BIGINT UNSIGNED PRIMARY KEY,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  integration_mode ENUM('home_assistant','view_direct','byme_direct') NOT NULL DEFAULT 'home_assistant',
  base_url VARCHAR(500) NULL,
  secret_encrypted MEDIUMTEXT NULL,
  verify_tls TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('configured','online','offline','error','disabled') NOT NULL DEFAULT 'disabled',
  last_test_at DATETIME NULL,
  last_error TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_vimar_edge_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bh_audit_logs
  ADD KEY IF NOT EXISTS ix_bh_audit_actor_date (installation_id,actor_local_user_id,created_at),
  ADD KEY IF NOT EXISTS ix_bh_audit_property_date (installation_id,property_id,created_at),
  ADD KEY IF NOT EXISTS ix_bh_audit_action_date (installation_id,action,created_at);
