# Prueba real de recuperación — Premier

## Fase A — Property Hub Central

1. Actualizar primero el Hub Central a 0.10.0.
2. Ejecutar migraciones y confirmar `0016_backup_recovery.sql`. La actualización genera primero un `PRE_UPDATE`; si no existía clave de recuperación, la muestra una sola vez para guardarla fuera del servidor.
3. Abrir `Sistema central → Respaldo y recuperación` y confirmar que el PRE_UPDATE aparece en el historial.
4. Crear un respaldo manual del Central.
5. El Central queda preparado para recibir copias cifradas de los Property Nodes.

## Fase B — Premier origen (servidor donde está la configuración que no se quiere repetir)

1. Actualizar Premier a 0.10.0 y migrar. Antes de modificar el schema debe crearse automáticamente el `PRE_UPDATE`; guarde la clave de recuperación que se muestre si fue generada en ese momento.
2. Abrir `Sistema local → Respaldo y recuperación` y confirmar el PRE_UPDATE.
3. Activar, si se desea, `Enviar copia cifrada al Property Hub Central`.
4. Crear un respaldo manual.
5. Confirmar:
   - estado `completed` o `warning` explicable;
   - SHA-256 y tamaño visibles;
   - descarga local funciona;
   - si se activó copia central, el archivo aparece en el Hub Central.
6. Descargar el archivo desde el Central para la prueba. Así se valida también el circuito offsite.

## Fase C — Servidor nuevo donde convivirá Property Hub con ZKBio CVSecurity

1. Instalar XAMPP/PHP requerido y Property Hub 0.10.0.
2. Crear una instalación básica `building_node`, sin datos demo. Puede usar un código temporal, por ejemplo `PREMIER-RECOVERY-TEMP`.
3. No es necesario registrar el nodo temporal contra el Central.
4. Guardar la clave de recuperación temporal que muestra el instalador; se usa para el punto `PRE_RESTORE`.
5. Iniciar sesión y abrir `Sistema local → Respaldo y recuperación`.
6. Cargar el `.phbackup` de Premier y escribir **la clave de recuperación de Premier origen**.
7. Revisar el manifiesto.
8. Elegir `Recuperar la misma instalación`.
9. Confirmar escribiendo el código temporal actual (`PREMIER-RECOVERY-TEMP`).
10. Property Hub crea un `PRE_RESTORE`, reemplaza la base y recupera la identidad real de Premier.
11. Volver a iniciar sesión con un administrador que existía en el servidor Premier origen.

## Fase D — Conciliación

Verificar especialmente:

- nombre/código/UUID de Premier;
- URL pública: debe ser la del servidor nuevo porque es un dato de servidor;
- MariaDB: debe seguir usando la base/usuario/clave creados en el servidor nuevo;
- estructura, apartamentos, pisos, áreas y estacionamientos;
- personas y relaciones;
- usuarios Resident App;
- sistemas conectados e inventario;
- mapeos externos y IDs ZKTeco;
- endpoint de ZKBio CVSecurity, que puede requerir ajuste según la red real;
- cámaras/NVR/UniFi/Grandstream/Vimar existentes;
- vínculo con Property Hub Central.

En el primer heartbeat, el Central actualiza `base_url` con la URL del servidor recuperado.

## Fase E — Evitar dos Premier activas

Antes de probar heartbeat/comandos desde la instalación recuperada, apagar Apache/Property Hub del servidor Premier origen o aislarlo. Dos servidores usando simultáneamente el mismo UUID y credencial técnica producirían telemetría ambigua y, cuando habilitemos comandos reales, sería inseguro.

## Fase F — Automatización definitiva

En el servidor recuperado:

```bat
tools\install-backup-task.bat
```

Confirmar después un respaldo automático y una copia externa. La tarea Windows no se restaura deliberadamente desde el backup: debe instalarse de nuevo en cada servidor físico.
