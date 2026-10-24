<?php
declare(strict_types=1);

/**
 * Defensive local client for the VimarHub bridge.
 *
 * Property Hub intentionally does not assume a single frozen VimarHub HTTP
 * contract.  The bridge has evolved while VIEW Wireless / By-me Plus / KNX
 * support was being explored, therefore Edge probes a small set of read-only
 * endpoints and records which contract actually answered on this site.
 */
final class VimarHubBridgeClient
{
    private string $baseUrl;

    public function __construct(
        string $baseUrl='http://127.0.0.1:47821',
        private string $token='',
        private bool $verifyTls=true,
        private string $preferredInventoryPath=''
    ) {
        $baseUrl=rtrim(trim($baseUrl),'/');
        if($baseUrl===''||!preg_match('#^https?://#i',$baseUrl))throw new InvalidArgumentException('La URL del bridge VimarHub debe comenzar por http:// o https://.');
        $this->baseUrl=$baseUrl;
        $this->preferredInventoryPath=$this->normalizePath($preferredInventoryPath);
    }

    public function health(): array
    {
        $paths=['/health','/api/health','/api/v1/health','/status','/api/status','/'];
        $attempts=[];
        foreach($paths as $path){
            $r=$this->request('GET',$path,null,5);
            $attempts[]=$this->attemptSummary($r);
            if(!empty($r['ok'])){
                $json=$r['json'];
                $message='VimarHub respondió en '.$path.'.';
                if(is_array($json)){
                    $state=strtolower(trim((string)($json['status']??$json['state']??$json['message']??'')));
                    if($state!=='')$message.=' Estado: '.$state.'.';
                }
                return ['ok'=>true,'message'=>$message,'endpoint'=>$path,'http_status'=>$r['http_status'],'latency_ms'=>$r['latency_ms'],'data'=>$json,'attempts'=>$attempts];
            }
        }
        return ['ok'=>false,'message'=>'No se encontró un endpoint de salud compatible en VimarHub. Verifique que el daemon local esté iniciado y que la URL sea correcta.','endpoint'=>'','attempts'=>$attempts];
    }

    /**
     * Read-only inventory discovery.  GET contracts are preferred.  A POST is
     * attempted only on endpoints explicitly named discover and carries a
     * read_only flag; Property Hub never sends a device command here.
     */
    public function discover(): array
    {
        $getPaths=[];
        if($this->preferredInventoryPath!=='')$getPaths[]=$this->preferredInventoryPath;
        $getPaths=array_values(array_unique(array_merge($getPaths,[
            '/api/property-hub/v1/inventory','/api/v1/inventory','/api/inventory','/inventory',
            '/api/v1/snapshot','/api/snapshot','/snapshot',
            '/api/v1/devices','/api/devices','/devices',
        ])));
        $attempts=[];
        foreach($getPaths as $path){
            $r=$this->request('GET',$path,null,12);$attempts[]=$this->attemptSummary($r);
            if(empty($r['ok'])||!is_array($r['json']))continue;
            $parsed=$this->parseInventory($r['json']);
            if($this->looksLikeInventory($parsed))return $parsed+['ok'=>true,'endpoint'=>$path,'http_status'=>$r['http_status'],'latency_ms'=>$r['latency_ms'],'attempts'=>$attempts,'method'=>'GET'];
        }

        foreach(['/api/property-hub/v1/discover','/api/v1/discover','/api/discover','/discover'] as $path){
            $r=$this->request('POST',$path,['mode'=>'read_only','read_only'=>true,'commands'=>false],20);$attempts[]=$this->attemptSummary($r);
            if(empty($r['ok'])||!is_array($r['json']))continue;
            $parsed=$this->parseInventory($r['json']);
            if($this->looksLikeInventory($parsed))return $parsed+['ok'=>true,'endpoint'=>$path,'http_status'=>$r['http_status'],'latency_ms'=>$r['latency_ms'],'attempts'=>$attempts,'method'=>'POST'];
        }

        $health=$this->health();
        return [
            'ok'=>false,
            'message'=>!empty($health['ok'])
                ? 'VimarHub está activo, pero esta versión del bridge no expuso un contrato de inventario reconocido. Property Hub no ejecutó comandos físicos.'
                : 'No fue posible consultar VimarHub ni descubrir inventario.',
            'gateways'=>[],'devices'=>[],'rooms'=>[],'groups'=>[],'attempts'=>$attempts,'health'=>$health,
        ];
    }

