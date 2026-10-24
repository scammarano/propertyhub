-- Property Hub 0.14.52 · Component Registry & immutable release lifecycle.
-- Central gains a permanent component identity table. Versions remain in bh_repo_releases,
-- but every release is linked by component_id and channels are assignments over the same artifact.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS bh_repo_components (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  component_code VARCHAR(120) NOT NULL,
  component_type ENUM('core','ui','module','connector','api','bridge','video_gateway','language','report','other') NOT NULL,
  display_name VARCHAR(190) NOT NULL,
  connector_code VARCHAR(80) NULL,
  vendor_code VARCHAR(120) NULL,
  lifecycle_status ENUM('active','deprecated','retired') NOT NULL DEFAULT 'active',
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_repo_component_code (component_code),
  UNIQUE KEY uq_bh_repo_component_connector (connector_code),
  KEY ix_bh_repo_component_type (component_type,lifecycle_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bh_repo_component_aliases (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  component_id BIGINT UNSIGNED NOT NULL,
  alias_code VARCHAR(120) NOT NULL,
  alias_type ENUM('component_code','connector_code','legacy','vendor') NOT NULL DEFAULT 'legacy',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bh_repo_component_alias (alias_code),
  KEY ix_bh_repo_component_alias_component (component_id),
  CONSTRAINT fk_bh_repo_component_alias_component FOREIGN KEY (component_id) REFERENCES bh_repo_components(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bh_repo_releases
  ADD COLUMN IF NOT EXISTS component_id BIGINT UNSIGNED NULL AFTER id,
  ADD COLUMN IF NOT EXISTS artifact_locked_at DATETIME NULL AFTER signature,
  ADD COLUMN IF NOT EXISTS package_metadata_json JSON NULL AFTER release_notes;

INSERT INTO bh_repo_components (component_code,component_type,display_name,connector_code,lifecycle_status,metadata_json)
SELECT r.component_code,
       SUBSTRING_INDEX(GROUP_CONCAT(r.component_type ORDER BY r.id DESC),',',1),
       SUBSTRING_INDEX(GROUP_CONCAT(r.display_name ORDER BY r.id DESC SEPARATOR '\n'), '\n', 1),
       CASE WHEN SUBSTRING_INDEX(GROUP_CONCAT(r.component_type ORDER BY r.id DESC),',',1)='connector' AND r.component_code LIKE 'connector-%'
            THEN SUBSTRING(r.component_code,11) ELSE NULL END,
       'active',JSON_OBJECT('source','release_backfill')
FROM bh_repo_releases r
GROUP BY r.component_code
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),updated_at=NOW();

-- Canonicalize legacy duplicates before enforcing uniqueness. Older versions of the
-- publisher could create more than one row for the same component/version because the
-- channel/random release_code was part of the identity. Keep the newest published row
-- (or newest row when none is published) and preserve every duplicate as archived audit
-- evidence with component_id=NULL. New writes can no longer reproduce this state.
DROP TEMPORARY TABLE IF EXISTS ph0042_release_keep;
CREATE TEMPORARY TABLE ph0042_release_keep AS
SELECT component_code,version,
       COALESCE(MAX(CASE WHEN status='published' THEN id END),MAX(id)) keep_id
FROM bh_repo_releases
GROUP BY component_code,version;

UPDATE bh_repo_releases r
JOIN ph0042_release_keep k ON k.keep_id=r.id
JOIN bh_repo_components c ON c.component_code=r.component_code
SET r.component_id=c.id,
    r.artifact_locked_at=COALESCE(r.artifact_locked_at,r.published_at,r.created_at)
WHERE r.component_id IS NULL;

UPDATE bh_repo_releases r
JOIN ph0042_release_keep k ON k.component_code=r.component_code AND k.version=r.version AND k.keep_id<>r.id
SET r.package_metadata_json=JSON_OBJECT(
      'source','0042_legacy_duplicate_normalization',
      'canonical_release_id',k.keep_id,
      'legacy_release_code',r.release_code,
      'legacy_sha256',r.package_hash_sha256
    ),
    r.artifact_locked_at=COALESCE(r.artifact_locked_at,r.published_at,r.created_at),
    r.component_id=NULL,
    r.status='archived';

DROP TEMPORARY TABLE IF EXISTS ph0042_release_keep;

-- Enforce the permanent identity invariant at database level: one semantic version
-- belongs to one canonical component exactly once, regardless of publication channel.
CREATE UNIQUE INDEX IF NOT EXISTS uq_bh_repo_release_component_version ON bh_repo_releases (component_id,version);

INSERT INTO bh_repo_component_aliases (component_id,alias_code,alias_type)
SELECT id,component_code,'component_code' FROM bh_repo_components
ON DUPLICATE KEY UPDATE component_id=VALUES(component_id),alias_type=VALUES(alias_type);

INSERT INTO bh_repo_component_aliases (component_id,alias_code,alias_type)
SELECT id,connector_code,'connector_code' FROM bh_repo_components WHERE connector_code IS NOT NULL AND connector_code<>''
ON DUPLICATE KEY UPDATE component_id=VALUES(component_id),alias_type=VALUES(alias_type);

CREATE TABLE IF NOT EXISTS bh_repo_release_channels (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  release_id BIGINT UNSIGNED NOT NULL,
  channel ENUM('development','beta','stable','pinned') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  promoted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  promoted_by BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_bh_repo_release_channel (release_id,channel),
  KEY ix_bh_repo_release_channel_lookup (channel,active,release_id),
  CONSTRAINT fk_bh_repo_release_channel_release FOREIGN KEY (release_id) REFERENCES bh_repo_releases(id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_repo_release_channel_user FOREIGN KEY (promoted_by) REFERENCES bh_local_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO bh_repo_release_channels (release_id,channel,active,promoted_at,promoted_by)
SELECT id,channel,1,COALESCE(published_at,created_at),created_by FROM bh_repo_releases
WHERE status='published'
ON DUPLICATE KEY UPDATE active=1;

ALTER TABLE bh_repo_release_dependencies
  ADD COLUMN IF NOT EXISTS dependency_component_id BIGINT UNSIGNED NULL AFTER release_id;
CREATE INDEX IF NOT EXISTS ix_bh_repo_dependency_component ON bh_repo_release_dependencies (dependency_component_id);

UPDATE bh_repo_release_dependencies d
JOIN bh_repo_components c ON c.component_code=d.dependency_component_code
SET d.dependency_component_id=c.id
WHERE d.dependency_component_id IS NULL;

-- Existing repository tables intentionally keep component_id nullable for upgrade safety.
-- Canonical writes always populate it and the UNIQUE index above is the hard invariant for
-- all new releases. The new alias/channel tables carry their own foreign keys.

SET FOREIGN_KEY_CHECKS=1;
