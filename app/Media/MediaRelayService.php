<?php
declare(strict_types=1);

final class MediaRelayService
{
    public function __construct(private PDO $pdo) {}

    private function installationId(): int
    {
        $stmt=$this->pdo->prepare('SELECT id FROM bh_installations WHERE code=? LIMIT 1');
        $stmt->execute([(string)config('app.instance_code')]);
        $id=(int)$stmt->fetchColumn();
        if($id<1)throw new RuntimeException('No se encontró la instalación local para configurar el Media Relay.');
        return $id;
    }

    public function settings(): array
    {
        $iid=$this->installationId();
        $stmt=$this->pdo->prepare('SELECT * FROM bh_media_relay_settings WHERE installation_id=? LIMIT 1');
        $stmt->execute([$iid]);
        $row=$stmt->fetch();
        if(!is_array($row)){
            return [
                'installation_id'=>$iid,'relay_name'=>'Property Hub Media Relay','media_mode'=>'cpanel_test','webrtc_base_url'=>'',
                'publish_rtsp_base_url'=>'','status'=>'not_configured','last_test_at'=>null,'last_error'=>null,
                'publish_user'=>'','publish_password_set'=>false,'viewer_user'=>'','viewer_password_set'=>false,
            ];
        }
        $secret=EdgeSecretStore::decrypt((string)($row['credentials_encrypted']??''));
        $row['publish_user']=(string)($secret['publish_user']??'');
        $row['viewer_user']=(string)($secret['viewer_user']??'');
        $row['publish_password_set']=trim((string)($secret['publish_password']??''))!=='';
        $row['viewer_password_set']=trim((string)($secret['viewer_password']??''))!=='';
        return $row;
    }

    public function save(array $data): void
    {
        if(!InstallationMode::isNode())throw new RuntimeException('El Media Relay se configura en el Property Node.');
        $iid=$this->installationId();
        $current=$this->settings();
        $oldSecret=[];
        $stmt=$this->pdo->prepare('SELECT credentials_encrypted FROM bh_media_relay_settings WHERE installation_id=? LIMIT 1');
        $stmt->execute([$iid]);
        $old=(string)($stmt->fetchColumn()?:'');
        if($old!=='')$oldSecret=EdgeSecretStore::decrypt($old);

        $name=trim((string)($data['relay_name']??'Property Hub Media Relay')) ?: 'Property Hub Media Relay';
        $mode=(string)($data['media_mode']??'cpanel_test');if(!in_array($mode,['cpanel_test','edge_direct_webrtc','external_webrtc'],true))$mode='cpanel_test';
        $webrtc=$this->normalizeHttpBase((string)($data['webrtc_base_url']??''));
        $publish=$this->normalizeRtspBase((string)($data['publish_rtsp_base_url']??''));
        $publishUser=trim((string)($data['publish_user']??''));
        $viewerUser=trim((string)($data['viewer_user']??''));
        $publishPass=(string)($data['publish_password']??'');
        $viewerPass=(string)($data['viewer_password']??'');
        if($publishPass==='')$publishPass=(string)($oldSecret['publish_password']??'');
        if($viewerPass==='')$viewerPass=(string)($oldSecret['viewer_password']??'');
        if($publishUser==='')$publishUser=(string)($oldSecret['publish_user']??'');
        if($viewerUser==='')$viewerUser=(string)($oldSecret['viewer_user']??'');
        if(($mode==='edge_direct_webrtc'||$mode==='external_webrtc')&&($mode==='edge_direct_webrtc'||($webrtc!==''&&$publish!==''))){
            $suffix=strtolower(substr((string)preg_replace('/[^A-Za-z0-9]/','',(string)config('app.instance_code')),0,18));
            if($publishUser==='')$publishUser='ph_publish_'.($suffix?:'node');
            if($viewerUser==='')$viewerUser='ph_view_'.($suffix?:'node');
            if($publishPass==='')$publishPass=rtrim(strtr(base64_encode(random_bytes(24)),'+/','-_'),'=');
            if($viewerPass==='')$viewerPass=rtrim(strtr(base64_encode(random_bytes(24)),'+/','-_'),'=');
        }

        $status=$mode==='cpanel_test'?'configured':($mode==='edge_direct_webrtc'?'configured':(($webrtc!==''&&$publish!=='')?'configured':'not_configured'));
        $enc=EdgeSecretStore::encrypt([
            'publish_user'=>$publishUser,'publish_password'=>$publishPass,
            'viewer_user'=>$viewerUser,'viewer_password'=>$viewerPass,
        ]);
        $sql="INSERT INTO bh_media_relay_settings (installation_id,relay_name,media_mode,webrtc_base_url,publish_rtsp_base_url,credentials_encrypted,status,last_error) VALUES (?,?,?,?,?,?,?,NULL) ON DUPLICATE KEY UPDATE relay_name=VALUES(relay_name),media_mode=VALUES(media_mode),webrtc_base_url=VALUES(webrtc_base_url),publish_rtsp_base_url=VALUES(publish_rtsp_base_url),credentials_encrypted=VALUES(credentials_encrypted),status=VALUES(status),last_error=NULL";
        $this->pdo->prepare($sql)->execute([$iid,$name,$mode,$webrtc?:null,$publish?:null,$enc,$status]);
    }

