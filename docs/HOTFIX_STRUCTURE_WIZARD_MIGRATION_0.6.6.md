# Hotfix 0.6.6 — Migración del asistente de estructura

## Incidente corregido

Al abrir **Gestión de la propiedad → Asistente de estructura**, Property Hub podía mostrar:

```text
SQLSTATE[42S02]: Base table or view not found: 1146
Table '...bh_structure_wizard_runs' doesn't exist
```

El archivo `0012_structure_generation_wizard.sql` estaba dentro del paquete 0.6.5, pero no estaba agregado a tres listas de ejecución:

- Instalador web.
- Reparación y migraciones desde la aplicación.
- `tools/migrate.php` / `tools/migrate.bat`.

## Corrección

La versión 0.6.6 incluye `0012_structure_generation_wizard.sql` en las tres rutas. La pantalla de reparación ahora detecta la migración pendiente y no deja abrir el asistente hasta aplicarla.

## Actualización de Premier

1. Respaldar base de datos y archivos.
2. Sustituir los archivos por 0.6.6.
3. Conservar `.env` y `storage/`.
4. Ejecutar:

```bat
tools\migrate.bat
```

O abrir:

```text
public/index.php?route=system-repair
```

5. Confirmar que `0012_structure_generation_wizard.sql` aparezca como `applied`.
6. Volver a abrir el asistente.

## Verificación SQL opcional

```sql
SHOW TABLES LIKE 'bh_structure_wizard_runs';
SHOW TABLES LIKE 'bh_structure_wizard_items';
```

Ambas consultas deben devolver una tabla.

## Integridad

El contenido de `0012_structure_generation_wizard.sql` no fue modificado; únicamente se corrigió su incorporación a los flujos de instalación y migración.