    private function request(string $method,string $path,?array $payload,int $timeout): array
    {
        if(!extension_loaded('curl'))throw new RuntimeException('La extensión PHP cURL no está disponible.');
        $path=$this->normalizePath($path);if($path==='')$path='/';$url=$this->baseUrl.$path;
        $ch=curl_init($url);if($ch===false)throw new RuntimeException('No fue posible iniciar cURL para VimarHub.');
        $headers=['Accept: application/json','User-Agent: Property-Hub-Edge/0.14.51'];
        if(trim($this->token)!==''){$headers[]='Authorization: Bearer '.trim($this->token);$headers[]='X-API-Key: '.trim($this->token);}
        $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>$timeout,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>$headers,CURLOPT_SSL_VERIFYPEER=>$this->verifyTls,CURLOPT_SSL_VERIFYHOST=>$this->verifyTls?2:0];
        if(strtoupper($method)==='POST'){$body=json_encode($payload??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$headers[]='Content-Type: application/json';$opts[CURLOPT_HTTPHEADER]=$headers;$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$body;}
        else $opts[CURLOPT_HTTPGET]=true;
        curl_setopt_array($ch,$opts);$start=microtime(true);$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$contentType=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);$err=curl_error($ch);curl_close($ch);$lat=(int)round((microtime(true)-$start)*1000);
        $json=null;if(is_string($body)&&trim($body)!==''){$tmp=json_decode($body,true);if(is_array($tmp))$json=$tmp;}
        return ['ok'=>$body!==false&&$status>=200&&$status<300,'method'=>$method,'path'=>$path,'http_status'=>$status,'latency_ms'=>$lat,'content_type'=>$contentType,'error'=>$err,'json'=>$json,'body_sample'=>is_string($body)?substr(trim(strip_tags($body)),0,220):''];
    }

    private function parseInventory(array $payload): array
    {
        $root=$payload;
        foreach(['data','result','payload'] as $key){if(isset($root[$key])&&is_array($root[$key])&&!array_is_list($root[$key]))$root=$root[$key];}
        $inventory=(isset($root['inventory'])&&is_array($root['inventory']))?$root['inventory']:$root;
        if(isset($inventory['data'])&&is_array($inventory['data'])&&!array_is_list($inventory['data']))$inventory=$inventory['data'];

        $gateways=$this->extractRows($inventory,['gateways','gateway','bridges','hubs']);
        $devices=$this->extractRows($inventory,['devices','entities','objects','items','accessories']);
        $rooms=$this->extractRows($inventory,['rooms','areas','environments','ambienti']);
        $groups=$this->extractRows($inventory,['groups','zones']);

        // Some bridges return a bare array from /devices.
        if($devices===[]&&array_is_list($root))$devices=array_values(array_filter($root,'is_array'));
        if($devices===[]&&array_is_list($payload))$devices=array_values(array_filter($payload,'is_array'));

        $normalized=[];foreach($devices as $row)$normalized[]=$this->normalizeDevice($row);
        $normalizedGateways=[];foreach($gateways as $row)$normalizedGateways[]=$this->normalizeGateway($row);
        $warnings=[];
        foreach($normalized as $d){if(($d['normalized_type']??'other')==='other')$warnings[]='Tipo no concluyente: '.(string)($d['name']??$d['external_id']??'dispositivo').'. Se conserva como other hasta mapearlo.';}
        return ['message'=>'Inventario VimarHub leído en modo seguro.','gateways'=>$normalizedGateways,'devices'=>$normalized,'rooms'=>$rooms,'groups'=>$groups,'warnings'=>array_values(array_unique($warnings)),'raw_keys'=>array_keys($inventory)];
    }

    private function extractRows(array $source,array $keys): array
    {
        foreach($keys as $key){if(!isset($source[$key])||!is_array($source[$key]))continue;$v=$source[$key];if(isset($v['data'])&&is_array($v['data']))$v=$v['data'];if(array_is_list($v))return array_values(array_filter($v,'is_array'));}
        return [];
    }

    private function normalizeGateway(array $row): array
    {
        $id=$this->first($row,['id','uuid','gateway_id','gatewayId','serial','serial_number','sn','mac','address']);
        $name=$this->first($row,['name','display_name','displayName','friendly_name','friendlyName','model']);if($name==='')$name=$id!==''?'Vimar Gateway '.$id:'Vimar Gateway';
        return [
            'external_id'=>$id!==''?$id:hash('sha256',json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)),
            'name'=>$name,'normalized_type'=>'gateway','vendor'=>$this->first($row,['vendor','manufacturer','brand'])?:'Vimar',
            'model'=>$this->first($row,['model','product','product_name','productName','type']),
            'serial_number'=>$this->first($row,['serial_number','serialNumber','serial','sn']),
            'firmware_version'=>$this->first($row,['firmware_version','firmwareVersion','firmware','version']),
            'ip_address'=>$this->first($row,['ip_address','ipAddress','ip','host','address']),
            'status'=>$this->normalizeStatus($this->first($row,['status','state','online','connected'])),
            'capabilities'=>['read_state'],'raw'=>$row,
        ];
    }

    private function normalizeDevice(array $row): array
    {
        $id=$this->first($row,['id','uuid','external_id','externalId','device_id','deviceId','entity_id','entityId','aid','iid','address','serial','sn']);
        if($id==='')$id=hash('sha256',json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        $name=$this->first($row,['name','display_name','displayName','friendly_name','friendlyName','label','entity_id']);if($name==='')$name='Vimar '.$id;
        $explicitType=strtolower(trim($this->first($row,['normalized_type','normalizedType','device_type','deviceType','kind','domain','service_type','serviceType','category','class','type'])));
        $type=$this->normalizeType($explicitType,$row);
        $caps=$this->normalizeCapabilities($row,$type);
        $gatewayId=$this->first($row,['gateway_id','gatewayId','bridge_id','bridgeId','hub_id','hubId','parent_id','parentId']);
        $room=$this->first($row,['room','room_name','roomName','area','area_name','areaName','environment','location']);
        return [
            'external_id'=>$id,'name'=>$name,'normalized_type'=>$type,'raw_type'=>$explicitType,
            'gateway_id'=>$gatewayId,'room'=>$room,'vendor'=>$this->first($row,['vendor','manufacturer','brand'])?:'Vimar',
            'model'=>$this->first($row,['model','product','product_name','productName']),
            'serial_number'=>$this->first($row,['serial_number','serialNumber','serial','sn']),
            'firmware_version'=>$this->first($row,['firmware_version','firmwareVersion','firmware']),
            'ip_address'=>$this->first($row,['ip_address','ipAddress','ip','host']),
            'status'=>$this->normalizeStatus($this->first($row,['status','availability','online','connected','state'])),
            'state'=>$row['state']??null,'capabilities'=>$caps,'raw'=>$row,
        ];
    }

    /** Conservative classification: a boolean On characteristic alone is NOT a light. */
    private function normalizeType(string $type,array $row): string
    {
        $t=str_replace(['-',' '],'_',$type);
        $map=[
            'relay'=>'relay','rele'=>'relay','switch'=>'switch','outlet'=>'switch','socket'=>'switch',
            'light'=>'light','lamp'=>'light','dimmer'=>'dimmer','dimmable_light'=>'dimmer',
            'cover'=>'cover','blind'=>'blind','shutter'=>'shutter','roller_shutter'=>'shutter',
            'thermostat'=>'thermostat','climate'=>'climate','hvac'=>'climate',
            'scene'=>'scene','sensor'=>'sensor','binary_sensor'=>'binary_sensor','contact'=>'contact','contact_sensor'=>'contact',
            'energy_meter'=>'energy_meter','meter'=>'energy_meter','power_meter'=>'energy_meter',
            'camera'=>'camera','intercom'=>'intercom','videointercom'=>'intercom','video_intercom'=>'intercom',
            'gateway'=>'gateway','bridge'=>'gateway','hub'=>'gateway',
        ];
        if(isset($map[$t]))return $map[$t];
        // HomeKit-style service names are explicit enough to use, unlike the presence of On alone.
        $service=strtolower(trim((string)($row['service']??$row['service_name']??$row['serviceName']??'')));
        if($service!==''){foreach($map as $needle=>$normalized){if(str_contains(str_replace(['-',' '],'_',$service),$needle))return $normalized;}}
        return 'other';
    }

    private function normalizeCapabilities(array $row,string $type): array
    {
        $caps=[];$sources=[];
        foreach(['capabilities','capability','features','actions','commands','services'] as $key){if(isset($row[$key]))$sources[]=$row[$key];}
        foreach($sources as $src){
            if(is_string($src))$src=preg_split('/[,|\s]+/',strtolower($src))?:[];
            if(!is_array($src))continue;
            foreach($src as $k=>$v){$token=is_int($k)?(string)$v:(string)$k;$token=strtolower(trim(str_replace(['-',' '],'_',$token)));if($token==='')continue;
                $known=['turn_on','turn_off','toggle','pulse','open','close','stop','set_position','set_level','set_temperature','read_state','read_value','subscribe_events','execute_scene'];
                if(in_array($token,$known,true))$caps[]=$token;
                elseif($token==='on')$caps[]='turn_on';elseif($token==='off')$caps[]='turn_off';
            }
        }
        if(in_array($type,['relay','switch','light','dimmer'],true)){if($caps===[])$caps=['read_state'];}
        elseif(in_array($type,['cover','blind','shutter'],true)){if($caps===[])$caps=['read_state'];}
        elseif(in_array($type,['sensor','binary_sensor','contact','energy_meter','thermostat','climate'],true)){if($caps===[])$caps=['read_value'];}
        elseif($type==='scene'){if($caps===[])$caps=['execute_scene'];}
        else{if($caps===[])$caps=['read_state'];}
        return array_values(array_unique($caps));
    }

    private function normalizeStatus(string $status): string
    {
        $s=strtolower(trim($status));
        if(in_array($s,['1','true','online','connected','ready','available','active','ok'],true))return 'online';
        if(in_array($s,['0','false','offline','disconnected','unavailable','inactive'],true))return 'offline';
        if(in_array($s,['disabled','disable'],true))return 'disabled';
        if(in_array($s,['error','failed','fault'],true))return 'error';
        return 'unknown';
    }

    private function first(array $row,array $keys): string
    {
        foreach($keys as $key){if(!array_key_exists($key,$row))continue;$v=$row[$key];if(is_bool($v))return $v?'true':'false';if(is_scalar($v)&&trim((string)$v)!=='')return trim((string)$v);}
        return '';
    }

    private function looksLikeInventory(array $parsed): bool
    {
        return count((array)($parsed['devices']??[]))>0||count((array)($parsed['gateways']??[]))>0||count((array)($parsed['rooms']??[]))>0||count((array)($parsed['groups']??[]))>0;
    }

    private function normalizePath(string $path): string
    {
        $path=trim($path);if($path==='')return '';
        if(preg_match('#^https?://#i',$path)){$parts=parse_url($path);$path=(string)($parts['path']??'/');}
        if(!str_starts_with($path,'/'))$path='/'.$path;
        return $path;
    }

    private function attemptSummary(array $r): array
    {
        return ['method'=>$r['method'],'path'=>$r['path'],'http_status'=>$r['http_status'],'latency_ms'=>$r['latency_ms'],'error'=>$r['error'],'json'=>is_array($r['json']),'sample'=>$r['body_sample']];
    }
}
