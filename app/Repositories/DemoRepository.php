<?php
declare(strict_types=1);

/**
 * Repositorio temporal usado únicamente cuando APP_DEMO_MODE=true.
 *
 * En una instalación real todos los métodos de lectura devuelven colecciones
 * vacías y las operaciones de escritura quedan bloqueadas hasta que el módulo
 * correspondiente utilice su repositorio persistente.
 */
final class DemoRepository
{
    private const SESSION_KEYS = [
        'demo_reservations',
        'demo_bulletins',
        'demo_notifications',
        'demo_invitations',
        'active_context',
    ];

    public static function enabled(): bool
    {
        return (bool) config('app.demo_mode', false);
    }

    public static function boot(): void
    {
        if (!self::enabled()) {
            self::clearSessionData();
            return;
        }
        if (!isset($_SESSION['demo_reservations'])) $_SESSION['demo_reservations'] = self::seedReservations();
        if (!isset($_SESSION['demo_bulletins'])) $_SESSION['demo_bulletins'] = self::seedBulletins();
        if (!isset($_SESSION['demo_notifications'])) $_SESSION['demo_notifications'] = self::seedNotifications();
        if (!isset($_SESSION['demo_invitations'])) $_SESSION['demo_invitations'] = self::seedInvitations();
        if (!isset($_SESSION['active_context'])) $_SESSION['active_context'] = 0;
    }

    public static function clearSessionData(): void
    {
        foreach (self::SESSION_KEYS as $key) unset($_SESSION[$key]);
    }

    private static function requireEnabled(): void
    {
        if (!self::enabled()) {
            throw new RuntimeException('Este módulo todavía no tiene persistencia operativa habilitada. Los datos de demostración están desactivados correctamente.');
        }
    }

    public static function memberships(): array
    {
        if (!self::enabled()) return [];
        return [
            ['installation_code'=>'TERRAZA','installation_name'=>'Urbanización Terraza del Ávila','installation_type'=>'urbanization','property_code'=>'CASA-A12','property_name'=>'Casa A-12 · Calle Norte','role'=>'Propietario'],
            ['installation_code'=>'PREMIER','installation_name'=>'Conjunto Premier Las Mercedes','installation_type'=>'residential_complex','property_code'=>'APT-8B','property_name'=>'Apartamento 8-B','role'=>'Propietario'],
            ['installation_code'=>'VILLA2','installation_name'=>'Portal del Ávila 2','installation_type'=>'residential','property_code'=>'APT-3A','property_name'=>'Apartamento 3-A','role'=>'Inquilino'],
            ['installation_code'=>'CENTRAL','installation_name'=>'Centro Empresarial Central','installation_type'=>'commercial','property_code'=>'LOCAL-PB04','property_name'=>'Local PB-04','role'=>'Representante'],
        ];
    }

    public static function currentContext(): array
    {
        $items = self::memberships();
        if ($items === []) {
            return [
                'installation_code'=>(string)config('app.instance_code',''),
                'installation_name'=>(string)config('app.instance_name','Property Hub'),
                'installation_type'=>(string)config('app.instance_type','other'),
                'property_code'=>'',
                'property_name'=>'Sin unidad vinculada',
                'role'=>'Sin relación asignada',
            ];
        }
        $i = max(0, min((int)($_SESSION['active_context'] ?? 0), count($items)-1));
        return $items[$i];
    }

    public static function setContext(int $index): void
    {
        self::requireEnabled();
        $items = self::memberships();
        if (!isset($items[$index])) throw new InvalidArgumentException('Propiedad no válida.');
        $_SESSION['active_context'] = $index;
    }

