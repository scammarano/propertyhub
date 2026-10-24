# Ciclo de vida de una instalación

## 1. Levantamiento

Antes de crear el expediente se documentan:

- Tipo y estructura de la propiedad.
- Sedes, garitas, áreas y redes.
- Servidores y plataformas existentes.
- Marcas, modelos, firmware y licencias.
- Flujos de visitantes, residentes y vigilancia.
- Cámaras, controladoras, NVR, PBX, AP, cerraduras e IoT.
- Políticas de privacidad y operación remota.

## 2. Preparación en Central

Ruta:

```text
Red de instalaciones → Nueva instalación
```

Se crea el código oficial, canal, alcance de telemetría, inventario previsto y token.

## 3. Despliegue físico

Se extrae el paquete en el servidor del cliente, se crea una base independiente y se ejecuta `/install/` como Property Node.

## 4. Registro

El nodo envía código, UUID, URL, versiones y clave pública. Central valida el token, registra el nodo y devuelve su clave pública para verificar manifiestos.

## 5. Configuración local

- Crear topología territorial.
- Cargar unidades y personas.
- Configurar roles y permisos.
- Registrar conectores.
- Cargar endpoints y secretos localmente.
- Probar comunicaciones en modo sandbox/test.
- Configurar privacidad y telemetría.

## 6. Puesta en producción

- Cambiar `APP_DEBUG=false`.
- Desactivar demo.
- Activar HTTPS.
- Crear respaldo inicial.
- Probar recuperación.
- Validar vigilancia, administración y Resident App.
- Confirmar heartbeat y canal estable.

## 7. Operación continua

El nodo reporta salud y versiones, recibe catálogos y consulta actualizaciones. Las incidencias se registran con código y logs locales.

## 8. Suspensión o cambio de administración

Debe poder suspenderse el vínculo con Central sin borrar la base local. Las claves y tokens se rotan cuando cambia el responsable técnico.

## 9. Retiro

Una instalación retirada debe:

- Revocar su clave en Central.
- Marcarse `retired` sin borrar la trazabilidad necesaria.
- Exportar o retener datos conforme al contrato.
- Eliminar accesos remotos.
- Conservar respaldos según la política acordada.

## Herramientas incluidas

- `tools/migrate.bat`: ejecutar migraciones.
- `tools/reset-demo.bat`: restablecer demo central.
- `tools/reseed-node-demo.bat`: restablecer demo territorial del nodo.
- `tools/factory-reset.bat`: reinicio total para laboratorio; destruye configuración y tablas.

`factory-reset` no debe usarse en producción sin respaldo y autorización expresa.

## Recuperación y renovación del vínculo

El token original nunca se almacena en texto plano; solamente se conserva su hash. Por ello no existe una función de “mostrar token anterior”.

Esto no obliga a eliminar una instalación:

- Un expediente pendiente permite regenerar su token inicial. El token anterior se revoca y el expediente conserva el mismo código y configuración.
- Un nodo registrado permite generar un token de reconexión desde su ficha central. El nodo usa ese token en `Registrar o renovar vínculo`.
- La reconexión conserva el registro central, métricas, inventario e historial, pero rota la clave técnica del nodo.
- Los tokens de reconexión son de un uso, tienen vencimiento y pueden revocarse.

Borrar y recrear una instalación solo corresponde a un retiro definitivo o a una prueba expresamente descartable.
