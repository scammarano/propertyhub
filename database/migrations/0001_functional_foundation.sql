-- 2CAM Building Hub 0.3.0
-- Base funcional inicial para MariaDB 10.4+ / MySQL 8+
-- No ejecutar sobre una instalación productiva sin respaldo.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS bh_installations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_uuid CHAR(36) NOT NULL UNIQUE,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  installation_type ENUM('residential','office','commercial','mixed','campus','other') NOT NULL DEFAULT 'mixed',
  timezone VARCHAR(64) NOT NULL DEFAULT 'America/Caracas',
  status ENUM('active','maintenance','suspended','retired') NOT NULL DEFAULT 'active',
  update_channel ENUM('development','beta','stable','pinned') NOT NULL DEFAULT 'stable',
  app_version VARCHAR(30) NOT NULL DEFAULT '0.3.0',
  api_version VARCHAR(20) NOT NULL DEFAULT 'v1',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_organizations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  organization_type ENUM('condominium','company','tenant_company','security_company','service_provider','other') NOT NULL,
  legal_name VARCHAR(180) NOT NULL,
  trade_name VARCHAR(180) NULL,
  tax_id VARCHAR(60) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_org_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_properties (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  property_type ENUM('building','tower','floor','apartment','office','commercial_unit','house','warehouse','parking','common','other') NOT NULL,
  code VARCHAR(60) NOT NULL,
  name VARCHAR(160) NOT NULL,
  floor_label VARCHAR(30) NULL,
  status ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_property_code (installation_id,code),
  KEY ix_bh_property_parent (parent_id),
  CONSTRAINT fk_bh_property_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_property_parent FOREIGN KEY (parent_id) REFERENCES bh_properties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_people (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  public_uuid CHAR(36) NOT NULL UNIQUE,
  person_kind ENUM('natural','organization_contact') NOT NULL DEFAULT 'natural',
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  display_name VARCHAR(210) NOT NULL,
  document_type VARCHAR(30) NULL,
  document_number VARCHAR(60) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(50) NULL,
  photo_path VARCHAR(255) NULL,
  status ENUM('active','inactive','blocked','deceased') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_person_document (installation_id,document_type,document_number),
  KEY ix_bh_person_name (installation_id,display_name),
  CONSTRAINT fk_bh_person_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_person_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id BIGINT UNSIGNED NOT NULL,
  document_category VARCHAR(50) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NULL,
  expires_at DATE NULL,
  encrypted TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_person_document_person FOREIGN KEY (person_id) REFERENCES bh_people(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_global_user_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  global_user_uuid CHAR(36) NOT NULL,
  local_person_id BIGINT UNSIGNED NOT NULL,
  membership_status ENUM('invited','pending','approved','suspended','revoked') NOT NULL DEFAULT 'pending',
  linked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_global_link (installation_id,global_user_uuid),
  CONSTRAINT fk_bh_global_link_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_global_link_person FOREIGN KEY (local_person_id) REFERENCES bh_people(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_local_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NULL,
  email VARCHAR(190) NULL,
  username VARCHAR(100) NULL,
  password_hash VARCHAR(255) NULL,
  user_type ENUM('superadmin','admin','security','operator','resident','service') NOT NULL,
  status ENUM('active','pending','blocked','revoked') NOT NULL DEFAULT 'pending',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_local_user_email (installation_id,email),
  UNIQUE KEY uq_bh_local_user_name (installation_id,username),
  CONSTRAINT fk_bh_local_user_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_local_user_person FOREIGN KEY (person_id) REFERENCES bh_people(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_user_identities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  global_user_uuid CHAR(36) NOT NULL,
  provider ENUM('google','apple','microsoft','local','other') NOT NULL,
  provider_subject VARCHAR(255) NOT NULL,
  email VARCHAR(190) NULL,
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_provider_subject (provider,provider_subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_property_memberships (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  property_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NOT NULL,
  relationship_type ENUM('owner','tenant','resident','family','representative','employee','domestic_staff','driver','security','maintenance','authorized','other') NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  can_invite TINYINT(1) NOT NULL DEFAULT 0,
  can_reserve TINYINT(1) NOT NULL DEFAULT 0,
  can_view_cameras TINYINT(1) NOT NULL DEFAULT 0,
  can_operate_access TINYINT(1) NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  status ENUM('active','pending','expired','revoked') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_bh_membership_property (property_id,status),
  KEY ix_bh_membership_person (person_id,status),
  CONSTRAINT fk_bh_membership_property FOREIGN KEY (property_id) REFERENCES bh_properties(id),
  CONSTRAINT fk_bh_membership_person FOREIGN KEY (person_id) REFERENCES bh_people(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_areas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  property_id BIGINT UNSIGNED NULL,
  code VARCHAR(60) NOT NULL,
  name VARCHAR(160) NOT NULL,
  area_type ENUM('common','reservable','security','technical','commercial','office','parking','circulation','other') NOT NULL,
  is_reservable TINYINT(1) NOT NULL DEFAULT 0,
  is_security_zone TINYINT(1) NOT NULL DEFAULT 0,
  capacity INT UNSIGNED NULL,
  opening_time TIME NULL,
  closing_time TIME NULL,
  base_rate DECIMAL(14,2) NOT NULL DEFAULT 0,
  security_deposit DECIMAL(14,2) NOT NULL DEFAULT 0,
  requires_approval TINYINT(1) NOT NULL DEFAULT 1,
  minimum_minutes INT UNSIGNED NOT NULL DEFAULT 60,
  maximum_minutes INT UNSIGNED NULL,
  advance_days INT UNSIGNED NOT NULL DEFAULT 90,
  cancellation_hours INT UNSIGNED NOT NULL DEFAULT 24,
  rules_text TEXT NULL,
  status ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_area_code (installation_id,code),
  CONSTRAINT fk_bh_area_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_area_property FOREIGN KEY (property_id) REFERENCES bh_properties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_area_blackouts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  area_id BIGINT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  reason VARCHAR(255) NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_blackout_area FOREIGN KEY (area_id) REFERENCES bh_areas(id),
  CONSTRAINT fk_bh_blackout_user FOREIGN KEY (created_by) REFERENCES bh_local_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_uuid CHAR(36) NOT NULL UNIQUE,
  installation_id BIGINT UNSIGNED NOT NULL,
  area_id BIGINT UNSIGNED NOT NULL,
  property_id BIGINT UNSIGNED NOT NULL,
  requester_person_id BIGINT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  purpose VARCHAR(255) NOT NULL,
  attendees INT UNSIGNED NOT NULL DEFAULT 1,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  deposit_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  payment_status ENUM('not_required','pending','paid','refunded','forfeited') NOT NULL DEFAULT 'not_required',
  status ENUM('draft','pending','approved','rejected','cancelled','completed','no_show') NOT NULL DEFAULT 'pending',
  approved_by BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL,
  rejection_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_bh_reservation_calendar (area_id,starts_at,ends_at,status),
  KEY ix_bh_reservation_requester (requester_person_id,status),
  CONSTRAINT fk_bh_reservation_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_reservation_area FOREIGN KEY (area_id) REFERENCES bh_areas(id),
  CONSTRAINT fk_bh_reservation_property FOREIGN KEY (property_id) REFERENCES bh_properties(id),
  CONSTRAINT fk_bh_reservation_requester FOREIGN KEY (requester_person_id) REFERENCES bh_people(id),
  CONSTRAINT fk_bh_reservation_approver FOREIGN KEY (approved_by) REFERENCES bh_local_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_reservation_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reservation_id BIGINT UNSIGNED NOT NULL,
  previous_status VARCHAR(30) NULL,
  new_status VARCHAR(30) NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_reservation_history_reservation FOREIGN KEY (reservation_id) REFERENCES bh_reservations(id),
  CONSTRAINT fk_bh_reservation_history_user FOREIGN KEY (changed_by) REFERENCES bh_local_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_bulletins (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  category ENUM('general','community','services','maintenance','security','emergency','commercial','office','other') NOT NULL DEFAULT 'general',
  audience_type ENUM('all','residents','commercial','office','security','custom') NOT NULL DEFAULT 'all',
  audience_json JSON NULL,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
  published_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_bulletin_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_bulletin_user FOREIGN KEY (created_by) REFERENCES bh_local_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_notification_campaigns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  audience_type ENUM('all','residents','commercial','office','security','property','custom') NOT NULL DEFAULT 'all',
  audience_json JSON NULL,
  channels_json JSON NOT NULL,
  scheduled_at DATETIME NULL,
  sent_at DATETIME NULL,
  status ENUM('draft','scheduled','sending','sent','partial','failed','cancelled') NOT NULL DEFAULT 'draft',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_campaign_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_campaign_user FOREIGN KEY (created_by) REFERENCES bh_local_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id BIGINT UNSIGNED NULL,
  global_user_uuid CHAR(36) NULL,
  local_user_id BIGINT UNSIGNED NULL,
  property_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  deep_link VARCHAR(255) NULL,
  priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  status ENUM('queued','delivered','read','failed','dismissed') NOT NULL DEFAULT 'queued',
  delivered_at DATETIME NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_bh_notification_user (global_user_uuid,status),
  CONSTRAINT fk_bh_notification_campaign FOREIGN KEY (campaign_id) REFERENCES bh_notification_campaigns(id),
  CONSTRAINT fk_bh_notification_local_user FOREIGN KEY (local_user_id) REFERENCES bh_local_users(id),
  CONSTRAINT fk_bh_notification_property FOREIGN KEY (property_id) REFERENCES bh_properties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_notification_deliveries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notification_id BIGINT UNSIGNED NOT NULL,
  channel ENUM('in_app','push','email','whatsapp','sms','voice','paging') NOT NULL,
  provider VARCHAR(80) NULL,
  provider_message_id VARCHAR(160) NULL,
  status ENUM('queued','sent','delivered','read','failed') NOT NULL DEFAULT 'queued',
  error_message VARCHAR(500) NULL,
  attempted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_delivery_notification FOREIGN KEY (notification_id) REFERENCES bh_notifications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_emergency_contacts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  category ENUM('security','administration','fire','medical','police','elevator','electricity','water','gas','other') NOT NULL,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(60) NOT NULL,
  availability VARCHAR(100) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_visible_to_residents TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  CONSTRAINT fk_bh_emergency_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_invitations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_uuid CHAR(36) NOT NULL UNIQUE,
  installation_id BIGINT UNSIGNED NOT NULL,
  property_id BIGINT UNSIGNED NOT NULL,
  host_person_id BIGINT UNSIGNED NOT NULL,
  visitor_name VARCHAR(180) NOT NULL,
  visitor_document VARCHAR(80) NULL,
  visitor_phone VARCHAR(50) NULL,
  vehicle_plate VARCHAR(30) NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  access_code VARCHAR(80) NOT NULL,
  qr_payload TEXT NULL,
  status ENUM('scheduled','active','used','expired','cancelled','rejected') NOT NULL DEFAULT 'scheduled',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_invitation_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_invitation_property FOREIGN KEY (property_id) REFERENCES bh_properties(id),
  CONSTRAINT fk_bh_invitation_host FOREIGN KEY (host_person_id) REFERENCES bh_people(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_visits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invitation_id BIGINT UNSIGNED NULL,
  installation_id BIGINT UNSIGNED NOT NULL,
  property_id BIGINT UNSIGNED NOT NULL,
  visitor_person_id BIGINT UNSIGNED NULL,
  visitor_name VARCHAR(180) NOT NULL,
  host_person_id BIGINT UNSIGNED NULL,
  check_in_at DATETIME NULL,
  check_out_at DATETIME NULL,
  temporary_credential_id BIGINT UNSIGNED NULL,
  status ENUM('expected','waiting','inside','completed','denied','no_show') NOT NULL DEFAULT 'expected',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_visit_invitation FOREIGN KEY (invitation_id) REFERENCES bh_invitations(id),
  CONSTRAINT fk_bh_visit_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_visit_property FOREIGN KEY (property_id) REFERENCES bh_properties(id),
  CONSTRAINT fk_bh_visit_person FOREIGN KEY (visitor_person_id) REFERENCES bh_people(id),
  CONSTRAINT fk_bh_visit_host FOREIGN KEY (host_person_id) REFERENCES bh_people(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_credentials (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  person_id BIGINT UNSIGNED NULL,
  property_id BIGINT UNSIGNED NULL,
  credential_type ENUM('rfid','vehicle_tag','pin','qr','fingerprint','face','nfc','mobile','temporary_card','ttlock_ekey','wifi_voucher','other') NOT NULL,
  display_number VARCHAR(100) NULL,
  external_reference VARCHAR(255) NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  status ENUM('available','active','assigned','returned','expired','lost','blocked','revoked') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_credential_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_credential_person FOREIGN KEY (person_id) REFERENCES bh_people(id),
  CONSTRAINT fk_bh_credential_property FOREIGN KEY (property_id) REFERENCES bh_properties(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bh_visits ADD CONSTRAINT fk_bh_visit_credential FOREIGN KEY (temporary_credential_id) REFERENCES bh_credentials(id);

CREATE TABLE IF NOT EXISTS bh_connector_definitions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  vendor VARCHAR(100) NULL,
  domain ENUM('access','video','intercom','automation','network','identity','notification','other') NOT NULL,
  version VARCHAR(30) NOT NULL,
  capabilities_json JSON NOT NULL,
  status ENUM('planned','research','beta','stable','deprecated') NOT NULL DEFAULT 'planned',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_connector_instances (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  connector_definition_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  endpoint_url VARCHAR(255) NULL,
  configuration_encrypted MEDIUMTEXT NULL,
  status ENUM('configured','online','offline','error','disabled') NOT NULL DEFAULT 'configured',
  last_health_at DATETIME NULL,
  last_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_connector_instance_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_connector_instance_definition FOREIGN KEY (connector_definition_id) REFERENCES bh_connector_definitions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_devices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  connector_instance_id BIGINT UNSIGNED NULL,
  device_type ENUM('access_controller','reader','lock','relay','camera','nvr','intercom','phone','speaker','network_controller','access_point','gateway','sensor','other') NOT NULL,
  name VARCHAR(160) NOT NULL,
  vendor VARCHAR(100) NULL,
  model VARCHAR(100) NULL,
  serial_number VARCHAR(120) NULL,
  ip_address VARCHAR(64) NULL,
  external_id VARCHAR(255) NULL,
  capabilities_json JSON NULL,
  status ENUM('online','offline','maintenance','disabled','unknown') NOT NULL DEFAULT 'unknown',
  last_seen_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bh_device_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_device_connector FOREIGN KEY (connector_instance_id) REFERENCES bh_connector_instances(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_access_points (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  area_id BIGINT UNSIGNED NULL,
  device_id BIGINT UNSIGNED NULL,
  code VARCHAR(60) NOT NULL,
  name VARCHAR(160) NOT NULL,
  access_type ENUM('door','gate','turnstile','elevator','barrier','lock','relay','other') NOT NULL,
  operation_mode ENUM('credential','remote','hybrid','manual') NOT NULL DEFAULT 'hybrid',
  remote_policy ENUM('disabled','local_only','allowed','second_factor','approval_required') NOT NULL DEFAULT 'disabled',
  status ENUM('active','maintenance','disabled') NOT NULL DEFAULT 'active',
  UNIQUE KEY uq_bh_access_point_code (installation_id,code),
  CONSTRAINT fk_bh_access_point_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_access_point_area FOREIGN KEY (area_id) REFERENCES bh_areas(id),
  CONSTRAINT fk_bh_access_point_device FOREIGN KEY (device_id) REFERENCES bh_devices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_cameras (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  area_id BIGINT UNSIGNED NULL,
  device_id BIGINT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  stream_mode ENUM('onvif','rtsp','vendor_api','nvr_channel','protect','other') NOT NULL,
  stream_config_encrypted MEDIUMTEXT NULL,
  resident_visibility ENUM('none','all','property_scope','custom') NOT NULL DEFAULT 'none',
  allow_recordings TINYINT(1) NOT NULL DEFAULT 0,
  allow_audio TINYINT(1) NOT NULL DEFAULT 0,
  allow_ptz TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('online','offline','maintenance','disabled') NOT NULL DEFAULT 'offline',
  CONSTRAINT fk_bh_camera_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_camera_area FOREIGN KEY (area_id) REFERENCES bh_areas(id),
  CONSTRAINT fk_bh_camera_device FOREIGN KEY (device_id) REFERENCES bh_devices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_external_mappings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  connector_instance_id BIGINT UNSIGNED NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  local_entity_id BIGINT UNSIGNED NOT NULL,
  external_entity_id VARCHAR(255) NOT NULL,
  sync_status ENUM('pending','queued','sent','confirmed','error','conflict','revoked') NOT NULL DEFAULT 'pending',
  last_synced_at DATETIME NULL,
  last_error TEXT NULL,
  UNIQUE KEY uq_bh_external_mapping (connector_instance_id,entity_type,local_entity_id),
  KEY ix_bh_external_id (connector_instance_id,entity_type,external_entity_id),
  CONSTRAINT fk_bh_external_mapping_connector FOREIGN KEY (connector_instance_id) REFERENCES bh_connector_instances(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_wifi_vouchers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  property_id BIGINT UNSIGNED NULL,
  invitation_id BIGINT UNSIGNED NULL,
  connector_instance_id BIGINT UNSIGNED NULL,
  voucher_code VARCHAR(100) NOT NULL,
  starts_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  device_limit INT UNSIGNED NOT NULL DEFAULT 1,
  data_limit_mb INT UNSIGNED NULL,
  status ENUM('created','active','expired','revoked','error') NOT NULL DEFAULT 'created',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_voucher_code (installation_id,voucher_code),
  CONSTRAINT fk_bh_voucher_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_voucher_property FOREIGN KEY (property_id) REFERENCES bh_properties(id),
  CONSTRAINT fk_bh_voucher_invitation FOREIGN KEY (invitation_id) REFERENCES bh_invitations(id),
  CONSTRAINT fk_bh_voucher_connector FOREIGN KEY (connector_instance_id) REFERENCES bh_connector_instances(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  actor_global_user_uuid CHAR(36) NULL,
  actor_local_user_id BIGINT UNSIGNED NULL,
  actor_person_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id BIGINT UNSIGNED NULL,
  property_id BIGINT UNSIGNED NULL,
  area_id BIGINT UNSIGNED NULL,
  connector_instance_id BIGINT UNSIGNED NULL,
  result ENUM('success','denied','error','partial') NOT NULL DEFAULT 'success',
  reason VARCHAR(255) NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_bh_audit_date (installation_id,created_at),
  KEY ix_bh_audit_entity (entity_type,entity_id),
  CONSTRAINT fk_bh_audit_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_audit_local_user FOREIGN KEY (actor_local_user_id) REFERENCES bh_local_users(id),
  CONSTRAINT fk_bh_audit_person FOREIGN KEY (actor_person_id) REFERENCES bh_people(id),
  CONSTRAINT fk_bh_audit_property FOREIGN KEY (property_id) REFERENCES bh_properties(id),
  CONSTRAINT fk_bh_audit_area FOREIGN KEY (area_id) REFERENCES bh_areas(id),
  CONSTRAINT fk_bh_audit_connector FOREIGN KEY (connector_instance_id) REFERENCES bh_connector_instances(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_update_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  component VARCHAR(100) NOT NULL,
  previous_version VARCHAR(30) NULL,
  target_version VARCHAR(30) NOT NULL,
  channel VARCHAR(30) NOT NULL,
  package_hash VARCHAR(128) NULL,
  backup_path VARCHAR(255) NULL,
  status ENUM('downloaded','validated','installed','rolled_back','failed') NOT NULL,
  details TEXT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  CONSTRAINT fk_bh_update_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
