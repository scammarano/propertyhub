<?php
declare(strict_types=1);

$database = trim((string) env('DB_DATABASE', ''));
// Protección de recuperación para actualizaciones/copias manuales: el nombre de
// la BD forma parte de storage/installed.lock. Si una .env histórica perdió el
// dato o cayó al nombre genérico `property_hub`, el lock de ESTA instalación
// recupera el nombre real sin pedir edición manual del archivo.
$lockPath = base_path('storage/installed.lock');
$lockedDatabase = '';
if (is_file($lockPath)) {
    $lock = json_decode((string) @file_get_contents($lockPath), true);
    if (is_array($lock)) $lockedDatabase = trim((string)($lock['database'] ?? ''));
}
if ($lockedDatabase !== '' && ($database === '' || ($database === 'property_hub' && $lockedDatabase !== 'property_hub'))) {
    $database = $lockedDatabase;
}
if ($database === '') $database = 'property_hub';

return [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'database' => $database,
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
];