    public function test(): array
    {
        if(!InstallationMode::isNode())throw new RuntimeException('El Media Relay se prueba desde el Property Node.');
        $settings=$this->settings();
        $mode=(string)($settings['media_mode']??'cpanel_test');
        if($mode==='cpanel_test')return ['ok'=>true,'message'=>'Modo snapshot cPanel activo. No hay video continuo en este modo.'];
        if($mode==='edge_direct_webrtc'){
            $endpoints=$this->directEndpoints();$online=[];
            foreach($endpoints as &$ep){$url=trim((string)($ep['public_base_url']??''));$probe=$url!==''?$this->probeHttp($url):['reachable'=>false,'error'=>'sin URL'];$isOnline=!empty($probe['reachable']);$ep['probe']=$probe;$this->pdo->prepare("UPDATE bh_edge_media_endpoints SET status=?,last_seen_at=CASE WHEN ?='online' THEN NOW() ELSE last_seen_at END,last_error=NULLIF(?,'') WHERE id=?")->execute([$isOnline?'online':'offline',$isOnline?'online':'offline',$isOnline?'':(string)($probe['error']??'sin respuesta'),(int)$ep['id']]);if($isOnline)$online[]=$ep;}unset($ep);
            $ok=count($online)>0;$error=$ok?'':'Ningún Edge Agent tiene un endpoint HTTPS WebRTC accesible ahora.';$stmt=$this->pdo->prepare("UPDATE bh_media_relay_settings SET status=?,last_test_at=NOW(),last_error=NULLIF(?,'') WHERE installation_id=?");$stmt->execute([$ok?'online':'configured',$error,$this->installationId()]);
            return ['ok'=>$ok,'endpoints'=>$endpoints,'message'=>$ok?'WebRTC directo Edge accesible: '.count($online).' endpoint(s).':'El Node está listo, pero todavía no alcanza un túnel WebRTC del Edge. Pulse “Preparar WebRTC cPanel” en el Agent.'];
        }
        $webrtc=trim((string)($settings['webrtc_base_url']??''));$publish=trim((string)($settings['publish_rtsp_base_url']??''));
        if($webrtc===''||$publish==='')throw new RuntimeException('Configure primero URL WebRTC/WHEP y URL RTSP de publicación del Media Relay.');
        $http=$this->probeHttp($webrtc);$tcp=$this->probeRtsp($publish);$ok=!empty($http['reachable'])&&!empty($tcp['reachable']);
        $error=$ok?'':trim((string)($http['error']??'').' '.(string)($tcp['error']??''));
        $stmt=$this->pdo->prepare("UPDATE bh_media_relay_settings SET status=?,last_test_at=NOW(),last_error=NULLIF(?,'') WHERE installation_id=?");$stmt->execute([$ok?'online':'error',$error,$this->installationId()]);
        return ['ok'=>$ok,'webrtc'=>$http,'publish_rtsp'=>$tcp,'message'=>$ok?'Media Relay accesible por HTTPS/HTTP y RTSP.':'Media Relay no pasó todas las pruebas.'];
    }

