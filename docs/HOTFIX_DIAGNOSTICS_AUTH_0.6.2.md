# Property Hub 0.6.2 — Diagnóstico y autenticación

## Motivo

La versión 0.6.1 enviaba cualquier excepción producida durante `local-login` a la pantalla genérica de error 500. Esto incluía errores esperados como una contraseña incorrecta o una cuenta inactiva. La pantalla además afirmaba que había detenido una redirección, aun cuando el problema real podía ser autenticación, base de datos, esquema o PHP.

## Correcciones

### Autenticación

- Una contraseña incorrecta, usuario inexistente, campos vacíos o cuenta inactiva vuelven al login con un mensaje comprensible.
- Estos intentos quedan en `storage/logs/auth-AAAA-MM-DD.jsonl`.
- No se registran contraseñas.
- Un acceso correcto actualiza `last_login_at` y genera un evento `auth_success`.
- Un error real de PDO, SQL o PHP continúa como incidente 500 para no ocultarlo.

### Registro de incidentes

Cada solicitud recibe un `request_id`. Las excepciones generan además un número de incidente.

Archivos principales:

```text
storage/logs/property-hub-AAAA-MM-DD.log
storage/logs/incidents-AAAA-MM-DD.jsonl
storage/logs/runtime-AAAA-MM-DD.jsonl
storage/logs/auth-AAAA-MM-DD.jsonl
storage/logs/php-runtime.log
```

Los logs incluyen:

- Fecha y hora.
- Instalación y versión.
- Método, ruta y URI.
- Usuario enmascarado.
- IP y navegador.
- Clase de excepción.
- Mensaje.
- Archivo y línea relativos al proyecto.
- Traza completa.
- Cadena de excepciones previas.

Nunca incluyen contraseñas, tokens, cookies, claves privadas ni cabeceras de autorización.

### Panel protegido

Ruta:

```text
/public/diagnostics.php
```

Solo se abre:

- Desde `localhost`; o
- Con el token generado por `tools\enable-diagnostics.bat`.

Muestra:

- Versión de Property Hub y PHP.
- Extensiones necesarias.
- Permisos de escritura en logs.
- Conexión con MariaDB.
- Existencia y cantidad de usuarios locales.
- Estado de migraciones.
- Últimos incidentes y eventos de autenticación.

## Activación inmediata

En Windows/XAMPP:

```bat
tools\enable-diagnostics.bat
```

El comando imprime una URL protegida. Para revisar el último log en consola:

```bat
tools\show-latest-log.bat
```

Para buscar directamente un incidente:

```bat
tools\find-incident.bat 20260725151615-0052cdb3
```

Después de resolver el problema:

```bat
tools\disable-diagnostics.bat
```

Los errores críticos continúan registrándose aunque el panel remoto sea desactivado.

## Actualización

No existe migración nueva.

1. Respaldar archivos y base de datos.
2. Sustituir el código de 0.6.1 por 0.6.2.
3. Conservar `.env` y `storage/`.
4. Ejecutar `tools\migrate.bat` para actualizar metadatos a 0.6.2.
5. Intentar el login nuevamente.

## Incidente existente

Para buscar un incidente específico en Windows PowerShell:

```powershell
Select-String -Path .\storage\logs\*.log -Pattern "NUMERO-DE-INCIDENTE" -Context 2,30
```

Ejemplo:

```powershell
Select-String -Path .\storage\logs\*.log -Pattern "20260725151615-0052cdb3" -Context 2,30
```
