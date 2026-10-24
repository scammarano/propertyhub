<?php
declare(strict_types=1);
require_once __DIR__ . '/SidebarLayout.php';

$groups = [
  ['code' => 'home', 'label' => 'Inicio', 'protected' => true, 'items' => [
    ['code' => 'home.dashboard', 'label' => 'Dashboard', 'permission' => 'dashboard.view', 'protected' => true],
  ]],
  ['code' => 'finance', 'label' => 'Finanzas', 'sections' => [
    ['code' => 'finance.tables', 'label' => 'Tablas', 'items' => [
      ['code' => 'finance.accounts', 'label' => 'Cuentas', 'permission' => 'accounts.view'],
    ]],
    ['code' => 'finance.processes', 'label' => 'Procesos', 'items' => [
      ['code' => 'finance.expenses', 'label' => 'Gastos', 'permission' => 'expenses.view'],
      ['code' => 'finance.payments', 'label' => 'Pagos', 'permission' => 'payments.view'],
    ]],
  ]],
];
$stored = [];      // SELECT * FROM sidebar_layout_preferences
$initialized = false;
$layout = SidebarLayout::normalize($groups, $stored, $initialized);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($layout, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
