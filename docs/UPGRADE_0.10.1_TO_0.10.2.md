# Upgrade 0.10.1 → 0.10.2

1. Actualice **solo Property Hub Central** manualmente desde 0.10.1.
2. Conserve `.env` y `storage/`.
3. Ejecute `tools\migrate.bat` o `php tools/migrate.php`.
4. Debe publicarse automáticamente `property-hub-core-0.10.2-stable`.
5. En Premier, que ya tiene Update Bootstrap 1.0.0, pulse **Sistema local → Actualizaciones → Buscar actualizaciones**.
6. Premier 0.9.1 debe ver 0.10.2 y actualizarse desde Central, sin copiar la release al nodo.

No hay migraciones SQL nuevas respecto de 0.10.1. La migración 0016 sigue siendo la última.
