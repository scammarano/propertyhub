<?php
declare(strict_types=1);

/**
 * Direct, service-free Vimar LAN discovery for Property Edge.
 *
 * This client deliberately starts with discovery and technical inventory only.
 * It does not require the standalone VimarHub daemon and never sends actuator
 * commands. Deep VIEW/By-me accessory enumeration can be layered on the same
 * adapter once a gateway has been identified/authenticated.
 */
final class VimarDirectDiscoveryClient
{
    private const MDNS_GROUP='224.0.0.251';
    private const MDNS_PORT=5353;
    private const KNX_GROUP='224.0.23.12';
    private const KNX_PORT=3671;
    private const SSDP_GROUP='239.255.255.250';
    private const SSDP_PORT=1900;

    public function __construct(private string $localIp='',private int $seconds=5)
    {
        $this->localIp=trim($this->localIp);
        if($this->localIp!==''&&!filter_var($this->localIp,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))throw new InvalidArgumentException('La interfaz Vimar debe ser una IPv4 válida.');
        $this->seconds=max(2,min(12,$this->seconds));
    }

    public function discover(): array
    {
        if(!extension_loaded('sockets'))return ['ok'=>false,'message'=>'La extensión PHP sockets no está activa. Es necesaria para descubrir gateways Vimar directamente en la LAN.','gateways'=>[],'devices'=>[],'candidates'=>[],'warnings'=>[]];
        $warnings=[];$gateways=[];$candidates=[];$attempts=[];

        try{$mdns=$this->discoverMdns();$attempts['mdns']=$mdns['stats'];foreach($mdns['gateways'] as $g)$gateways[]=$g;foreach($mdns['candidates'] as $g)$candidates[]=$g;}catch(Throwable $e){$warnings[]='mDNS: '.$e->getMessage();}
        try{$knx=$this->discoverKnx();$attempts['knx']=$knx['stats'];foreach($knx['gateways'] as $g)$gateways[]=$g;foreach($knx['candidates'] as $g)$candidates[]=$g;}catch(Throwable $e){$warnings[]='KNXnet/IP: '.$e->getMessage();}
        try{$ssdp=$this->discoverSsdp();$attempts['ssdp']=$ssdp['stats'];foreach($ssdp['gateways'] as $g)$gateways[]=$g;foreach($ssdp['candidates'] as $g)$candidates[]=$g;}catch(Throwable $e){$warnings[]='SSDP: '.$e->getMessage();}

        $gateways=$this->dedupe($gateways);$candidates=$this->dedupe($candidates);
        $acceptedKeys=[];foreach($gateways as $g)$acceptedKeys[$this->gatewayKey($g)]=true;
        $candidates=array_values(array_filter($candidates,fn(array $g):bool=>!isset($acceptedKeys[$this->gatewayKey($g)])));

        $count=count($gateways);$message=$count.' gateway(s) compatible(s) Vimar/VIEW/By-me/KNX identificado(s) directamente en la LAN.';
        if($count===0)$message='No se identificó ningún gateway Vimar compatible en esta búsqueda local. No se requiere VimarHub; pruebe otra interfaz o confirme que el gateway está en la misma LAN.';
        if($candidates!==[])$message.=' Se encontraron '.count($candidates).' candidato(s) adicionales que requieren confirmar fabricante/ecosistema.';

        return ['ok'=>true,'mode'=>'direct_auto','message'=>$message,'endpoint'=>'LAN direct','gateways'=>$gateways,'devices'=>[],'rooms'=>[],'groups'=>[],'candidates'=>$candidates,'warnings'=>$warnings,'attempts'=>$attempts,'read_only'=>true];
    }

