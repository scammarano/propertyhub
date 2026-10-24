# Actualización Property Hub 0.6.3 → 0.6.4

1. Respaldar la base de datos y la carpeta actual.
2. Sustituir los archivos del programa.
3. Conservar `.env` y todo el directorio `storage/`.
4. Ejecutar:

```bat
tools\migrate.bat
```

5. Confirmar la ejecución de `0012_structure_generation_wizard.sql`.
6. Ingresar en `Gestión de la propiedad → Estructura → Asistente de estructura`.

Las migraciones 0001–0011 no fueron modificadas. La 0012 solo agrega el historial y la trazabilidad del asistente.