    public function provisioning(): array
    {
        $settings=$this->settings();
        $stmt=$this->pdo->prepare('SELECT credentials_encrypted FROM bh_media_relay_settings WHERE installation_id=? LIMIT 1');
        $stmt->execute([$this->installationId()]);
        $secret=EdgeSecretStore::decrypt((string)($stmt->fetchColumn()?:''));
        $mode=(string)($settings['media_mode']??'cpanel_test');$enabled=$mode==='edge_direct_webrtc'||($mode==='external_webrtc'&&trim((string)($settings['webrtc_base_url']??''))!==''&&trim((string)($settings['publish_rtsp_base_url']??''))!=='');
        return [
            'enabled'=>$enabled,'media_mode'=>$mode,
            'relay_name'=>(string)($settings['relay_name']??'Property Hub Media Relay'),
            'webrtc_base_url'=>(string)($settings['webrtc_base_url']??''),
            'publish_rtsp_base_url'=>(string)($settings['publish_rtsp_base_url']??''),
            'publish_user'=>(string)($secret['publish_user']??''),
            'publish_password'=>(string)($secret['publish_password']??''),
            'viewer_user'=>(string)($secret['viewer_user']??''),
            'viewer_password'=>(string)($secret['viewer_password']??''),
            'status'=>(string)($settings['status']??'not_configured'),
        ];
    }

    public function viewerConfig(array $camera): array
    {
        $settings=$this->settings();
        $mode=(string)($settings['media_mode']??'cpanel_test');
        if($mode==='cpanel_test')return ['ok'=>false,'available'=>false,'mode'=>'cpanel_test','message'=>'Modo snapshot cPanel: active WebRTC directo Edge para video en vivo sin VPS.'];
        $stmt=$this->pdo->prepare('SELECT credentials_encrypted FROM bh_media_relay_settings WHERE installation_id=? LIMIT 1');$stmt->execute([$this->installationId()]);$secret=EdgeSecretStore::decrypt((string)($stmt->fetchColumn()?:''));
        $path=trim((string)($camera['live_path']??''),'/');$base='';$hlsBase='';
        if($mode==='edge_direct_webrtc'){
            $agentId=(int)($camera['edge_agent_id']??0);if($agentId>0){$ep=$this->pdo->prepare("SELECT public_base_url,metadata_json FROM bh_edge_media_endpoints WHERE installation_id=? AND edge_agent_id=? AND status='online' ORDER BY last_seen_at DESC,id DESC LIMIT 1");$ep->execute([$this->installationId(),$agentId]);$epRow=(array)($ep->fetch()?:[]);$base=rtrim(trim((string)($epRow['public_base_url']??'')),'/');$epMeta=json_decode((string)($epRow['metadata_json']??''),true);if(!is_array($epMeta))$epMeta=[];$hlsBase=rtrim(trim((string)($epMeta['hls_public_base_url']??'')),'/');}
        }else{$base=rtrim(trim((string)($settings['webrtc_base_url']??'')),'/');}
        if(empty($camera['live_enabled'])||$base===''||$path===''||(string)($camera['live_status']??'')!=='ready')return ['ok'=>false,'available'=>false,'mode'=>$mode,'message'=>$base===''?'El Edge Agent todavía no reportó un endpoint WebRTC público.':'Video WebRTC todavía no está listo para esta cámara.'];
        $viewer=$this->cameraViewerCredentials($path,$secret);
        $whepHost=strtolower((string)(parse_url($base,PHP_URL_HOST)?:''));
        $quickTunnel=$whepHost!==''&&str_ends_with($whepHost,'.trycloudflare.com');
        return [
            'ok'=>true,'available'=>true,'transport'=>($quickTunnel&&$hlsBase!=='')?'hls':'webrtc','prefer_hls'=>$quickTunnel&&$hlsBase!=='','whep_url'=>$base.'/'.$path.'/whep',
            'hls_url'=>$hlsBase!==''?$hlsBase.'/'.$path.'/index.m3u8':'',
            'hls_page_url'=>$hlsBase!==''?$hlsBase.'/'.$path:'',
            'user'=>$viewer['user'],'pass'=>$viewer['pass'],
            'camera_id'=>(int)($camera['id']??0),'camera_name'=>(string)($camera['name']??'Cámara'),
            'codec'=>(string)($camera['live_codec']??''),'allow_audio'=>!empty($camera['allow_audio']),
            'relay_name'=>$mode==='edge_direct_webrtc'?'Edge directo · cPanel':(string)($settings['relay_name']??'Media Relay'),
            'mode'=>$mode,
        ];
    }

