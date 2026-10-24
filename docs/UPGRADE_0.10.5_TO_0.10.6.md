# Actualización 0.10.5 → 0.10.6

## Property Hub Central

1. Subir el paquete 0.10.6 conservando `.env` y `storage/`.
2. Ejecutar la reparación/migración pendiente.
3. Debe aplicarse `0018_central_backup_vault.sql` únicamente en Central.
4. Abrir **Sistema central → Backup Vault de instalaciones**.
5. El Core 0.10.6 se publica automáticamente para los Nodes.

## Property Nodes

No copiar la release manualmente. En el Node:

1. **Sistema local → Actualizaciones → Buscar actualizaciones**.
2. Instalar 0.10.6 desde Central.
3. Confirmar que el runner de backup/mantenimiento esté instalado.
4. Desde Central solicitar un respaldo de ese Node y comprobar que aparece posteriormente en el Vault.

La migración 0018 no se ejecuta en Property Nodes.
