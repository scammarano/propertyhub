# Upgrade Property Hub Central 0.10.2 → 0.10.3

## Central

1. Haga una copia de `.env`, `storage/` y la base de datos actual si dispone de un mecanismo externo.
2. Suba por FTP el contenido del ZIP 0.10.3 sobre el Central.
3. No reemplace `.env` ni borre `storage/`.
4. Entre a **Versiones y paquetes**.
5. El bloque **Property Hub Core oficial** debe indicar 0.10.3. Pulse **Sincronizar** solo si desea forzar la comprobación inmediatamente; un pedido de manifiesto desde un Node también la ejecuta automáticamente.
6. No publique Core con **Publicar paquete adicional**.

No existe migración 0017. `0016_backup_recovery.sql` sigue siendo la última migración y no se modifica.

## Premier

No copie el release 0.10.3 manualmente al Node. Use:

`Sistema local → Actualizaciones → Buscar actualizaciones`

Premier con Core 0.9.1 y Update Bootstrap 1.0.0 es compatible con el salto directo a 0.10.3.
