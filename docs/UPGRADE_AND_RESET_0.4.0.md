# Actualización y reinicio 0.4.0

## Opción recomendada durante esta etapa: instalación limpia

Como todavía se trata de la primera instalación y la 0.4.0 cambia la separación conceptual, se recomienda reiniciar el Property Hub Central y probar nuevamente el instalador.

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\building-central\tools\reset-installation.php --factory-reset --confirm=BH-CENTRAL-001
```

Después abra:

```text
http://localhost/building-central/install/
```

## Conservar la instalación existente

1. Haga respaldo de la carpeta y base.
2. Sustituya el código, preservando `.env` y `storage/`.
3. Ejecute:

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\building-central\tools\migrate.php
```

4. Restaure el demo central:

```bat
C:\xampp\php\php.exe C:\xampp\htdocs\building-central\tools\reset-installation.php --demo-only --confirm=BH-CENTRAL-001
```

## Qué hace `--factory-reset`

- Lee la conexión actual desde `.env`.
- Exige que el código de confirmación coincida exactamente.
- Elimina todas las tablas de la base actual.
- Elimina `.env`, el lock y las claves del repositorio.
- No elimina la carpeta ni la base vacía.
