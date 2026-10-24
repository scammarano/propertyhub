# Actualización Property Hub 0.9.0 → 0.9.1

1. Respalde la base de datos y la carpeta de la instalación.
2. Conserve sin reemplazar `.env` y `storage/`.
3. Sustituya los demás archivos por los de 0.9.1.
4. Ejecute `tools\migrate.bat`. No existen migraciones SQL nuevas; el comando actualiza el número de versión del nodo.
5. Abra el Asistente de diseño y haga una recarga completa con `Ctrl+F5` la primera vez.

El sistema no elimina ni transforma estructuras, personas, áreas, conectores ni vínculos con Property Hub Central.
