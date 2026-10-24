-- 2CAM Property Hub 0.6.1
-- Distingue plataformas/servidores conectados, equipos administrados por ellas y conexiones directas.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE bh_connector_instances
  ADD COLUMN IF NOT EXISTS target_type ENUM('platform_server','cloud_platform','protocol_service','direct_device','technology_gateway','other') NOT NULL DEFAULT 'platform_server' AFTER name,
  ADD COLUMN IF NOT EXISTS manages_downstream_assets TINYINT(1) NOT NULL DEFAULT 1 AFTER target_type,
  ADD COLUMN IF NOT EXISTS direct_device_policy ENUM('not_applicable','forbidden','optional','required') NOT NULL DEFAULT 'not_applicable' AFTER manages_downstream_assets,
  ADD COLUMN IF NOT EXISTS platform_product VARCHAR(160) NULL AFTER direct_device_policy,
  ADD COLUMN IF NOT EXISTS platform_version VARCHAR(80) NULL AFTER platform_product;

ALTER TABLE bh_devices
  ADD COLUMN IF NOT EXISTS asset_role ENUM('managed_device','direct_device','platform_server','technology_gateway','reference_asset') NOT NULL DEFAULT 'managed_device' AFTER connector_instance_id,
  ADD COLUMN IF NOT EXISTS connection_path ENUM('via_platform','direct','vendor_cloud','technology_gateway','manual_reference') NOT NULL DEFAULT 'via_platform' AFTER asset_role,
  ADD COLUMN IF NOT EXISTS connection_notes VARCHAR(255) NULL AFTER connection_path;

UPDATE bh_connector_definitions
SET name='ZKTeco ZKBioSecurity Server',
    vendor='ZKTeco',
    domain='access',
    status='research'
WHERE code IN ('zkteco','zkteco_biosecurity');

UPDATE bh_connector_instances ci
JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id
SET ci.target_type='platform_server',
    ci.manages_downstream_assets=1,
    ci.direct_device_policy='forbidden',
    ci.platform_product=COALESCE(NULLIF(ci.platform_product,''),'ZKBioSecurity')
WHERE cd.code IN ('zkteco','zkteco_biosecurity');

UPDATE bh_devices d
JOIN bh_connector_instances ci ON ci.id=d.connector_instance_id
JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id
SET d.asset_role='managed_device',
    d.connection_path='via_platform',
    d.direct_to_core=0,
    d.managed_by=COALESCE(NULLIF(d.managed_by,''),ci.name),
    d.connection_notes=COALESCE(NULLIF(d.connection_notes,''),'Property Hub no se conecta directamente a este equipo; lo administra la plataforma enlazada.')
WHERE cd.code IN ('zkteco','zkteco_biosecurity');

SET FOREIGN_KEY_CHECKS=1;