    private function discoverMdns(): array
    {
        $services=['_hap._tcp.local','_knx._udp.local','_knx._tcp.local','_vimar._tcp.local','_view._tcp.local','_byme._tcp.local','_by-me._tcp.local'];
        $sock=@socket_create(AF_INET,SOCK_DGRAM,SOL_UDP);if($sock===false)throw new RuntimeException('No se pudo crear socket UDP.');
        @socket_set_option($sock,SOL_SOCKET,SO_REUSEADDR,1);if($this->localIp!==''&&!@socket_bind($sock,$this->localIp,0)){@socket_close($sock);throw new RuntimeException('No se pudo usar la interfaz '.$this->localIp.'.');}
        @socket_set_nonblock($sock);$sent=0;
        foreach($services as $service){$packet=$this->dnsQuery($service,12);for($i=0;$i<2;$i++){if(@socket_sendto($sock,$packet,strlen($packet),0,self::MDNS_GROUP,self::MDNS_PORT)!==false)$sent++;usleep(40000);}}
        $end=microtime(true)+$this->seconds;$records=[];$responses=0;
        while(microtime(true)<$end){$buf='';$from='';$port=0;$n=@socket_recvfrom($sock,$buf,65535,0,$from,$port);if($n===false||$n<=0){usleep(60000);continue;}$responses++;foreach($this->parseDnsPacket($buf,$from) as $r)$records[]=$r;}
        @socket_close($sock);

        $ptr=[];$srv=[];$txt=[];$addr=[];
        foreach($records as $r){$type=(int)$r['type'];$name=strtolower((string)$r['name']);if($type===12&&isset($r['ptr']))$ptr[$name][]=strtolower((string)$r['ptr']);elseif($type===33&&isset($r['target']))$srv[$name]=$r;elseif($type===16)$txt[$name]=(array)($r['txt']??[]);elseif($type===1&&isset($r['address']))$addr[$name]=(string)$r['address'];}

        $instances=[];foreach($ptr as $service=>$names)foreach(array_unique($names) as $instance)$instances[$instance]['services'][$service]=true;
        foreach($srv as $instance=>$r){$instances[$instance]['srv']=$r;}foreach($txt as $instance=>$kv){$instances[$instance]['txt']=$kv;}
        $gateways=[];$candidates=[];
        foreach($instances as $instance=>$meta){$s=(array)($meta['srv']??[]);$t=(array)($meta['txt']??[]);$target=strtolower((string)($s['target']??''));$ip=(string)($addr[$target]??$s['source_ip']??'');$port=(int)($s['port']??0);$services=array_keys((array)($meta['services']??[]));
            $evidence=strtolower($instance.' '.$target.' '.implode(' ',array_map('strval',$t)).' '.implode(' ',$services));$isVimar=$this->containsVendorEvidence($evidence);$isHap=in_array('_hap._tcp.local',$services,true);$isKnx=in_array('_knx._udp.local',$services,true)||in_array('_knx._tcp.local',$services,true);
            if(!$isVimar&&!$isKnx&&!$isHap)continue;
            $model=trim((string)($t['md']??$t['model']??''));$hapId=trim((string)($t['id']??''));$name=$this->cleanDnsName($instance);$ecosystem=$isKnx?'knx':($isHap?'view_homekit':'vimar');$confidence=$isVimar?'high':'protocol';
            $row=['external_id'=>'mdns:'.($hapId!==''?$hapId:hash('sha256',$instance.'|'.$ip.'|'.$port)),'normalized_type'=>'gateway','raw_type'=>'gateway','name'=>$name!==''?$name:($model!==''?$model:'Vimar Gateway'),'vendor'=>$isVimar?'Vimar':'','model'=>$model,'serial_number'=>$hapId,'ip_address'=>$ip,'status'=>'online','gateway_id'=>$hapId!==''?$hapId:$instance,'room'=>'','capabilities'=>['gateway_discovery','read_inventory_when_authenticated'],'firmware_version'=>'','protocol'=>$isKnx?'KNXnet/IP':($isHap?'HomeKit HAP / mDNS':'mDNS'),'ecosystem'=>$ecosystem,'confidence'=>$confidence,'port'=>$port,'pairing_required'=>$isHap?((string)($t['sf']??'')==='1'):null,'metadata'=>['instance'=>$instance,'target'=>$target,'services'=>$services,'txt'=>$t],'raw'=>['instance'=>$instance,'target'=>$target,'ip'=>$ip,'port'=>$port,'services'=>$services,'txt'=>$t]];
            if($isVimar)$gateways[]=$row;else $candidates[]=$row;
        }
        return ['gateways'=>$gateways,'candidates'=>$candidates,'stats'=>['queries_sent'=>$sent,'responses'=>$responses,'records'=>count($records),'instances'=>count($instances)]];
    }

