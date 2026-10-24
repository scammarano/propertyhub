# Actualización 0.10.6 → 0.10.7

1. En Property Hub Central subir por FTP el contenido de 0.10.7 conservando `.env` y `storage/`.
2. No hay migración nueva. Abrir **Sistema central → Diagnóstico central** y comprobar que las migraciones 0001–0018 estén aplicadas.
3. Abrir **Versiones y distribución → Paquetes y releases**. El Central debe sincronizar automáticamente `property-hub-core-0.10.7-stable`.
4. En el Node usar **Sistema local → Actualizaciones → Buscar actualizaciones**. No copiar el Core manualmente al Node.
