<?php
declare(strict_types=1);

final class NodeDemoSeeder
{
    public function __construct(private PDO $pdo) {}

    public function seed(int $installationId): array
    {
        $this->pdo->prepare(
            'INSERT INTO bh_technology_profiles '
            .'(installation_id,profile_name,data_scope,share_aggregate_metrics,share_connector_inventory,remote_support_enabled,remote_support_requires_local_approval,notes) '
            .'VALUES (?,\'Perfil tecnológico principal\',\'aggregates\',1,1,0,1,\'Las credenciales y los datos personales permanecen en esta instalación Property Hub.\') '
            .'ON DUPLICATE KEY UPDATE notes=VALUES(notes)'
        )->execute([$installationId]);

        $definitions = $this->pdo->query('SELECT id,code,name FROM bh_connector_definitions')->fetchAll();
        $byCode=[];
        foreach($definitions as $definition) $byCode[(string)$definition['code']]=$definition;

        $rows = [
            ['zkteco','ZKBioSecurity servidor principal','ZKBioSecurity API','Según versión instalada','API local + conciliación','http://127.0.0.1/zkbiosecurity','online',8,['people','credentials','events','elevators']],
            ['hikvision','CCTV Hikvision','ISAPI / ONVIF',null,'NVR y cámaras por red privada','http://192.168.20.10','online',22,['live_stream','events','recordings']],
            ['onvif','Cámaras Bimar y multimarca','ONVIF','Profile T/S','ONVIF directo desde el servidor','http://192.168.20.20','warning',12,['live_stream','events','ptz']],
            ['grandstream','Telefonía, intercom y perifonía','SIP / UCM',null,'SIP local','http://192.168.40.5','online',9,['extensions','paging','door_intercom']],
            ['unifi','UniFi Network / Protect','UniFi APIs',null,'Controlador local','https://192.168.30.1','online',14,['access_points','guest_clients','vouchers','cameras']],
            ['ttlock','Cerraduras TTLock','TTLock Open Platform',null,'Cloud API + gateways propios TTLock','https://api.ttlock.com','configured',4,['locks','passcodes','ekeys','records']],
            ['lorawan','LoRaWAN e IoT','ChirpStack / MQTT',null,'Servidor LoRaWAN local + gateways de radio','http://127.0.0.1:8080','online',16,['gateways','sensors','telemetry','alerts']],
            ['generic_relay','Relés y dispositivos IP','HTTP / MQTT',null,'Conexión directa LAN, Wi-Fi o Internet','http://192.168.50.10','configured',5,['pulse','status','digital_input']],
        ];
        $stmt=$this->pdo->prepare(
            'INSERT INTO bh_connector_instances '
            .'(installation_id,connector_definition_id,name,target_type,manages_downstream_assets,direct_device_policy,platform_product,platform_version,integration_mode,api_family,api_version,authentication_mode,endpoint_url,device_count,capabilities_json,environment,status,last_health_at) '
            .'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
        );
        $created=0;
        foreach($rows as $row){
            [$code,$name,$apiFamily,$apiVersion,$mode,$endpoint,$status,$devices,$caps]=$row;
            if(!isset($byCode[$code])) continue;
            $exists=$this->pdo->prepare('SELECT id FROM bh_connector_instances WHERE installation_id=? AND connector_definition_id=? AND name=?');
            $exists->execute([$installationId,(int)$byCode[$code]['id'],$name]);
            if($exists->fetchColumn()) continue;
            $targetType=match($code){'ttlock'=>'cloud_platform','onvif'=>'protocol_service','generic_relay'=>'direct_device',default=>'platform_server'};
            $manages=in_array($code,['zkteco','hikvision','grandstream','unifi','ttlock','lorawan'],true)?1:0;
            $directPolicy=match($code){'zkteco'=>'forbidden','onvif','generic_relay'=>'required',default=>'optional'};
            $product=match($code){'zkteco'=>'ZKBioSecurity','hikvision'=>'Hikvision NVR / plataforma','grandstream'=>'Grandstream UCM','unifi'=>'UniFi Console','ttlock'=>'TTLock Open Platform','lorawan'=>'ChirpStack',default=>null};
            $stmt->execute([$installationId,(int)$byCode[$code]['id'],$name,$targetType,$manages,$directPolicy,$product,$apiVersion,$mode,$apiFamily,$apiVersion,'local_secret_vault',$endpoint,$devices,json_encode($caps,JSON_UNESCAPED_SLASHES),'production',$status]);
            $created++;
        }

        $locations = $this->seedLocations($installationId);
        $links = $this->seedConnectivity($installationId, $locations);
        $devices = $this->seedDevices($installationId, $locations);
        $lora = $this->seedLoRaWAN($installationId, $locations);

        return [
            'connector_instances'=>$created,
            'locations'=>count($locations),
            'connectivity_links'=>$links,
            'devices'=>$devices,
            'lorawan_gateways'=>$lora['gateways'],
            'lorawan_devices'=>$lora['devices'],
        ];
    }