    private function discoverKnx(): array
    {
        $sock=@socket_create(AF_INET,SOCK_DGRAM,SOL_UDP);if($sock===false)throw new RuntimeException('No se pudo crear socket KNXnet/IP.');
        @socket_set_option($sock,SOL_SOCKET,SO_REUSEADDR,1);$bind=$this->localIp!==''?$this->localIp:'0.0.0.0';if(!@socket_bind($sock,$bind,0)){@socket_close($sock);throw new RuntimeException('No se pudo enlazar el socket KNXnet/IP.');}
        @socket_getsockname($sock,$boundIp,$boundPort);if($boundIp==='0.0.0.0'||$boundIp==='')$boundIp=$this->localIp!==''?$this->localIp:$this->bestLocalIp();$ipBytes=@inet_pton($boundIp);if($ipBytes===false)$ipBytes="\0\0\0\0";
        $hpai=pack('CC',8,1).$ipBytes.pack('n',(int)$boundPort);$packet=pack('CCnn',6,0x10,0x0201,14).$hpai;@socket_set_nonblock($sock);$sent=0;for($i=0;$i<3;$i++){if(@socket_sendto($sock,$packet,strlen($packet),0,self::KNX_GROUP,self::KNX_PORT)!==false)$sent++;usleep(60000);}
        $end=microtime(true)+$this->seconds;$seen=[];$responses=0;
        while(microtime(true)<$end){$buf='';$from='';$port=0;$n=@socket_recvfrom($sock,$buf,2048,0,$from,$port);if($n===false||$n<14){usleep(60000);continue;}$responses++;$u=unpack('Cheader/Cproto/nservice/nlength',$buf);if((int)($u['service']??0)!==0x0202)continue;$info=$this->parseKnxSearchResponse($buf,$from,$port);$key=$from.'|'.(string)($info['serial_number']??'');$seen[$key]=$info;}
        @socket_close($sock);$gateways=[];$candidates=[];foreach($seen as $row){$evidence=strtolower((string)($row['name']??'').' '.(string)($row['model']??'').' '.(string)($row['vendor']??''));if($this->containsVendorEvidence($evidence))$gateways[]=$row;else $candidates[]=$row;}
        return ['gateways'=>$gateways,'candidates'=>$candidates,'stats'=>['queries_sent'=>$sent,'responses'=>$responses,'gateways'=>count($seen)]];
    }

    private function discoverSsdp(): array
    {
        $sock=@socket_create(AF_INET,SOCK_DGRAM,SOL_UDP);if($sock===false)throw new RuntimeException('No se pudo crear socket SSDP.');@socket_set_option($sock,SOL_SOCKET,SO_REUSEADDR,1);if($this->localIp!==''&&!@socket_bind($sock,$this->localIp,0)){@socket_close($sock);throw new RuntimeException('No se pudo usar la interfaz SSDP.');}@socket_set_nonblock($sock);
        $msg="M-SEARCH * HTTP/1.1\r\nHOST: ".self::SSDP_GROUP.":".self::SSDP_PORT."\r\nMAN: \"ssdp:discover\"\r\nMX: 2\r\nST: ssdp:all\r\n\r\n";$sent=0;for($i=0;$i<2;$i++){if(@socket_sendto($sock,$msg,strlen($msg),0,self::SSDP_GROUP,self::SSDP_PORT)!==false)$sent++;usleep(50000);}
        $end=microtime(true)+min(5,$this->seconds);$rows=[];$responses=0;
        while(microtime(true)<$end){$buf='';$from='';$port=0;$n=@socket_recvfrom($sock,$buf,8192,0,$from,$port);if($n===false||$n<=0){usleep(60000);continue;}$responses++;$headers=$this->httpHeaders($buf);$evidence=strtolower($buf);if(!$this->containsVendorEvidence($evidence))continue;$location=(string)($headers['location']??'');$model=trim((string)($headers['server']??''));$id=trim((string)($headers['usn']??''));$rows[]=['external_id'=>'ssdp:'.($id!==''?$id:hash('sha256',$from.'|'.$location)),'normalized_type'=>'gateway','raw_type'=>'gateway','name'=>'Vimar Gateway '.$from,'vendor'=>'Vimar','model'=>$model,'serial_number'=>'','ip_address'=>$from,'status'=>'online','gateway_id'=>$id!==''?$id:$from,'room'=>'','capabilities'=>['gateway_discovery'],'firmware_version'=>'','protocol'=>'SSDP/UPnP','ecosystem'=>$this->guessEcosystem($evidence),'confidence'=>'high','port'=>$this->portFromUrl($location),'metadata'=>['location'=>$location,'headers'=>$headers],'raw'=>['ip'=>$from,'location'=>$location,'headers'=>$headers]];}
        @socket_close($sock);return ['gateways'=>$rows,'candidates'=>[],'stats'=>['queries_sent'=>$sent,'responses'=>$responses,'matched'=>count($rows)]];
    }

