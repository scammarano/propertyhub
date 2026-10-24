<?php
declare(strict_types=1);

final class OnvifClient
{
    public function __construct(
        private string $deviceServiceUrl,
        private string $username='',
        private string $password='',
        private bool $verifyTls=true,
        private int $timeout=10,
    ) {
        $this->deviceServiceUrl=self::normalizeDeviceServiceUrl($deviceServiceUrl);
    }

    public static function normalizeDeviceServiceUrl(string $value,int $port=80): string
    {
        $value=trim($value);
        if($value==='') throw new InvalidArgumentException('Indique IP, hostname o URL ONVIF.');
        if(!preg_match('#^https?://#i',$value)){
            $host=$value;
            if(str_contains($host,'/')) $host=explode('/',$host,2)[0];
            $scheme=$port===443?'https':'http';
            $authority=$host.(in_array($port,[80,443],true)?'':':'.$port);
            return $scheme.'://'.$authority.'/onvif/device_service';
        }
        $parts=parse_url($value);
        if(!is_array($parts)||empty($parts['host'])) throw new InvalidArgumentException('La URL ONVIF no es válida.');
        $path=(string)($parts['path']??'');
        if($path===''||$path==='/') $value=rtrim($value,'/').'/onvif/device_service';
        return $value;
    }

    public function probe(): array
    {
        $started=microtime(true);
        $deviceXml=$this->soap($this->deviceServiceUrl,'http://www.onvif.org/ver10/device/wsdl/GetDeviceInformation','<tds:GetDeviceInformation/>');
        $deviceDoc=$this->xml($deviceXml);
        $info=[
            'manufacturer'=>$this->xpathString($deviceDoc,'string(//*[local-name()="GetDeviceInformationResponse"]/*[local-name()="Manufacturer"])'),
            'model'=>$this->xpathString($deviceDoc,'string(//*[local-name()="GetDeviceInformationResponse"]/*[local-name()="Model"])'),
            'firmware_version'=>$this->xpathString($deviceDoc,'string(//*[local-name()="GetDeviceInformationResponse"]/*[local-name()="FirmwareVersion"])'),
            'serial_number'=>$this->xpathString($deviceDoc,'string(//*[local-name()="GetDeviceInformationResponse"]/*[local-name()="SerialNumber"])'),
            'hardware_id'=>$this->xpathString($deviceDoc,'string(//*[local-name()="GetDeviceInformationResponse"]/*[local-name()="HardwareId"])'),
        ];

        $services=[];$serviceError='';
        try{$services=$this->getServices();}catch(Throwable $e){$serviceError=$e->getMessage();}

        $capMediaUrl='';$capError='';
        try{
            $capXml=$this->soap($this->deviceServiceUrl,'http://www.onvif.org/ver10/device/wsdl/GetCapabilities','<tds:GetCapabilities><tds:Category>All</tds:Category></tds:GetCapabilities>');
            $capDoc=$this->xml($capXml);
            $capMediaUrl=$this->xpathString($capDoc,'string(//*[local-name()="Capabilities"]/*[local-name()="Media"]/@XAddr)');
        }catch(Throwable $e){$capError=$e->getMessage();}

        $candidates=$this->mediaCandidates($services,$capMediaUrl);
        $profiles=[];$streamUri='';$snapshotUri='';$mediaUrl='';$mediaVersion='';$attempts=[];$mediaError='';
        foreach($candidates as $candidate){
            $url=(string)$candidate['url'];$version=(string)$candidate['version'];
            try{
                $candidateProfiles=$this->getProfiles($url,$version);
                if($candidateProfiles===[]){
                    $attempts[]=['version'=>$version,'url'=>$url,'ok'=>true,'profiles'=>0,'message'=>'Servicio respondió sin perfiles.'];
                    continue;
                }
                foreach($candidateProfiles as &$profile){
                    $token=(string)($profile['token']??'');if($token==='')continue;
                    try{$profile['stream_uri']=$this->getStreamUri($url,$token,$version);}catch(Throwable $e){$profile['stream_error']=$e->getMessage();}
                    try{$profile['snapshot_uri']=$this->getSnapshotUri($url,$token,$version);}catch(Throwable $e){$profile['snapshot_error']=$e->getMessage();}
                }
                unset($profile);
                $profiles=$candidateProfiles;$mediaUrl=$url;$mediaVersion=$version;
                $streamUri=(string)($profiles[0]['stream_uri']??'');$snapshotUri=(string)($profiles[0]['snapshot_uri']??'');
                $attempts[]=['version'=>$version,'url'=>$url,'ok'=>true,'profiles'=>count($profiles)];
                break;
            }catch(Throwable $e){
                $mediaError=$e->getMessage();$attempts[]=['version'=>$version,'url'=>$url,'ok'=>false,'profiles'=>0,'message'=>$e->getMessage()];
            }
        }
        if($mediaUrl===''&&$candidates!==[]){$mediaUrl=(string)$candidates[0]['url'];$mediaVersion=(string)$candidates[0]['version'];}

        $lat=(int)round((microtime(true)-$started)*1000);
        $message=$profiles!==[]?'Equipo ONVIF respondió correctamente y expuso perfiles multimedia.':'Equipo ONVIF respondió, pero no se pudieron enumerar perfiles multimedia.';
        return [
            'ok'=>true,'message'=>$message,'latency_ms'=>$lat,
            'device_service_url'=>$this->deviceServiceUrl,'media_service_url'=>$mediaUrl,'media_service_version'=>$mediaVersion,
            'device'=>$info,'profiles'=>$profiles,'profile_count'=>count(array_filter($profiles,static fn($p)=>!empty($p['token']))),
            'rtsp_uri'=>$streamUri,'snapshot_uri'=>$snapshotUri,
            'media_error'=>$profiles===[]?$mediaError:'',
            'media_diagnostics'=>[
                'services'=>$services,'service_discovery_error'=>$serviceError,'capabilities_error'=>$capError,
                'candidates'=>$candidates,'attempts'=>$attempts,
            ],
        ];
    }

