<?php
declare(strict_types=1);

final class CentralDemoSeeder
{
    public function __construct(private PDO $pdo) {}

    public function seed(): array
    {
        $buildings = [
            [
                'uuid'=>'10000000-0000-4000-8000-000000000010','code'=>'DEMO-TERRAZA-AVILA','name'=>'Urbanización Terraza del Ávila','type'=>'urbanization',
                'url'=>'http://localhost/property-terraza-avila/public','app'=>'0.11.0','api'=>'v1','ui'=>'1.2.0','channel'=>'beta','status'=>'active','health'=>'healthy','seen'=>'-1 minute','scope'=>'aggregates',
                'metrics'=>[542,896,214,27,4,3,46,24,9,128,121,5,2,1,38654705664],
                'infrastructure'=>['location_count'=>9,'guard_post_count'=>3,'connectivity_link_count'=>7,'lorawan_network_count'=>1,'lorawan_gateway_count'=>2,'lorawan_device_count'=>16,'lorawan_warning_count'=>1,'architecture'=>'single_core_direct_connectivity','requires_property_edge'=>false],
                'connectors'=>[
                    ['zkteco','Servidor ZKBioSecurity urbanización','ZKTeco','access','Servidor ZKBioSecurity; inBio administrados en 4 garitas','0.1.0','ZKBioSecurity API','Según versión instalada','Servidor principal por LAN/fibra','online',14,'Personas, tarjetas, eventos, barreras, torniquetes y garitas'],
                    ['hikvision','CCTV garitas y perímetro','Hikvision','video','NVR y cámaras IP','0.1.0','ISAPI / ONVIF','Según firmware','Red privada + RTSP','online',32,'Video, eventos y grabaciones'],
                    ['grandstream','Intercom y perifonía','Grandstream','intercom','UCM / GDS / GSC','0.1.0','SIP',null,'SIP local','online',18,'Garitas, extensiones, grupos y anuncios'],
                    ['unifi','Red urbanización','Ubiquiti UniFi','network','UCG + switches + AP','0.1.0','UniFi Network API',null,'Controlador principal','online',28,'LAN, Wi-Fi, invitados y vouchers'],
                    ['lorawan','LoRaWAN parques y servicios','LoRaWAN','automation','ChirpStack + gateways de radio','0.1.0','MQTT / API',null,'Servidor principal + gateways LoRaWAN','online',18,'Tanques, fugas, botones de emergencia, iluminación y perímetro'],
                    ['ttlock','Cerraduras de casa club','TTLock','access','Cerraduras inteligentes','0.1.0','TTLock Open Platform',null,'Internet directo / gateway TTLock','configured',4,'PIN, eKeys y registros'],
                    ['generic_relay','Relés IP aislados','Multi-marca','automation','Relés LAN / Wi-Fi','0.1.0','HTTP / MQTT',null,'Conexión directa','online',8,'Pulsos, estado y entradas digitales'],
                    ['onvif','Cámaras multimarca','ONVIF','video','Cámaras directas y NVR','0.1.0','ONVIF','Profile T/S','Red privada','online',46,'Perfiles, eventos y PTZ'],
                    ['rtsp','Video para navegador','2CAM','video','RTSP a WebRTC/HLS','0.1.0','RTSP',null,'Servicio en servidor principal','online',46,'Transmisión segura sin exponer credenciales'],
                ],
            ],
            [
                'uuid'=>'10000000-0000-4000-8000-000000000001','code'=>'DEMO-PREMIER-LM','name'=>'Conjunto Premier Las Mercedes','type'=>'residential',
                'url'=>'http://localhost/property-premier/public','app'=>'0.11.0','api'=>'v1','ui'=>'1.2.0','channel'=>'beta','status'=>'active','health'=>'healthy','seen'=>'-2 minutes','scope'=>'aggregates',
                'metrics'=>[286,482,164,18,3,2,34,18,7,76,73,2,0,0,25769803776],
                'infrastructure'=>['location_count'=>6,'guard_post_count'=>1,'connectivity_link_count'=>4,'lorawan_network_count'=>0,'lorawan_gateway_count'=>0,'lorawan_device_count'=>0,'lorawan_warning_count'=>0,'architecture'=>'single_core_direct_connectivity','requires_property_edge'=>false],
                'connectors'=>[
                    ['zkteco','Servidor ZKBioSecurity Premier','ZKTeco','access','Servidor ZKBioSecurity; inBio 160 / 260 / 460 administrados','0.1.0','ZKBioSecurity API','Según versión instalada','API + conciliación','online',8,'Personas, tarjetas, eventos, torniquetes y ascensores'],
                    ['hikvision','Hikvision CCTV','Hikvision','video','NVR y cámaras IP','0.1.0','ISAPI / ONVIF','Según firmware','ONVIF + RTSP','online',22,'Video en vivo y eventos'],
                    ['onvif','Cámaras Bimar','Bimar','video','Cámaras ONVIF','0.1.0','ONVIF','Profile T/S','ONVIF','warning',12,'Video en vivo; 1 cámara sin conexión'],
                    ['vimar','Videoportero Vimar','Vimar','intercom','Frente de calle y pantallas','0.1.0','Vimar por modelo',null,'Levantamiento pendiente','planned',1,'Intercom y apertura según capacidades'],
                    ['grandstream','Telefonía y perifonía','Grandstream','intercom','UCM / GDS / GSC','0.1.0','SIP',null,'SIP local','configured',9,'Intercom, grupos y anuncios'],
                    ['rtsp','Gateway de video','2CAM','video','RTSP a WebRTC/HLS','0.1.0','RTSP',null,'Gateway local','online',34,'Transmisión segura al navegador'],
                    ['generic_relay','Relés auxiliares','Vimar / genérico','automation','Relés de puertas auxiliares','0.1.0','HTTP / contacto seco',null,'Conexión directa','configured',2,'Pulso controlado y auditoría'],
                ],
            ],
            [
                'uuid'=>'10000000-0000-4000-8000-000000000002','code'=>'DEMO-PORTAL-AVILA-2','name'=>'Portal del Ávila 2','type'=>'mixed',
                'url'=>'http://localhost/property-portal-avila-2/public','app'=>'0.11.0','api'=>'v1','ui'=>'1.2.0','channel'=>'stable','status'=>'active','health'=>'warning','seen'=>'-8 minutes','scope'=>'aggregates',
                'metrics'=>[198,331,92,14,1,5,22,12,6,51,45,4,2,1,19327352832],
                'infrastructure'=>['location_count'=>7,'guard_post_count'=>2,'connectivity_link_count'=>5,'lorawan_network_count'=>0,'lorawan_gateway_count'=>0,'lorawan_device_count'=>0,'lorawan_warning_count'=>0,'architecture'=>'single_core_direct_connectivity','requires_property_edge'=>false],
                'connectors'=>[
                    ['hikvision','Hikvision Access','Hikvision','access','Controladores de acceso','0.1.0','ISAPI',null,'API local','online',6,'Personas, tarjetas, puertas y eventos'],
                    ['unifi','UniFi Network','Ubiquiti UniFi','network','UCG + AP','0.1.0','UniFi Network API',null,'Controlador local','online',14,'AP, clientes y vouchers'],
                    ['unifi','UniFi Protect','Ubiquiti UniFi','video','Protect NVR','0.1.0','Protect API',null,'API local','online',16,'Cámaras y grabaciones'],
                    ['ttlock','Cerraduras TTLock','TTLock','access','Cerraduras de cowork y depósitos','0.1.0','TTLock Open Platform',null,'Cloud + gateway','warning',8,'PIN temporal, eKeys y registros'],
                    ['grandstream','Intercom Grandstream','Grandstream','intercom','GDS + UCM','0.1.0','SIP',null,'SIP local','online',7,'Intercom y grupos de llamada'],
                    ['sonoff','Relés eWeLink','Sonoff','automation','Portones secundarios','0.1.0','eWeLink CUBE',null,'API local','configured',3,'Pulsos y estado de relés'],
                ],
            ],
            [
                'uuid'=>'10000000-0000-4000-8000-000000000003','code'=>'DEMO-TORRE-CASTELLANA','name'=>'Torre Empresarial Castellana','type'=>'office',
                'url'=>'http://localhost/property-torre-castellana/public','app'=>'0.4.0','api'=>'v1','ui'=>'1.2.0','channel'=>'stable','status'=>'offline','health'=>'offline','seen'=>'-2 hours','scope'=>'metadata_only',
                'metrics'=>[372,529,86,21,0,0,48,26,8,103,0,0,0,3,12884901888],
                'infrastructure'=>['location_count'=>5,'guard_post_count'=>1,'connectivity_link_count'=>4,'lorawan_network_count'=>0,'lorawan_gateway_count'=>0,'lorawan_device_count'=>0,'lorawan_warning_count'=>0,'architecture'=>'single_core_direct_connectivity','requires_property_edge'=>false],
                'connectors'=>[
                    ['dahua','Dahua DSS','Dahua','video','DSS + cámaras','0.1.0','DSS OpenAPI',null,'Plataforma local','offline',48,'Video, eventos y grabaciones'],
                    ['dahua','Dahua Access','Dahua','access','Controladores de acceso','0.1.0','DSS / NetSDK',null,'Plataforma local','offline',12,'Personas, puertas y eventos'],
                    ['grandstream','UCM y perifonía','Grandstream','intercom','UCM + GSC','0.1.0','SIP / UCM',null,'SIP local','offline',28,'Telefonía IP, intercom y anuncios'],
                    ['unifi','UniFi Network','Ubiquiti UniFi','network','Gateway y AP','0.1.0','UniFi Network API',null,'Controlador local','offline',24,'Red corporativa e invitados'],
                    ['generic_relay','Relés de locales','Genérico','automation','Relés IP','0.1.0','HTTP / MQTT',null,'Conexión directa','offline',7,'Aperturas auxiliares'],
                    ['onvif','Cámaras ONVIF','Multi-marca','video','Cámaras IP','0.1.0','ONVIF','Profile T','ONVIF','offline',48,'Perfiles de transmisión'],
                    ['rtsp','Gateway de video','2CAM','video','Video Gateway','0.1.0','RTSP',null,'Gateway local','offline',48,'WebRTC/HLS'],
                    ['unifi','Vouchers visitantes','Ubiquiti UniFi','network','Hotspot corporativo','0.1.0','External Hotspot API',null,'Controlador local','offline',1,'Vouchers temporales'],
                ],
            ],
        ];
        $installStmt = $this->pdo->prepare(
            'INSERT INTO bh_repo_installations (installation_uuid,installation_code,installation_name,installation_type,base_url,app_version,api_version,ui_version,update_channel,installation_key_hash,status,is_demo,environment_type,data_scope,health_status,last_seen_at,metadata_json) '
            .'VALUES (?,?,?,?,?,?,?,?,?,?,?,1,\'test\',?,?,?,?) ON DUPLICATE KEY UPDATE installation_name=VALUES(installation_name),installation_type=VALUES(installation_type),base_url=VALUES(base_url),app_version=VALUES(app_version),api_version=VALUES(api_version),ui_version=VALUES(ui_version),update_channel=VALUES(update_channel),status=VALUES(status),is_demo=1,environment_type=\'test\',data_scope=VALUES(data_scope),health_status=VALUES(health_status),last_seen_at=VALUES(last_seen_at),metadata_json=VALUES(metadata_json)'
        );
        $metricStmt = $this->pdo->prepare(
            'INSERT INTO bh_repo_installation_metrics (installation_id,user_count,people_count,unit_count,area_count,active_visitor_count,pending_reservation_count,camera_count,access_point_count,connector_count,device_count,online_device_count,warning_device_count,queue_depth,critical_alert_count,disk_free_bytes,summary_json,reported_at) '
            .'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
        );
        $connectorStmt = $this->pdo->prepare(
            'INSERT INTO bh_repo_connector_inventory (installation_id,connector_code,display_name,vendor_name,domain,product_family,connector_version,api_family,api_version,integration_mode,status,device_count,capabilities_json,last_seen_at) '
            .'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
        );
        $connectorCount = 0;
        foreach ($buildings as $building) {
            $seen = date('Y-m-d H:i:s', strtotime($building['seen']));
            $installStmt->execute([
                $building['uuid'],$building['code'],$building['name'],$building['type'],$building['url'],$building['app'],$building['api'],$building['ui'],$building['channel'],
                hash('sha256','demo-'.$building['code']),$building['status'],$building['scope'],$building['health'],$seen,
                json_encode(['demo'=>true,'city'=>'Caracas','country'=>'VE'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ]);
            $stmt=$this->pdo->prepare('SELECT id FROM bh_repo_installations WHERE installation_uuid=?');$stmt->execute([$building['uuid']]);$id=(int)$stmt->fetchColumn();
            $this->pdo->prepare('DELETE FROM bh_repo_installation_metrics WHERE installation_id=?')->execute([$id]);
            $this->pdo->prepare('DELETE FROM bh_repo_connector_inventory WHERE installation_id=?')->execute([$id]);
            $m=$building['metrics'];
            $metricStmt->execute([$id,...$m,json_encode(['source'=>'role_specific_demo','contains_personal_data'=>false,'infrastructure'=>$building['infrastructure']??[]],JSON_UNESCAPED_SLASHES)]);
            foreach ($building['connectors'] as $connector) {
                [$code,$name,$vendor,$domain,$family,$version,$apiFamily,$apiVersion,$mode,$status,$devices,$capabilities]=$connector;
                $connectorStmt->execute([$id,$code,$name,$vendor,$domain,$family,$version,$apiFamily,$apiVersion,$mode,$status,$devices,json_encode(['summary'=>$capabilities],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
                $connectorCount++;
            }
        }
        $plans = [
            [
                'uuid'=>'20000000-0000-4000-8000-000000000001',
                'code'=>'DEMO-VALLE-ARRIBA',
                'name'=>'Residencias Valle Arriba',
                'type'=>'residential_complex',
                'url'=>'https://demo.local/property-valle-arriba/public',
                'channel'=>'beta',
                'scope'=>'aggregates',
                'technology'=>['zkteco','hikvision','grandstream','onvif','rtsp'],
                'notes'=>'Levantamiento inicial: dos torres, una garita, ZKBioSecurity y NVR Hikvision.',
            ],
            [
                'uuid'=>'20000000-0000-4000-8000-000000000002',
                'code'=>'DEMO-GALERIAS-AVILA',
                'name'=>'Centro Comercial Galerías del Ávila',
                'type'=>'shopping_center',
                'url'=>'https://demo.local/property-galerias-avila/public',
                'channel'=>'stable',
                'scope'=>'metadata_only',
                'technology'=>['dahua','unifi','ttlock','grandstream','generic_relay'],
                'notes'=>'Plan de instalación comercial con locales, áreas comunes, vouchers e intercomunicación.',
            ],
        ];
        $planStmt=$this->pdo->prepare(
            "INSERT INTO bh_repo_installation_plans (plan_uuid,installation_code,installation_name,installation_type,planned_base_url,update_channel,data_scope,status,technology_json,notes,registration_token_id) "
            ."VALUES (?,?,?,?,?,?,?,'ready',?,?,NULL) "
            ."ON DUPLICATE KEY UPDATE installation_name=VALUES(installation_name),installation_type=VALUES(installation_type),planned_base_url=VALUES(planned_base_url),update_channel=VALUES(update_channel),data_scope=VALUES(data_scope),status='ready',technology_json=VALUES(technology_json),notes=VALUES(notes),registered_installation_id=NULL,registered_at=NULL"
        );
        foreach($plans as $plan){
            $planStmt->execute([
                $plan['uuid'],$plan['code'],$plan['name'],$plan['type'],$plan['url'],$plan['channel'],$plan['scope'],
                json_encode($plan['technology'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$plan['notes'],
            ]);
        }
        return ['installations'=>count($buildings),'plans'=>count($plans),'connectors'=>$connectorCount];
    }
}