    private function parseKnxSearchResponse(string $buf,string $from,int $port): array
    {
        $offset=14;$name='KNXnet/IP '.$from;$serial='';$mac='';$individual='';$multicast='';
        while($offset+2<=strlen($buf)){$len=ord($buf[$offset]);$type=ord($buf[$offset+1]);if($len<2||$offset+$len>strlen($buf))break;$dib=substr($buf,$offset,$len);
            if($type===0x01&&$len>=54){$individual=sprintf('%d.%d.%d',(ord($dib[4])>>4)&0x0F,ord($dib[4])&0x0F,ord($dib[5]));$serial=strtoupper(bin2hex(substr($dib,8,6)));$multicast=@inet_ntop(substr($dib,14,4))?:'';$mac=strtoupper(implode(':',str_split(bin2hex(substr($dib,18,6)),2)));$friendly=rtrim(substr($dib,24,30),"\0 \t\r\n");if($friendly!=='')$name=$friendly;}
            $offset+=$len;
        }
        $vimar=$this->containsVendorEvidence(strtolower($name));return ['external_id'=>'knx:'.($serial!==''?$serial:$from),'normalized_type'=>'gateway','raw_type'=>'gateway','name'=>$name,'vendor'=>$vimar?'Vimar':'','model'=>'KNXnet/IP Gateway','serial_number'=>$serial,'ip_address'=>$from,'status'=>'online','gateway_id'=>$serial!==''?$serial:$from,'room'=>'','capabilities'=>['gateway_discovery','knxnet_ip'],'firmware_version'=>'','protocol'=>'KNXnet/IP','ecosystem'=>'knx','confidence'=>$vimar?'high':'protocol','port'=>$port?:self::KNX_PORT,'metadata'=>['individual_address'=>$individual,'multicast_address'=>$multicast,'mac'=>$mac],'raw'=>['ip'=>$from,'port'=>$port,'individual_address'=>$individual,'multicast_address'=>$multicast,'mac'=>$mac,'serial'=>$serial,'name'=>$name]];
    }

    private function dnsQuery(string $name,int $type): string
    {
        $header=pack('nnnnnn',0,0,1,0,0,0);$q='';foreach(explode('.',$name) as $label){if($label==='')continue;$q.=chr(strlen($label)).$label;}$q.="\0".pack('nn',$type,0x8001);return $header.$q;
    }

    private function parseDnsPacket(string $packet,string $sourceIp): array
    {
        if(strlen($packet)<12)return [];$h=unpack('nid/nflags/nqd/nan/nns/nar',$packet);$offset=12;for($i=0;$i<(int)$h['qd'];$i++){$this->dnsName($packet,$offset);$offset+=4;if($offset>strlen($packet))return [];}
        $records=[];$count=(int)$h['an']+(int)$h['ns']+(int)$h['ar'];for($i=0;$i<$count;$i++){$name=$this->dnsName($packet,$offset);if($offset+10>strlen($packet))break;$rr=unpack('ntype/nclass/Nttl/nlen',substr($packet,$offset,10));$offset+=10;$len=(int)$rr['len'];if($offset+$len>strlen($packet))break;$rdataOffset=$offset;$row=['name'=>$name,'type'=>(int)$rr['type'],'ttl'=>(int)$rr['ttl'],'source_ip'=>$sourceIp];
            if((int)$rr['type']===12){$p=$rdataOffset;$row['ptr']=$this->dnsName($packet,$p);}elseif((int)$rr['type']===33&&$len>=6){$u=unpack('npriority/nweight/nport',substr($packet,$rdataOffset,6));$p=$rdataOffset+6;$row+=['priority'=>$u['priority'],'weight'=>$u['weight'],'port'=>$u['port'],'target'=>$this->dnsName($packet,$p)];}elseif((int)$rr['type']===16){$row['txt']=$this->parseTxt(substr($packet,$rdataOffset,$len));}elseif((int)$rr['type']===1&&$len===4){$row['address']=@inet_ntop(substr($packet,$rdataOffset,4))?:'';}
            $records[]=$row;$offset+=$len;}
        return $records;
    }

