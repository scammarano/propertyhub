# Property Hub 0.9.1 — Corrección del Asistente de diseño

## Problemas corregidos

1. La vista mostraba **Maleteros / depósitos** como título global. El bucle PHP de nomenclaturas utilizaba la variable `$title`, sobrescribiendo el título recibido por el layout. Se sustituyó por una variable local específica.
2. El botón **Continuar** podía parecer inactivo cuando el navegador conservaba una copia anterior de `property-structure-wizard.js` o cuando ocurría una excepción JavaScript durante la inicialización.

## Nuevo comportamiento

- Título fijo: **Asistente de diseño del inmueble**.
- El archivo JavaScript se solicita con `?v=0.9.1`.
- Mientras inicia se muestra un aviso visible.
- Al terminar correctamente, el aviso desaparece.
- Si falta un dato de un paso, la validación aparece dentro del asistente.
- Si el JavaScript falla, la pantalla muestra la causa y se genera un registro en `storage/logs/frontend-AAAA-MM-DD.jsonl`.

## Datos registrados en el log de interfaz

- Fecha y hora.
- Request ID.
- Código de instalación.
- Usuario y rol.
- Fase del asistente.
- Mensaje y traza JavaScript.
- URL y navegador.

No se registran contraseñas, tokens de fabricantes ni credenciales de dispositivos.
