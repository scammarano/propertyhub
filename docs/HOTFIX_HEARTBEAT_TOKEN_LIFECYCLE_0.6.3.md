# Property Hub 0.6.3 — Heartbeat y ciclo de vida de credenciales

## Problemas corregidos

### Heartbeat HTTP 404

El Property Node construía una ruta fija para el heartbeat. Según la URL guardada en `CENTRAL_REPOSITORY_URL` y la configuración de Apache, esa ruta podía apuntar a un recurso inexistente.

La 0.6.3 normaliza la URL y prueba en orden:

1. `/public/api/repository/v1/heartbeat.php`
2. `/public/index.php?route=repository-api-heartbeat`

La misma estrategia se utiliza para `ping`, registro y manifiestos. El nodo conserva la URL canónica que respondió correctamente.

### Token perdido

Los tokens de registro se guardan como SHA-256. Eso impide recuperar su texto original, incluso desde la base de datos. Esta conducta es intencional.

La solución no es borrar la instalación. Property Hub ofrece dos reemplazos:

- **Regenerar token inicial:** para un expediente que todavía no completó el registro.
- **Generar token de reconexión:** para un Property Node ya registrado.

## Flujo para una instalación todavía no registrada

1. Entrar en Property Hub Central.
2. Abrir `Red de instalaciones → Instalaciones y conjuntos`.
3. En la sección **En preparación**, pulsar `Regenerar token`.
4. El token anterior queda revocado.
5. Copiar el nuevo token, visible una sola vez.
6. Completar el instalador o el registro del Property Node.

El expediente, nombre, código, tecnologías previstas y configuración central se conservan.

## Flujo para una instalación ya registrada

1. Abrir la ficha de la instalación en Property Hub Central.
2. Pulsar `Generar token de reconexión`.
3. Copiar el token, válido por 72 horas y un solo uso.
4. En el Property Node abrir `Sistema local → Vínculo con Property Hub Central`.
5. Pegar la URL del Central y el token.
6. Pulsar `Registrar o renovar vínculo`.
7. Enviar el heartbeat.

El Central actualiza las credenciales del mismo registro. Conserva:

- ID y ficha central.
- Código de instalación.
- Métricas históricas.
- Inventario de conectores.
- Registros de entrega.
- Configuración de privacidad.
- Plan de instalación asociado.

El nodo anterior deja de autenticarse si sus credenciales ya no coinciden. Esto evita dos nodos activos usando la misma identidad.

## Revocación

Los tokens activos pueden revocarse desde:

- `Versiones y distribución → Tokens de instalación`.
- La ficha de una instalación, para tokens de reconexión.

Revocar un token no elimina el Property Node ni su expediente.

## Orden de actualización

Actualizar primero el Central, porque incorpora la migración y los endpoints nuevos:

```bat
tools\migrate.bat
```

Después actualizar el Property Node y ejecutar el mismo comando. Conservar siempre:

```text
.env
storage/
```

## Migración

```text
0011_registration_token_lifecycle.sql
```

Agrega propósito, instalación asociada, reemplazo, revocación y auditoría de tokens, además de la fecha de última rotación de credenciales.
