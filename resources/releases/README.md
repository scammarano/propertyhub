# Release artifacts

This directory is intentionally kept free of generated ZIP packages in Git.

Property Hub Core, connector and add-on ZIP files are **immutable distribution artifacts**, not source files. They are built from source, validated, signed and published through the release/update pipeline.

Rules:

- Do not commit generated `*.zip` releases here.
- Never replace the bytes of a version that was already published.
- A byte change requires a new component version.
- Runtime release caches belong to the installation, not to source control.