    public function ensureLivePath(int $cameraId,string $cameraUuid): string
    {
        $stmt=$this->pdo->prepare('SELECT live_path FROM bh_cameras WHERE id=? AND installation_id=? LIMIT 1');
        $stmt->execute([$cameraId,$this->installationId()]);
        $existing=trim((string)($stmt->fetchColumn()?:''));
        if($existing!=='')return $existing;
        $code=strtolower((string)preg_replace('/[^A-Za-z0-9]+/','-',(string)config('app.instance_code')));
        $uuidShort=substr((string)preg_replace('/[^a-f0-9]/i','',$cameraUuid),0,12);
        $path='ph-'.$code.'-'.$uuidShort.'-'.bin2hex(random_bytes(8));
        $this->pdo->prepare('UPDATE bh_cameras SET live_path=?,live_transport=CASE WHEN live_enabled=1 THEN \'webrtc\' ELSE live_transport END WHERE id=? AND installation_id=?')->execute([$path,$cameraId,$this->installationId()]);
        return $path;
    }

    public function directEndpoints(): array
    {
        if(!InstallationMode::isNode())return [];
        $stmt=$this->pdo->prepare("SELECT e.*,a.agent_name FROM bh_edge_media_endpoints e LEFT JOIN bh_edge_agents a ON a.id=e.edge_agent_id WHERE e.installation_id=? ORDER BY e.agent_code");$stmt->execute([$this->installationId()]);return $stmt->fetchAll()?:[];
    }

    public function publicRelayConfig(): string
    {
        if(!InstallationMode::isNode())throw new RuntimeException('La configuración del Media Relay se genera desde el Property Node.');
        $settings=$this->settings();if((string)($settings['media_mode']??'cpanel_test')!=='external_webrtc')throw new RuntimeException('La descarga de mediamtx.yml público solo corresponde al modo Relay WebRTC externo.');$webrtc=trim((string)($settings['webrtc_base_url']??''));$publish=trim((string)($settings['publish_rtsp_base_url']??''));
        if($webrtc===''||$publish==='')throw new RuntimeException('Configure primero las URLs públicas del Media Relay.');
        $stmt=$this->pdo->prepare('SELECT credentials_encrypted FROM bh_media_relay_settings WHERE installation_id=? LIMIT 1');$stmt->execute([$this->installationId()]);$secret=EdgeSecretStore::decrypt((string)($stmt->fetchColumn()?:''));
        $pubUser=(string)($secret['publish_user']??'');$pubPass=(string)($secret['publish_password']??'');$viewUser=(string)($secret['viewer_user']??'');$viewPass=(string)($secret['viewer_password']??'');
        if($pubUser===''||$pubPass===''||$viewUser===''||$viewPass==='')throw new RuntimeException('Faltan credenciales del Media Relay. Guarde de nuevo la configuración para generarlas.');
        $host=(string)(parse_url($webrtc,PHP_URL_HOST)?:parse_url($publish,PHP_URL_HOST)?:'');if($host==='')throw new RuntimeException('No se pudo determinar el hostname público del Media Relay.');
        $q=static fn(string $v): string => json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?: '""';
        $lines=[
            '# Property Hub Media Relay · MediaMTX 1.20.0',
            '# Generated by '.$q((string)config('app.instance_code')).' on '.date(DATE_ATOM),
            'logLevel: info','logDestinations: [stdout]',
            'authMethod: internal','authInternalUsers:',
            '  - user: '.$q($pubUser),'    pass: '.$q($pubPass),'    ips: []','    permissions:','      - action: publish','        path:',
            '  - user: '.$q($viewUser),'    pass: '.$q($viewPass),'    ips: []','    permissions:','      - action: read','        path:',
            '  - user: any','    pass:','    ips: ["127.0.0.1", "::1"]','    permissions:','      - action: api','      - action: metrics','      - action: pprof',
            'rtsp: true','rtspTransports: [tcp]','rtspAddress: :8554','rtmp: false','hls: false','srt: false','moq: false','playback: false',
            'webrtc: true','webrtcAddress: :8889','webrtcEncryption: false','webrtcAllowOrigins: ["*"]','webrtcLocalUDPAddress: :8189','webrtcLocalTCPAddress: :8189','webrtcIPsFromInterfaces: true','webrtcAdditionalHosts: ['.$q($host).']',
            'api: true','apiAddress: 127.0.0.1:9997','metrics: false','pprof: false',
            'paths:','  all_others:','    source: publisher',
        ];
        return implode("\n",$lines)."\n";
    }