    private function seedLocations(int $installationId): array
    {
        $rows = [
            ['SITE','Urbanización / conjunto principal','main_site',null,0,'mixed','online','Una sola instalación lógica administrada por el servidor principal.'],
            ['HQ','Sede principal y cuarto de servidores','headquarters','SITE',1,'mixed','online','Property Hub, ZKBioSecurity, NVR, UniFi y servicios centrales.'],
            ['G-PRI','Garita principal','guard_post','SITE',0,'fiber','online','Control de acceso, cámaras e intercom conectados por fibra.'],
            ['G-NOR','Garita norte','guard_post','SITE',0,'fiber','online','Controladores y NVR visibles directamente desde el servidor principal.'],
            ['G-SUR','Garita sur','guard_post','SITE',0,'utp','warning','Enlace UTP principal y respaldo LTE del router de la garita.'],
            ['PARQUE','Parque central','park','SITE',0,'lorawan','online','Sensores LoRaWAN, iluminación y botón de emergencia.'],
            ['CLUB','Casa club y áreas reservables','common_area','SITE',0,'wifi','online','Cerraduras TTLock, cámaras y Wi-Fi de invitados.'],
            ['PER-ESTE','Perímetro este','perimeter','SITE',0,'lorawan','online','Sensores de apertura, movimiento e iluminación.'],
            ['BOMBAS','Cuarto de bombas y tanques','technical_area','SITE',0,'lorawan','online','Sensores de nivel, presión y fuga.'],
        ];
        $ids=[];
        $select=$this->pdo->prepare('SELECT id FROM bh_site_locations WHERE installation_id=? AND code=?');
        $insert=$this->pdo->prepare(
            'INSERT INTO bh_site_locations '
            .'(installation_id,parent_id,code,name,location_type,is_operational_center,connectivity_mode,status,notes,metadata_json) '
            .'VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        foreach ($rows as $row) {
            [$code,$name,$type,$parentCode,$core,$connectivity,$status,$notes]=$row;
            $select->execute([$installationId,$code]);
            $id=(int)$select->fetchColumn();
            if(!$id){
                $parentId=$parentCode ? ($ids[$parentCode]??null) : null;
                $insert->execute([$installationId,$parentId,$code,$name,$type,$core,$connectivity,$status,$notes,json_encode(['demo'=>true],JSON_UNESCAPED_SLASHES)]);
                $id=(int)$this->pdo->lastInsertId();
            }
            $ids[$code]=$id;
        }
        return $ids;
    }

