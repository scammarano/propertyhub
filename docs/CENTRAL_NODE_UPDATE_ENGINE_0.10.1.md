# Property Hub 0.10.1 — Motor Central → Node

## Problema corregido

Hasta 0.10.0 existían el repositorio, firma de manifiestos, descarga API y tablas de seguimiento, pero faltaban dos piezas operativas:

1. instalar una versión en el Central no la publicaba automáticamente como release para nodos;
2. los nodos 0.9.3/0.10.0 solo consultaban y contaban el manifiesto, sin comparar, descargar ni instalar.

0.10.1 completa ese ciclo.

## Flujo

```text
Paquete oficial 0.10.1 en Property Hub Central
        ↓
resources/releases/property-hub-core-0.10.1.zip
        ↓
Publicación firmada en bh_repo_releases (stable)
        ↓
Property Node → Buscar actualizaciones
        ↓
Manifiesto firmado + comparación semántica
        ↓
Actualizar ahora
        ↓
Descarga autenticada
        ↓
SHA-256
        ↓
Snapshot rollback + PRE_UPDATE cuando está disponible
        ↓
Archivos + migraciones
        ↓
Health check
        ↓
Instalado / rollback automático
```

## Seguridad

Nunca se reemplazan desde un paquete:

- `.env`
- `storage/keys`
- `storage/uploads`
- `storage/backups`
- `storage/logs`
- `storage/repository`
- `storage/updates/rollback`
- `storage/installed.lock`

Los paquetes requieren `update-package.json` y el hash debe coincidir con el manifiesto firmado por el Central.

## Nodo 0.9.3

0.9.3 no contiene el instalador de paquetes. Para romper ese círculo existe `PROPERTY_HUB_UPDATE_BOOTSTRAP_V1.0.0_FOR_0.9.3.zip`. Es el último hotfix que debe copiarse manualmente al nodo. No cambia la versión de Property Hub; solamente instala Update Engine 1.0.0.

Después del hotfix:

1. abrir **Sistema local → Actualizaciones**;
2. pulsar **Buscar actualizaciones**;
3. debe aparecer Property Hub Core 0.10.1;
4. pulsar **Actualizar ahora**.

## Central

El Central no consulta su propio repositorio. El paquete oficial 0.10.1 se instala manualmente en el Central, se ejecuta `tools/migrate.bat` y ese proceso publica automáticamente el Core distribuible para nodos.
