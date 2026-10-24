-- Property Hub 0.14.46 · VimarHub Local Bridge + site discovery metadata.
-- Non-Central roles only. Existing Vimar Home Assistant configuration remains valid.

ALTER TABLE bh_vimar_edge_config
  MODIFY COLUMN integration_mode ENUM('vimarhub_bridge','home_assistant','view_direct','byme_direct') NOT NULL DEFAULT 'vimarhub_bridge',
  ADD COLUMN IF NOT EXISTS inventory_path VARCHAR(500) NULL AFTER base_url,
  ADD COLUMN IF NOT EXISTS last_endpoint VARCHAR(500) NULL AFTER status,
  ADD COLUMN IF NOT EXISTS last_discovery_at DATETIME NULL AFTER last_test_at;
