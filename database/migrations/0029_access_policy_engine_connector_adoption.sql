-- Property Hub 0.14.7 · Perfiles de acceso, motor de políticas y adopción de plataformas externas.
-- Property Hub es el maestro de negocio. Los fabricantes son destinos de descubrimiento/provisión.

ALTER TABLE bh_property_memberships
  ADD COLUMN IF NOT EXISTS access_profile_id BIGINT UNSIGNED NULL AFTER relationship_type;

CREATE INDEX IF NOT EXISTS ix_bh_membership_access_profile ON bh_property_memberships (access_profile_id);

CREATE TABLE IF NOT EXISTS bh_access_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  scope_property_id BIGINT UNSIGNED NULL,
  parent_profile_id BIGINT UNSIGNED NULL,
  code VARCHAR(80) NOT NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  relationship_hint VARCHAR(40) NULL,
  can_invite TINYINT(1) NOT NULL DEFAULT 0,
  can_reserve TINYINT(1) NOT NULL DEFAULT 0,
  can_view_notifications TINYINT(1) NOT NULL DEFAULT 1,
  can_view_documents TINYINT(1) NOT NULL DEFAULT 0,
  can_use_domotics TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_access_profile_code (installation_id,code),
  KEY ix_bh_access_profile_scope (installation_id,scope_property_id,status),
  CONSTRAINT fk_bh_access_profile_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_access_profile_scope_property FOREIGN KEY (scope_property_id) REFERENCES bh_properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_access_profile_parent FOREIGN KEY (parent_profile_id) REFERENCES bh_access_profiles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bh_property_memberships
  ADD CONSTRAINT fk_bh_membership_access_profile FOREIGN KEY (access_profile_id) REFERENCES bh_access_profiles(id) ON DELETE SET NULL;

ALTER TABLE bh_cameras
  ADD COLUMN IF NOT EXISTS access_scope ENUM('common','property','tower','floor','unit','custom') NOT NULL DEFAULT 'common' AFTER resident_visibility,
  ADD COLUMN IF NOT EXISTS access_scope_property_id BIGINT UNSIGNED NULL AFTER access_scope,
  ADD COLUMN IF NOT EXISTS access_scope_group VARCHAR(100) NULL AFTER access_scope_property_id;

ALTER TABLE bh_access_points
  ADD COLUMN IF NOT EXISTS access_scope ENUM('common','property','tower','floor','unit','custom') NOT NULL DEFAULT 'common' AFTER remote_policy,
  ADD COLUMN IF NOT EXISTS access_scope_property_id BIGINT UNSIGNED NULL AFTER access_scope,
  ADD COLUMN IF NOT EXISTS access_scope_group VARCHAR(100) NULL AFTER access_scope_property_id;

CREATE TABLE IF NOT EXISTS bh_access_profile_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  profile_id BIGINT UNSIGNED NOT NULL,
  resource_domain ENUM('camera','access','reservation_area','notification','domotic') NOT NULL,
  scope_type ENUM('all','common','property','tower','floor','unit','custom','specific') NOT NULL DEFAULT 'common',
  resource_id BIGINT UNSIGNED NULL,
  scope_group VARCHAR(100) NULL,
  effect ENUM('allow','deny') NOT NULL DEFAULT 'allow',
  days_of_week VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5,6,7',
  start_time TIME NOT NULL DEFAULT '00:00:00',
  end_time TIME NOT NULL DEFAULT '23:59:59',
  priority SMALLINT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_bh_profile_rule_profile (profile_id,resource_domain,priority),
  KEY ix_bh_profile_rule_resource (resource_domain,resource_id),
  CONSTRAINT fk_bh_profile_rule_profile FOREIGN KEY (profile_id) REFERENCES bh_access_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventario leído desde fabricantes. No concede autoridad: sirve para aprendizaje/adopción y conciliación.
