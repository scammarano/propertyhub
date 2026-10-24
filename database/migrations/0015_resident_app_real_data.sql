-- 2CAM Property Hub 0.9.0
-- Property App persistente y configuración de la experiencia móvil.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS bh_resident_app_settings (
  installation_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  app_title VARCHAR(160) NOT NULL,
  welcome_message VARCHAR(255) NULL,
  hero_image_url VARCHAR(500) NULL,
  theme_color CHAR(7) NOT NULL DEFAULT '#176b45',
  features_json JSON NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_resident_app_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_resident_app_user FOREIGN KEY (updated_by) REFERENCES bh_local_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS ix_bh_local_user_person_type ON bh_local_users (installation_id,person_id,user_type,status);
CREATE INDEX IF NOT EXISTS ix_bh_resident_reservations ON bh_reservations (installation_id,property_id,requester_person_id,status,starts_at);
CREATE INDEX IF NOT EXISTS ix_bh_resident_invitations ON bh_invitations (installation_id,property_id,host_person_id,status,starts_at);
CREATE INDEX IF NOT EXISTS ix_bh_resident_notifications ON bh_notifications (local_user_id,property_id,status,created_at);
CREATE INDEX IF NOT EXISTS ix_bh_resident_bulletins ON bh_bulletins (installation_id,status,audience_type,published_at,expires_at);

SET FOREIGN_KEY_CHECKS=1;
