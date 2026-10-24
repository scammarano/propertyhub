# Property Hub 0.13.4 - Recuperación de Central con .env + BD

Escenario soportado: se perdió la carpeta/runtime de Property Hub Central, pero sobreviven la base de datos MySQL y el `.env` original.

1. Subir el paquete 0.13.4 a una carpeta limpia.
2. Copiar el `.env` sobreviviente en la raíz.
3. Abrir `/install/`.
4. Usar **Recuperar Central existente**, no el instalador normal.
5. Confirmar con un usuario/correo y contraseña de un administrador activo ya existente en esa BD.

La recuperación conserva la BD y reconstruye `storage/installed.lock`, directorios runtime y el repositorio. Si faltan las claves `storage/keys/repository-*.pem`, genera un nuevo par RSA. Esa rotación exige renovar una vez la confianza del Property Node mediante un token BHR-RELINK; el Edge Agent puede reparar luego su vínculo Central a través del Node.

No se recrean usuarios, instalaciones registradas, tokens, historial ni tablas.
