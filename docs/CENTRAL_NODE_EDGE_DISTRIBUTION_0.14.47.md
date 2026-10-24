# Property Hub 0.14.47 — Central → Node → Edge Distribution

## Objetivo

Hacer que la distribución de versiones respete la jerarquía operativa del producto:

```text
Property Hub Central
        ↓
Property Node
        ↓
Property Edge Agent
```

El Edge no necesita ni conserva credenciales de actualización de Central. Su única fuente inmediata es el Property Node al que está emparejado.

## Flujo de manifiesto

1. El Edge solicita `api/edge/v1/update-manifest.php` a su Node con su credencial PHEDGE.
2. El Node se autentica frente a Central con su propia identidad y solicita un manifiesto para `downstream_role=edge_agent`.
3. Central genera y firma RSA-SHA256 el manifiesto para el rol Edge.
4. El Node no reemplaza la firma. Adjunta una allowlist separada con los releases permitidos para las integraciones habilitadas en esa propiedad.
5. El Edge valida la firma con la clave pública de Central provisionada por el Node y aplica además la allowlist del Node.

## Flujo de paquete

1. El Edge solicita el `release_code` al endpoint `api/edge/v1/update-package.php` de su Node.
2. El Node vuelve a validar que ese release siga autorizado.
3. Si no está en caché, el Node descarga el ZIP autenticado desde Central.
4. El Node valida el SHA-256 firmado y guarda el ZIP en `storage/updates/node-distribution-cache/`.
5. El Edge descarga desde el Node, valida SHA-256 y luego ejecuta el Update Engine normal con respaldo/rollback.

## Conectores

Los releases tipo `connector` se filtran en el Node según sus integraciones activas. Ejemplos de canonicalización:

- Vimar → `vimar`
- ZKBio / ZKTeco → `zkteco`
- ONVIF / RTSP / Hikvision / Dahua → `onvif`
- UniFi → `unifi`

El Core puede seguir siendo común a todos los roles; la activación local de laboratorios/drivers se controla por el estado de `Sistemas conectados`.

## Compatibilidad de transición

Central conserva sus endpoints históricos para que un Edge anterior pueda recibir la versión de transición. Una vez actualizado y emparejado/refrescado, 0.14.47 retira la credencial directa de Central y usa exclusivamente su Node para las siguientes actualizaciones.

## Instalaciones nuevas

El instalador ya no graba versiones históricas fijas. Lee `VERSION.json` como fuente de verdad para `bh_installations`, `bh_installed_components` y `storage/installed.lock`.

No se agrega migración SQL en 0.14.47. Las migraciones `0001` a `0040` permanecen intactas.

## Registro y transición

- Central acepta registro directo únicamente de `building_node`; un Edge nuevo se empareja con `PHEDGE` en su Property Node.
- Los Edge históricos pueden recibir la transición desde su flujo anterior; al emparejar/refrescar 0.14.47 se retiran las credenciales directas de Central.
- El Node conserva una autorización de distribución firmada por Central por Agent. Si Central cae temporalmente, el Node puede servir un ZIP **ya cacheado** cuyo SHA-256 coincida y que siga permitido por la política actual de conectores. No descarga ni resucita releases retirados estando Central disponible.
