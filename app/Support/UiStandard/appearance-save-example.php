<?php
/**
 * Ejemplo mínimo de guardado:
 * 1. verificar sesión y SUPERADMIN;
 * 2. verificar CSRF;
 * 3. UiTheme::sanitize($_POST);
 * 4. guardar JSON global;
 * 5. auditar sin secretos.
 */
require_once __DIR__.'/UiTheme.php';
$theme=UiTheme::sanitize($_POST?:UiTheme::defaults());
header('Content-Type: application/json; charset=utf-8');
echo json_encode($theme,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