    public static function areas(): array
    {
        if (!self::enabled()) return [];
        return [
            ['id'=>1,'name'=>'Salón de eventos','type'=>'reservable','capacity'=>80,'reservable'=>true,'security'=>true,'rate'=>120,'deposit'=>100,'approval'=>true,'schedule'=>'08:00–23:00','icon'=>'bi bi-balloon'],
            ['id'=>2,'name'=>'Cowork','type'=>'reservable','capacity'=>18,'reservable'=>true,'security'=>true,'rate'=>20,'deposit'=>0,'approval'=>false,'schedule'=>'06:00–22:00','icon'=>'bi bi-laptop'],
            ['id'=>3,'name'=>'Parrillera','type'=>'reservable','capacity'=>24,'reservable'=>true,'security'=>true,'rate'=>45,'deposit'=>40,'approval'=>true,'schedule'=>'10:00–22:00','icon'=>'bi bi-fire'],
            ['id'=>4,'name'=>'Cancha multiuso','type'=>'reservable','capacity'=>20,'reservable'=>true,'security'=>true,'rate'=>15,'deposit'=>0,'approval'=>false,'schedule'=>'06:00–21:00','icon'=>'bi bi-dribbble'],
            ['id'=>5,'name'=>'Lobby principal','type'=>'security','capacity'=>0,'reservable'=>false,'security'=>true,'rate'=>0,'deposit'=>0,'approval'=>false,'schedule'=>'24 horas','icon'=>'bi bi-building'],
            ['id'=>6,'name'=>'Cuarto de CCTV','type'=>'technical','capacity'=>2,'reservable'=>false,'security'=>true,'rate'=>0,'deposit'=>0,'approval'=>false,'schedule'=>'Restringido','icon'=>'bi bi-camera-reels'],
            ['id'=>7,'name'=>'Cuarto de bombas','type'=>'technical','capacity'=>2,'reservable'=>false,'security'=>true,'rate'=>0,'deposit'=>0,'approval'=>false,'schedule'=>'Restringido','icon'=>'bi bi-gear'],
            ['id'=>8,'name'=>'Plaza comercial','type'=>'common','capacity'=>150,'reservable'=>false,'security'=>true,'rate'=>0,'deposit'=>0,'approval'=>false,'schedule'=>'06:00–23:00','icon'=>'bi bi-shop'],
        ];
    }

    public static function reservations(): array
    {
        if (!self::enabled()) return [];
        self::boot();
        return array_values($_SESSION['demo_reservations']);
    }

    public static function createReservation(array $data): int
    {
        self::requireEnabled(); self::boot();
        $area=self::findArea((int)($data['area_id']??0));
        if(!$area||!$area['reservable']) throw new InvalidArgumentException('Seleccione un área reservable.');
        $date=(string)($data['date']??'');$start=(string)($data['start']??'');$end=(string)($data['end']??'');
        if(!$date||!$start||!$end||strtotime($date.' '.$end)<=strtotime($date.' '.$start)) throw new InvalidArgumentException('Revise la fecha y el horario.');
        foreach(self::reservations() as $existing){
            if((int)$existing['area_id']!==(int)$area['id']||$existing['date']!==$date||in_array($existing['status'],['rejected','cancelled'],true)) continue;
            if($start<$existing['end']&&$end>$existing['start']) throw new InvalidArgumentException('El horario se solapa con otra solicitud activa.');
        }
        $id=count($_SESSION['demo_reservations'])?max(array_column($_SESSION['demo_reservations'],'id'))+1:1;
        $context=self::currentContext();$user=current_user()??['name'=>'Usuario'];
        $_SESSION['demo_reservations'][]=[
            'id'=>$id,'area_id'=>$area['id'],'area'=>$area['name'],'date'=>$date,'start'=>$start,'end'=>$end,
            'status'=>$area['approval']?'pending':'approved','requester'=>$user['name'],'unit'=>$context['property_name'],
            'purpose'=>trim((string)($data['purpose']??'Reserva de área')),'attendees'=>(int)($data['attendees']??1),
            'amount'=>(float)$area['rate'],'deposit'=>(float)$area['deposit'],'created_at'=>date('Y-m-d H:i:s')
        ];
        return $id;
    }

