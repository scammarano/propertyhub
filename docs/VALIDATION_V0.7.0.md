# Validación Property Hub 0.7.0

## Validaciones realizadas

- 131 archivos PHP sin errores de sintaxis.
- 15 archivos JavaScript sin errores de sintaxis.
- Scripts embebidos de Diseño y unidades, Áreas e Inventario tecnológico validados con `node --check`.
- 33 archivos JSON válidos.
- 33 enlaces internos Markdown verificados sin rutas rotas.
- 13 migraciones procesadas correctamente por `SqlStatementSplitter`.
- Migración 0013 incluida en instalador, reparación web y migrador CLI.
- Migraciones 0001–0012 comparadas con el paquete 0.6.7 y conservadas sin cambios.
- Rutas POST incorporadas para duplicación de estructura y acciones masivas de áreas.
- Confirmación y estado visible incorporados a la eliminación masiva.
- Ubicación estructural, área específica y etapa del activo incorporadas al inventario.
- ZIP final sometido a prueba de integridad antes de la entrega.

## Validaciones funcionales estáticas

- Pisos con cero apartamentos se conservan como contenedores.
- Configuración por piso forma parte del payload y vuelve a validarse en el servidor.
- La duplicación limpia serial, IP e identificadores externos de equipos copiados.
- Los equipos copiados quedan en etapa `planned` y estado `unknown`.
- La eliminación individual y masiva protege propiedades y áreas con activos relacionados.
- Las áreas repetidas se distinguen por código y ubicación.

## Pruebas pendientes en XAMPP/MariaDB real

- Aplicación de `0013_structure_designer_assets.sql` sobre la base de Premier.
- Eliminación masiva de apartamentos libres.
- Desactivación de pisos o áreas con dependencias.
- Duplicación de una torre con áreas, activos, accesos y cámaras.
- Duplicación de áreas con componentes.
- Rendimiento con estructuras de varios miles de elementos.

La versión no ejecuta todavía acciones físicas sobre fabricantes. Persiste el diseño lógico del inmueble y la ubicación de sus componentes BMS.
