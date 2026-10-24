# Hotfix 0.10.8 — compatibilidad del actualizador

## Problema reproducido

Un Property Node 0.10.4 puede descubrir y descargar correctamente el Core publicado por Central, pero el paquete 0.10.7 contenía `.env.example`. El Update Bootstrap 1.0.0 protege `.env` y cualquier `.env.*`, por lo que la instalación se detenía antes de aplicar archivos.

La excepción original podía quedar oculta por el `ErrorReporter` antiguo del nodo, que no aceptaba contexto estructurado.

## Correcciones

1. El ZIP distribuible del Core excluye `.env.example` y toda ruta `.env.*`.
2. El Central valida rutas sin eliminar el punto inicial del nombre.
3. La validación contempla una carpeta raíz opcional del ZIP.
4. El actualizador nuevo reporta errores con un wrapper fail-safe para que el logger nunca sustituya la excepción original.
5. No se requiere modificación manual del nodo 0.10.4 antes de actualizar.

## Criterio de aceptación

Una instalación 0.10.4 original debe poder: descubrir 0.10.8, descargarla, validar firma/SHA/ZIP, crear rollback y PRE_UPDATE, aplicar archivos, ejecutar migraciones ya registradas/pendientes, superar health checks y quedar registrada como 0.10.8.
