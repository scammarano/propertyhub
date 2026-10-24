# Property Hub 0.14.61 — Automatic Sequential Update Batch

## Objetivo

Eliminar la operación manual de instalar cada Core intermedio por separado. La compatibilidad progresiva se conserva, pero el operador inicia una sola acción.

## Flujo

1. Node/Edge solicita el manifiesto firmado.
2. Central calcula la ruta completa compatible del Core.
3. El operador pulsa **Descargar todo y actualizar** una sola vez.
4. Property Hub descarga **todos** los ZIP de la ruta antes de modificar la instalación.
5. Cada ZIP se valida contra el SHA-256 incluido dentro del manifiesto RSA firmado.
6. Cuando todos están disponibles localmente, Property Hub instala un Core por solicitud HTTP, en el orden exacto de la ruta.
7. Cada salto conserva el rollback y PRE_UPDATE del Update Engine normal.
8. La pantalla se auto-reanuda después de cada salto y después de `update-finalize.php` en Windows.
9. Si una descarga o instalación falla, el lote se detiene y puede reanudarse desde el punto exacto sin volver a descargar lo ya validado.

Ejemplo:

`0.14.61 -> 0.14.62 -> 0.14.63 -> 0.14.64`

El operador no vuelve a pulsar Buscar/Actualizar entre versiones.

## Razón para usar varias solicitudes HTTP

No se ejecuta toda la cadena dentro de un request PHP largo. Hacerlo así volvería a introducir timeouts de Apache/LiteSpeed y mantendría en memoria código del updater anterior después de sustituir archivos.

El lote persistente vive en `storage/updates/batches/`. El navegador dispara automáticamente un paso por solicitud. De esta forma:

- cada paso usa el código PHP recién instalado;
- se reduce el riesgo de timeout;
- el proceso puede recuperarse tras una desconexión del navegador;
- los paquetes ya descargados no se pierden.

## Seguridad

- La ruta completa y los hashes están dentro del manifiesto firmado por Central.
- Antes de cada instalación se vuelve a pedir un manifiesto actual: un release revocado después de la descarga no se instala.
- El Core esperado antes de cada salto se verifica contra `VERSION.json`.
- Edge mantiene la arquitectura Central -> Node -> Edge; el Node sólo permite predescargar Core presentes en la ruta progresiva firmada.

## Compatibilidad

La funcionalidad de lote queda disponible a partir de 0.14.61. Una instalación anterior debe llegar una vez a 0.14.61 mediante el updater que ya posee; a partir de allí las rutas progresivas futuras son automáticas.

No se agrega migración SQL.
