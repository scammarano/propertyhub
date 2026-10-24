# Property Hub 0.14.25 — Collapsible Digital Twin + Edge Media Watchdog

## Mapa jerárquico

- Todas las ramas comienzan cerradas.
- La flecha abre/cierra el nivel inmediato.
- El control de rama expande/colapsa todos sus descendientes.
- Existen acciones globales **Expandir todo** y **Colapsar todo**.
- Los badges de edificio/piso/unidad se calculan sobre toda la rama y resumen áreas, cámaras, accesos, IoT, personas, vehículos y ascensores visibles para el rol actual.

## Media Watchdog

El Property Edge Agent supervisa el flujo local de video cuando el Node usa `edge_direct_webrtc`:

1. valida MediaMTX;
2. lo reinicia si está detenido;
3. prueba los endpoints públicos WHEP y HLS;
4. acepta 401/404 como evidencia de que Cloudflare sí alcanzó el origen protegido;
5. considera DNS/conexión fallida o HTTP 5xx (incluido 530) como túnel muerto;
6. genera un nuevo Quick Tunnel sólo para la salida afectada;
7. reporta las URLs actuales al Node inmediatamente;
8. refresca el reporte aun cuando todo esté sano para evitar endpoints históricos.

El supervisor se ejecuta desde el worker Edge cada ~30 segundos. `tools\install-edge-command-worker.bat`, ejecutado una sola vez como Administrador, instala ese worker como tarea `ONSTART` bajo SYSTEM. Si no existe la tarea, abrir el Dashboard Edge inicia el worker y el watchdog como recuperación de último recurso.

## Prueba de reinicio

Después de actualizar el Edge:

1. confirme en Dashboard que **Autoarranque Windows** figure configurado; si no, ejecute `tools\install-edge-command-worker.bat` como Administrador una sola vez;
2. reinicie Windows;
3. espere 30–60 segundos;
4. abra Dashboard Edge y confirme MediaMTX, WHEP y HLS en línea;
5. desde Node pruebe una cámara en vivo;
6. revise `storage/logs/runtime-AAAA-MM-DD.jsonl` buscando `edge_media_watchdog`.

Los Quick Tunnels continúan siendo una facilidad de laboratorio sin garantía de uptime de Cloudflare. El watchdog elimina la intervención manual durante pruebas, pero producción debe migrar a Named Tunnel/hostname estable o relay/TURN administrado.