CREATE TABLE IF NOT EXISTS bh_external_objects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  connector_instance_id BIGINT UNSIGNED NOT NULL,
  object_type ENUM('device','door','reader','department','person','card','access_level','vehicle','credential','other') NOT NULL,
  external_id VARCHAR(190) NOT NULL,
  external_code VARCHAR(190) NULL,
  name VARCHAR(255) NULL,
  status VARCHAR(40) NULL,
  payload_json MEDIUMTEXT NULL,
  discovered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  adopted_at DATETIME NULL,
  adoption_status ENUM('discovered','reviewed','adopted','ignored','conflict') NOT NULL DEFAULT 'discovered',
  UNIQUE KEY uq_bh_external_object (connector_instance_id,object_type,external_id),
  KEY ix_bh_external_object_type (installation_id,object_type,adoption_status),
  CONSTRAINT fk_bh_external_object_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_external_object_connector FOREIGN KEY (connector_instance_id) REFERENCES bh_connector_instances(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_external_mappings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  connector_instance_id BIGINT UNSIGNED NOT NULL,
  local_entity_type ENUM('person','credential','vehicle','access_point','schedule','profile','device','camera','other') NOT NULL,
  local_entity_id BIGINT UNSIGNED NOT NULL,
  external_object_type VARCHAR(40) NOT NULL,
  external_id VARCHAR(190) NOT NULL,
  external_name VARCHAR(255) NULL,
  authority ENUM('property_hub','external','shared') NOT NULL DEFAULT 'property_hub',
  sync_status ENUM('pending','synced','warning','error','disabled') NOT NULL DEFAULT 'pending',
  last_synced_at DATETIME NULL,
  last_error TEXT NULL,
  UNIQUE KEY uq_bh_external_mapping_local (connector_instance_id,local_entity_type,local_entity_id),
  UNIQUE KEY uq_bh_external_mapping_external (connector_instance_id,external_object_type,external_id),
  CONSTRAINT fk_bh_external_mapping_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_external_mapping_connector FOREIGN KEY (connector_instance_id) REFERENCES bh_connector_instances(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de horarios compilados. Evita crear un horario por persona en el fabricante.
CREATE TABLE IF NOT EXISTS bh_vendor_schedule_bindings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  connector_instance_id BIGINT UNSIGNED NOT NULL,
  schedule_hash CHAR(64) NOT NULL,
  schedule_name VARCHAR(160) NOT NULL,
  days_of_week VARCHAR(20) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  external_id VARCHAR(190) NULL,
  external_name VARCHAR(255) NULL,
  sync_status ENUM('pending','synced','manual_mapping_required','error') NOT NULL DEFAULT 'pending',
  last_error TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_vendor_schedule_hash (connector_instance_id,schedule_hash),
  CONSTRAINT fk_bh_vendor_schedule_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_vendor_schedule_connector FOREIGN KEY (connector_instance_id) REFERENCES bh_connector_instances(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_policy_sync_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  connector_instance_id BIGINT UNSIGNED NULL,
  entity_type VARCHAR(40) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  operation VARCHAR(80) NOT NULL,
  payload_json MEDIUMTEXT NULL,
  status ENUM('pending','running','synced','warning','error','cancelled') NOT NULL DEFAULT 'pending',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  KEY ix_bh_policy_sync_queue (installation_id,status,created_at),
  CONSTRAINT fk_bh_policy_sync_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_policy_sync_connector FOREIGN KEY (connector_instance_id) REFERENCES bh_connector_instances(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Perfiles iniciales reutilizables por instalación.
INSERT IGNORE INTO bh_access_profiles (installation_id,code,name,description,relationship_hint,can_invite,can_reserve,can_view_notifications,can_view_documents,can_use_domotics)
SELECT id,'OWNER','Propietario','Perfil base de propietario.','owner',1,1,1,1,1 FROM bh_installations;
INSERT IGNORE INTO bh_access_profiles (installation_id,code,name,description,relationship_hint,can_invite,can_reserve,can_view_notifications,can_view_documents,can_use_domotics)
SELECT id,'TENANT','Inquilino','Perfil base de inquilino.','tenant',1,1,1,1,1 FROM bh_installations;
INSERT IGNORE INTO bh_access_profiles (installation_id,code,name,description,relationship_hint,can_invite,can_reserve,can_view_notifications,can_view_documents,can_use_domotics)
SELECT id,'RESIDENT','Residente','Perfil base de residente.','resident',1,1,1,0,1 FROM bh_installations;
INSERT IGNORE INTO bh_access_profiles (installation_id,code,name,description,relationship_hint,can_invite,can_reserve,can_view_notifications,can_view_documents,can_use_domotics)
SELECT id,'FAMILY','Familiar','Perfil base de familiar.','family',0,0,1,0,0 FROM bh_installations;
INSERT IGNORE INTO bh_access_profiles (installation_id,code,name,description,relationship_hint,can_invite,can_reserve,can_view_notifications,can_view_documents,can_use_domotics)
SELECT id,'DOMESTIC','Empleado doméstico','Perfil base de personal doméstico.','domestic_staff',0,0,1,0,0 FROM bh_installations;

-- Reglas base: cámaras y accesos comunes. Los horarios se ajustan desde Property Hub.
INSERT INTO bh_access_profile_rules (profile_id,resource_domain,scope_type,effect,days_of_week,start_time,end_time,priority)
SELECT p.id,'camera','common','allow','1,2,3,4,5,6,7','00:00:00','23:59:59',100 FROM bh_access_profiles p
WHERE p.code IN ('OWNER','TENANT','RESIDENT','FAMILY') AND NOT EXISTS (SELECT 1 FROM bh_access_profile_rules r WHERE r.profile_id=p.id AND r.resource_domain='camera' AND r.scope_type='common');
INSERT INTO bh_access_profile_rules (profile_id,resource_domain,scope_type,effect,days_of_week,start_time,end_time,priority)
SELECT p.id,'access','common','allow','1,2,3,4,5,6,7','00:00:00','23:59:59',100 FROM bh_access_profiles p
WHERE p.code IN ('OWNER','TENANT','RESIDENT','FAMILY') AND NOT EXISTS (SELECT 1 FROM bh_access_profile_rules r WHERE r.profile_id=p.id AND r.resource_domain='access' AND r.scope_type='common');
INSERT INTO bh_access_profile_rules (profile_id,resource_domain,scope_type,effect,days_of_week,start_time,end_time,priority)
SELECT p.id,'access','common','allow','1,2,3,4,5,6','06:00:00','20:00:00',100 FROM bh_access_profiles p
WHERE p.code='DOMESTIC' AND NOT EXISTS (SELECT 1 FROM bh_access_profile_rules r WHERE r.profile_id=p.id AND r.resource_domain='access' AND r.scope_type='common');

-- Adopta automáticamente el perfil base según la relación existente, sin borrar excepciones antiguas.
UPDATE bh_property_memberships m
JOIN bh_properties pr ON pr.id=m.property_id
JOIN bh_access_profiles p ON p.installation_id=pr.installation_id AND p.scope_property_id IS NULL
SET m.access_profile_id=p.id
WHERE m.access_profile_id IS NULL AND p.code=CASE m.relationship_type
  WHEN 'owner' THEN 'OWNER' WHEN 'tenant' THEN 'TENANT' WHEN 'resident' THEN 'RESIDENT'
  WHEN 'family' THEN 'FAMILY' WHEN 'domestic_staff' THEN 'DOMESTIC' ELSE 'RESIDENT' END;

-- Vínculo universal entre el punto funcional de Property Hub y el actuador real.
-- Para el usuario sigue siendo una puerta/portón/ascensor; el binding decide cómo ejecutarlo.
CREATE TABLE IF NOT EXISTS bh_access_point_bindings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  access_point_id BIGINT UNSIGNED NOT NULL,
  connector_instance_id BIGINT UNSIGNED NULL,
  driver_key VARCHAR(80) NOT NULL,
  actuator_type VARCHAR(80) NOT NULL,
  external_id VARCHAR(190) NOT NULL,
  external_name VARCHAR(255) NULL,
  execution_mode ENUM('provisioned','realtime','hybrid') NOT NULL DEFAULT 'realtime',
  pulse_seconds DECIMAL(6,2) NULL,
  metadata_json MEDIUMTEXT NULL,
  status ENUM('active','inactive','error') NOT NULL DEFAULT 'active',
  last_test_at DATETIME NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_access_point_binding (access_point_id,driver_key,external_id),
  KEY ix_bh_access_point_binding_connector (connector_instance_id,status),
  CONSTRAINT fk_bh_ap_binding_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_ap_binding_access_point FOREIGN KEY (access_point_id) REFERENCES bh_access_points(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_ap_binding_connector FOREIGN KEY (connector_instance_id) REFERENCES bh_connector_instances(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
