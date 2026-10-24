# Property Hub — GitHub development model

## Canonical repository

Repository: `scammarano/propertyhub`

The `main` branch is the source of truth. The first canonical GitHub baseline is Property Hub **0.14.65 — Camera Access Contextual Controls**.

## Branch model

Use short-lived branches:

- `feat/<scope>` for new capabilities.
- `fix/<scope>` for corrections.
- `hotfix/<scope>` only for urgent production corrections.

Changes are integrated into `main` through Pull Requests.

## Version and migration rules

1. Published SQL migrations are append-only.
2. Do not edit the bytes of an already published migration.
3. Published Core/component releases are immutable.
4. If any distributed byte changes, publish a new version.
5. Keep compatibility requirements explicit in the package manifest.

## Source vs distribution artifacts

Git tracks source code, migrations, configuration templates, documentation and CI.

Git does **not** track:

- `.env` or secrets.
- `storage/**`, logs or runtime state.
- `*.phbackup`.
- generated `.propertyhub-deploy` files.
- generated Core/connector/add-on ZIP files under `resources/releases`.

Distribution artifacts belong in the release/update pipeline and are addressed by component code, version, SHA-256 and signature.

## Pull Request gate

Before merge:

1. PHP syntax validation must pass.
2. JSON syntax validation must pass.
3. Repository hygiene checks must pass.
4. New migrations must be additive and preserve previous migration checksums.
5. Release/package metadata must match the intended version and target roles.
6. Security-sensitive changes require explicit review of authorization, enrollment and credential handling.

## Release flow

The normal sequence is:

`feature/fix branch → Pull Request → CI → merge to main → build immutable package → validate/sign → publish to Property Hub Central`.

The repository is the development source; Property Hub Central remains the runtime distribution authority for Nodes, Edge Agents and connector artifacts.
