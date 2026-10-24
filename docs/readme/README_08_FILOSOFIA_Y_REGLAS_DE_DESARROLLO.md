# Filosofía y reglas de desarrollo

## Un producto, no proyectos por cliente

Las diferencias de cada propiedad deben resolverse mediante configuración, temas, permisos, módulos, conectores y campos controlados. No se deben crear ramas permanentes ni modificar el Core directamente para un solo cliente.

## Núcleo independiente de fabricantes

El dominio no debe utilizar nombres o estructuras de una marca como modelo principal. Las integraciones se encapsulan en conectores y mapeos externos.

## API antes que escritura directa

Orden de integración recomendado:

1. API oficial.
2. Servicio/SDK documentado.
3. Importación oficial.
4. Lectura de base para conciliación.
5. Escritura directa únicamente después de documentar transacciones, colas y riesgos, y con una decisión explícita.

## Migraciones inmutables

- Toda modificación de esquema usa una migración numerada.
- Una migración ya publicada no se modifica.
- Debe poder detectarse si está pendiente o aplicada.
- Las migraciones destructivas requieren respaldo y nota especial.

## Módulos y conectores

Cada módulo o conector debe tener:

- Código estable.
- Manifiesto.
- Versión.
- Dependencias.
- Capacidades.
- Estado de ciclo de vida.
- README.
- Pruebas.
- Migraciones propias cuando corresponda.

Estados recomendados: `planned`, `research`, `prototype`, `beta`, `stable`, `deprecated`.

## Reglas de interfaz administrativa

Basadas en el estándar común de los demás desarrollos:

- Las páginas de listado muestran primero el grid y un botón **Nuevo**.
- Crear o editar se realiza en modal o pantalla dedicada, no con un formulario permanente encima del grid.
- Todo módulo debe contemplar CRUD completo, limitado por permisos.
- Los grids muestran valores relacionados, no IDs internos.
- Deben incluir filtros relevantes y paginación real.
- Selector de registros: 10, 25, 50 y 100.
- Para tablas grandes, preferir paginación del lado del servidor.
- El sidebar debe permanecer operativo durante la carga.
- Acciones destructivas requieren confirmación y auditoría.

Estas reglas son objetivo obligatorio aunque algunas pantallas actuales de la base funcional todavía no las cumplan.

## Manejo de datos de formularios

- No aplicar transformaciones globales a contraseñas, tokens, claves, URLs, correos o secretos.
- Validar y normalizar solo campos de negocio que lo requieran.
- Los identificadores internos no se exponen como dato principal al usuario.

## Errores

- Toda excepción genera un número de incidente.
- El detalle técnico se registra en `storage/logs`.
- En producción, el usuario no ve stack traces ni secretos.
- Nunca redirigir una excepción a la misma ruta que la genera.

## Seguridad desde el diseño

- Secretos fuera de `public`.
- Consultas parametrizadas para valores.
- Identificadores SQL validados, no parametrizados como valores.
- CSRF en operaciones de escritura.
- Roles/permisos en servidor, no solo en interfaz.
- Auditoría de acciones sensibles.

## Dependencia visual

`public/assets/vendor/2cam-ui/` es una dependencia reemplazable. No se modifica desde Property Hub; las adaptaciones viven fuera.

## Definición de terminado

Una función no se considera lista solo porque tenga una pantalla. Debe incluir persistencia, validaciones, permisos, auditoría, estados, manejo de errores, pruebas y documentación.
