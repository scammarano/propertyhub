# Validación Property Hub 0.10.5

Validaciones del paquete:

- lint de todos los archivos PHP;
- parseo de JSON/webmanifest;
- `node --check` de JavaScript;
- integridad ZIP;
- checksums de migraciones 0001–0016 conservados respecto de 0.10.4;
- nueva migración Central 0017;
- 0017 no forma parte del plan de migración de Property Nodes;
- `storage/updates` excluido por `BackupManager`;
- detalle de contenido incorporado al manifiesto de respaldos nuevos;
- acciones Central de entorno/duplicación/eliminación protegidas por `system.manage` + CSRF;
- eliminación física restringida a Prueba/Laboratorio/Demo;
- Producción protegida de eliminación física.

Pendiente de validar en servidores reales:

- reducción efectiva del tamaño del próximo `.phbackup` de Premier;
- restauración completa de ese backup en el servidor local nuevo;
- migración 0017 contra MariaDB del Central;
- eliminación/duplicación de un nodo de prueba real;
- actualización Premier 0.10.4 → 0.10.5 desde Central.
