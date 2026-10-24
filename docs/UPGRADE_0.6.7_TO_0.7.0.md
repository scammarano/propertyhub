# Actualización Property Hub 0.6.7 a 0.7.0

1. Respaldar archivos y base de datos.
2. Conservar `.env` y `storage/`.
3. Copiar los archivos 0.7.0.
4. Ejecutar `tools\migrate.bat` o la pantalla de reparación.
5. Confirmar la aplicación de `0013_structure_designer_assets.sql`.
6. Probar selección múltiple, eliminación, duplicación y ubicación de activos con registros de prueba antes de trabajar sobre la estructura completa.

La migración agrega ubicación estructural y por área a los activos, etapa de ciclo de vida, tipos técnicos ampliados y trazabilidad de duplicaciones. No elimina información existente.
