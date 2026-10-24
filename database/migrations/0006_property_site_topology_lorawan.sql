-- 2CAM Property Hub 0.5.0
-- Topología territorial, conectividad distribuida y LoRaWAN sin nodos de cómputo adicionales.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE bh_installations
  MODIFY COLUMN installation_type ENUM(
    'residential','urbanization','residential_complex','office','office_building',
    'commercial','shopping_center','mixed','campus','industrial','other'
  ) NOT NULL DEFAULT 'mixed';

ALTER TABLE bh_properties
  MODIFY COLUMN property_type ENUM(
    'site','urbanization','sector','street','block','parcel','building','tower','floor',
    'apartment','house','townhouse','office','commercial_unit','warehouse','parking',
    'guard_post','park','common','technical','other'
  ) NOT NULL;

ALTER TABLE bh_connector_instances
  MODIFY COLUMN status ENUM('configured','online','offline','warning','error','disabled','planned') NOT NULL DEFAULT 'configured';

CREATE TABLE IF NOT EXISTS bh_site_locations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  code VARCHAR(60) NOT NULL,
  name VARCHAR(160) NOT NULL,
  location_type ENUM(
    'main_site','headquarters','sector','street','block','parcel','building','tower','floor',
    'unit','guard_post','park','common_area','technical_area','parking','perimeter','remote_point','other'
  ) NOT NULL DEFAULT 'other',
  is_operational_center TINYINT(1) NOT NULL DEFAULT 0,
  connectivity_mode ENUM('lan','fiber','utp','wifi','radio','vpn','internet','cellular','lorawan','mixed','none') NOT NULL DEFAULT 'lan',
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  status ENUM('online','warning','offline','planned','maintenance','unknown') NOT NULL DEFAULT 'unknown',
  notes TEXT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_site_location_code (installation_id,code),
  KEY ix_bh_site_location_parent (parent_id),
  KEY ix_bh_site_location_type (installation_id,location_type,status),
  CONSTRAINT fk_bh_site_location_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_site_location_parent FOREIGN KEY (parent_id) REFERENCES bh_site_locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_connectivity_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  from_location_id BIGINT UNSIGNED NULL,
  to_location_id BIGINT UNSIGNED NULL,
  code VARCHAR(60) NOT NULL,
  name VARCHAR(160) NOT NULL,
  link_type ENUM('fiber','utp','wifi','radio','vpn','internet','cellular','lorawan','mixed','other') NOT NULL,
  link_role ENUM('primary','backup','independent','iot') NOT NULL DEFAULT 'primary',
  provider VARCHAR(160) NULL,
  capacity_label VARCHAR(80) NULL,
  status ENUM('online','warning','offline','planned','maintenance','unknown') NOT NULL DEFAULT 'unknown',
  monitored TINYINT(1) NOT NULL DEFAULT 1,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_connectivity_link_code (installation_id,code),
  KEY ix_bh_connectivity_status (installation_id,status),
  CONSTRAINT fk_bh_connectivity_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_connectivity_from_location FOREIGN KEY (from_location_id) REFERENCES bh_site_locations(id),
  CONSTRAINT fk_bh_connectivity_to_location FOREIGN KEY (to_location_id) REFERENCES bh_site_locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bh_devices
  ADD COLUMN IF NOT EXISTS site_location_id BIGINT UNSIGNED NULL AFTER installation_id,
  ADD COLUMN IF NOT EXISTS connectivity_mode VARCHAR(40) NULL AFTER managed_by,
  ADD COLUMN IF NOT EXISTS direct_to_core TINYINT(1) NOT NULL DEFAULT 1 AFTER connectivity_mode;

CREATE INDEX IF NOT EXISTS ix_bh_device_site_location ON bh_devices (site_location_id);

CREATE TABLE IF NOT EXISTS bh_lorawan_networks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(60) NOT NULL,
  name VARCHAR(160) NOT NULL,
  network_server ENUM('chirpstack','the_things_stack','vendor_cloud','mqtt_generic','webhook_generic','other') NOT NULL DEFAULT 'chirpstack',
  deployment_mode ENUM('local','cloud','hybrid') NOT NULL DEFAULT 'local',
  endpoint_url VARCHAR(500) NULL,
  region_code VARCHAR(40) NULL,
  status ENUM('online','warning','offline','configured','planned','unknown') NOT NULL DEFAULT 'configured',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_lorawan_network_code (installation_id,code),
  CONSTRAINT fk_bh_lorawan_network_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_lorawan_gateways (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  network_id BIGINT UNSIGNED NOT NULL,
  site_location_id BIGINT UNSIGNED NULL,
  gateway_eui VARCHAR(32) NOT NULL,
  name VARCHAR(160) NOT NULL,
  backhaul_type ENUM('ethernet','wifi','cellular','fiber','other') NOT NULL DEFAULT 'ethernet',
  ip_address VARCHAR(64) NULL,
  status ENUM('online','warning','offline','planned','unknown') NOT NULL DEFAULT 'unknown',
  last_seen_at DATETIME NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_lorawan_gateway_eui (installation_id,gateway_eui),
  CONSTRAINT fk_bh_lorawan_gateway_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_lorawan_gateway_network FOREIGN KEY (network_id) REFERENCES bh_lorawan_networks(id),
  CONSTRAINT fk_bh_lorawan_gateway_location FOREIGN KEY (site_location_id) REFERENCES bh_site_locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_lorawan_devices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  network_id BIGINT UNSIGNED NOT NULL,
  site_location_id BIGINT UNSIGNED NULL,
  dev_eui VARCHAR(32) NOT NULL,
  name VARCHAR(160) NOT NULL,
  device_type VARCHAR(80) NOT NULL,
  manufacturer VARCHAR(120) NULL,
  model VARCHAR(120) NULL,
  battery_percent TINYINT UNSIGNED NULL,
  last_value VARCHAR(190) NULL,
  last_value_unit VARCHAR(30) NULL,
  rssi DECIMAL(8,2) NULL,
  snr DECIMAL(8,2) NULL,
  status ENUM('online','warning','offline','planned','unknown') NOT NULL DEFAULT 'unknown',
  last_seen_at DATETIME NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_lorawan_device_eui (installation_id,dev_eui),
  KEY ix_bh_lorawan_device_status (installation_id,status,device_type),
  CONSTRAINT fk_bh_lorawan_device_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id),
  CONSTRAINT fk_bh_lorawan_device_network FOREIGN KEY (network_id) REFERENCES bh_lorawan_networks(id),
  CONSTRAINT fk_bh_lorawan_device_location FOREIGN KEY (site_location_id) REFERENCES bh_site_locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_iot_automation_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installation_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(60) NOT NULL,
  name VARCHAR(160) NOT NULL,
  source_type ENUM('lorawan_device','device','connector','schedule','manual') NOT NULL,
  source_reference VARCHAR(120) NULL,
  condition_json JSON NULL,
  action_json JSON NULL,
  priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('active','paused','draft','error') NOT NULL DEFAULT 'draft',
  last_triggered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_iot_rule_code (installation_id,code),
  CONSTRAINT fk_bh_iot_rule_installation FOREIGN KEY (installation_id) REFERENCES bh_installations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
