# Actualizar Property Hub Central 0.10.0 → 0.10.1

1. Respaldar `.env`, `storage/` y la base de datos.
2. Copiar 0.10.1 sobre el Central conservando `.env` y `storage/`.
3. Ejecutar `tools\migrate.bat`.
4. Confirmar en la salida:

```text
Core publicado para nodos: property-hub-core-0.10.1-stable
```

5. En **Central → Versiones y paquetes** debe aparecer `2CAM Property Hub Core 0.10.1`, canal `stable`, estado `published`.
6. En un nodo con Update Engine 1.0.0, pulsar **Buscar actualizaciones**.

No existe migración 0017. 0.10.1 reutiliza el schema 0.10.0 y mantiene intactos los checksums 0001–0016.