    private function seedConnectivity(int $installationId, array $locations): int
    {
        $rows = [
            ['FIB-GPRI','Fibra sede principal ↔ garita principal','HQ','G-PRI','fiber','primary','10 Gbps','online','Backbone privado.'],
            ['FIB-GNOR','Fibra sede principal ↔ garita norte','HQ','G-NOR','fiber','primary','1 Gbps','online','Backbone privado.'],
            ['UTP-GSUR','UTP sede principal ↔ garita sur','HQ','G-SUR','utp','primary','1 Gbps','warning','Revisar pérdida intermitente.'],
            ['LTE-GSUR','Internet LTE de respaldo garita sur','G-SUR',null,'cellular','backup','4G LTE','online','Respaldo del router; no requiere servidor adicional.'],
            ['WIFI-CLUB','Wi-Fi administrado casa club','HQ','CLUB','wifi','primary','Wi-Fi 6','online','UniFi y dispositivos directos.'],
            ['LORA-PARK','Cobertura LoRaWAN parque y perímetro','HQ','PARQUE','lorawan','iot','868/915 MHz','online','Radio de bajo consumo para sensores.'],
            ['INET-CLOUD','Internet para servicios cloud','HQ',null,'internet','independent','Fibra 800 Mbps','online','TTLock y APIs externas.'],
        ];
        $select=$this->pdo->prepare('SELECT id FROM bh_connectivity_links WHERE installation_id=? AND code=?');
        $insert=$this->pdo->prepare(
            'INSERT INTO bh_connectivity_links '
            .'(installation_id,from_location_id,to_location_id,code,name,link_type,link_role,capacity_label,status,notes) '
            .'VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $count=0;
        foreach($rows as $row){
            [$code,$name,$from,$to,$type,$role,$capacity,$status,$notes]=$row;
            $select->execute([$installationId,$code]);
            if($select->fetchColumn()) continue;
            $insert->execute([$installationId,$locations[$from]??null,$to?($locations[$to]??null):null,$code,$name,$type,$role,$capacity,$status,$notes]);
            $count++;
        }
        return $count;
    }

    private function seedDevices(int $installationId, array $locations): int
    {
        $connectorMap=[];
        $stmt=$this->pdo->prepare(
            'SELECT cd.code,ci.id FROM bh_connector_instances ci JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id WHERE ci.installation_id=?'
        );
        $stmt->execute([$installationId]);
        foreach($stmt->fetchAll() as $row)$connectorMap[(string)$row['code']]=(int)$row['id'];
        $rows=[
            ['zkteco','access_controller','Controlador inBio 460 Garita Principal','ZKTeco','inBio 460','ZK-GP-001','192.168.10.21','G-PRI','LAN-FIBRA','lan',1,'online',['doors'=>4,'turnstile'=>true]],
            ['zkteco','access_controller','Controlador inBio 260 Garita Norte','ZKTeco','inBio 260','ZK-GN-001','192.168.10.31','G-NOR','LAN-FIBRA','lan',1,'online',['doors'=>2]],
            ['zkteco','access_controller','Controlador inBio 260 Garita Sur','ZKTeco','inBio 260','ZK-GS-001','192.168.10.41','G-SUR','LAN-UTP','lan',1,'maintenance',['doors'=>2]],
            ['hikvision','nvr','NVR principal de videovigilancia','Hikvision','NVR 32 canales','HK-NVR-001','192.168.20.10','HQ','VIDEO','lan',1,'online',['channels'=>32,'recording'=>true]],
            ['hikvision','nvr','NVR Garita Norte','Hikvision','NVR 8 canales','HK-NVR-GN','192.168.20.31','G-NOR','VIDEO','lan',1,'online',['channels'=>8,'recording'=>true]],
            ['grandstream','phone','UCM telefonía e intercom','Grandstream','UCM','GS-UCM-001','192.168.40.5','HQ','VOICE','lan',1,'online',['sip'=>true,'paging'=>true]],
            ['unifi','network_controller','UniFi Cloud Gateway','Ubiquiti','UCG','UF-UCG-001','192.168.30.1','HQ','MANAGEMENT','lan',1,'online',['network'=>true,'protect'=>true]],
            ['ttlock','gateway','Gateway TTLock Casa Club','TTLock','Gateway G2','TTL-GW-001','192.168.31.50','CLUB','IOT-WIFI','wifi',0,'online',['locks'=>4,'cloud'=>true]],
            ['lorawan','gateway','Gateway LoRaWAN Sede Principal','Multi-marca','LoRaWAN Gateway','LORA-GW-001','192.168.60.10','HQ','IOT-LORA','lan',1,'online',['backhaul'=>'ethernet']],
            ['lorawan','gateway','Gateway LoRaWAN Garita Norte','Multi-marca','LoRaWAN Gateway','LORA-GW-002','192.168.60.11','G-NOR','IOT-LORA','lan',1,'online',['backhaul'=>'ethernet']],
            ['generic_relay','relay','Relé iluminación parque','Multi-marca','Relé IP','REL-PARQUE-01','192.168.50.21','PARQUE','IOT-WIFI','wifi',1,'online',['channels'=>4]],
        ];
        $select=$this->pdo->prepare('SELECT id FROM bh_devices WHERE installation_id=? AND serial_number=?');
        $insert=$this->pdo->prepare(
            'INSERT INTO bh_devices (installation_id,site_location_id,connector_instance_id,asset_role,connection_path,connection_notes,device_type,name,vendor,model,firmware_version,serial_number,ip_address,network_zone,managed_by,connectivity_mode,direct_to_core,external_id,capabilities_json,status,last_seen_at) '
            .'VALUES (?,?,?,?,?,?,?,?,?,?,NULL,?,?,?,?,?,?,?,?,?,NOW())'
        );
        $count=0;
        foreach($rows as $r){
            [$code,$type,$name,$vendor,$model,$serial,$ip,$location,$zone,$connectivity,$direct,$status,$caps]=$r;
            $select->execute([$installationId,$serial]);if($select->fetchColumn())continue;
            $role=match($code){'zkteco','hikvision','grandstream','unifi'=>'managed_device','ttlock','lorawan'=>'technology_gateway','generic_relay'=>'direct_device',default=>'reference_asset'};
            $path=match($code){'zkteco','hikvision','grandstream','unifi'=>'via_platform','ttlock'=>'vendor_cloud','lorawan'=>'technology_gateway','generic_relay'=>'direct',default=>'manual_reference'};
            $responsible=match($code){'zkteco'=>'ZKBioSecurity servidor principal','hikvision'=>'CCTV Hikvision','grandstream'=>'Telefonía, intercom y perifonía','unifi'=>'UniFi Network / Protect','ttlock'=>'TTLock Open Platform','lorawan'=>'Red LoRaWAN privada','generic_relay'=>'Property Hub directo',default=>'Inventario manual'};
            $notes=$code==='zkteco'?'Property Hub consulta este panel a través del servidor ZKBioSecurity; no existe conexión directa al inBio.':null;
            $insert->execute([$installationId,$locations[$location]??null,$connectorMap[$code]??null,$role,$path,$notes,$type,$name,$vendor,$model,$serial,$ip,$zone,$responsible,$connectivity,$path==='direct'?1:0,$serial,json_encode($caps,JSON_UNESCAPED_SLASHES),$status]);
            $count++;
        }
        return $count;
    }

    private function seedLoRaWAN(int $installationId, array $locations): array
    {
        $select=$this->pdo->prepare('SELECT id FROM bh_lorawan_networks WHERE installation_id=? AND code=?');
        $select->execute([$installationId,'LORA-PRIVADA']);
        $networkId=(int)$select->fetchColumn();
        if(!$networkId){
            $this->pdo->prepare(
                'INSERT INTO bh_lorawan_networks (installation_id,code,name,network_server,deployment_mode,endpoint_url,region_code,status,notes) '
                .'VALUES (?,\'LORA-PRIVADA\',\'Red LoRaWAN privada\',\'chirpstack\',\'local\',\'http://127.0.0.1:8080\',\'US915\',\'online\',\'El servidor principal recibe la telemetría por MQTT/API. Los gateways son equipos de radio, no computadoras Property Hub adicionales.\')'
            )->execute([$installationId]);
            $networkId=(int)$this->pdo->lastInsertId();
        }
        $gateways = [
            ['A84041FFFF000001','Gateway LoRaWAN sede principal','HQ','ethernet','192.168.60.10','online'],
            ['A84041FFFF000002','Gateway LoRaWAN garita norte','G-NOR','ethernet','192.168.60.11','online'],
        ];
        $gatewayInsert=$this->pdo->prepare(
            'INSERT INTO bh_lorawan_gateways (installation_id,network_id,site_location_id,gateway_eui,name,backhaul_type,ip_address,status,last_seen_at,notes) '
            .'VALUES (?,?,?,?,?,?,?,?,NOW(),?)'
        );
        $gatewaySelect=$this->pdo->prepare('SELECT id FROM bh_lorawan_gateways WHERE installation_id=? AND gateway_eui=?');
        $gatewayCount=0;
        foreach($gateways as $g){
            [$eui,$name,$location,$backhaul,$ip,$status]=$g;
            $gatewaySelect->execute([$installationId,$eui]);
            if($gatewaySelect->fetchColumn()) continue;
            $gatewayInsert->execute([$installationId,$networkId,$locations[$location]??null,$eui,$name,$backhaul,$ip,$status,'Gateway propio de la red LoRaWAN.']);
            $gatewayCount++;
        }
        $devices = [
            ['70B3D57ED0000001','Nivel tanque principal','level_sensor','BOMBAS',86,'64','%','online'],
            ['70B3D57ED0000002','Detector de fuga cuarto de bombas','leak_sensor','BOMBAS',92,'Seco',null,'online'],
            ['70B3D57ED0000003','Botón de emergencia parque','panic_button','PARQUE',74,'Normal',null,'online'],
            ['70B3D57ED0000004','Iluminación sendero parque','lighting_controller','PARQUE',null,'Encendida',null,'online'],
            ['70B3D57ED0000005','Sensor perimetral este 01','perimeter_sensor','PER-ESTE',67,'Cerrado',null,'online'],
            ['70B3D57ED0000006','Sensor perimetral este 02','perimeter_sensor','PER-ESTE',18,'Batería baja',null,'warning'],
        ];
        $deviceInsert=$this->pdo->prepare(
            'INSERT INTO bh_lorawan_devices '
            .'(installation_id,network_id,site_location_id,dev_eui,name,device_type,manufacturer,model,battery_percent,last_value,last_value_unit,rssi,snr,status,last_seen_at,metadata_json) '
            .'VALUES (?,?,?,?,?,?,\'Demo IoT\',\'LoRa Sensor\',?,?,?,?,?,?,NOW(),?)'
        );
        $deviceSelect=$this->pdo->prepare('SELECT id FROM bh_lorawan_devices WHERE installation_id=? AND dev_eui=?');
        $deviceCount=0;
        foreach($devices as $d){
            [$eui,$name,$type,$location,$battery,$value,$unit,$status]=$d;
            $deviceSelect->execute([$installationId,$eui]);
            if($deviceSelect->fetchColumn()) continue;
            $deviceInsert->execute([$installationId,$networkId,$locations[$location]??null,$eui,$name,$type,$battery,$value,$unit,-78.5,8.4,$status,json_encode(['demo'=>true],JSON_UNESCAPED_SLASHES)]);
            $deviceCount++;
        }
        $this->pdo->prepare(
            'INSERT INTO bh_iot_automation_rules (installation_id,code,name,source_type,source_reference,condition_json,action_json,priority,enabled,status) '
            .'VALUES (?,\'TANQUE-BAJO\',\'Alertar nivel bajo del tanque\',\'lorawan_device\',\'70B3D57ED0000001\',?, ?,\'critical\',1,\'active\') '
            .'ON DUPLICATE KEY UPDATE name=VALUES(name),condition_json=VALUES(condition_json),action_json=VALUES(action_json),status=VALUES(status)'
        )->execute([
            $installationId,
            json_encode(['operator'=>'lt','value'=>20,'unit'=>'%'],JSON_UNESCAPED_SLASHES),
            json_encode(['notify'=>['maintenance','security'],'create_incident'=>true],JSON_UNESCAPED_SLASHES),
        ]);
        return ['gateways'=>$gatewayCount,'devices'=>$deviceCount];
    }
}
