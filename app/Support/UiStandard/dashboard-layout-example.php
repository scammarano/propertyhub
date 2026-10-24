<?php
declare(strict_types=1);
/** Ejemplo global. La pantalla de edición debe validar SUPERADMIN. */
require_once __DIR__.'/DashboardLayout.php';
DashboardLayout::assertSuperadmin($currentRoleCode??'SUPERADMIN');
$catalog=[
    'kpi_primary'=>['name'=>'Indicador principal','group'=>'Resumen','cols'=>3,'rows'=>2],
    'activity_chart'=>['name'=>'Actividad por período','group'=>'Análisis','cols'=>8,'rows'=>5],
    'status_breakdown'=>['name'=>'Distribución por estado','group'=>'Análisis','cols'=>4,'rows'=>5],
];
$allowedCodes=array_keys($catalog);
$stored=[]; // SELECT * FROM dashboard_widget_layouts ORDER BY sort_order
$layout=DashboardLayout::normalize($catalog,$stored,$allowedCodes);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($layout,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