    private function getServices(): array
    {
        $xml=$this->soap($this->deviceServiceUrl,'http://www.onvif.org/ver10/device/wsdl/GetServices','<tds:GetServices><tds:IncludeCapability>false</tds:IncludeCapability></tds:GetServices>');
        $doc=$this->xml($xml);$xp=new DOMXPath($doc);$rows=[];$nodes=$xp->query('//*[local-name()="GetServicesResponse"]/*[local-name()="Service"]');
        if($nodes===false)return [];
        foreach($nodes as $node){if(!$node instanceof DOMElement)continue;$ns=trim((string)$xp->evaluate('string(./*[local-name()="Namespace"])',$node));$x=trim((string)$xp->evaluate('string(./*[local-name()="XAddr"])',$node));if($x!=='')$rows[]=['namespace'=>$ns,'xaddr'=>$x];}
        return $rows;
    }

    private function mediaCandidates(array $services,string $capMediaUrl): array
    {
        $rows=[];$seen=[];$add=static function(array &$rows,array &$seen,string $url,string $version,string $source): void {
            $url=trim($url);if($url==='')return;$key=strtolower($version.'|'.rtrim($url,'/'));if(isset($seen[$key]))return;$seen[$key]=true;$rows[]=['url'=>$url,'version'=>$version,'source'=>$source];
        };
        foreach($services as $service){if(!is_array($service))continue;$ns=strtolower((string)($service['namespace']??''));$x=(string)($service['xaddr']??'');if(str_contains($ns,'/ver20/media/wsdl'))$add($rows,$seen,$x,'media2','GetServices');elseif(str_contains($ns,'/ver10/media/wsdl'))$add($rows,$seen,$x,'media1','GetServices');}
        if($capMediaUrl!==''){
            $matched='';foreach($services as $service){if(!is_array($service))continue;if(rtrim((string)($service['xaddr']??''),'/')===rtrim($capMediaUrl,'/')){$ns=strtolower((string)($service['namespace']??''));$matched=str_contains($ns,'/ver20/media/wsdl')?'media2':'media1';break;}}
            $add($rows,$seen,$capMediaUrl,$matched?:'media1','GetCapabilities');
        }
        $add($rows,$seen,$this->guessMediaUrl($this->deviceServiceUrl),'media1','fallback');
        $add($rows,$seen,$this->guessMedia2Url($this->deviceServiceUrl),'media2','fallback');
        return $rows;
    }

    private function getProfiles(string $mediaUrl,string $version): array
    {
        if($version==='media2'){
            $xml=$this->soap($mediaUrl,'http://www.onvif.org/ver20/media/wsdl/GetProfiles','<tr2:GetProfiles><tr2:Type>All</tr2:Type></tr2:GetProfiles>');
            return $this->parseProfiles($this->xml($xml),'media2');
        }
        $xml=$this->soap($mediaUrl,'http://www.onvif.org/ver10/media/wsdl/GetProfiles','<trt:GetProfiles/>');
        return $this->parseProfiles($this->xml($xml),'media1');
    }

