# Hotfix 0.11.6 — Deferred Health Contract Fix

## Incidente observado

Durante la instalación de 0.11.5 el payload se aplicaba, pero el health check abortaba con:

`Health check falló: falta storage/updates/deferred/update-finalize.json.`

La causa era contractual: 0.11.5 declaró como `health_files` dos archivos de `storage/updates/deferred/`. Esos archivos no pertenecen al paquete. Solo se crean en tiempo de ejecución en Windows/XAMPP cuando `public/index.php` no puede sustituirse dentro de la misma solicitud. En Linux/cPanel no deben existir.

## Corrección

- Se retiran los artefactos runtime de `health_files`.
- Se conserva `public/index.php` como archivo real del Core.
- Central valida que los health files existan en el ZIP y prohíbe `storage/updates/deferred/*` en el contrato.
- El mecanismo Windows de `public/update-finalize.php` permanece intacto.
- No hay migraciones SQL nuevas.