    public static function updateReservationStatus(int $id,string $status): void
    {
        self::requireEnabled(); self::boot();
        if(!in_array($status,['approved','rejected','cancelled','completed'],true)) throw new InvalidArgumentException('Estado no permitido.');
        foreach($_SESSION['demo_reservations'] as &$item){if((int)$item['id']===$id){$item['status']=$status;return;}}
        throw new InvalidArgumentException('Solicitud no encontrada.');
    }

    public static function bulletins(): array
    {
        if (!self::enabled()) return [];
        self::boot(); return array_values($_SESSION['demo_bulletins']);
    }

    public static function createBulletin(array $data): int
    {
        self::requireEnabled(); self::boot();
        $title=trim((string)($data['title']??''));$body=trim((string)($data['body']??''));
        if($title===''||$body==='') throw new InvalidArgumentException('Título y contenido son obligatorios.');
        $id=count($_SESSION['demo_bulletins'])?max(array_column($_SESSION['demo_bulletins'],'id'))+1:1;
        array_unshift($_SESSION['demo_bulletins'],['id'=>$id,'title'=>$title,'body'=>$body,'category'=>(string)($data['category']??'general'),'audience'=>(string)($data['audience']??'all'),'pinned'=>!empty($data['pinned']),'published_at'=>date('Y-m-d H:i:s'),'expires_at'=>(string)($data['expires_at']??''),'author'=>(current_user()['name']??'Administración')]);
        return $id;
    }

    public static function notifications(): array
    {
        if (!self::enabled()) return [];
        self::boot(); return array_values($_SESSION['demo_notifications']);
    }

    public static function createNotification(array $data): int
    {
        self::requireEnabled(); self::boot();
        $title=trim((string)($data['title']??''));$message=trim((string)($data['message']??''));
        if($title===''||$message==='') throw new InvalidArgumentException('Título y mensaje son obligatorios.');
        $id=count($_SESSION['demo_notifications'])?max(array_column($_SESSION['demo_notifications'],'id'))+1:1;
        array_unshift($_SESSION['demo_notifications'],['id'=>$id,'title'=>$title,'message'=>$message,'priority'=>(string)($data['priority']??'normal'),'audience'=>(string)($data['audience']??'all'),'channels'=>['in_app'],'status'=>'delivered','sent_at'=>date('Y-m-d H:i:s')]);
        return $id;
    }

    public static function emergencyContacts(): array
    {
        if (!self::enabled()) return [];
        return [
            ['name'=>'Garita principal','number'=>'0212-555-0101','available'=>'24 horas','icon'=>'bi bi-shield-check'],
            ['name'=>'Garita norte','number'=>'0212-555-0103','available'=>'24 horas','icon'=>'bi bi-shield'],
            ['name'=>'Administración','number'=>'0212-555-0102','available'=>'Lun–Vie 08:00–17:00','icon'=>'bi bi-building-gear'],
            ['name'=>'Bomberos','number'=>'911','available'=>'24 horas','icon'=>'bi bi-fire'],
            ['name'=>'Emergencias médicas','number'=>'911','available'=>'24 horas','icon'=>'bi bi-heart-pulse'],
            ['name'=>'Empresa de ascensores','number'=>'0414-555-0188','available'=>'24 horas','icon'=>'bi bi-arrow-up-square'],
            ['name'=>'Electricidad / planta','number'=>'0412-555-0199','available'=>'Guardia técnica','icon'=>'bi bi-lightning-charge'],
        ];
    }

    public static function people(): array
    {
        if (!self::enabled()) return [];
        return [
            ['name'=>'María González','type'=>'Propietaria','unit'=>'Apartamento 8-B','credential'=>'Tarjeta + móvil','status'=>'active'],
            ['name'=>'Carlos Rivas','type'=>'Inquilino','unit'=>'Apartamento 3-A','credential'=>'Tarjeta','status'=>'active'],
            ['name'=>'Luisa Pérez','type'=>'Personal doméstico','unit'=>'Apartamento 8-B','credential'=>'Huella','status'=>'active'],
            ['name'=>'Seguridad Alfa C.A.','type'=>'Empresa contratista','unit'=>'Áreas comunes','credential'=>'12 personas','status'=>'active'],
        ];
    }

