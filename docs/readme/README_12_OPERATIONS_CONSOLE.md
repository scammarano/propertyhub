# Operations Console

## Objetivo

Operations Console es la web completa para administración, vigilancia, recepción, operadores y soporte. Debe aprovechar escritorio y tablet para mostrar grids, filtros, calendarios, estados y herramientas de diagnóstico.

## Roles de referencia

### Administrador de la propiedad

Gestiona estructura, personas, permisos, áreas, reservas, mensajes, políticas y reportes.

### Vigilancia / recepción

Registra y valida visitas, entrega/recibe tarjetas, consulta autorizaciones, registra entradas/salidas e incidencias. No configura APIs ni controladoras.

### Operador administrativo

Carga residentes, documentos, vehículos, personal y solicitudes según permisos.

### Soporte técnico

Administra conectores, diagnóstico, actualizaciones y configuración técnica con alcance controlado.

## Reglas de pantalla

- Grid como contenido principal.
- Botón **Nuevo** visible.
- Modal o pantalla dedicada para crear/editar.
- CRUD completo con acciones condicionadas por permisos.
- Mostrar nombres/valores relacionados, no IDs internos.
- Filtros por tipo, estado, fecha, unidad, área, marca y demás contexto aplicable.
- Paginación real y selector 10/25/50/100.
- El sidebar nunca debe bloquearse mientras carga un listado.
- Estados, errores y operaciones largas deben mostrar progreso.

## Separación de funciones

La vigilancia no debe acceder a secretos, APIs, releases ni configuración técnica. El administrador local no debe administrar otras instalaciones desde su nodo. El superadministrador central no recibe automáticamente datos privados del nodo.

## Auditoría

Toda modificación relevante debe registrar usuario, fecha, entidad, valores antes/después, motivo, resultado y origen. Aperturas, revocaciones, descargas de evidencia y soporte remoto requieren auditoría reforzada.

## Estado actual

La navegación, layouts y varias vistas están disponibles; gran parte de los grids operativos todavía son demostrativos y deben evolucionar a CRUD persistente siguiendo estas reglas.