    public function publishDestination(string $path,array $provisioning): string
    {
        $base=rtrim((string)($provisioning['publish_rtsp_base_url']??''),'/');
        if($base==='')return '';
        $parts=parse_url($base);
        if(!is_array($parts)||empty($parts['host']))return '';
        $scheme=(string)($parts['scheme']??'rtsp');$host=(string)$parts['host'];$port=isset($parts['port'])?':'.(int)$parts['port']:'';
        $user=(string)($provisioning['publish_user']??'');$pass=(string)($provisioning['publish_password']??'');
        $auth=$user!==''?rawurlencode($user).':'.rawurlencode($pass).'@':'';
        $basePath=trim((string)($parts['path']??''),'/');
        $fullPath=trim($basePath.'/'.$path,'/');
        return $scheme.'://'.$auth.$host.$port.'/'.$fullPath;
    }

    public function proxyHlsAsset(array $camera,string $asset='index.m3u8',string $range=''): array
    {
        if(!InstallationMode::isNode())throw new RuntimeException('El proxy HLS seguro solo está disponible en el Property Node.');
        if(!extension_loaded('curl'))throw new RuntimeException('PHP cURL es necesario para el proxy HLS del Node.');
        $settings=$this->settings();$mode=(string)($settings['media_mode']??'cpanel_test');
        if($mode!=='edge_direct_webrtc')throw new RuntimeException('El proxy HLS cPanel requiere modo WebRTC directo Edge.');
        if(empty($camera['live_enabled'])||(string)($camera['live_status']??'')!=='ready')throw new RuntimeException('La cámara todavía no está lista para video en vivo.');
        $path=trim((string)($camera['live_path']??''),'/');if($path==='')throw new RuntimeException('La cámara no tiene live_path asignado.');
        $agentId=(int)($camera['edge_agent_id']??0);if($agentId<1)throw new RuntimeException('La cámara no está asociada a un Edge Agent.');
        $ep=$this->pdo->prepare("SELECT metadata_json FROM bh_edge_media_endpoints WHERE installation_id=? AND edge_agent_id=? AND status='online' ORDER BY last_seen_at DESC,id DESC LIMIT 1");
        $ep->execute([$this->installationId(),$agentId]);$meta=json_decode((string)($ep->fetchColumn()?:''),true);if(!is_array($meta))$meta=[];
        $hlsBase=rtrim(trim((string)($meta['hls_public_base_url']??'')),'/');if($hlsBase==='')throw new RuntimeException('El Edge todavía no reportó un túnel HLS público.');
        $asset=trim($asset);if($asset==='')$asset='index.m3u8';$asset=str_replace('\\','/',$asset);$asset=ltrim($asset,'/');
        if(str_contains($asset,"\0")||str_contains($asset,'..')||preg_match('#^[a-z][a-z0-9+.-]*://#i',$asset))throw new InvalidArgumentException('Ruta HLS no válida.');
        $stmt=$this->pdo->prepare('SELECT credentials_encrypted FROM bh_media_relay_settings WHERE installation_id=? LIMIT 1');$stmt->execute([$this->installationId()]);$secret=EdgeSecretStore::decrypt((string)($stmt->fetchColumn()?:''));
        $viewer=$this->cameraViewerCredentials($path,$secret);if($viewer['user']===''||$viewer['pass']==='')throw new RuntimeException('No se pudo resolver la credencial de lectura de la cámara.');
        $upstream=$hlsBase.'/'.$path.'/'.$asset;$headers=[];
        if($range!=='')$headers[]='Range: '.$range;
        $ch=curl_init($upstream);if($ch===false)throw new RuntimeException('No se pudo iniciar cURL para HLS.');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>$viewer['user'].':'.$viewer['pass'],CURLOPT_HTTPHEADER=>$headers,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'2CAM-Property-Hub-Node-HLS/0.14.3']);
        $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$type=(string)(curl_getinfo($ch,CURLINFO_CONTENT_TYPE)?:'');$effective=(string)(curl_getinfo($ch,CURLINFO_EFFECTIVE_URL)?:$upstream);$err=curl_error($ch);curl_close($ch);
        if(!is_string($body)||$status<200||$status>=300)throw new RuntimeException('HLS upstream respondió HTTP '.$status.($err!==''?' · '.$err:''));
        return ['ok'=>true,'status'=>$status,'content_type'=>$type,'body'=>$body,'upstream_url'=>$effective,'asset'=>$asset,'live_path'=>$path];
    }

    private function normalizeHttpBase(string $value): string
    {
        $value=rtrim(trim($value),'/');
        if($value==='')return '';
        if(!preg_match('#^https?://#i',$value))throw new InvalidArgumentException('La URL WebRTC/WHEP debe comenzar por https:// (o http:// solo para laboratorio).');
        if(parse_url($value,PHP_URL_HOST)===null)throw new InvalidArgumentException('URL WebRTC/WHEP no válida.');
        return $value;
    }


    private function cameraViewerCredentials(string $path,array $secret): array
    {
        $path=trim($path,'/');
        $master=(string)($secret['viewer_password']??'');
        if($path===''||$master==='')return ['user'=>'','pass'=>''];
        $user='phcam_'.substr(hash('sha256',$path),0,16);
        $raw=hash_hmac('sha256','property-hub-media-read|'.$path,$master,true);
        $pass=rtrim(strtr(base64_encode($raw),'+/','-_'),'=');
        return ['user'=>$user,'pass'=>$pass];
    }

    private function normalizeRtspBase(string $value): string
    {
        $value=rtrim(trim($value),'/');
        if($value==='')return '';
        if(!preg_match('#^rtsps?://#i',$value))throw new InvalidArgumentException('La URL de publicación debe comenzar por rtsp:// o rtsps://.');
        if(parse_url($value,PHP_URL_HOST)===null)throw new InvalidArgumentException('URL RTSP de publicación no válida.');
        return $value;
    }

    private function probeHttp(string $url): array
    {
        if(!extension_loaded('curl'))return ['reachable'=>false,'error'=>'cURL no disponible.'];
        $ch=curl_init($url.'/');
        if($ch===false)return ['reachable'=>false,'error'=>'No se pudo iniciar cURL.'];
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_NOBODY=>true,CURLOPT_TIMEOUT=>8,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
        return ['reachable'=>$status>0,'http_status'=>$status,'error'=>$status>0?'':$err];
    }

    private function probeRtsp(string $url): array
    {
        $parts=parse_url($url);$host=(string)($parts['host']??'');$scheme=strtolower((string)($parts['scheme']??'rtsp'));$port=(int)($parts['port']??($scheme==='rtsps'?8322:8554));
        if($host==='')return ['reachable'=>false,'error'=>'Host RTSP inválido.'];
        $errno=0;$errstr='';$start=microtime(true);$fp=@fsockopen($host,$port,$errno,$errstr,5);$lat=(int)round((microtime(true)-$start)*1000);
        if(!is_resource($fp))return ['reachable'=>false,'host'=>$host,'port'=>$port,'latency_ms'=>$lat,'error'=>$errstr!==''?$errstr:'No se pudo abrir TCP.'];
        fclose($fp);return ['reachable'=>true,'host'=>$host,'port'=>$port,'latency_ms'=>$lat];
    }
}