    private function dnsName(string $packet,int &$offset,int $depth=0): string
    {
        if($depth>15||$offset>=strlen($packet))return '';$labels=[];$jumped=false;$cursor=$offset;$guard=0;
        while($cursor<strlen($packet)&&$guard++<128){$len=ord($packet[$cursor]);if($len===0){$cursor++;if(!$jumped)$offset=$cursor;break;}if(($len&0xC0)===0xC0){if($cursor+1>=strlen($packet))break;$ptr=(($len&0x3F)<<8)|ord($packet[$cursor+1]);if(!$jumped)$offset=$cursor+2;$jumped=true;$p=$ptr;$suffix=$this->dnsName($packet,$p,$depth+1);if($suffix!=='')$labels[]=$suffix;break;}$cursor++;if($cursor+$len>strlen($packet))break;$labels[]=substr($packet,$cursor,$len);$cursor+=$len;if(!$jumped)$offset=$cursor;}
        return implode('.',$labels);
    }

    private function parseTxt(string $data): array
    {
        $out=[];$i=0;$n=strlen($data);while($i<$n){$len=ord($data[$i++]);if($len<=0||$i+$len>$n)break;$part=substr($data,$i,$len);$i+=$len;$pos=strpos($part,'=');if($pos===false){$out[strtolower($part)]='';continue;}$out[strtolower(substr($part,0,$pos))]=substr($part,$pos+1);}return $out;
    }

    private function containsVendorEvidence(string $text): bool
    {
        return preg_match('/\b(vimar|view\s*wireless|by[-_ ]?me|elvox)\b/i',$text)===1;
    }

    private function guessEcosystem(string $text): string
    {
        if(str_contains($text,'knx'))return 'knx';if(str_contains($text,'by-me')||str_contains($text,'byme'))return 'byme';if(str_contains($text,'view'))return 'view';return 'vimar';
    }

    private function cleanDnsName(string $name): string
    {
        $name=preg_replace('/\._(?:hap|knx|vimar|view|byme|by-me)\._(?:tcp|udp)\.local$/i','',$name)??$name;return trim(str_replace(['\\032','_'],[' ',' '],$name));
    }

    private function httpHeaders(string $raw): array
    {
        $out=[];foreach(preg_split('/\r?\n/',$raw)?:[] as $line){$p=strpos($line,':');if($p===false)continue;$out[strtolower(trim(substr($line,0,$p)))]=trim(substr($line,$p+1));}return $out;
    }

    private function portFromUrl(string $url): int
    {
        $p=parse_url($url);if(!is_array($p))return 0;if(isset($p['port']))return (int)$p['port'];return (($p['scheme']??'')==='https')?443:(($p['scheme']??'')==='http'?80:0);
    }

    private function bestLocalIp(): string
    {
        foreach((array)@gethostbynamel((string)gethostname()) as $ip)if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)&&!str_starts_with($ip,'127.'))return $ip;return '0.0.0.0';
    }

    private function dedupe(array $rows): array
    {
        $out=[];foreach($rows as $row){if(!is_array($row))continue;$key=$this->gatewayKey($row);if(!isset($out[$key])){$out[$key]=$row;continue;}$old=$out[$key];if(($old['vendor']??'')===''&&($row['vendor']??'')!=='')$old['vendor']=$row['vendor'];$old['capabilities']=array_values(array_unique(array_merge((array)($old['capabilities']??[]),(array)($row['capabilities']??[]))));$old['metadata']=array_merge((array)($old['metadata']??[]),(array)($row['metadata']??[]));$out[$key]=$old;}return array_values($out);
    }

    private function gatewayKey(array $row): string
    {
        $ip=trim((string)($row['ip_address']??''));if($ip!=='')return 'ip:'.$ip;$serial=trim((string)($row['serial_number']??''));if($serial!=='')return 'serial:'.strtolower($serial);return 'id:'.strtolower((string)($row['external_id']??hash('sha256',json_encode($row))));
    }
}
