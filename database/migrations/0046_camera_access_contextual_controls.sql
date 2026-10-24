-- Property Hub 0.14.65 · Asociación Cámara ↔ Acceso y controles contextuales de apertura.
-- La relación es many-to-many y pertenece al modelo funcional de Property Hub,
-- independiente del fabricante que finalmente ejecute la apertura.

CREATE TABLE IF NOT EXISTS bh_camera_access_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  camera_id BIGINT UNSIGNED NOT NULL,
  access_point_id BIGINT UNSIGNED NOT NULL,
  viewer_enabled TINYINT(1) NOT NULL DEFAULT 1,
  display_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_camera_access_link (installation_id,camera_id,access_point_id),
  KEY ix_bh_camera_access_camera (installation_id,camera_id,viewer_enabled,display_order),
  KEY ix_bh_camera_access_access (installation_id,access_point_id),
  CONSTRAINT fk_bh_camera_access_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_camera_access_camera FOREIGN KEY (camera_id) REFERENCES bh_cameras(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_camera_access_access FOREIGN KEY (access_point_id) REFERENCES bh_access_points(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