    private function getStreamUri(string $mediaUrl,string $token,string $version): string
    {
        if($version==='media2'){
            $body='<tr2:GetStreamUri><tr2:Protocol>RtspUnicast</tr2:Protocol><tr2:ProfileToken>'.self::x($token).'</tr2:ProfileToken></tr2:GetStreamUri>';
            $xml=$this->soap($mediaUrl,'http://www.onvif.org/ver20/media/wsdl/GetStreamUri',$body);
            return $this->xpathString($this->xml($xml),'string(//*[local-name()="GetStreamUriResponse"]/*[local-name()="Uri"])');
        }
        $body='<trt:GetStreamUri><trt:StreamSetup><tt:Stream>RTP-Unicast</tt:Stream><tt:Transport><tt:Protocol>RTSP</tt:Protocol></tt:Transport></trt:StreamSetup><trt:ProfileToken>'.self::x($token).'</trt:ProfileToken></trt:GetStreamUri>';
        $xml=$this->soap($mediaUrl,'http://www.onvif.org/ver10/media/wsdl/GetStreamUri',$body);
        return $this->xpathString($this->xml($xml),'string(//*[local-name()="MediaUri"]/*[local-name()="Uri"])');
    }

    private function getSnapshotUri(string $mediaUrl,string $token,string $version): string
    {
        if($version==='media2'){
            $body='<tr2:GetSnapshotUri><tr2:ProfileToken>'.self::x($token).'</tr2:ProfileToken></tr2:GetSnapshotUri>';
            $xml=$this->soap($mediaUrl,'http://www.onvif.org/ver20/media/wsdl/GetSnapshotUri',$body);
            return $this->xpathString($this->xml($xml),'string(//*[local-name()="GetSnapshotUriResponse"]/*[local-name()="Uri"])');
        }
        $body='<trt:GetSnapshotUri><trt:ProfileToken>'.self::x($token).'</trt:ProfileToken></trt:GetSnapshotUri>';
        $xml=$this->soap($mediaUrl,'http://www.onvif.org/ver10/media/wsdl/GetSnapshotUri',$body);
        return $this->xpathString($this->xml($xml),'string(//*[local-name()="MediaUri"]/*[local-name()="Uri"])');
    }

    private function parseProfiles(DOMDocument $doc,string $version='media1'): array
    {
        $xp=new DOMXPath($doc);$rows=[];$nodes=$xp->query('//*[local-name()="Profiles"]');if($nodes===false)return [];
        foreach($nodes as $node){
            if(!$node instanceof DOMElement)continue;
            $token=trim((string)$node->getAttribute('token'));if($token==='')$token=trim((string)$xp->evaluate('string(./*[local-name()="Token"])',$node));
            $name=trim((string)$xp->evaluate('string(./*[local-name()="Name"])',$node));
            $encoding=trim((string)$xp->evaluate('string(.//*[local-name()="VideoEncoderConfiguration"]/*[local-name()="Encoding"])',$node));
            if($encoding==='')$encoding=trim((string)$xp->evaluate('string(.//*[local-name()="VideoEncoder"]/*[local-name()="Encoding"])',$node));
            $w=trim((string)$xp->evaluate('string(.//*[local-name()="VideoEncoderConfiguration"]/*[local-name()="Resolution"]/*[local-name()="Width"])',$node));
            $h=trim((string)$xp->evaluate('string(.//*[local-name()="VideoEncoderConfiguration"]/*[local-name()="Resolution"]/*[local-name()="Height"])',$node));
            if($w==='')$w=trim((string)$xp->evaluate('string(.//*[local-name()="VideoEncoder"]/*[local-name()="Resolution"]/*[local-name()="Width"])',$node));
            if($h==='')$h=trim((string)$xp->evaluate('string(.//*[local-name()="VideoEncoder"]/*[local-name()="Resolution"]/*[local-name()="Height"])',$node));
            $sourceToken=trim((string)$xp->evaluate('string(.//*[local-name()="VideoSourceConfiguration"]/*[local-name()="SourceToken"])',$node));
            $sourceConfigToken=trim((string)$xp->evaluate('string(.//*[local-name()="VideoSourceConfiguration"]/@token)',$node));
            $encoderToken=trim((string)$xp->evaluate('string(.//*[local-name()="VideoEncoderConfiguration"]/@token)',$node));
            if($version==='media2'){
                if($sourceConfigToken==='')$sourceConfigToken=$this->configurationRefToken($xp,$node,'VideoSource');
                if($encoderToken==='')$encoderToken=$this->configurationRefToken($xp,$node,'VideoEncoder');
            }
            $fps=trim((string)$xp->evaluate('string(.//*[local-name()="VideoEncoderConfiguration"]/*[local-name()="RateControl"]/*[local-name()="FrameRateLimit"])',$node));
            if($token==='')continue;
            $rows[]=['token'=>$token,'name'=>$name!==''?$name:$token,'encoding'=>$encoding,'resolution'=>($w!==''&&$h!=='')?$w.'×'.$h:'','video_source_token'=>$sourceToken,'video_source_config_token'=>$sourceConfigToken,'video_encoder_token'=>$encoderToken,'fps'=>$fps,'media_version'=>$version];
        }
        return $rows;
    }

