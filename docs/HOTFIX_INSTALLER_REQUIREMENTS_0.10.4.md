# Property Hub 0.10.4 — Hotfix de requisitos del instalador

## Problema corregido
En instalaciones nuevas, especialmente Windows/XAMPP, el paso 6 podía terminar con el mensaje genérico `La instalación no puede continuar porque faltan requisitos obligatorios` aunque las extensiones PHP visibles en el paso 1 aparecieran en verde.

## Cambio
- Los permisos de la carpeta raíz y `storage/` se verifican creando y eliminando un archivo temporal real.
- El acceso a `.env` se comprueba explícitamente.
- `storage/` y `storage/logs/` se preparan antes del chequeo.
- El paso 6 utiliza una sola fotografía de requisitos para evitar resultados distintos entre comprobaciones consecutivas.
- Si algo falla, el mensaje y el log indican exactamente el requisito y la ruta que fallaron.

## Sin cambios de base de datos
No existe migración 0017. Se conservan las migraciones 0001–0016.

## Prueba esperada en XAMPP
Al abrir `/install/`, todos los requisitos deben aparecer en verde, incluidos al final:
- Carpeta raíz escribible — `escritura real OK`
- Storage escribible — `escritura real OK`
- Archivo `.env` disponible

Si cualquiera falla, el botón Continuar queda deshabilitado y se muestra el detalle real.
