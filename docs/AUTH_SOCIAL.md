# Autenticación con Google y Apple

## Regla funcional

Google y Apple autentican una identidad. No conceden acceso automático a un edificio. Después del primer login, la administración debe vincular la identidad global con una persona local y una relación válida con un apartamento, oficina o local.

## Google

La base usa el flujo OpenID Connect de servidor:

1. Configurar un proyecto en Google Cloud.
2. Crear credenciales OAuth para aplicación web.
3. Registrar la URI exacta de retorno.
4. Activar las variables `GOOGLE_*` en `.env`.
5. El servidor intercambia el código, valida la firma del ID token, emisor, audiencia, fechas y nonce.

Variables:

- `GOOGLE_AUTH_ENABLED`
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI`

## Apple

La base usa Sign in with Apple para web:

1. Habilitar Sign in with Apple en Apple Developer.
2. Crear y asociar un Services ID.
3. Registrar dominio y URI de retorno.
4. Crear una clave privada `.p8`.
5. Configurar Team ID, Key ID y ruta de la clave.
6. El sistema genera dinámicamente el client secret ES256 y valida el ID token recibido.

Variables recomendadas:

- `APPLE_AUTH_ENABLED`
- `APPLE_CLIENT_ID`
- `APPLE_TEAM_ID`
- `APPLE_KEY_ID`
- `APPLE_PRIVATE_KEY_PATH`
- `APPLE_REDIRECT_URI`

También se acepta temporalmente `APPLE_CLIENT_SECRET`, pero requiere rotación manual.

## Producción

- Usar HTTPS.
- Cifrar claves y secretos.
- Guardar la clave `.p8` fuera del directorio público.
- Desactivar `APP_DEMO_MODE`.
- Implementar alta, aprobación, suspensión y revocación de membresías.
- Registrar sesiones y dispositivos.
- Incorporar segundo factor para acciones sensibles.