    public static function visitors(): array
    {
        if (!self::enabled()) return [];
        return [
            ['name'=>'Juan Pérez','unit'=>'Apartamento 8-B','host'=>'María González','entry'=>date('Y-m-d').' 10:35','exit'=>'','status'=>'active','credential'=>'Provisional 014'],
            ['name'=>'Ana Torres','unit'=>'Local PB-04','host'=>'Servicios ABC','entry'=>date('Y-m-d',strtotime('-1 day')).' 14:10','exit'=>date('Y-m-d',strtotime('-1 day')).' 15:02','status'=>'completed','credential'=>'QR'],
            ['name'=>'Técnico de ascensor','unit'=>'Área técnica','host'=>'Administración','entry'=>date('Y-m-d',strtotime('+1 day')).' 09:00','exit'=>'','status'=>'scheduled','credential'=>'Pendiente'],
        ];
    }

    public static function accessPoints(): array
    {
        if (!self::enabled()) return [];
        return [
            ['name'=>'Entrada Garita Principal','area'=>'Acceso principal','connector'=>'ZKTeco BioSecurity','status'=>'online','action'=>'Tarjeta / QR / apertura autorizada'],
            ['name'=>'Barrera Garita Norte','area'=>'Acceso norte','connector'=>'ZKTeco BioSecurity','status'=>'online','action'=>'Tag vehicular'],
            ['name'=>'Portón Garita Sur','area'=>'Acceso sur','connector'=>'UniFi Access','status'=>'online','action'=>'Tag / apertura remota'],
            ['name'=>'Puerta Casa Club','area'=>'Casa club','connector'=>'TTLock','status'=>'online','action'=>'PIN temporal / móvil'],
            ['name'=>'Iluminación Parque','area'=>'Parque central','connector'=>'Relé IP / LoRaWAN','status'=>'online','action'=>'Horario / automatización'],
        ];
    }

    public static function cameras(): array
    {
        if (!self::enabled()) return [];
        return [
            ['name'=>'Garita principal','brand'=>'Hikvision','protocol'=>'ONVIF / RTSP','status'=>'online','visibility'=>'Residentes y vigilancia'],
            ['name'=>'Entrada Garita Norte','brand'=>'UniFi Protect','protocol'=>'Protect API','status'=>'online','visibility'=>'Vigilancia'],
            ['name'=>'Parque central','brand'=>'Bimar','protocol'=>'ONVIF','status'=>'online','visibility'=>'Residentes y vigilancia'],
            ['name'=>'Perímetro este','brand'=>'Dahua','protocol'=>'ONVIF / RTSP','status'=>'online','visibility'=>'Vigilancia'],
        ];
    }

    public static function connectors(): array
    {
        if (!self::enabled()) return [];
        return [
            ['name'=>'ZKTeco BioSecurity','domain'=>'Control de acceso','capabilities'=>'Personas, credenciales, eventos, ascensores','status'=>'planned'],
            ['name'=>'TTLock','domain'=>'Cerraduras inteligentes','capabilities'=>'PIN temporales, eKeys, eventos, gateways','status'=>'planned'],
            ['name'=>'Grandstream','domain'=>'Intercomunicación','capabilities'=>'SIP, extensiones, intercom, perifonía','status'=>'planned'],
            ['name'=>'UniFi Access','domain'=>'Control de acceso','capabilities'=>'Puertas, credenciales, eventos','status'=>'planned'],
            ['name'=>'UniFi Protect','domain'=>'Video','capabilities'=>'Cámaras, eventos, grabaciones','status'=>'planned'],
            ['name'=>'UniFi Network','domain'=>'Internet','capabilities'=>'Redes de invitados, AP, vouchers','status'=>'planned'],
            ['name'=>'Hikvision','domain'=>'Video y acceso','capabilities'=>'ISAPI, HikCentral, ONVIF','status'=>'planned'],
            ['name'=>'Dahua','domain'=>'Video y acceso','capabilities'=>'DSS, CGI, NetSDK, ONVIF','status'=>'planned'],
            ['name'=>'Vimar','domain'=>'Videoportero y automatización','capabilities'=>'Relés, intercom, integración según modelo','status'=>'research'],
            ['name'=>'ONVIF / RTSP','domain'=>'Video genérico','capabilities'=>'Descubrimiento, transmisión, eventos','status'=>'planned'],
            ['name'=>'LoRaWAN / IoT','domain'=>'Sensores y automatización','capabilities'=>'Gateways, telemetría, alarmas, batería y comandos de bajo volumen','status'=>'planned'],
        ];
    }

