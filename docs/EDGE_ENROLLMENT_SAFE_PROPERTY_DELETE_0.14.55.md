# Property Hub 0.14.55 · Edge enrollment y eliminación segura de instalaciones

## Incidente corregido
El log aportado para el incidente `20260903053627-ce1a02ca` demuestra que el Agent estaba ejecutando el contrato antiguo de 0.14.52: `InstallerService.php` exigía `parent_property_code`. Desde 0.14.53 el contrato correcto es exclusivamente URL del Property Node + token de enrolamiento y el código padre se obtiene del Node.

0.14.55 agrega una huella de contrato al instalador y a los logs para detectar inmediatamente una carpeta mezclada con archivos antiguos.

## Eliminación de propiedades
Central permite eliminar físicamente una propiedad preparada por error o una instalación inactiva desde **Instalaciones → Propiedad → Administración**.

Protecciones:
- exige escribir el código exacto de la propiedad;
- bloquea la eliminación si cualquier Property Node o Edge Agent reportó heartbeat durante los últimos 5 minutos;
- elimina el plan, Node/Edge, enrolamientos y tokens relacionados;
- conserva Backup Vault por defecto; el borrado físico de respaldos requiere opción explícita.
