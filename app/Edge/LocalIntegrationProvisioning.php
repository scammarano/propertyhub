<?php
declare(strict_types=1);

/**
 * Contrato Node -> Edge para integraciones locales.
 *
 * El Node declara qué plataformas forman parte de la instalación. El Edge solo
 * presenta/configura drivers locales que hayan sido habilitados en el Node.
 * Las credenciales LAN continúan existiendo exclusivamente en el Edge.
 */
final class LocalIntegrationProvisioning
{
    private const ENABLED_STATUSES=['configured','online','offline','warning','error'];

    private static function canonical(string $code): string
    {
        return DesiredConnectorPolicy::canonical($code);
    }

    /** @return array<string,array<string,mixed>> */
    public static function nodeIntegrations(?PDO $pdo=null): array
    {
        if(!InstallationMode::isNode())return [];
        $pdo??=Connection::pdo();
        try{
            $repo=new TechnologyRepository($pdo);$iid=$repo->installationId();
            $q=$pdo->prepare('SELECT ci.id,ci.name,ci.status,ci.platform_product,ci.platform_version,ci.integration_mode,ci.api_family,ci.api_version,cd.code connector_code,cd.name connector_name,cd.vendor,cd.domain FROM bh_connector_instances ci JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id WHERE ci.installation_id=? ORDER BY ci.id');
            $q->execute([$iid]);$out=[];
            foreach($q->fetchAll()?:[] as $row){
                $code=self::canonical((string)($row['connector_code']??''));if($code==='')continue;
                $status=strtolower((string)($row['status']??'planned'));$enabled=in_array($status,self::ENABLED_STATUSES,true);
                $item=[
                    'enabled'=>$enabled,'connector_instance_id'=>(int)$row['id'],'code'=>$code,'name'=>(string)$row['name'],'status'=>$status,
                    'platform_product'=>(string)($row['platform_product']??''),'platform_version'=>(string)($row['platform_version']??''),'integration_mode'=>(string)($row['integration_mode']??''),
                    'api_family'=>(string)($row['api_family']??''),'api_version'=>(string)($row['api_version']??''),'vendor'=>(string)($row['vendor']??''),'domain'=>(string)($row['domain']??''),
                ];
                // Si hay más de una instancia del mismo fabricante, basta una activa para
                // provisionar el driver local; conservamos la primera activa como referencia.
                if(!isset($out[$code])||(!$out[$code]['enabled']&&$enabled))$out[$code]=$item;
            }
            return $out;
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'node_local_integrations_provisioning']);return [];}
    }

    public static function nodeEnabled(string $code,?PDO $pdo=null): bool
    {
        $all=self::nodeIntegrations($pdo);$code=self::canonical($code);return !empty($all[$code]['enabled']);
    }

    /** @return array<string,array<string,mixed>> */
    public static function edgeIntegrations(): array
    {
        if(!InstallationMode::isEdge())return [];
        try{
            $conn=(new ConnectionRegistry())->parentNode();$meta=json_decode((string)($conn['metadata_json']??''),true);
            $raw=is_array($meta)?(array)($meta['local_integrations']??[]):[];$out=[];
            foreach($raw as $code=>$item){$canon=self::canonical((string)$code);$out[$canon]=is_array($item)?$item:['enabled'=>(bool)$item];}
            return $out;
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_local_integrations_cache']);return [];}
    }

    public static function edgeEnabled(string $code): bool
    {
        $all=self::edgeIntegrations();$code=self::canonical($code);return !empty($all[$code]['enabled']);
    }


    /** @param array<int|string,mixed> $desired */
    public static function reconcileNodeDesired(array $desired,?PDO $pdo=null): array
    {
        if(!InstallationMode::isNode())return ['created'=>0,'enabled'=>0,'disabled'=>0];
        $pdo??=Connection::pdo();(new CatalogSeeder($pdo,base_path()))->seedConnectorDefinitions();
        $repo=new TechnologyRepository($pdo);$iid=$repo->installationId();$wanted=[];$desiredMeta=[];
        foreach($desired as $key=>$item){
            $code=is_array($item)?(string)($item['code']??$key):(is_string($key)?$key:(string)$item);
            $enabled=is_array($item)?!array_key_exists('enabled',$item)||!empty($item['enabled']):true;
            $code=self::canonical($code);if(!$enabled||$code==='')continue;
            $wanted[$code]=true;
            $desiredMeta[$code]=[
                'version'=>is_array($item)?trim((string)($item['version']??'')):'',
                'version_policy'=>is_array($item)?strtolower(trim((string)($item['version_policy']??'latest_compatible'))):'latest_compatible',
                'version_constraint'=>is_array($item)?trim((string)($item['version_constraint']??'')):'',
            ];
        }
        $defs=$pdo->query('SELECT id,code,name,domain,version FROM bh_connector_definitions')->fetchAll()?:[];$byCode=[];foreach($defs as $d)$byCode[self::canonical((string)$d['code'])]=$d;
        $q=$pdo->prepare('SELECT ci.*,cd.code connector_code FROM bh_connector_instances ci JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id WHERE ci.installation_id=? ORDER BY ci.id');$q->execute([$iid]);$existing=$q->fetchAll()?:[];
        $group=[];foreach($existing as $row)$group[self::canonical((string)$row['connector_code'])][]=$row;
        $created=0;$enabledCount=0;$disabled=0;
        foreach($wanted as $code=>$_){
            $def=$byCode[$code]??null;if(!is_array($def))continue;$rows=$group[$code]??[];
            if($rows===[]){
                $profile=self::defaultProvisioningProfile($code,(string)$def['name']);$policy=$desiredMeta[$code]??[];$caps=['provisioned_by_central'=>true,'desired_connector_version'=>(string)($policy['version']??''),'desired_connector_version_policy'=>(string)($policy['version_policy']??'latest_compatible'),'desired_connector_version_constraint'=>(string)($policy['version_constraint']??''),'catalog_connector_version'=>(string)$def['version'],'desired_state_updated_at'=>date(DATE_ATOM)];
                $sql="INSERT INTO bh_connector_instances (installation_id,connector_definition_id,name,target_type,manages_downstream_assets,direct_device_policy,platform_product,integration_mode,api_family,status,capabilities_json) VALUES (?,?,?,?,?,?,?,?,?,'configured',?)";
                $pdo->prepare($sql)->execute([$iid,(int)$def['id'],$profile['name'],$profile['target_type'],$profile['manages'],$profile['direct_policy'],$profile['product'],$profile['mode'],$profile['api'],json_encode($caps,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);$created++;continue;
            }
            foreach($rows as $row){
                if(!in_array((string)$row['status'],self::ENABLED_STATUSES,true)){$pdo->prepare("UPDATE bh_connector_instances SET status='configured' WHERE id=?")->execute([(int)$row['id']]);$enabledCount++;}
                $caps=json_decode((string)($row['capabilities_json']??''),true);if(!is_array($caps))$caps=[];$policy=$desiredMeta[$code]??[];$caps['provisioned_by_central']=true;$caps['desired_connector_version']=(string)($policy['version']??'');$caps['desired_connector_version_policy']=(string)($policy['version_policy']??'latest_compatible');$caps['desired_connector_version_constraint']=(string)($policy['version_constraint']??'');$caps['catalog_connector_version']=(string)$def['version'];$caps['desired_state_updated_at']=date(DATE_ATOM);$pdo->prepare('UPDATE bh_connector_instances SET capabilities_json=? WHERE id=?')->execute([json_encode($caps,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),(int)$row['id']]);
            }
        }
        foreach($existing as $row){
            $code=self::canonical((string)$row['connector_code']);if(isset($wanted[$code]))continue;$caps=json_decode((string)($row['capabilities_json']??''),true);if(!is_array($caps)||empty($caps['provisioned_by_central']))continue;
            if((string)$row['status']!=='disabled'){$pdo->prepare("UPDATE bh_connector_instances SET status='disabled' WHERE id=?")->execute([(int)$row['id']]);$disabled++;}
        }
        return ['created'=>$created,'enabled'=>$enabledCount,'disabled'=>$disabled,'desired'=>array_keys($wanted)];
    }

    /** @return array{name:string,target_type:string,manages:int,direct_policy:string,product:string,mode:string,api:string} */
    private static function defaultProvisioningProfile(string $code,string $name): array
    {
        return match($code){
            'vimar'=>['name'=>'Vimar Universal · provisionado por Central','target_type'=>'technology_gateway','manages'=>1,'direct_policy'=>'optional','product'=>'Vimar Universal Connector','mode'=>'Discovery local directo vía Edge','api'=>'VIEW / By-me / KNX / Elvox IP'],
            'zkteco'=>['name'=>'ZKBioSecurity · provisionado por Central','target_type'=>'platform_server','manages'=>1,'direct_policy'=>'forbidden','product'=>'ZKBioSecurity','mode'=>'API local vía Edge','api'=>'ZKBioSecurity API'],
            'onvif'=>['name'=>'Video ONVIF / RTSP · provisionado por Central','target_type'=>'protocol_service','manages'=>1,'direct_policy'=>'optional','product'=>'ONVIF / RTSP','mode'=>'Discovery local vía Edge','api'=>'ONVIF / RTSP'],
            default=>['name'=>$name.' · provisionado por Central','target_type'=>'technology_gateway','manages'=>1,'direct_policy'=>'optional','product'=>$name,'mode'=>'Provisionado por Central','api'=>$name],
        };
    }

    public static function assertEdgeEnabled(string $code): void
    {
        if(!InstallationMode::isEdge())throw new RuntimeException('Esta operación corresponde a Property Edge Agent.');
        if(self::edgeEnabled($code))return;
        throw new RuntimeException('Esta integración no está habilitada por el Property Node. Actívela primero en Infraestructura y tecnología → Sistemas conectados y refresque el Agent.');
    }
}