    private function configurationRefToken(DOMXPath $xp,DOMElement $profile,string $wanted): string
    {
        $refs=$xp->query('.//*[local-name()="Configurations" or local-name()="Configuration"]',$profile);if($refs===false)return '';
        foreach($refs as $ref){if(!$ref instanceof DOMElement)continue;$type=trim((string)$xp->evaluate('string(./*[local-name()="Type"])',$ref));if($type!==''&&!str_contains(strtolower($type),strtolower($wanted)))continue;$token=trim((string)$xp->evaluate('string(./*[local-name()="Token"])',$ref));if($token!=='')return $token;}
        return '';
    }

    private function soap(string $url,string $action,string $body): string
    {
        if(!extension_loaded('curl'))throw new RuntimeException('La extensión cURL no está disponible.');
        $security=$this->securityHeader();
        $envelope='<?xml version="1.0" encoding="UTF-8"?>'
            .'<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope" xmlns:tds="http://www.onvif.org/ver10/device/wsdl" xmlns:trt="http://www.onvif.org/ver10/media/wsdl" xmlns:tr2="http://www.onvif.org/ver20/media/wsdl" xmlns:tt="http://www.onvif.org/ver10/schema" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">'
            .'<s:Header>'.$security.'</s:Header><s:Body>'.$body.'</s:Body></s:Envelope>';
        $ch=curl_init($url);if($ch===false)throw new RuntimeException('No fue posible iniciar cURL para ONVIF.');
        $headers=['Accept: application/soap+xml, application/xml, text/xml','Content-Type: application/soap+xml; charset=utf-8; action="'.$action.'"','SOAPAction: "'.$action.'"'];
        $opts=[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>$this->timeout,CURLOPT_CONNECTTIMEOUT=>min(5,$this->timeout),CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>$envelope,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>$this->verifyTls,CURLOPT_SSL_VERIFYHOST=>$this->verifyTls?2:0];
        if($this->username!==''){$opts[CURLOPT_HTTPAUTH]=CURLAUTH_ANY;$opts[CURLOPT_USERPWD]=$this->username.':'.$this->password;}
        curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);$text=$raw===false?'':(string)$raw;
        if($raw===false)throw new RuntimeException('No fue posible conectar con ONVIF: '.$err);
        if($status===401||$status===403)throw new RuntimeException('ONVIF rechazó las credenciales (HTTP '.$status.'). Revise usuario/contraseña y que ONVIF esté habilitado en la cámara.');
        if($status<200||$status>=300)throw new RuntimeException('ONVIF respondió HTTP '.$status.($text!==''?' · '.mb_substr(trim(strip_tags($text)),0,220):''));
        $doc=$this->xml($text);$fault=$this->xpathString($doc,'string(//*[local-name()="Fault"]//*[local-name()="Text"])');if($fault==='')$fault=$this->xpathString($doc,'string(//*[local-name()="Fault"]//*[local-name()="Reason"])');if($fault!=='')throw new RuntimeException('ONVIF SOAP Fault: '.$fault);return $text;
    }

    private function securityHeader(): string
    {
        if($this->username==='')return '';$nonce=random_bytes(20);$created=gmdate('Y-m-d\TH:i:s\Z');$digest=base64_encode(sha1($nonce.$created.$this->password,true));
        return '<wsse:Security s:mustUnderstand="1"><wsse:UsernameToken><wsse:Username>'.self::x($this->username).'</wsse:Username><wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest">'.$digest.'</wsse:Password><wsse:Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">'.base64_encode($nonce).'</wsse:Nonce><wsu:Created>'.$created.'</wsu:Created></wsse:UsernameToken></wsse:Security>';
    }

    private function xml(string $xml): DOMDocument
    {
        if(!class_exists('DOMDocument'))throw new RuntimeException('La extensión DOM/XML de PHP no está disponible.');$doc=new DOMDocument();$old=libxml_use_internal_errors(true);$ok=$doc->loadXML($xml,LIBXML_NONET|LIBXML_NOBLANKS);$errors=libxml_get_errors();libxml_clear_errors();libxml_use_internal_errors($old);if(!$ok){$msg=$errors?trim((string)$errors[0]->message):'XML inválido';throw new RuntimeException('ONVIF devolvió XML inválido: '.$msg);}return $doc;
    }

    private function xpathString(DOMDocument $doc,string $expr): string { $xp=new DOMXPath($doc);return trim((string)$xp->evaluate($expr)); }
    private function guessMediaUrl(string $device): string { $parts=parse_url($device);if(!is_array($parts)||empty($parts['scheme'])||empty($parts['host']))return $device;$authority=$parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');return $authority.'/onvif/media_service'; }
    private function guessMedia2Url(string $device): string { $parts=parse_url($device);if(!is_array($parts)||empty($parts['scheme'])||empty($parts['host']))return $device;$authority=$parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');return $authority.'/onvif/media2_service'; }
    private static function x(string $value): string { return htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8'); }
}
