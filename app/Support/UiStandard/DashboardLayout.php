<?php
declare(strict_types=1);

/**
 * Distribución GLOBAL del dashboard.
 *
 * Regla 2CAM UI 1.1: solo SUPERADMIN puede modificar esta configuración.
 * Los usuarios normales consumen la distribución global y solo ven widgets
 * permitidos por sus permisos y alcance de datos.
 */
final class DashboardLayout
{
    public static function normalize(array $catalog, array $stored, array $allowedCodes): array
    {
        $byCode=[];
        foreach($stored as $row)$byCode[(string)$row['widget_code']]=$row;
        $result=[];$fallbackOrder=1000;
        foreach($allowedCodes as $code){
            if(!isset($catalog[$code]))continue;
            $default=$catalog[$code];$row=$byCode[$code]??[];
            $result[]=[
                'widget_code'=>$code,
                'name'=>$default['name'],
                'group'=>$default['group']??'General',
                'sort_order'=>(int)($row['sort_order']??$fallbackOrder++),
                'active'=>(int)($row['active']??1),
                'cols'=>max(2,min(12,(int)($row['cols']??$default['cols']??4))),
                'row_span'=>max(1,min(8,(int)($row['row_span']??$default['rows']??2))),
                'title_override'=>trim((string)($row['title_override']??'')),
                'settings_json'=>$row['settings_json']??null,
            ];
        }
        usort($result,static fn($a,$b)=>($a['sort_order']<=>$b['sort_order'])?:strcmp($a['widget_code'],$b['widget_code']));
        return $result;
    }

    public static function assertSuperadmin(string $roleCode): void
    {
        if(strtoupper(trim($roleCode))!=='SUPERADMIN'){
            throw new RuntimeException('Solo el superadministrador puede modificar el dashboard global.');
        }
    }
}
