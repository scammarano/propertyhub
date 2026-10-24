<?php
$theme = UiTheme::sanitize(['preset'=>'violet','primary'=>'#5a40d6','sidebar'=>'#15182b','appearance'=>'light','density'=>'compact','table_density'=>'compact','kpi_style'=>'soft']);
$user = current_user() ?? [];
$currentRoute = route_name();
$navigation = config('navigation', []);
$menu = sidebar_menu_for_user($navigation);
$activeCode = sidebar_active_code($navigation, $currentRoute);
$isCentral = InstallationMode::isCentral();
$context = $isCentral ? ['installation_name'=>(string)config('app.instance_name'),'property_name'=>'Administración central'] : DemoRepository::currentContext();
$flashes = pull_flashes();
$currentUri = (string)($_SERVER['REQUEST_URI'] ?? '');
?><!doctype html>
<html lang="es" data-bs-theme="<?=e($theme['appearance'] === 'dark' ? 'dark' : 'light')?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?=e($title ?? config('app.name'))?> · <?= $isCentral?'Property Hub Central':'Operaciones' ?></title>
<style><?=UiTheme::css($theme)?></style>
<link rel="stylesheet" href="<?=asset('assets/vendor/2cam-ui/assets/adminlte/adminlte.min.css')?>">
<link rel="stylesheet" href="<?=asset('assets/vendor/2cam-ui/assets/css/2cam-adminlte.css')?>">
<link rel="stylesheet" href="<?=asset('assets/vendor/2cam-ui/assets/css/2cam-modern-ui.css')?>">
<link rel="stylesheet" href="<?=asset('assets/vendor/2cam-ui/assets/css/2cam-dashboard-responsive.css')?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?=asset('assets/css/building-admin.css')?>">
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary cam-adminlte cam-ui-v1 density-<?=e($theme['density'])?> table-<?=e($theme['table_density'])?> kpi-<?=e($theme['kpi_style'])?>">
<script src="<?=asset('assets/vendor/2cam-ui/assets/js/2cam-sidebar-bootstrap.js')?>"></script>
<div class="app-wrapper">
<nav class="app-header navbar navbar-expand bg-body shadow-sm px-2 px-md-3">
  <a class="nav-link cam-sidebar-toggle" data-lte-toggle="sidebar" href="#" role="button" aria-label="Abrir o cerrar menú"><i class="bi bi-list"></i></a>
  <span class="cam-header-context ms-1 ms-md-2"><strong><?=e(config('app.instance_name'))?></strong><small><?=e(strtoupper((string)($user['role'] ?? 'OPERADOR')))?> · <?= $isCentral?'Property Hub Central':'Consola de operaciones' ?></small></span>
  <div class="ms-auto d-flex align-items-center gap-2">
    <span class="badge text-bg-success d-none d-md-inline">Local disponible</span>
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <span class="d-none d-sm-inline"><?=e($user['name'] ?? '')?></span></button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><span class="dropdown-item-text small text-body-secondary"><?=e($user['email'] ?? '')?></span></li>
        <?php if (!$isCentral && ($user['role'] ?? '') === 'ADMIN'): ?><li><a class="dropdown-item" href="<?=url('resident-home')?>"><i class="bi bi-phone me-2"></i>Vista residente</a></li><?php endif; ?>
        <li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="<?=url('logout')?>"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
      </ul>
    </div>
  </div>
</nav>
<?=SidebarRenderer::render([
    'home_url' => url($isCentral?'repository-dashboard':'dashboard'),
    'brand_name' => 'Property Hub',
    'company_name' => (string)config('app.instance_name'),
    'brand_mark' => 'PH',
    'site_code' => (string)config('app.instance_code'),
    'role' => strtoupper((string)($user['role'] ?? 'OPERADOR')),
], $menu, $currentUri, $activeCode)?>
<main class="app-main">
  <?php if (empty($pageHeaderSuppressed)): ?>
  <div class="app-content-header"><div class="container-fluid"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div><h3 class="mb-0"><?=e($title ?? 'Dashboard')?></h3><p class="text-body-secondary mb-0"><?=e($subtitle ?? ($isCentral?'Supervisión de instalaciones, versiones, APIs y conectores':$context['installation_name'].' · '.$context['property_name']))?></p></div>
    <?php if (!empty($headerActions)) echo $headerActions; ?>
  </div></div></div>
  <?php endif; ?>
  <div class="app-content"><div class="container-fluid cam-content">
    <?php foreach ($flashes as $flash): ?><div class="alert alert-<?=e($flash['type'])?> alert-dismissible fade show"><span><?=e($flash['message'])?></span><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endforeach; ?>
    <?= $content ?>
  </div></div>
</main>
<footer class="app-footer"><strong>2CAM Property Hub <?=e(config('app.version'))?></strong> · UI Standard <?=e(config('app.ui_standard_version'))?> · <?=e($user['role'] ?? '')?></footer>
</div>
<script src="<?=asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')?>"></script>
<script src="<?=asset('assets/vendor/2cam-ui/assets/adminlte/adminlte.min.js')?>"></script>
<script src="<?=asset('assets/vendor/2cam-ui/assets/plugins/2cam-vendor-ui.js')?>"></script>
<script src="<?=asset('assets/vendor/2cam-ui/assets/plugins/chartjs-2.7.2.bundle.js')?>"></script>
<script src="<?=asset('assets/vendor/2cam-ui/assets/js/2cam-ui.js')?>"></script>
<script src="<?=asset('assets/js/building-admin.js')?>"></script>
</body></html>
