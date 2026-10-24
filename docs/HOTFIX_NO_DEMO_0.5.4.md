# Property Hub 0.5.4 — corrección de datos demo

## Problema corregido

La 0.5.3 podía mostrar datos demostrativos guardados en sesión aunque el instalador hubiera generado `APP_DEMO_MODE=false`. La causa era que `DemoRepository::boot()` cargaba información temporal sin consultar la configuración. Además, algunas vistas incluían cifras y vouchers estáticos.

## Corrección

- `APP_DEMO_MODE` usa `false` como valor seguro por defecto.
- El repositorio demo solo funciona cuando el modo está expresamente activado.
- Las operaciones temporales se bloquean en instalaciones reales.
- Las vistas operativas muestran estados vacíos en lugar de información ficticia.
- Se añadió una limpieza segura de sesión y base de datos.

## Limpieza desde la web

Abra:

```text
/public/index.php?route=system-demo-cleanup
```

Confirme con el código de la instalación. La herramienta conserva:

- administrador local;
- `.env`;
- UUID y código de instalación;
- vínculo con Property Hub Central;
- migraciones;
- catálogo de conectores y APIs;
- registros reales que no coincidan con los identificadores oficiales del demo.

## Limpieza por consola

```bat
tools\clean-demo-data.bat PREMIER-LM
```

## Catálogos que deben permanecer

Los conectores ZKTeco, Hikvision, Dahua, TTLock, Grandstream, UniFi, ONVIF, RTSP, Vimar, Sonoff, LoRaWAN y relés genéricos forman parte del catálogo del producto. Su presencia no significa que estén configurados ni que exista información operativa ficticia.
