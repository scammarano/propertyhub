# Actualización Property Hub 0.6.2 → 0.6.3

## Objetivo

Corregir el heartbeat HTTP 404 y habilitar regeneración/reconexión de tokens sin borrar instalaciones.

## Orden obligatorio

1. Actualizar **Property Hub Central**.
2. Ejecutar en Central:

```bat
tools\migrate.bat
```

3. Confirmar que `0011_registration_token_lifecycle.sql` aparezca aplicada.
4. Actualizar el **Property Node** de Premier.
5. Ejecutar en Premier:

```bat
tools\migrate.bat
```

El nodo no ejecuta 0011 porque las tablas de tokens pertenecen exclusivamente al Central.

## Archivos que se conservan

No sobrescribir ni eliminar:

```text
.env
storage/
```

## Recuperar el vínculo de Premier

1. En Central abrir `Red de instalaciones → Conjunto Premier Las Mercedes`.
2. Pulsar `Generar token de reconexión`.
3. Copiar el token mostrado.
4. En Premier abrir `Sistema local → Vínculo con Property Hub Central`.
5. Probar el endpoint.
6. Pegar el token en `Token de registro o reconexión`.
7. Pulsar `Registrar o renovar vínculo`.
8. Enviar el heartbeat.

No se borra ni se recrea Premier. Se conserva la ficha central y se rotan las credenciales técnicas.
