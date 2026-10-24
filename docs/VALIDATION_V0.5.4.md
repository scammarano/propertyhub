# Validación Property Hub 0.5.4

- Archivos PHP validados: 122.
- Archivos JSON válidos: 33.
- `APP_DEMO_MODE=false` devuelve colecciones operativas vacías.
- `APP_DEMO_MODE=true` conserva la demostración navegable.
- Las ocho migraciones mantienen los mismos checksums de la 0.5.3.
- No se incluye `.env`, `installed.lock`, claves RSA ni secretos.
- Se incorporó limpieza web y CLI de datos demo.
- Se corrigieron cifras y vouchers estáticos en modo real.

La limpieza SQL debe confirmarse en MariaDB/XAMPP sobre la instalación del usuario.
