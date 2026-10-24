# Actualización Property Hub 0.8.0 → 0.9.0

1. Respaldar la base de datos y la carpeta del Property Node.
2. Conservar `.env` y `storage/`.
3. Sustituir los demás archivos.
4. Ejecutar:

```bat
tools\migrate.bat
```

5. Confirmar que se aplicó `0015_resident_app_real_data.sql`.
6. Entrar en **Servicios y comunidad → Property App · Residentes**.
7. Crear una cuenta residente desde **Personas** y probar desde el teléfono.

La migración 0015 crea únicamente la configuración de Property App y agrega índices. No modifica las migraciones 0001–0014.
