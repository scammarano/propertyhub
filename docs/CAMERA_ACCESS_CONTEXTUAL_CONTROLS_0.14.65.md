# Property Hub 0.14.65 — Camera Access Contextual Controls

## Objetivo

Permitir que un operador o residente autorizado abra una puerta/acceso directamente desde el visor de una cámara asociada, sin cerrar, recargar ni abandonar el video. Así el flujo operativo puede ser **ver → identificar → abrir → observar el paso/cierre** dentro de la misma vista.

## Modelo de relación

Se incorpora `bh_camera_access_links` como relación explícita many-to-many:

- una cámara puede controlar/contextualizar varias puertas;
- una puerta puede tener varias cámaras asociadas;
- la relación se administra desde **Editar cámara** o **Editar puerta/acceso**;
- compartir Área/Espacio no crea una relación implícita: la asociación debe ser explícita.

La tabla conserva además `viewer_enabled` y `display_order` para que el modelo pueda evolucionar sin depender del fabricante del acceso o de la cámara.

## Visor de cámara

Los visores administrativo y residente consultan los accesos asociados a la cámara abierta. Cuando existen relaciones, muestran una barra **Accesos asociados** dentro del mismo modal del video.

Al pulsar **Abrir**:

1. se conserva el streaming/snapshot actual;
2. se valida de nuevo la sesión, permiso y relación cámara↔acceso;
3. se valida que el acceso esté autorizado para el contexto actual;
4. se valida política remota y una ruta de ejecución compatible;
5. se encola la apertura mediante el bridge operacional;
6. el visor consulta el estado del comando y muestra confirmado/error sin cerrar el video.

## Seguridad y permisos

La visualización y la operación continúan separadas:

- `cameras.view` permite ver la cámara;
- `access.operate` permite operar accesos como operador;
- `access.operate.allowed` limita residentes a accesos efectivos para su unidad/perfil.

Para residentes, la lista Camera↔Access se intersecta con `ResidentRepository::accessPoints()`, por lo que una asociación administrativa no concede por sí sola acceso a una puerta.

La apertura inmediata sólo se habilita cuando:

- hay una ruta de ejecución activa;
- el ejecutor contextual está implementado (en esta versión ZKBio/ZKTeco);
- la política remota es `allowed` o `local_only`.

Políticas como segundo factor o aprobación se muestran deshabilitadas con su motivo; no se omiten silenciosamente ni se eluden.

## Auditoría

Las aperturas desde el visor generan auditoría `camera.access.open` con:

- cámara origen;
- acceso operado;
- usuario/persona;
- correlation UUID;
- resultado del bridge operacional.

Además, el comando normal de acceso conserva su trazabilidad en `bh_access_commands` / `bh_access_events`.

## Migración

`0046_camera_access_contextual_controls.sql` crea `bh_camera_access_links` con claves foráneas y unicidad por instalación/cámara/acceso. No modifica migraciones anteriores.

## Empaquetado del Core

El Core Node/Edge no contiene `resources/releases/**`. 0.14.65 alinea también el manifiesto embebido y elimina esas rutas repository-only de `health_files`, evitando que un health check Node/Edge dependa del vault histórico exclusivo de Central.
