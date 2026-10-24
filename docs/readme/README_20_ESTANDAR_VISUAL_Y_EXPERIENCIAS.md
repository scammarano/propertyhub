# Estándar visual y experiencias

## Baseline

Property Hub utiliza **2CAM UI Standard 1.2.0** como dependencia común.

## Regla de dependencia

No editar:

```text
public/assets/vendor/2cam-ui/
```

Las adaptaciones viven en CSS/JS/layouts propios de Property Hub. Cuando cambie el estándar, se sustituye la dependencia en una copia de prueba y se valida antes de distribuir.

## Operations Console

- AdminLTE.
- Sidebar jerárquico con grupos, secciones y opciones.
- Estado abierto/cerrado persistente.
- Menú filtrado por permiso.
- Dashboard, grids, modales, filtros y calendario.
- Compatible con pantallas pequeñas sin bloquear navegación.

## Resident App

- PWA móvil independiente.
- Iconos y tarjetas táctiles.
- Menú corto y navegación inferior.
- Selector rápido de propiedad.
- Sin configuración técnica.

## Login

El login debe identificar claramente el tipo de acceso y no mezclar el portal del residente con la consola administrativa. Google/Apple son alternativas de identidad, no atajos de permisos.

## Reglas de grids y CRUD

- Botón **Nuevo**, no formulario permanente encima de la tabla.
- Crear/editar en modal o vista dedicada.
- Mostrar valores relacionados en lugar de IDs.
- Filtros adecuados al módulo.
- Paginación 10/25/50/100.
- Carga no bloqueante y sidebar siempre disponible.
- Acciones por permisos.

## Actualización visual

1. Comparar changelog y manifiesto.
2. Reemplazar `vendor/2cam-ui`.
3. Actualizar versión.
4. Probar sidebar, widgets, modales, tablas, calendario y mobile.
5. Corregir adaptadores externos.
6. Publicar el UI como componente independiente cuando el motor de actualizaciones lo permita.
