<?php
/** Plantilla de referencia. Conectar autenticación, permisos y rutas del proyecto. */
require_once __DIR__ . '/UiTheme.php';
require_once __DIR__ . '/SidebarRenderer.php';
$theme = UiTheme::sanitize($theme ?? []);
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$menu = $menu ?? [
    [
        'code' => 'home', 'label' => 'Inicio', 'icon_html' => '⌂', 'items' => [
            ['code' => 'home.dashboard', 'label' => 'Dashboard', 'href' => 'index.php'],
        ],
    ],
    [
        'code' => 'admin', 'label' => 'Administración', 'icon_html' => '◆', 'sections' => [
            [
                'code' => 'admin.tables', 'label' => 'Tablas', 'icon_html' => '▦', 'items' => [
                    ['code' => 'admin.customers', 'label' => 'Clientes', 'href' => 'customers.php'],
                    ['code' => 'admin.products', 'label' => 'Artículos', 'href' => 'products.php'],
                ],
            ],
            [
                'code' => 'admin.processes', 'label' => 'Procesos', 'icon_html' => '▶', 'items' => [
                    ['code' => 'admin.invoice', 'label' => 'Facturación', 'href' => 'invoice.php'],
                ],
            ],
        ],
    ],
];
?><!doctype html>
<html lang="es" data-bs-theme="<?=htmlspecialchars($theme['appearance'] === 'dark' ? 'dark' : 'light')?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style><?=UiTheme::css($theme)?></style>
<link rel="stylesheet" href="../assets/adminlte/adminlte.min.css">
<link rel="stylesheet" href="../assets/css/2cam-adminlte.css">
<link rel="stylesheet" href="../assets/css/2cam-modern-ui.css">
<link rel="stylesheet" href="../assets/css/2cam-dashboard-responsive.css">
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary cam-adminlte cam-ui-v1 density-<?=htmlspecialchars($theme['density'])?> table-<?=htmlspecialchars($theme['table_density'])?> kpi-<?=htmlspecialchars($theme['kpi_style'])?>">
<?=SidebarRenderer::earlyRestoreScript()?>
<div class="app-wrapper">
<nav class="app-header navbar navbar-expand bg-body shadow-sm">
  <a class="nav-link cam-sidebar-toggle" data-lte-toggle="sidebar" href="#" role="button" aria-label="Abrir o cerrar menú">☰</a>
  <span class="cam-header-context"><strong>Encabezado del proyecto</strong><small>Empresa · sede</small></span>
</nav>
<?=SidebarRenderer::render([
    'home_url' => 'index.php', 'brand_name' => '2CAM', 'company_name' => 'Empresa de ejemplo',
    'site_code' => 'SEDE-01', 'role' => 'SUPERADMIN',
], $menu, $currentUri, $activeCode ?? null)?>
<main class="app-main"><div class="app-content"><div class="container-fluid cam-content">Contenido</div></div></main>
<footer class="app-footer">2CAM UI Standard 1.2.0</footer>
</div>
<script src="../assets/adminlte/adminlte.min.js"></script>
<script src="../assets/plugins/2cam-vendor-ui.js"></script>
<script src="../assets/plugins/chartjs-2.7.2.bundle.js"></script>
<script src="../assets/js/2cam-ui.js"></script>
</body></html>
