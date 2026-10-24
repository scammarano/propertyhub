# ZKBio API Matrix — Property Hub 0.14.16

## Objetivo

0.14.16 convierte el ZKBio API Lab del Property Edge en un laboratorio de compatibilidad exhaustivo basado en el manual oficial **ZKBio CVSecurity 3rd Party API User Manual v1.2 (November 2025), software 6.0.0+**.

El catálogo incorporado contiene las **220 definiciones de endpoint** detectadas en el manual, incluidas variantes V1/V2 cuando existen, junto con método, ruta, query documentado, contenido/parametrización del request, respuesta documentada, descripción de respuesta y el apéndice de códigos de error.

## RUN completo

El botón **RUN diagnóstico completo seguro** crea una corrida persistente y procesa los casos uno por uno. Cada caso genera su propio JSON. Al finalizar se crea un ZIP con:

- `catalog.json`: catálogo documental completo.
- `state.json`: estado final y fixtures técnicos obtenidos de lecturas reales.
- `responses/*.json`: request saneado, respuesta observada y contrato documentado de cada caso.
- `summary.json`.
- `summary.csv`.
- `README.md`.

La corrida puede reanudarse si el navegador se cierra o la ejecución se interrumpe.

## Seguridad del RUN

El RUN automático es **read-only**. Todos los endpoints están presentes, pero Property Edge no autoejecuta:

- altas/ediciones/eliminaciones;
- asignaciones de permisos;
- sincronizaciones que modifican equipos;
- aperturas/cierres físicos;
- cobros/pagos;
- endpoints cuyo riesgo no esté confirmado.

Esos casos quedan en el ZIP como `SKIPPED_MUTATING`, `SKIPPED_PHYSICAL_ACTION`, `SKIPPED_FINANCIAL_ACTION`, `SKIPPED_UNKNOWN_RISK` o `SKIPPED_MISSING_FIXTURE`, según corresponda. Pueden probarse luego manualmente con confirmación explícita.

## Compatibilidad observada en CVSecurity 6.7.1

La instalación Premier demostró el 21-08-2026:

- `GET /api/person/get/{pin}`: correcto.
- `POST /api/person/getPersonList` con JSON body: error funcional 400.
- `POST /api/person/getPersonList` con `pins`, `deptCodes`, `pageNo`, `pageSize` en query y body vacío: **correcto**.
- `POST /api/v2/person/getPersonList` con JSON body: error funcional 400.

Por ello Person List incorpora casos `documented_body` y `query_only` para que la matriz detecte automáticamente qué variante acepta cada instalación.

## Saneamiento

El `access_token` nunca se incluye en el ZIP. También se redactan campos de password/hash/secret (`selfPwd`, `personPwd`, etc.) y blobs grandes de foto/Base64 antes de persistir respuestas de laboratorio.

## Provisioning Node → Edge

Desde 0.14.16 las integraciones locales dejan de aparecer automáticamente en el Agent.

La instalación debe declarar primero la plataforma en:

**Property Node → Infraestructura y tecnología → Sistemas conectados**

Cuando una instancia ZKTeco/ZKBio está en estado operativo/configurado, el Node la incluye en la configuración del Agent. El Edge entonces habilita **ZKBio API Lab**. Si se marca `Planificado` o `Deshabilitado`, el laboratorio deja de exponerse.

El mismo contrato se aplica a Vimar: la tecnología debe declararse primero en el Node y luego el Agent habilita su configuración local. Las credenciales LAN permanecen exclusivamente en Edge.

## Modelo

- Node decide **qué integraciones pertenecen a la propiedad**.
- Edge conserva **cómo conectarse localmente** a esas integraciones.
- Central administra versiones/instalaciones, no los secretos locales.
