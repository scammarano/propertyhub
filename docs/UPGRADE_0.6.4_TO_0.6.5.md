# Actualización Property Hub 0.6.4 → 0.6.5

## Alcance

Añade selección y edición masiva de la estructura. No modifica el esquema de base de datos.

## Procedimiento

1. Respaldar la carpeta y la base de datos.
2. Sustituir los archivos del producto.
3. Conservar `.env` y todo `storage/`.
4. Ejecutar `tools\migrate.bat` para actualizar los metadatos de versión.
5. Abrir `Gestión de la propiedad → Estructura y unidades`.
6. Probar con dos o tres registros antes de procesar lotes grandes.

## Migraciones

No hay migraciones nuevas. Los archivos `0001` a `0012` deben conservar sus checksums.

## Prueba recomendada

1. Seleccionar dos apartamentos de prueba.
2. Cambiar su estado a mantenimiento.
3. Renombrarlos agregando un sufijo temporal.
4. Moverlos a otro piso de prueba.
5. Revertir manualmente las operaciones.
6. Verificar el registro de auditoría.