    public static function invitations(): array
    {
        if (!self::enabled()) return [];
        self::boot(); return array_values($_SESSION['demo_invitations']);
    }

    public static function createInvitation(array $data): int
    {
        self::requireEnabled(); self::boot();
        $name=trim((string)($data['name']??''));$date=(string)($data['date']??'');$start=(string)($data['start']??'');$end=(string)($data['end']??'');
        if($name===''||$date===''||$start===''||$end==='') throw new InvalidArgumentException('Nombre, fecha y horario son obligatorios.');
        if(strtotime($date.' '.$end)<=strtotime($date.' '.$start)) throw new InvalidArgumentException('El horario final debe ser posterior al inicial.');
        $id=count($_SESSION['demo_invitations'])?max(array_column($_SESSION['demo_invitations'],'id'))+1:1;$context=self::currentContext();
        array_unshift($_SESSION['demo_invitations'],['id'=>$id,'name'=>$name,'document'=>trim((string)($data['document']??'')),'phone'=>trim((string)($data['phone']??'')),'date'=>$date,'start'=>$start,'end'=>$end,'vehicle'=>trim((string)($data['vehicle']??'')),'unit'=>$context['property_name'],'status'=>'scheduled','code'=>'INV-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8)),'created_at'=>date('Y-m-d H:i:s')]);
        return $id;
    }

    public static function markNotificationRead(int $id): void
    {
        self::requireEnabled(); self::boot();
        foreach($_SESSION['demo_notifications'] as &$item){if((int)$item['id']===$id){$item['status']='read';return;}}
    }

    public static function unreadNotificationCount(): int
    {
        return count(array_filter(self::notifications(),fn(array $n):bool=>($n['status']??'')!=='read'));
    }

    public static function dashboardMetrics(): array
    {
        return [
            'properties'=>count(self::memberships()),
            'people'=>count(self::people()),
            'visitors_active'=>count(array_filter(self::visitors(),fn(array $v):bool=>$v['status']==='active')),
            'reservations_pending'=>count(array_filter(self::reservations(),fn(array $r):bool=>$r['status']==='pending')),
            'notifications_unread'=>self::unreadNotificationCount(),
            'connectors'=>count(self::connectors()),
        ];
    }

    public static function findArea(int $id): ?array
    {
        foreach(self::areas() as $a) if((int)$a['id']===$id) return $a;
        return null;
    }

    private static function seedInvitations(): array
    {
        return [
            ['id'=>1,'name'=>'Pedro Suárez','document'=>'V-12.345.678','phone'=>'0414-555-0100','date'=>date('Y-m-d',strtotime('+1 day')),'start'=>'15:00','end'=>'18:00','vehicle'=>'AB123CD','unit'=>'Apartamento 8-B','status'=>'scheduled','code'=>'INV-7A2B4C1D','created_at'=>date('Y-m-d H:i:s')],
            ['id'=>2,'name'=>'Laura Méndez','document'=>'V-20.111.222','phone'=>'0424-555-0200','date'=>date('Y-m-d'),'start'=>'09:00','end'=>'12:00','vehicle'=>'','unit'=>'Apartamento 8-B','status'=>'active','code'=>'INV-4F8A9C2E','created_at'=>date('Y-m-d H:i:s',strtotime('-1 hour'))],
        ];
    }

    private static function seedReservations(): array
    {
        $month=date('Y-m');$d=(int)date('d');
        $day=fn(int $offset)=>$month.'-'.str_pad((string)max(1,min(28,$d+$offset)),2,'0',STR_PAD_LEFT);
        return [
            ['id'=>1,'area_id'=>1,'area'=>'Salón de eventos','date'=>$day(2),'start'=>'18:00','end'=>'22:00','status'=>'pending','requester'=>'María González','unit'=>'Apartamento 8-B','purpose'=>'Cumpleaños familiar','attendees'=>35,'amount'=>120,'deposit'=>100,'created_at'=>date('Y-m-d H:i:s')],
            ['id'=>2,'area_id'=>2,'area'=>'Cowork','date'=>$day(1),'start'=>'09:00','end'=>'12:00','status'=>'approved','requester'=>'Carlos Rivas','unit'=>'Apartamento 3-A','purpose'=>'Reunión de trabajo','attendees'=>5,'amount'=>20,'deposit'=>0,'created_at'=>date('Y-m-d H:i:s')],
            ['id'=>3,'area_id'=>4,'area'=>'Cancha multiuso','date'=>$day(4),'start'=>'17:00','end'=>'19:00','status'=>'approved','requester'=>'Comité deportivo','unit'=>'Áreas comunes','purpose'=>'Práctica juvenil','attendees'=>16,'amount'=>15,'deposit'=>0,'created_at'=>date('Y-m-d H:i:s')],
        ];
    }

    private static function seedBulletins(): array
    {
        return [
            ['id'=>1,'title'=>'Racionamiento programado de agua','body'=>'El tanque principal será abastecido por sectores. Se recomienda almacenar agua para uso esencial entre 08:00 y 13:00.','category'=>'services','audience'=>'all','pinned'=>true,'published_at'=>date('Y-m-d 08:00:00'),'expires_at'=>date('Y-m-d',strtotime('+3 days')),'author'=>'Administración'],
            ['id'=>2,'title'=>'Mantenimiento preventivo de ascensores','body'=>'El ascensor 2 estará fuera de servicio el sábado de 09:00 a 12:00. El ascensor 1 continuará operativo.','category'=>'maintenance','audience'=>'all','pinned'=>false,'published_at'=>date('Y-m-d H:i:s',strtotime('-1 day')),'expires_at'=>date('Y-m-d',strtotime('+7 days')),'author'=>'Administración'],
            ['id'=>3,'title'=>'Nuevos horarios del cowork','body'=>'A partir del lunes el cowork estará disponible de 06:00 a 22:00 mediante reserva previa.','category'=>'community','audience'=>'residents','pinned'=>false,'published_at'=>date('Y-m-d H:i:s',strtotime('-3 days')),'expires_at'=>'','author'=>'Junta de condominio'],
        ];
    }

    private static function seedNotifications(): array
    {
        return [
            ['id'=>1,'title'=>'Corte eléctrico programado','message'=>'La empresa eléctrica informó una interrupción estimada de 10:00 a 12:00. La planta atenderá únicamente áreas esenciales.','priority'=>'high','audience'=>'all','channels'=>['in_app'],'status'=>'delivered','sent_at'=>date('Y-m-d H:i:s')],
            ['id'=>2,'title'=>'Reserva aprobada','message'=>'Su solicitud para el Cowork fue aprobada.','priority'=>'normal','audience'=>'user','channels'=>['in_app'],'status'=>'read','sent_at'=>date('Y-m-d H:i:s',strtotime('-2 hours'))],
            ['id'=>3,'title'=>'Visitante en recepción','message'=>'Juan Pérez registró su ingreso y recibió la tarjeta provisional 014.','priority'=>'normal','audience'=>'unit','channels'=>['in_app'],'status'=>'delivered','sent_at'=>date('Y-m-d H:i:s',strtotime('-25 minutes'))],
        ];
    }
}
