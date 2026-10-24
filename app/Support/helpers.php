<?php
declare(strict_types=1);

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__, 2);
    return $path === '' ? $base : $base.DIRECTORY_SEPARATOR.ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

function load_env_file(): void
{
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    $file = base_path('.env');
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) continue;
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
    }
}

function env(string $key, mixed $default = null): mixed { load_env_file(); $v = getenv($key); return $v === false ? $default : $v; }
function env_bool(string $key, bool $default = false): bool { $v = env($key, null); return $v === null ? $default : (filter_var($v, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default); }

function config(string $key, mixed $default = null): mixed
{
    static $files = [];
    [$file, $nested] = array_pad(explode('.', $key, 2), 2, null);
    if (!array_key_exists($file, $files)) {
        $path = base_path('config/'.$file.'.php');
        $files[$file] = is_file($path) ? require $path : [];
    }
    if ($nested === null) return $files[$file] ?? $default;
    $value = $files[$file] ?? [];
    foreach (explode('.', $nested) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) return $default;
        $value = $value[$segment];
    }
    return $value;
}

function e(string|int|float|null $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function app_base_url(): string
{
    $configured = (string) config('app.url', '');
    if ($configured !== '') return $configured;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($scheme.'://'.$host.($dir === '/' ? '' : $dir), '/');
}
function url(string $route = 'dashboard', array $params = []): string { return 'index.php?'.http_build_query(['route' => $route] + $params); }
function asset(string $path): string
{
    $clean = ltrim($path, '/');
    // Cada release debe invalidar CSS/JS cacheados, incluso si un Service Worker
    // histórico sigue controlando /public. Esto evita pantallas sin estilo que sólo
    // se corregían después de F5.
    $version = trim((string) config('app.version', ''));
    if ($version === '' || preg_match('~^(?:https?:)?//~i', $clean)) return $clean;
    return $clean.(str_contains($clean, '?') ? '&' : '?').'v='.rawurlencode($version);
}

function ui_normalize_heading(string $value): string
{
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/\\s+/u', ' ', trim($value)) ?? trim($value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function ui_content_repeats_page_title(string $content, string $title): bool
{
    $title = ui_normalize_heading($title);
    if ($title === '') return false;
    if (!preg_match('/<h[1-6]\\b[^>]*>(.*?)<\\/h[1-6]>/is', substr($content, 0, 12000), $m)) return false;
    return ui_normalize_heading((string)$m[1]) === $title;
}
function redirect(string $route, array $params = []): never { header('Location: '.url($route, $params), true, is_post() ? 303 : 302); exit; }

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name((string) config('app.session_name', 'property_hub_session'));
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function csrf_token(): string { if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(24)); return (string) $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void
{
    $token = (string) ($_POST['_csrf'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('La sesión del formulario venció.');
    }
}

function flash(string $type, string $message): void { $_SESSION['_flash'][] = ['type' => $type, 'message' => $message]; }
function pull_flashes(): array { $value = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return is_array($value) ? $value : []; }
function current_user(): ?array { return $_SESSION['auth_user'] ?? null; }
function require_auth(): void { if (!current_user()) redirect('login'); }

function can(string $permission): bool
{
    $user = current_user();
    if (!$user) return false;
    $role = strtoupper((string) ($user['role'] ?? 'RESIDENT'));
    if ($role === 'SUPERADMIN') return true;
    $maps = [
        'ADMIN' => ['resident.app','dashboard.view','properties.view','properties.manage','areas.view','areas.manage','people.view','people.manage','visitors.view','visitors.manage','reservations.view','reservations.create','reservations.manage','bulletin.view','bulletin.manage','notifications.view','notifications.manage','emergency.view','emergency.manage','access.view','access.operate','access.manage','cameras.view','intercom.view','internet.view','integrations.view','integrations.manage','system.manage'],
        'SECURITY' => ['dashboard.view','areas.view','people.view','visitors.view','visitors.manage','reservations.view','bulletin.view','notifications.view','emergency.view','emergency.manage','access.view','access.operate','cameras.view','intercom.view'],
        'RESIDENT' => ['resident.app','dashboard.view','properties.view','areas.view','visitors.view','visitors.manage.own','reservations.view','reservations.create','bulletin.view','notifications.view','emergency.view','emergency.manage','access.view','access.operate.allowed','cameras.view','intercom.view','internet.view'],
    ];
    return in_array($permission, $maps[$role] ?? [], true);
}

function require_permission(string $permission): void
{
    require_auth();
    if (!can($permission)) {
        http_response_code(403);
        render('errors/403', ['title' => 'Acceso no autorizado'], portal_layout());
        exit;
    }
}

function route_name(): string { return preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($_GET['route'] ?? ''))) ?: ''; }
function portal_layout(): string
{
    $user = current_user();
    return (($user['portal'] ?? '') === 'resident' || str_starts_with(route_name(), 'resident-')) ? 'resident' : 'admin';
}

function render(string $view, array $data = [], string|false|null $layout = null): void
{
    $file = base_path('app/Views/'.$view.'.php');
    if (!is_file($file)) { http_response_code(500); exit('Vista no encontrada: '.e($view)); }
    extract($data, EXTR_SKIP);
    if ($layout === false) { require $file; return; }
    ob_start();
    require $file;
    $content = (string) ob_get_clean();
    // Si la vista ya trae el mismo título (a menudo junto a botones/acciones),
    // el layout no lo vuelve a imprimir. Se recupera espacio vertical sin perder contexto.
    $pageHeaderSuppressed = !empty($data['hide_page_header']) || ui_content_repeats_page_title($content, (string)($title ?? ''));
    $selected = $layout ?? portal_layout();
    $layoutFile = base_path('app/Views/layouts/'.$selected.'.php');
    if (!is_file($layoutFile)) { http_response_code(500); exit('Layout no encontrado.'); }
    require $layoutFile;
}

function format_date_es(string $date): string
{
    $months = [1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre'];
    $ts = strtotime($date);
    if ($ts === false) return $date;
    return date('j', $ts).' de '.$months[(int) date('n', $ts)].' de '.date('Y', $ts);
}

function format_datetime_es(string $date): string
{
    $ts = strtotime($date);
    return $ts === false ? $date : date('d/m/Y · H:i', $ts);
}

function status_badge(string $status): string
{
    return match ($status) {
        'approved','active','online','published','delivered','available','stable','healthy' => 'success',
        'pending','draft','scheduled','maintenance','warning','planned','research','conditional','configured','beta','development' => 'warning',
        'rejected','cancelled','offline','error','blocked','critical' => 'danger',
        'completed','read','closed','inactive','disabled','unknown','revoked','expired','deceased' => 'secondary',
        default => 'info',
    };
}

function status_label(string $status): string
{
    return match ($status) {
        'approved' => 'Aprobada', 'pending' => 'Pendiente', 'rejected' => 'Rechazada', 'cancelled' => 'Cancelada', 'completed' => 'Completada',
        'active' => 'Activo', 'online' => 'En línea', 'offline' => 'Sin conexión', 'maintenance' => 'Mantenimiento', 'scheduled' => 'Programado',
        'configured' => 'Configurado', 'warning' => 'Advertencia', 'error' => 'Error', 'planned' => 'Planificado', 'research' => 'En estudio', 'conditional' => 'Condicional', 'inactive' => 'Inactivo', 'disabled' => 'Deshabilitado', 'unknown' => 'Desconocido', 'blocked' => 'Bloqueado', 'revoked' => 'Revocado', 'expired' => 'Vencido', 'deceased' => 'Fallecido',
        'delivered' => 'Entregada', 'read' => 'Leída', 'available' => 'Disponible', default => ucfirst($status),
    };
}


function empty_state(string $title, string $message, string $icon='bi bi-inbox'): string
{
    return '<div class="empty-state text-center py-5 px-3"><i class="'.e($icon).' fs-1 text-body-secondary"></i><h5 class="mt-3 mb-1">'.e($title).'</h5><p class="text-body-secondary mb-0">'.e($message).'</p></div>';
}

function demo_mode_enabled(): bool { return (bool)config('app.demo_mode', false); }

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function post_string(string $key, string $default = ''): string { return trim((string) ($_POST[$key] ?? $default)); }
function post_int(string $key, int $default = 0): int { return filter_var($_POST[$key] ?? $default, FILTER_VALIDATE_INT) ?: $default; }

function month_calendar(string $month, array $reservations): array
{
    $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
    $first = new DateTimeImmutable($month.'-01');
    $days = (int) $first->format('t');
    $startOffset = ((int) $first->format('N')) - 1;
    $byDate = [];
    foreach ($reservations as $reservation) $byDate[$reservation['date']][] = $reservation;
    $cells = array_fill(0, $startOffset, null);
    for ($day = 1; $day <= $days; $day++) {
        $date = $month.'-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        $cells[] = ['day' => $day, 'date' => $date, 'items' => $byDate[$date] ?? []];
    }
    while (count($cells) % 7 !== 0) $cells[] = null;
    return ['month' => $month, 'label' => ucfirst(format_date_es($month.'-01')), 'cells' => $cells];
}


/**
 * Convierte la configuración funcional al contrato visual GROUP/SECTION/ITEM.
 * Los permisos se validan aquí, en servidor, antes de renderizar el menú.
 */
function sidebar_menu_for_user(array $groups): array
{
    $result = [];
    foreach ($groups as $group) {
        if (!is_array($group)) continue;
        $normalized = sidebar_normalize_node($group, 'GROUP');
        $sections = [];
        foreach ((array)($group['sections'] ?? []) as $section) {
            if (!is_array($section)) continue;
            $items = sidebar_filter_items((array)($section['items'] ?? []));
            if ($items === []) continue;
            $normalizedSection = sidebar_normalize_node($section, 'SECTION');
            $normalizedSection['items'] = $items;
            $sections[] = $normalizedSection;
        }
        $items = sidebar_filter_items((array)($group['items'] ?? []));
        if ($sections === [] && $items === []) continue;
        $normalized['sections'] = $sections;
        $normalized['items'] = $items;
        $result[] = $normalized;
    }
    return $result;
}

function sidebar_filter_items(array $items): array
{
    $result = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $permission = (string)($item['permission'] ?? '');
        if ($permission !== '' && !can($permission)) continue;
        $route = (string)($item['route'] ?? '');
        if ($route === '') continue;
        $node = sidebar_normalize_node($item, 'ITEM');
        $node['href'] = url($route);
        $node['route'] = $route;
        $result[] = $node;
    }
    return $result;
}

function sidebar_normalize_node(array $node, string $type): array
{
    $icon = preg_replace('/[^a-zA-Z0-9 _-]/', '', (string)($node['icon'] ?? 'bi bi-circle')) ?: 'bi bi-circle';
    return [
        'code' => (string)($node['code'] ?? ''),
        'label' => (string)($node['label'] ?? ''),
        'entry_type' => $type,
        'active' => true,
        'icon_html' => '<i class="'.e($icon).'" aria-hidden="true"></i>',
    ];
}

function sidebar_active_code(array $groups, string $route): ?string
{
    foreach ($groups as $group) {
        foreach ((array)($group['items'] ?? []) as $item) {
            if (($item['route'] ?? null) === $route) return (string)($item['code'] ?? '');
        }
        foreach ((array)($group['sections'] ?? []) as $section) {
            foreach ((array)($section['items'] ?? []) as $item) {
                if (($item['route'] ?? null) === $route) return (string)($item['code'] ?? '');
            }
        }
    }
    return null;
}

function health_badge(string $status): string
{
    return match ($status) {
        'healthy' => 'success', 'warning' => 'warning', 'critical' => 'danger', 'offline' => 'secondary', default => 'info',
    };
}
function health_label(string $status): string
{
    return match ($status) {
        'healthy' => 'Saludable', 'warning' => 'Advertencia', 'critical' => 'Crítico', 'offline' => 'Sin conexión', default => 'Desconocido',
    };
}
function installation_type_label(string $type): string
{
    return match ($type) {
        'urbanization' => 'Urbanización',
        'residential_complex' => 'Conjunto residencial',
        'residential' => 'Edificio residencial',
        'office_building' => 'Edificio de oficinas',
        'office' => 'Oficinas',
        'shopping_center' => 'Centro comercial',
        'commercial' => 'Comercial / locales',
        'mixed' => 'Complejo mixto',
        'campus' => 'Campus',
        'industrial' => 'Industrial',
        default => 'Otro',
    };
}
function installation_role_label(string $role): string
{
    return match ($role) {
        'central_repository' => 'Property Hub Central',
        'building_node' => 'Property Node',
        'edge_agent' => 'Edge Agent',
        default => 'Instalación',
    };
}
function installation_role_short(string $role): string
{
    return match ($role) {
        'central_repository' => 'CENTRAL',
        'building_node' => 'NODE',
        'edge_agent' => 'EDGE',
        default => strtoupper($role ?: 'INST'),
    };
}

function data_scope_label(string $scope): string
{
    return match ($scope) {
        'metadata_only' => 'Solo metadatos', 'aggregates' => 'Métricas agregadas', 'support_snapshot' => 'Snapshot de soporte', 'operational_mirror' => 'Espejo operativo', default => 'Métricas agregadas',
    };
}
function technology_label(string $code): string
{
    return match ($code) {
        'zkteco'=>'ZKTeco','hikvision'=>'Hikvision','dahua'=>'Dahua','ttlock'=>'TTLock','grandstream'=>'Grandstream',
        'unifi'=>'Ubiquiti UniFi','vimar'=>'Vimar','sonoff'=>'Sonoff / eWeLink','onvif'=>'ONVIF','rtsp'=>'RTSP / Video Gateway',
        'lorawan'=>'LoRaWAN / IoT','generic_relay'=>'Relés IP / MQTT / HTTP',default=>ucfirst(str_replace('_',' ',$code)),
    };
}

function query_string(string $key, string $default=''): string { return trim((string)($_GET[$key] ?? $default)); }
function query_int(string $key, int $default=0): int { $v=filter_var($_GET[$key] ?? $default,FILTER_VALIDATE_INT); return $v===false?$default:(int)$v; }
function query_per_page(int $default=25): int { $v=query_int('per_page',$default); return in_array($v,[10,25,50,100],true)?$v:$default; }

function pagination_html(string $route,array $pageData,array $params=[]): string
{
    $page=max(1,(int)($pageData['page']??1));$pages=max(1,(int)($pageData['pages']??1));$total=max(0,(int)($pageData['total']??0));
    if($pages<=1)return '<div class="small text-body-secondary">'.e($total).' registro(s)</div>';
    $start=max(1,$page-2);$end=min($pages,$page+2);$html='<nav aria-label="Paginación"><ul class="pagination pagination-sm mb-0">';
    $make=function(int $p,string $label,bool $disabled=false,bool $active=false)use($route,$params){$classes=trim(($disabled?' disabled':'').($active?' active':''));$href=$disabled?'#':url($route,$params+['page'=>$p]);return '<li class="page-item'.$classes.'"><a class="page-link" href="'.e($href).'">'.$label.'</a></li>';};
    $html.=$make(max(1,$page-1),'&laquo;',$page===1);
    for($i=$start;$i<=$end;$i++)$html.=$make($i,(string)$i,false,$i===$page);
    $html.=$make(min($pages,$page+1),'&raquo;',$page===$pages);
    return $html.'</ul></nav>';
}

function property_type_label(string $type): string
{
    return match($type){'site'=>'Conjunto / predio','urbanization'=>'Urbanización','sector'=>'Sector','street'=>'Calle','block'=>'Manzana','parcel'=>'Parcela','building'=>'Edificio','tower'=>'Torre','floor'=>'Piso','ground_floor'=>'Planta baja','basement'=>'Sótano','mezzanine'=>'Mezzanina','terrace'=>'Terraza','roof'=>'Azotea','technical_floor'=>'Piso técnico','apartment'=>'Apartamento','house'=>'Casa','townhouse'=>'Town house','office'=>'Oficina','commercial_unit'=>'Local comercial','warehouse'=>'Depósito','storage'=>'Maletero / depósito','parking'=>'Puesto de estacionamiento','parking_zone'=>'Zona de estacionamiento','garage'=>'Estacionamiento / garaje','guard_post'=>'Garita','park'=>'Parque','common'=>'Área común','technical'=>'Área técnica',default=>'Otro'};
}
function property_row_type_label(array $row): string
{
    $type=(string)($row['property_type']??'other');
    $meta=json_decode((string)($row['metadata_json']??''),true);if(!is_array($meta))$meta=[];
    if(($meta['structure_role']??'physical')==='organizer')return 'Contenedor organizativo';
    $label=strtoupper(trim((string)($row['floor_label']??'')));
    // Compatibilidad con niveles elevados creados históricamente como basement.
    // No reinterpreta la BD: evita mostrar el absurdo visual "Sótano · E1".
    if($type==='basement' && preg_match('/^E\s*\d+$/',$label)) return 'Nivel elevado';
    return property_type_label($type);
}
function relationship_label(string $type): string
{
    return match($type){'owner'=>'Propietario','tenant'=>'Inquilino','resident'=>'Residente','family'=>'Familiar','representative'=>'Representante','employee'=>'Empleado','domestic_staff'=>'Personal doméstico','driver'=>'Chofer','security'=>'Seguridad privada','maintenance'=>'Mantenimiento','authorized'=>'Autorizado',default=>'Otro'};
}
function area_type_label(string $type): string
{
    return match($type){'common'=>'Común','reservable'=>'Reservable','security'=>'Seguridad','technical'=>'Técnica','commercial'=>'Comercial','office'=>'Oficina','parking'=>'Estacionamiento','circulation'=>'Circulación',default=>'Otra'};
}

function area_scope_label(string $scope): string
{
    return match($scope){'common'=>'Común','private'=>'Privado','technical'=>'Técnico','service'=>'Servicio','restricted'=>'Restringido','public'=>'Público',default=>'Común'};
}
function area_usage_label(string $usage): string
{
    return match($usage){'general'=>'General','lobby'=>'Lobby / recepción','circulation'=>'Circulación','parking'=>'Estacionamiento','office'=>'Oficina / administración','commercial'=>'Comercial','technical'=>'Infraestructura técnica','security'=>'Seguridad / control','recreation'=>'Recreación','amenity'=>'Amenidad / servicio común','reservable'=>'Espacio reservable','storage'=>'Depósito / almacenamiento','residential'=>'Residencial','other'=>'Otro',default=>ucfirst(str_replace('_',' ',$usage))};
}
function structure_role_label(array $row): string
{
    $meta=json_decode((string)($row['metadata_json']??''),true);if(!is_array($meta))$meta=[];
    return (($meta['structure_role']??'physical')==='organizer')?'Contenedor organizativo':'Ubicación física';
}
function structure_accepts_resources(array $row): bool
{
    $meta=json_decode((string)($row['metadata_json']??''),true);if(!is_array($meta))$meta=[];
    if(array_key_exists('accepts_resources',$meta))return !empty($meta['accepts_resources']);
    return (($meta['structure_role']??'physical')!=='organizer');
}
function connector_domain_label(string $domain): string
{
    return match($domain){'access'=>'Control de acceso','video'=>'Video y cámaras','intercom'=>'Intercomunicación','automation'=>'Automatización','network'=>'Red e Internet','identity'=>'Identidad','notification'=>'Notificaciones',default=>'Otro'};
}

function connector_target_type_label(string $type): string
{
    return match($type){
        'platform_server'=>'Servidor o plataforma local',
        'cloud_platform'=>'Plataforma cloud',
        'protocol_service'=>'Servicio de protocolo',
        'direct_device'=>'Dispositivo directo',
        'technology_gateway'=>'Gateway tecnológico',
        default=>'Otro destino',
    };
}
function asset_role_label(string $role): string
{
    return match($role){
        'managed_device'=>'Administrado por plataforma',
        'direct_device'=>'Conectado directamente',
        'platform_server'=>'Servidor / plataforma',
        'technology_gateway'=>'Gateway tecnológico',
        'reference_asset'=>'Referencia de inventario',
        default=>'Activo técnico',
    };
}
function asset_stage_label(string $stage): string
{
    return match($stage){
        'planned'=>'Planificado',
        'installed'=>'Instalado',
        'commissioned'=>'En servicio',
        'retired'=>'Retirado',
        default=>'No definido',
    };
}
function connection_path_label(string $path): string
{
    return match($path){
        'via_platform'=>'A través de plataforma',
        'direct'=>'Conexión directa',
        'vendor_cloud'=>'Nube del fabricante',
        'technology_gateway'=>'Gateway tecnológico',
        'manual_reference'=>'Referencia manual',
        default=>'No definida',
    };
}
function device_type_label(string $type): string
{
    return match($type){'access_controller'=>'Controlador de acceso','reader'=>'Lector','lock'=>'Cerradura','relay'=>'Relé','camera'=>'Cámara','nvr'=>'NVR/DVR','intercom'=>'Intercomunicador','phone'=>'Teléfono IP','speaker'=>'Altavoz / perifonía','network_controller'=>'Controlador de red','network_switch'=>'Switch de red','poe_switch'=>'Switch PoE','access_point'=>'Punto de acceso Wi‑Fi','patch_panel'=>'Patch panel','fiber_distributor'=>'Distribuidor de fibra','rack'=>'Rack / gabinete','ups'=>'UPS / respaldo','gateway'=>'Gateway','elevator_controller'=>'Controlador de ascensor','turnstile'=>'Torniquete','barrier'=>'Barrera vehicular','door_operator'=>'Operador de puerta','fire_panel'=>'Panel de incendio','pump'=>'Bomba','meter'=>'Medidor','sensor'=>'Sensor','iot_actuator'=>'Actuador IoT',default=>'Otro'};
}

/**
 * Normaliza nombres de personas para UI/BD: cada palabra inicia en mayúscula y el resto en minúscula.
 * Se usa tanto en creación manual como en adopciones de fabricantes.
 */
function normalize_person_name_part(string $value): string
{
    $value=trim((string)(preg_replace('/\s+/u',' ',$value)??$value));
    if($value==='')return '';
    if(function_exists('mb_convert_case')&&function_exists('mb_strtolower'))return mb_convert_case(mb_strtolower($value,'UTF-8'),MB_CASE_TITLE,'UTF-8');
    // Fallback UTF-8 para instalaciones sin mbstring. strtolower()/ucwords() solos no
    // convierten correctamente ÁÉÍÓÚÜÑ y producirían resultados como PÉrez.
    $lowerMap=['À'=>'à','Á'=>'á','Â'=>'â','Ã'=>'ã','Ä'=>'ä','Å'=>'å','Æ'=>'æ','Ç'=>'ç','È'=>'è','É'=>'é','Ê'=>'ê','Ë'=>'ë','Ì'=>'ì','Í'=>'í','Î'=>'î','Ï'=>'ï','Ð'=>'ð','Ñ'=>'ñ','Ò'=>'ò','Ó'=>'ó','Ô'=>'ô','Õ'=>'õ','Ö'=>'ö','Ø'=>'ø','Ù'=>'ù','Ú'=>'ú','Û'=>'û','Ü'=>'ü','Ý'=>'ý','Þ'=>'þ','Š'=>'š','Ž'=>'ž'];
    $upperMap=array_flip($lowerMap);$value=strtolower(strtr($value,$lowerMap));
    return (string)(preg_replace_callback('/(^|[\s\-\'’])([\p{L}])/u',static function(array $m) use($upperMap): string{$c=(string)$m[2];return (string)$m[1].($upperMap[$c]??strtoupper($c));},$value)??$value);
}
function normalize_person_display_name(string $first,string $last,string $fallback=''): string
{
    $first=normalize_person_name_part($first);$last=normalize_person_name_part($last);
    $full=trim($first.' '.$last);
    return $full!==''?$full:normalize_person_name_part($fallback);
}
