# Property App móvil optimizada — 0.9.2

## Objetivo

Mejorar la experiencia móvil y comercial de la Property App sin convertirla en una versión reducida de la consola administrativa.

## Cambios

- Encabezado verde reducido de aproximadamente 220 px a 138 px.
- Nombre del inmueble, unidad y estado de conexión conservados en un bloque compacto.
- Iconos principales ampliados a 57–60 px.
- Cada función se presenta como una tarjeta táctil independiente.
- Grid de cuatro columnas en teléfonos amplios y tres columnas en pantallas estrechas.
- Tarjetas de actividad con iconos más visibles.
- Barra inferior con iconos y etiquetas más grandes.
- Mejor aprovechamiento del primer pantallazo.

## Datos y permisos

La modificación es exclusivamente visual. Las funciones siguen apareciendo según:

- módulos habilitados en la instalación;
- permisos del residente;
- unidad activa;
- datos reales disponibles en el Property Node.

## PWA y caché

Se actualizó la caché a `property-hub-v0.9.2-static` y se añadió `?v=0.9.2` a los assets principales para evitar que el teléfono conserve los estilos anteriores.
