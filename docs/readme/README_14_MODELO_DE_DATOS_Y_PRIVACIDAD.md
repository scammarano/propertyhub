# Modelo de datos y privacidad

## Fuente de verdad

- Property Node es la fuente de verdad operacional de la propiedad.
- Property Hub Central es la fuente de verdad de instalaciones, versiones, releases, catálogos y telemetría agregada.
- Los fabricantes siguen siendo fuente técnica de ciertos eventos o credenciales hasta que se concilien.

## Dominios principales de tablas

### Operación local

- Instalaciones, organizaciones, propiedades y unidades.
- Personas, documentos, usuarios, identidades y membresías.
- Áreas, bloqueos, reservas e historial.
- Cartelera, campañas, notificaciones y entregas.
- Invitaciones, visitas y credenciales.
- Conectores, dispositivos, accesos, cámaras y mapeos.
- Vouchers, auditoría y actualizaciones.

### Despliegue

- Migraciones.
- Configuración del despliegue.
- Componentes instalados.
- Fuentes y trabajos de actualización.
- Salud de la instalación.

### Central

- Instalaciones registradas.
- Planes y tokens.
- APIs, releases, dependencias y asignaciones.
- Métricas, inventario de conectores y APIs de fabricantes.
- Privacidad y soporte.

### Territorio e IoT

- Ubicaciones.
- Enlaces de conectividad.
- Redes, gateways y dispositivos LoRaWAN.
- Reglas de automatización.

## Prefijo `bh_`

Las tablas conservan el prefijo histórico `bh_` para mantener compatibilidad con migraciones existentes. El nombre comercial ya es Property Hub. Renombrar tablas no aporta valor inmediato y requeriría una migración amplia; solo debe evaluarse con una estrategia de compatibilidad.

## Datos personales

Deben permanecer en el nodo:

- Identificación y documentos.
- Fotografías.
- Historial individual de visitas/accesos.
- Credenciales y biometría referenciada.
- Mensajes operativos privados.
- Secretos de fabricantes.

## Telemetría hacia Central

Perfiles:

- `metadata_only`: versión, estado y metadatos mínimos.
- `aggregates`: conteos e inventario no sensible.
- `support_snapshot`: instantánea temporal aprobada para soporte.

No existe un modo predeterminado para copiar datos personales.

## Demo y persistencia

Las tablas representan el modelo objetivo, pero parte de las pantallas operativas actuales usan `DemoRepository` en sesión. Antes de producción, cada módulo debe migrar a repositorios persistentes, transacciones, auditoría y políticas de retención.

## Mapeos externos

`bh_external_mappings` y campos equivalentes deben relacionar entidades locales con IDs externos sin convertir la base del fabricante en el núcleo de Property Hub.
