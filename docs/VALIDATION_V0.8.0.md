# Validación Property Hub 0.8.0

Validaciones realizadas sobre el paquete:

- 132 archivos PHP sin errores de sintaxis;
- 15 archivos JavaScript sin errores de sintaxis;
- 33 archivos JSON válidos;
- 105 enlaces internos Markdown verificados, sin destinos faltantes;
- inclusión de `0014_topology_designer.sql` en instalador, reparador web y migrador CLI;
- conservación exacta de los checksums de las migraciones 0001–0013;
- checksum de la nueva migración 0014: `d57d1c2b2311b9260e743e929fe0cdc0ea2aa1560ed42b3f981d4021ddfce6ce`;
- sustitución segura de variables superpuestas como `{TORRE}`, `{TORRE_LETRA}` y `{TORRE_NOMBRE}`;
- formatos frecuentes y plantillas independientes para pisos, apartamentos, estacionamientos y maleteros;
- trazabilidad del origen de la nomenclatura: documento de condominio, regla interna, mixta o pendiente;
- generación de niveles sin apartamentos;
- múltiples zonas de estacionamiento y maleteros ubicadas en niveles concretos;
- áreas repetidas con códigos propios y ubicaciones diferentes;
- ascensores con torres y paradas distintas;
- puertas planificadas por parada con códigos que incluyen la ubicación completa;
- rollback de propiedades, áreas, ascensores y accesos generados;
- duplicación de circulación vertical y componentes asociados;
- ausencia de `.env`, bloqueos de instalación, claves privadas y logs reales dentro del ZIP.

La prueba transaccional final debe ejecutarse contra MariaDB en una copia de la base de Premier antes de utilizar el diseñador sobre la estructura definitiva. Este entorno de construcción no dispone de la base real de Premier ni de sus datos.
