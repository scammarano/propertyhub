# Actualización Property Hub 0.10.4 → 0.10.5

## Property Hub Central

1. Respalde el Central.
2. Suba por FTP el contenido de 0.10.5 conservando `.env` y `storage/`.
3. Ejecute la actualización/migración desde el mecanismo habitual.
4. Debe aplicarse únicamente la nueva migración Central `0017_backup_ux_test_installations.sql`.
5. Abra `Versiones y paquetes`; el Core 0.10.5 debe sincronizarse automáticamente para los nodos.

## Property Nodes

No copie el Core manualmente. Una vez publicado 0.10.5 en Central:

1. `Sistema local → Actualizaciones`.
2. `Buscar actualizaciones`.
3. Actualice desde Central.
4. El Node no ejecuta `0017`, porque esa migración pertenece al portafolio Central.

## Verificación de respaldo

Después de actualizar un Node:

1. genere un respaldo manual nuevo;
2. abra `Ver contenido`;
3. confirme que `storage/updates` aparece como excluido;
4. compare el tamaño con copias anteriores;
5. no elimine respaldos históricos hasta completar una restauración real en el servidor de prueba.
