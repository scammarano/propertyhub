<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
$root = dirname(__DIR__);
require_once $root.'/app/Support/helpers.php';
require_once $root.'/app/Database/Connection.php';
require_once $root.'/app/Deployment/InstallationMode.php';
require_once $root.'/app/Seeders/CatalogSeeder.php';
require_once $root.'/app/Seeders/NodeDemoSeeder.php';
if (!is_file($root.'/.env')) { fwrite(STDERR, "No existe .env. Ejecute el instalador web.\n"); exit(1); }
if (InstallationMode::isCentral()) { fwrite(STDERR, "Este comando solo aplica a un Property Node.\n"); exit(2); }
try {
    $pdo = Connection::pdo();
    $catalog = new CatalogSeeder($pdo, $root);
    $catalog->seedConnectorDefinitions();
    $uuid = (string) config('deployment.installation_uuid');
    $stmt = $pdo->prepare('SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1');
    $stmt->execute([$uuid]);
    $installationId = (int) $stmt->fetchColumn();
    if ($installationId < 1) throw new RuntimeException('No se encontró la instalación local.');
    $result = (new NodeDemoSeeder($pdo))->seed($installationId);
    echo "Demo territorial del Property Node sincronizado.\n";
    foreach ($result as $key => $value) echo $key.': '.$value.PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage().PHP_EOL);
    exit(3);
}
