-- Property Hub 0.14.50 · Vimar Universal Connector direct LAN discovery.
-- Property Edge discovers Vimar/VIEW/By-me/KNX gateways itself; VimarHub remains optional.

ALTER TABLE bh_vimar_edge_config
  MODIFY COLUMN integration_mode ENUM('direct_auto','vimarhub_bridge','home_assistant','view_direct','byme_direct') NOT NULL DEFAULT 'direct_auto',
  ADD COLUMN IF NOT EXISTS local_ip VARCHAR(45) NULL AFTER inventory_path,
  ADD COLUMN IF NOT EXISTS discovery_seconds TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER local_ip;

-- Migrate only the old default localhost bridge when it never established a contract.
-- Explicit remote/custom VimarHub installations remain untouched.
UPDATE bh_vimar_edge_config
SET integration_mode='direct_auto',
    base_url=NULL,
    inventory_path=NULL,
    status=CASE WHEN enabled=1 THEN 'configured' ELSE 'disabled' END,
    last_error=NULL
WHERE integration_mode='vimarhub_bridge'
  AND (base_url IS NULL OR base_url='' OR base_url='http://127.0.0.1:47821')
  AND (last_endpoint IS NULL OR last_endpoint='');
