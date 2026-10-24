# Actualización de Property Hub 0.6.0 a 0.6.1

1. Respaldar la base de datos y la carpeta actual.
2. Sustituir los archivos de la aplicación.
3. Conservar `.env` y toda la carpeta `storage/`.
4. Ejecutar `tools\migrate.bat` o usar `Sistema local → Diagnóstico → Reparación y migraciones`.
5. Verificar que `0010_platform_managed_assets.sql` aparezca aplicada.
6. Entrar en `Infraestructura y tecnología → Sistemas conectados`.
7. Crear o editar la instancia ZKTeco como `Servidor ZKBioSecurity Premier`.
8. Entrar en `Equipos administrados y activos` y asociar los paneles inBio a esa plataforma.

La migración convierte automáticamente los activos ZKTeco ya vinculados a una instancia ZKBioSecurity en equipos administrados por plataforma y desactiva el indicador histórico de conexión directa.
