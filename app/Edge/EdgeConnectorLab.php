<?php
declare(strict_types=1);

final class EdgeConnectorLab
{
    public function __construct(private PDO $pdo) {}

    private function assertEdge(): void
    {
        if(!InstallationMode::isEdge()) throw new RuntimeException('El laboratorio de integraciones locales solo está disponible en Property Edge Agent.');
    }

    private function installationId(): int
    {
        // El UUID del Agent identifica al proceso Edge frente al Node, pero bases Edge
        // provenientes de instalaciones antiguas pueden conservar una única fila local
        // bh_installations con el UUID histórico de la propiedad. No debemos perder
        // acceso a cámaras/conectores locales por esa diferencia de identidad.
        $uuid=trim((string)config('deployment.installation_uuid'));
        if($uuid!==''){
            $stmt=$this->pdo->prepare('SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1');
            $stmt->execute([$uuid]);
            $id=(int)$stmt->fetchColumn();
            if($id>0)return $id;
        }

        // Compatibilidad Edge: si existe una sola instalación local, es inequívoca.
        // No alteramos public_uuid ni reescribimos FKs: simplemente resolvemos su PK.
        $rows=$this->pdo->query('SELECT id,public_uuid,code,name FROM bh_installations ORDER BY id')->fetchAll()?:[];
        if(count($rows)===1){
            $id=(int)($rows[0]['id']??0);
            if($id>0){
                ErrorReporter::report(new RuntimeException('Edge UUID local no coincide con bh_installations; se utilizó la única instalación disponible.'),[
                    'operation'=>'edge_local_installation_fallback',
                    'agent_uuid'=>$uuid,
                    'database_installation_uuid'=>(string)($rows[0]['public_uuid']??''),
                    'database_installation_code'=>(string)($rows[0]['code']??''),
                ]);
                return $id;
            }
        }

        // Si hay más de una fila, solo aceptamos una candidata que tenga inventario
        // Edge real. Evita escoger silenciosamente una instalación incorrecta.
        $q=$this->pdo->query("SELECT i.id FROM bh_installations i WHERE EXISTS(SELECT 1 FROM bh_edge_cameras c WHERE c.installation_id=i.id) OR EXISTS(SELECT 1 FROM bh_connector_instances ci WHERE ci.installation_id=i.id) ORDER BY i.id");
        $ids=array_values(array_unique(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)?:[])));
        if(count($ids)===1&&$ids[0]>0)return $ids[0];

        throw new RuntimeException('No se encontró una instalación Edge local inequívoca. Revise INSTANCE_UUID y bh_installations.');
    }

    private function definitionId(string $code): int
    {
        $stmt=$this->pdo->prepare('SELECT id FROM bh_connector_definitions WHERE code=? LIMIT 1');
        $stmt->execute([$code]);
        $id=(int)$stmt->fetchColumn();
        if($id<=0) throw new RuntimeException('Conector no disponible en el catálogo: '.$code);
        return $id;
    }

    public function zkBioConfig(): array
    {
        $this->assertEdge();
        $id=$this->definitionId('zkteco');
        $stmt=$this->pdo->prepare('SELECT * FROM bh_connector_instances WHERE installation_id=? AND connector_definition_id=? ORDER BY id LIMIT 1');
        $stmt->execute([$this->installationId(),$id]);
        $row=$stmt->fetch();
        if(!is_array($row)) return ['endpoint_url'=>'http://127.0.0.1:8098','configured'=>false,'status'=>'disabled'];
        $secret=EdgeSecretStore::decrypt((string)($row['configuration_encrypted']??''));
        return $row+['configured'=>true,'token_masked'=>!empty($secret['access_token'])?'••••••••'.substr((string)$secret['access_token'],-4):''];
    }

    public function saveZkBio(string $endpoint,string $token): void
    {
        $this->assertEdge();LocalIntegrationProvisioning::assertEdgeEnabled('zkteco');
        $endpoint=rtrim(trim($endpoint),'/');
        if(!preg_match('#^https?://#i',$endpoint)) throw new InvalidArgumentException('Indique una URL HTTP/HTTPS válida para ZKBio CVSecurity.');
        $def=$this->definitionId('zkteco');$iid=$this->installationId();
        $stmt=$this->pdo->prepare('SELECT id,configuration_encrypted FROM bh_connector_instances WHERE installation_id=? AND connector_definition_id=? ORDER BY id LIMIT 1');
        $stmt->execute([$iid,$def]);$existing=$stmt->fetch();$id=is_array($existing)?(int)$existing['id']:0;
        $token=trim($token);
        if($token===''&&is_array($existing)){$oldSecret=EdgeSecretStore::decrypt((string)($existing['configuration_encrypted']??''));$token=trim((string)($oldSecret['access_token']??''));}
        if($token==='')throw new InvalidArgumentException('Indique el access_token de ZKBio.');
        $enc=EdgeSecretStore::encrypt(['access_token'=>$token]);
        if($id>0){
            $this->pdo->prepare("UPDATE bh_connector_instances SET name='ZKBio CVSecurity',target_type='platform_server',platform_product='ZKBio CVSecurity',endpoint_url=?,configuration_encrypted=?,authentication_mode='access_token',api_family='ZKBio CVSecurity 3rd Party API',integration_mode='local_rest',status='configured' WHERE id=?")->execute([$endpoint,$enc,$id]);
        }else{
            $this->pdo->prepare("INSERT INTO bh_connector_instances (installation_id,connector_definition_id,name,target_type,manages_downstream_assets,direct_device_policy,platform_product,integration_mode,api_family,authentication_mode,endpoint_url,configuration_encrypted,status) VALUES (?,?,'ZKBio CVSecurity','platform_server',1,'forbidden','ZKBio CVSecurity','local_rest','ZKBio CVSecurity 3rd Party API','access_token',?,?,'configured')")->execute([$iid,$def,$endpoint,$enc]);
        }
    }

    public function testZkBio(): array
    {
        $this->assertEdge();LocalIntegrationProvisioning::assertEdgeEnabled('zkteco');$cfg=$this->zkBioConfig();
        if(empty($cfg['configured']))throw new RuntimeException('Configure primero ZKBio CVSecurity.');
        $secret=EdgeSecretStore::decrypt((string)($cfg['configuration_encrypted']??''));$token=(string)($secret['access_token']??'');
        if($token==='')throw new RuntimeException('El token ZKBio no está disponible.');
        $base=rtrim((string)$cfg['endpoint_url'],'/');$started=microtime(true);
        $devices=$this->zkGet($base.'/api/device/accList?pageNo=1&pageSize=20&access_token='.rawurlencode($token));
        $doors=$this->zkGet($base.'/api/v2/door/list?pageNo=1&pageSize=100&access_token='.rawurlencode($token));
        $deviceRows=$this->rowsFromZk($devices);$doorRows=$this->rowsFromZk($doors);$lat=(int)round((microtime(true)-$started)*1000);
        $this->pdo->prepare("UPDATE bh_connector_instances SET status='online',device_count=?,last_health_at=NOW(),last_error=NULL,capabilities_json=? WHERE id=?")
            ->execute([count($deviceRows),json_encode(['devices'=>count($deviceRows),'doors'=>count($doorRows),'last_test_ms'=>$lat],JSON_UNESCAPED_SLASHES),(int)$cfg['id']]);
        return ['ok'=>true,'latency_ms'=>$lat,'devices'=>count($deviceRows),'doors'=>count($doorRows),'device_sample'=>array_slice($deviceRows,0,10),'door_sample'=>array_slice($doorRows,0,20),'message'=>'ZKBio CVSecurity respondió correctamente.'];
    }


    public function discoverZkBio(): array
    {
        $this->assertEdge();LocalIntegrationProvisioning::assertEdgeEnabled('zkteco');$cfg=$this->zkBioConfig();
        if(empty($cfg['configured']))throw new RuntimeException('Configure primero ZKBio CVSecurity.');
        $secret=EdgeSecretStore::decrypt((string)($cfg['configuration_encrypted']??''));$token=trim((string)($secret['access_token']??''));if($token==='')throw new RuntimeException('El token ZKBio no está disponible.');
        $base=rtrim((string)$cfg['endpoint_url'],'/');$cid=(int)$cfg['id'];$started=microtime(true);$warnings=[];$sets=[];$pageSize=500;
        $calls=[
            'device'=>fn()=>$this->zkPagedGet($base.'/api/device/accList','access_token='.$this->zkToken($token),$pageSize),
            'door'=>fn()=>$this->zkPagedGet($base.'/api/v2/door/list','access_token='.$this->zkToken($token),$pageSize),
            'reader'=>fn()=>$this->zkPagedGet($base.'/api/v2/reader/list','access_token='.$this->zkToken($token),$pageSize),
            'department'=>fn()=>$this->zkPagedGet($base.'/api/department/getDepartmentList','access_token='.$this->zkToken($token),$pageSize,true),
            'access_level'=>fn()=>$this->zkPagedGet($base.'/api/v2/accLevel/list','access_token='.$this->zkToken($token),$pageSize),
            'person'=>fn()=>$this->zkPagedPersons($base,$token,$pageSize),
        ];
        foreach($calls as $type=>$call){try{$sets[$type]=$call();}catch(Throwable $e){$sets[$type]=[];$warnings[]=$type.': '.$e->getMessage();}}

        // La lista v2 de personas ya devuelve tarjeta principal, tarjetas secundarias y placas.
        // Las derivamos localmente para evitar una llamada HTTP por persona en instalaciones grandes.
        $cards=[];$vehicles=[];
        foreach($sets['person']??[] as $person){
            $pin=trim((string)($person['pin']??''));
            $primary=trim((string)($person['cardNo']??''));
            if($primary!=='')$cards[]=['id'=>$pin.':'.$primary,'pin'=>$pin,'cardNo'=>$primary,'cardType'=>'0','name'=>(string)($person['name']??'')];
            foreach(array_filter(array_map('trim',explode(',',(string)($person['supplyCards']??'')))) as $card){$cards[]=['id'=>$pin.':'.$card,'pin'=>$pin,'cardNo'=>$card,'cardType'=>'1','name'=>(string)($person['name']??'')];}
            foreach(array_filter(array_map('trim',explode(',',(string)($person['carPlate']??'')))) as $plate){$vehicles[]=['id'=>$pin.':'.$plate,'pin'=>$pin,'carPlate'=>$plate,'name'=>(string)($person['name']??'')];}
        }
        $sets['card']=$cards;$sets['vehicle']=$vehicles;

        $stored=[];foreach($sets as $type=>$rows)$stored[$type]=$this->persistExternalObjects($cid,$type,$rows);
        $lat=(int)round((microtime(true)-$started)*1000);$caps=['discovery'=>true,'objects'=>$stored,'warnings'=>count($warnings),'last_discovery_ms'=>$lat,'authority'=>'property_hub','read_only_adoption'=>true,'schedule_strategy'=>'deduplicate_by_hash'];
        $this->pdo->prepare("UPDATE bh_connector_instances SET status='online',device_count=?,last_health_at=NOW(),last_error=NULL,capabilities_json=? WHERE id=?")->execute([(int)($stored['device']??0),json_encode($caps,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$cid]);
        return ['ok'=>true,'latency_ms'=>$lat,'counts'=>$stored,'warnings'=>array_slice($warnings,0,30),'samples'=>array_map(static fn(array $r):array=>array_slice($r,0,12),$sets),'message'=>'Aprendizaje ZKBio completado en modo solo lectura. Property Hub inventarió estructura, personas, credenciales, placas y niveles sin modificar ZKBio.'];
    }

    private function zkToken(string $token): string{return rawurlencode($token);}

    private function zkPagedGet(string $url,string $extraQuery,int $pageSize=500,bool $post=false): array
    {
        $all=[];$page=1;$guard=0;
        do{
            $sep=str_contains($url,'?')?'&':'?';$full=$url.$sep.'pageNo='.$page.'&pageSize='.$pageSize.'&'.$extraQuery;
            $response=$post?$this->zkPost($full,null):$this->zkGet($full);$rows=$this->rowsFromZk($response);foreach($rows as $r)$all[]=$r;
            $data=$response['data']??[];$last=is_array($data)&&array_key_exists('lastPage',$data)?(bool)$data['lastPage']:count($rows)<$pageSize;
            $page++;$guard++;
        }while(!$last&&count($rows)>0&&$guard<100);
        return $all;
    }

    private function zkPagedPersons(string $base,string $token,int $pageSize=500): array
    {
        // CVSecurity 6.7.1 real: V1 funciona con POST sin body y parámetros en query.
        // Primero intentamos listado global. Si una edición exige deptCode, recorremos
        // departamentos y deduplicamos por id/PIN.
        $readPages=function(string $deptCode='') use($base,$token,$pageSize): array {
            $all=[];$page=1;$guard=0;
            do{
                $query=['pins'=>'','deptCodes'=>$deptCode,'pageNo'=>$page,'pageSize'=>$pageSize,'access_token'=>$token];
                $response=$this->zkPost($base.'/api/person/getPersonList?'.http_build_query($query,'','&',PHP_QUERY_RFC3986),null);
                $rows=$this->rowsFromZk($response);foreach($rows as $r)$all[]=$r;
                $data=$response['data']??[];$last=is_array($data)&&array_key_exists('lastPage',$data)?(bool)$data['lastPage']:count($rows)<$pageSize;
                $page++;$guard++;
            }while(!$last&&count($rows)>0&&$guard<200);
            return $all;
        };
        try{return $readPages('');}catch(Throwable $globalError){
            $departments=$this->zkPagedGet($base.'/api/department/getDepartmentList','access_token='.$this->zkToken($token),200,true);$byId=[];$errors=[];
            foreach($departments as $d){$code=trim((string)($d['code']??''));if($code==='')continue;try{foreach($readPages($code) as $r){$key=trim((string)($r['id']??$r['pin']??''));if($key!=='')$byId[$key]=$r;}}catch(Throwable $e){$errors[]=$code.': '.$e->getMessage();}}
            if($byId===[])throw new RuntimeException('No fue posible listar personas por API V1. Global: '.$globalError->getMessage().($errors!==[]?' · Departamentos: '.implode(' | ',array_slice($errors,0,5)):''));
            return array_values($byId);
        }
    }

    public function zkBioDiscoverySummary(): array
    {
        $this->assertEdge();$cfg=$this->zkBioConfig();if(empty($cfg['id']))return ['counts'=>[],'items'=>[]];
        $q=$this->pdo->prepare('SELECT object_type,COUNT(*) total FROM bh_external_objects WHERE connector_instance_id=? GROUP BY object_type ORDER BY object_type');$q->execute([(int)$cfg['id']]);$counts=[];foreach($q->fetchAll()?:[] as $r)$counts[(string)$r['object_type']]=(int)$r['total'];
        $q=$this->pdo->prepare('SELECT object_type,external_code,name,status,adoption_status,last_seen_at FROM bh_external_objects WHERE connector_instance_id=? ORDER BY last_seen_at DESC,id DESC LIMIT 80');$q->execute([(int)$cfg['id']]);return ['counts'=>$counts,'items'=>$q->fetchAll()?:[]];
    }

    public function zkBioExplorerCatalog(): array
    {
        $this->assertEdge();
        return [
            'devices'=>['label'=>'Dispositivos / controladoras','method'=>'GET','path'=>'/api/device/accList','description'=>'Lista de dispositivos de control de acceso.','params'=>['pageNo'=>1,'pageSize'=>20]],
            'doors'=>['label'=>'Puertas','method'=>'GET','path'=>'/api/v2/door/list','description'=>'Lista v2 de puertas registradas en ZKBio.','params'=>['pageNo'=>1,'pageSize'=>100]],
            'readers'=>['label'=>'Lectores','method'=>'GET','path'=>'/api/v2/reader/list','description'=>'Lista v2 de lectores.','params'=>['pageNo'=>1,'pageSize'=>100]],
            'departments'=>['label'=>'Departamentos','method'=>'POST','path'=>'/api/department/getDepartmentList','description'=>'Árbol/estructura de departamentos.','params'=>['pageNo'=>1,'pageSize'=>100]],
            'access_levels'=>['label'=>'Niveles de acceso','method'=>'GET','path'=>'/api/v2/accLevel/list','description'=>'Lista v2 de Access Levels.','params'=>['pageNo'=>1,'pageSize'=>100]],
            'persons'=>['label'=>'Personas','method'=>'POST','path'=>'/api/person/getPersonList','description'=>'Lista de personas. Compatibilidad CVSecurity 6.7.1: POST con parámetros en query y body vacío.','params'=>['pageNo'=>1,'pageSize'=>20,'pins'=>'','deptCodes'=>'']],
            'transactions'=>['label'=>'Transacciones de acceso','method'=>'GET','path'=>'/api/v2/transaction/list','description'=>'Últimas transacciones de acceso con total.','params'=>['pageNo'=>1,'pageSize'=>20,'personPin'=>'','startDate'=>'','endDate'=>'']],
        ];
    }

    public function runZkBioPreset(string $code,array $params=[],string $origin='edge_local',?int $createdBy=null): array
    {
        $this->assertEdge();LocalIntegrationProvisioning::assertEdgeEnabled('zkteco');$catalog=$this->zkBioExplorerCatalog();
        if(!isset($catalog[$code]))throw new InvalidArgumentException('Prueba ZKBio desconocida: '.$code);
        $spec=$catalog[$code];$defaults=(array)$spec['params'];$clean=[];
        foreach($defaults as $key=>$default){$value=$params[$key]??$default;if(in_array($key,['pageNo','pageSize'],true)){$value=max(1,(int)$value);if($key==='pageSize')$value=min(1000,$value);}else{$value=trim((string)$value);}$clean[$key]=$value;}
        if($code==='persons'){
            $query=['pins'=>(string)$clean['pins'],'deptCodes'=>(string)$clean['deptCodes'],'pageNo'=>(int)$clean['pageNo'],'pageSize'=>(int)$clean['pageSize']];$payload=null;
        }else{
            $query=array_filter($clean,static fn($v)=>$v!==''&&$v!==null);$payload=null;
        }
        return $this->executeZkBioRequest($code,(string)$spec['method'],(string)$spec['path'],$query,$payload,$origin,$createdBy);
    }

    public function runZkBioCustom(string $method,string $path,string $queryString='',?array $payload=null,string $origin='edge_local',?int $createdBy=null): array
    {
        $this->assertEdge();LocalIntegrationProvisioning::assertEdgeEnabled('zkteco');$method=strtoupper(trim($method));if(!in_array($method,['GET','POST','DELETE'],true))throw new InvalidArgumentException('El laboratorio admite GET, POST o DELETE.');
        $path=trim($path);if(!str_starts_with($path,'/api/'))throw new InvalidArgumentException('La ruta manual debe comenzar por /api/.');
        if(str_contains($path,'..')||str_contains($path,'://')||str_contains($path,'?')||str_contains($path,'#'))throw new InvalidArgumentException('Ruta API no válida. Use el campo Query para los parámetros.');
        parse_str(ltrim(trim($queryString),'?'),$query);unset($query['access_token'],$query['accessToken'],$query['token']);
        return $this->executeZkBioRequest('custom',$method,$path,$query,$payload,$origin,$createdBy);
    }

    public function remoteOpenZkBio(string $doorId,int $interval,?int $createdBy=null): array
    {
        $this->assertEdge();LocalIntegrationProvisioning::assertEdgeEnabled('zkteco');$doorId=trim($doorId);if($doorId==='')throw new InvalidArgumentException('Indique el doorId exacto obtenido de ZKBio.');$interval=max(1,min(30,$interval));
        return $this->executeZkBioRequest('remote_open','POST','/api/door/remoteOpenById',['doorId'=>$doorId,'interval'=>$interval],null,'edge_local',$createdBy);
    }

    /** Inventario compacto que puede viajar Edge -> Node sin secretos ni blobs. */
    public function zkBioInventoryPayload(): array
    {
        $this->assertEdge();LocalIntegrationProvisioning::assertEdgeEnabled('zkteco');$cfg=$this->zkBioConfig();if(empty($cfg['configured']))throw new RuntimeException('Configure primero ZKBio CVSecurity.');
        $secret=EdgeSecretStore::decrypt((string)($cfg['configuration_encrypted']??''));$token=trim((string)($secret['access_token']??''));if($token==='')throw new RuntimeException('El access_token ZKBio no está disponible.');$base=rtrim((string)$cfg['endpoint_url'],'/');
        $sets=[];$sets['device']=$this->zkPagedGet($base.'/api/device/accList','access_token='.$this->zkToken($token),200);$sets['door']=$this->zkPagedGet($base.'/api/v2/door/list','access_token='.$this->zkToken($token),200);$sets['reader']=$this->zkPagedGet($base.'/api/v2/reader/list','access_token='.$this->zkToken($token),300);$sets['department']=$this->zkPagedGet($base.'/api/department/getDepartmentList','access_token='.$this->zkToken($token),300,true);$sets['access_level']=$this->zkPagedGet($base.'/api/v2/accLevel/list','access_token='.$this->zkToken($token),300);$people=$this->zkPagedPersons($base,$token,500);
        $sets['person']=array_map(function(array $r):array{return (array)$this->sanitizeZkBioPayload(['id'=>$r['id']??null,'pin'=>$r['pin']??null,'deptCode'=>$r['deptCode']??null,'deptName'=>$r['deptName']??null,'name'=>$r['name']??null,'lastName'=>$r['lastName']??null,'gender'=>$r['gender']??null,'cardNo'=>$r['cardNo']??null,'supplyCards'=>$r['supplyCards']??null,'carPlate'=>$r['carPlate']??null,'email'=>$r['email']??null,'mobilePhone'=>$r['mobilePhone']??null,'accLevelIds'=>$r['accLevelIds']??null,'accStartTime'=>$r['accStartTime']??null,'accEndTime'=>$r['accEndTime']??null,'isDisabled'=>$r['isDisabled']??false]);},$people);
        return ['ok'=>true,'inventory'=>$sets,'counts'=>array_map('count',$sets),'generated_at'=>date(DATE_ATOM)];
    }

    public function createZkBioPerson(array $person): array
    {
        $pin=trim((string)($person['pin']??''));$dept=trim((string)($person['deptCode']??''));if($pin===''||$dept==='')throw new InvalidArgumentException('Persona ZKBio: pin y deptCode son obligatorios.');
        return $this->executeZkBioRequest('create_person','POST','/api/person/add',[],$person,'node_remote',null);
    }

    public function createZkBioVisitor(array $visitor): array
    {
        foreach(['persPersonPin','certType','certNum','visEmpName','startTime','endTime'] as $k)if(trim((string)($visitor[$k]??''))==='')throw new InvalidArgumentException('Visitante ZKBio: falta '.$k.'.');
        return $this->executeZkBioRequest('create_visitor','POST','/api/visRegistration/add',[],$visitor,'node_remote',null);
    }

    public function createZkBioVehicle(array $vehicle): array
    {
        $mode=trim((string)($vehicle['mode']??'rfid_person'))?:'rfid_person';
        if($mode==='parking_plate'){$pin=trim((string)($vehicle['pin']??''));$plate=trim((string)($vehicle['carNumber']??''));if($pin===''||$plate==='')throw new InvalidArgumentException('Vehículo ZKBio Parking: pin y carNumber son obligatorios.');return $this->executeZkBioRequest('create_vehicle_parking','POST','/api/v1/parkPlate/addPlate',[],$vehicle,'node_remote',null);}
        $pin=trim((string)($vehicle['pin']??''));$dept=trim((string)($vehicle['deptCode']??''));$card=trim((string)($vehicle['cardNo']??''));if($pin===''||$dept===''||$card==='')throw new InvalidArgumentException('Vehículo ZKBio RFID: pin, deptCode y cardNo son obligatorios.');
        $person=['pin'=>$pin,'deptCode'=>$dept,'name'=>(string)($vehicle['name']??'VEHICULO'),'lastName'=>(string)($vehicle['lastName']??''),'gender'=>'','cardNo'=>$card,'personPhoto'=>'','accLevelIds'=>(string)($vehicle['accLevelIds']??''),'accStartTime'=>'','accEndTime'=>''];
        $created=$this->executeZkBioRequest('create_vehicle_rfid_person','POST','/api/person/add',[],$person,'node_remote',null);
        // La placa se conserva en Property. El autenticador real de Premier es cardNo/RFID.
        return $created+['vehicle_mode'=>'rfid_person','vehicle_pin'=>$pin,'rfid_card_no'=>$card,'plate'=>(string)($vehicle['plate']??'')];
    }

    public function readZkBioBiometrics(string $pin): array
    {
        $pin=trim($pin);if($pin==='')throw new InvalidArgumentException('Indique el PIN ZKBio para leer biometría.');$v1=$this->runZkBioDiagnosticRequest('GET','/api/bioTemplate/getFgListByPin/'.rawurlencode($pin),[],null,'edge_local',null,'bio_read_v1');if(!empty($v1['ok']))return $v1+['variant'=>'v1-path'];$v2=$this->runZkBioDiagnosticRequest('GET','/api/v2/bioTemplate/getFgListByPin',['pin'=>$pin],null,'edge_local',null,'bio_read_v2');return $v2+['variant'=>'v2-query','fallback_from'=>$v1];
    }

    public function uploadZkBioBiometric(array $bio): array
    {
        foreach(['personId','template','templateNo'] as $k)if(trim((string)($bio[$k]??''))==='')throw new InvalidArgumentException('BioTemplate ZKBio: falta '.$k.'.');$bio['validType']=trim((string)($bio['validType']??'1'))?:'1';return $this->executeZkBioRequest('bio_upload','POST','/api/bioTemplate/add',[],$bio,'edge_local',null);
    }

    public function executeOperationalCommand(string $type,array $payload): array
    {
        return match($type){
            'zkbio.inventory'=>$this->zkBioInventoryPayload(),
            'zkbio.remote_open'=>$this->remoteOpenZkBio((string)($payload['door_id']??''),(int)($payload['interval']??3),null),
            'zkbio.create_person'=>$this->createZkBioPerson((array)($payload['person']??[])),
            'zkbio.create_visitor'=>$this->createZkBioVisitor((array)($payload['visitor']??[])),
            'zkbio.create_vehicle'=>$this->createZkBioVehicle((array)($payload['vehicle']??[])),
            'zkbio.bio_read'=>$this->readZkBioBiometrics((string)($payload['pin']??'')),
            'zkbio.bio_upload'=>$this->uploadZkBioBiometric((array)($payload['bio']??[])),
            default=>throw new InvalidArgumentException('Comando ZKBio operativo desconocido: '.$type),
        };
    }

    public function zkBioTestHistory(int $limit=40): array
    {
        $this->assertEdge();$cfg=$this->zkBioConfig();$limit=max(1,min(200,$limit));if(empty($cfg['id']))return [];
        $q=$this->pdo->prepare("SELECT * FROM bh_connector_api_tests WHERE installation_id=? AND connector_instance_id=? ORDER BY id DESC LIMIT {$limit}");$q->execute([$this->installationId(),(int)$cfg['id']]);return $q->fetchAll()?:[];
    }

    public function zkBioTelemetry(): array
    {
        $this->assertEdge();$cfg=$this->zkBioConfig();$caps=json_decode((string)($cfg['capabilities_json']??''),true);return [
            'configured'=>!empty($cfg['configured']),'status'=>(string)($cfg['status']??'disabled'),'platform'=>'ZKBio CVSecurity','last_health_at'=>$cfg['last_health_at']??null,
            'last_error'=>$cfg['last_error']??null,'device_count'=>(int)($cfg['device_count']??0),'capabilities'=>is_array($caps)?$caps:[],'token_present'=>trim((string)($cfg['token_masked']??''))!=='',
        ];
    }

    private function executeZkBioRequest(string $operation,string $method,string $path,array $query,?array $payload,string $origin,?int $createdBy,bool $throwOnVendorError=true): array
    {
        $cfg=$this->zkBioConfig();if(empty($cfg['configured']))throw new RuntimeException('Configure primero ZKBio CVSecurity.');
        $secret=EdgeSecretStore::decrypt((string)($cfg['configuration_encrypted']??''));$token=trim((string)($secret['access_token']??''));if($token==='')throw new RuntimeException('El access_token ZKBio no está disponible.');
        $method=strtoupper($method);$query['access_token']=$token;$base=rtrim((string)$cfg['endpoint_url'],'/');$url=$base.$path.($query!==[]?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):'');$started=microtime(true);$result=null;$error='';$status='success';$httpStatus=null;$vendor=['ok'=>true,'code'=>null,'message'=>''];
        try{$raw=$this->zkRequestRaw($method,$url,$payload);$result=$raw['data'];$httpStatus=(int)$raw['http_status'];$vendor=$this->zkVendorStatus((array)$result);if(!$vendor['ok']){$status='error';$error=(string)$vendor['message'];}}
        catch(Throwable $e){$status='error';$error=$e->getMessage();}
        $lat=(int)round((microtime(true)-$started)*1000);$safeRequest=['query'=>$query,'body'=>$payload];unset($safeRequest['query']['access_token']);$safeRequest=$this->sanitizeZkBioPayload($safeRequest);$safeResponse=$this->sanitizeZkBioPayload($result);$vendorCode=$vendor['code']??null;
        try{$this->pdo->prepare("INSERT INTO bh_connector_api_tests (installation_id,connector_instance_id,origin,operation_code,http_method,request_path,request_json,response_json,http_status,vendor_code,status,latency_ms,error_message,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$this->installationId(),(int)$cfg['id'],$origin,$operation,$method,$path,json_encode($safeRequest,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$safeResponse===null?null:json_encode($safeResponse,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$httpStatus,$vendorCode,$status,$lat,$error!==''?$error:null,$createdBy]);}catch(Throwable $logError){ErrorReporter::report($logError,['operation'=>'zkbio_lab_history']);}
        ErrorReporter::runtimeLog('zkbio_api_lab',['operation_code'=>$operation,'method'=>$method,'path'=>$path,'origin'=>$origin,'result'=>$status,'latency_ms'=>$lat,'http_status'=>$httpStatus,'vendor_code'=>$vendorCode,'error'=>$error]);
        if($status==='error'&&$throwOnVendorError)throw new RuntimeException($error!==''?$error:'ZKBio rechazó la operación.');
        $rows=is_array($result)?$this->rowsFromZk($result):[];$data=is_array($result)?($result['data']??[]):[];$total=is_array($data)&&isset($data['total'])?(int)$data['total']:count($rows);
        return ['ok'=>$status==='success','operation'=>$operation,'method'=>$method,'path'=>$path,'request'=>$safeRequest,'response'=>$safeResponse,'latency_ms'=>$lat,'http_status'=>$httpStatus,'vendor_code'=>$vendorCode,'row_count'=>count($rows),'total'=>$total,'error'=>$error!==''?$error:null,'executed_at'=>date(DATE_ATOM)];
    }

    public function runZkBioDiagnosticRequest(string $method,string $path,array $query=[],?array $payload=null,string $origin='matrix_runner',?int $createdBy=null,string $operation='matrix'): array
    {
        $this->assertEdge();LocalIntegrationProvisioning::assertEdgeEnabled('zkteco');$method=strtoupper(trim($method));if(!in_array($method,['GET','POST','DELETE'],true))throw new InvalidArgumentException('Método ZKBio no permitido.');
        if(!str_starts_with($path,'/api/'))throw new InvalidArgumentException('La ruta debe comenzar por /api/.');unset($query['access_token'],$query['accessToken'],$query['token'],$query['acc_token']);
        return $this->executeZkBioRequest($operation,$method,$path,$query,$payload,$origin,$createdBy,false);
    }

    private function sanitizeZkBioPayload(mixed $value): mixed
    {
        if(is_array($value)){$out=[];foreach($value as $k=>$v){$key=(string)$k;$lower=strtolower($key);$compact=preg_replace('/[^a-z0-9]/','',$lower)??$lower;
            $secretKey=str_contains($compact,'token')||str_contains($compact,'secret')||str_contains($compact,'password')||str_ends_with($compact,'pwd')||in_array($compact,['selfpwd','personpwd','passwd','pass'],true);
            if($secretKey){$out[$k]='[redacted]';continue;}
            if((str_contains($lower,'base64')||str_contains($lower,'photo')||str_contains($lower,'image'))&&is_string($v)&&strlen($v)>120){$out[$k]='[binary/base64 omitted: '.strlen($v).' bytes]';continue;}
            $out[$k]=$this->sanitizeZkBioPayload($v);
        }return $out;}
        if(is_string($value)&&strlen($value)>200000)return substr($value,0,200000).'…[truncated]';return $value;
    }

    private function persistExternalObjects(int $connectorId,string $type,array $rows): int
    {
        $iid=$this->installationId();$count=0;$sql="INSERT INTO bh_external_objects (installation_id,connector_instance_id,object_type,external_id,external_code,name,status,payload_json,discovered_at,last_seen_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE external_code=VALUES(external_code),name=VALUES(name),status=VALUES(status),payload_json=VALUES(payload_json),last_seen_at=NOW()";$ins=$this->pdo->prepare($sql);
        foreach($rows as $row){if(!is_array($row))continue;$externalId=trim((string)($row['id']??$row['pin']??$row['code']??$row['cardNo']??$row['carPlate']??$row['sn']??''));if($externalId==='')$externalId=hash('sha256',json_encode($row));$externalCode=trim((string)($row['pin']??$row['code']??$row['cardNo']??$row['carPlate']??$row['sn']??$row['readerNo']??''));$name=trim((string)($row['name']??$row['deptName']??$row['doorName']??$row['readerName']??''));$status=(string)($row['status']??$row['state']??'');$ins->execute([$iid,$connectorId,$type,$externalId,$externalCode?:null,$name?:null,$status?:null,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);$count++;}return $count;
    }

    private function zkPost(string $url,?array $payload): array
    {
        $raw=$this->zkRequestRaw('POST',$url,$payload);$data=(array)$raw['data'];$vendor=$this->zkVendorStatus($data);if(!$vendor['ok'])throw new RuntimeException((string)$vendor['message']);return $data;
    }

    private function zkGet(string $url): array
    {
        $raw=$this->zkRequestRaw('GET',$url,null);$data=(array)$raw['data'];$vendor=$this->zkVendorStatus($data);if(!$vendor['ok'])throw new RuntimeException((string)$vendor['message']);return $data;
    }

    private function zkRequestRaw(string $method,string $url,?array $payload): array
    {
        if(!extension_loaded('curl'))throw new RuntimeException('La extensión cURL no está disponible.');$method=strtoupper($method);$ch=curl_init($url);if($ch===false)throw new RuntimeException('No fue posible iniciar cURL.');$headers=['Accept: application/json'];$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>$headers];
        if($method==='POST'){$opts[CURLOPT_POST]=true;if($payload!==null){$body=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$headers[]='Content-Type: application/json';$opts[CURLOPT_POSTFIELDS]=$body;}}
        elseif($method==='DELETE'){$opts[CURLOPT_CUSTOMREQUEST]='DELETE';if($payload!==null){$body=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$headers[]='Content-Type: application/json';$opts[CURLOPT_POSTFIELDS]=$body;}}
        else $opts[CURLOPT_HTTPGET]=true;
        // Reassign headers after they may have changed.
        $opts[CURLOPT_HTTPHEADER]=$headers;curl_setopt_array($ch,$opts);$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
        if($body===false||$status<200||$status>=300)throw new RuntimeException('ZKBio respondió HTTP '.$status.($err!==''?' · '.$err:''));$data=json_decode((string)$body,true);if(!is_array($data))throw new RuntimeException('ZKBio no devolvió JSON válido.');return ['data'=>$data,'http_status'=>$status];
    }

    private function zkVendorStatus(array $data): array
    {
        $code=$data['code']??($data['ret']??null);$success=$data['success']??null;$msg=trim((string)($data['message']??$data['msg']??''));$ok=true;
        if($success===false)$ok=false;
        if(array_key_exists('code',$data)&&is_numeric($data['code'])&&(int)$data['code']<0)$ok=false;
        if(array_key_exists('ret',$data)){$ret=strtolower(trim((string)$data['ret']));if(is_numeric($ret)&&(int)$ret>=400)$ok=false;elseif(in_array($ret,['fail','failed','error','false'],true))$ok=false;elseif(in_array($ret,['ok','success','0','200'],true)&&$success!==false)$ok=true;}
        if(!$ok&&$msg==='')$msg='ZKBio rechazó la operación'.($code!==null?' ('.$code.')':'').'.';elseif(!$ok)$msg='ZKBio: '.$msg.($code!==null?' ['.$code.']':'');
        return ['ok'=>$ok,'code'=>$code===null?null:(string)$code,'message'=>$msg];
    }

    private function rowsFromZk(array $response): array
    {
        $d=$response['data']??[];
        if(isset($d['data'])&&is_array($d['data']))return array_values(array_filter($d['data'],'is_array'));
        return is_array($d)&&array_is_list($d)?array_values(array_filter($d,'is_array')):[];
    }

    public function networkInterfaces(): array
    {
        $this->assertEdge();$out=[];
        if(function_exists('net_get_interfaces')){
            $interfaces=@net_get_interfaces();
            if(is_array($interfaces)){
                foreach($interfaces as $name=>$info){
                    foreach((array)($info['unicast']??[]) as $u){
                        $ip=(string)($u['address']??'');
                        if(($u['family']??null)!==AF_INET || !filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4) || str_starts_with($ip,'127.'))continue;
                        $out[$ip]=['name'=>(string)$name,'ip'=>$ip,'netmask'=>(string)($u['netmask']??'')];
                    }
                }
            }
        }
        if($out===[]){
            foreach((array)@gethostbynamel((string)gethostname()) as $ip){
                if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)&&!str_starts_with($ip,'127.'))$out[$ip]=['name'=>'IPv4 local','ip'=>$ip,'netmask'=>''];
            }
        }
        return array_values($out);
    }

    public function onvifDiscover(int $seconds=4,string $localIp=''): array
    {
        $this->assertEdge();
        if(!extension_loaded('sockets'))return ['ok'=>false,'message'=>'La extensión PHP sockets no está activa. Actívela para descubrimiento ONVIF/WS-Discovery.','devices'=>[],'interfaces'=>$this->networkInterfaces()];
        $interfaces=$this->networkInterfaces();$validIps=array_values(array_unique(array_filter(array_column($interfaces,'ip'))));$localIp=trim($localIp);
        if($localIp!==''&&!in_array($localIp,$validIps,true))return ['ok'=>false,'message'=>'La interfaz IPv4 seleccionada ya no está disponible.','devices'=>[],'interfaces'=>$interfaces];
        // En modo automático se prueba cada interfaz IPv4 local. En servidores Windows con
        // Ethernet/VPN/Wi-Fi, depender de la ruta multicast por defecto puede ocultar cámaras.
        $targets=$localIp!==''?[$localIp]:($validIps!==[]?$validIps:['']);$sockets=[];$sent=0;
        $uuid='urn:uuid:'.self::uuid();
        $probe='<?xml version="1.0" encoding="UTF-8"?><e:Envelope xmlns:e="http://www.w3.org/2003/05/soap-envelope" xmlns:w="http://schemas.xmlsoap.org/ws/2004/08/addressing" xmlns:d="http://schemas.xmlsoap.org/ws/2005/04/discovery" xmlns:dn="http://www.onvif.org/ver10/network/wsdl"><e:Header><w:MessageID>'.$uuid.'</w:MessageID><w:To e:mustUnderstand="true">urn:schemas-xmlsoap-org:ws:2005:04:discovery</w:To><w:Action e:mustUnderstand="true">http://schemas.xmlsoap.org/ws/2005/04/discovery/Probe</w:Action></e:Header><e:Body><d:Probe><d:Types>dn:NetworkVideoTransmitter</d:Types></d:Probe></e:Body></e:Envelope>';
        foreach($targets as $ip){
            $sock=@socket_create(AF_INET,SOCK_DGRAM,SOL_UDP);if($sock===false)continue;
            @socket_set_option($sock,SOL_SOCKET,SO_REUSEADDR,1);
            if($ip!==''&&!@socket_bind($sock,$ip,0)){@socket_close($sock);continue;}
            @socket_set_nonblock($sock);$sockets[]=['socket'=>$sock,'ip'=>$ip];
            for($i=0;$i<3;$i++){if(@socket_sendto($sock,$probe,strlen($probe),0,'239.255.255.250',3702)!==false)$sent++;usleep(70000);}
        }
        if($sockets===[])return ['ok'=>false,'message'=>'No fue posible abrir un socket WS-Discovery en ninguna interfaz IPv4.','devices'=>[],'interfaces'=>$interfaces,'selected_ip'=>$localIp,'searched_ips'=>$targets,'probe_packets_sent'=>0,'responses_received'=>0];
        $end=microtime(true)+max(2,min(10,$seconds));$found=[];$responses=0;
        while(microtime(true)<$end){$receivedThisPass=false;
            foreach($sockets as $entry){$sock=$entry['socket'];$buf='';$from='';$port=0;$bytes=@socket_recvfrom($sock,$buf,65535,0,$from,$port);
                if($bytes===false||$bytes<=0)continue;$receivedThisPass=true;$responses++;
                $xaddrs=[];
                if(preg_match_all('#<[^:>]*:?XAddrs[^>]*>(.*?)</[^:>]*:?XAddrs>#si',$buf,$m))foreach($m[1] as $chunk)foreach(preg_split('/\s+/',trim(strip_tags($chunk)))?:[] as $x)if($x!=='')$xaddrs[]=$x;
                $scopes='';if(preg_match('#<[^:>]*:?Scopes[^>]*>(.*?)</[^:>]*:?Scopes>#si',$buf,$sm))$scopes=trim(strip_tags($sm[1]));
                $key=$from.'|'.implode(',',$xaddrs);$found[$key]=['ip'=>$from,'port'=>$port,'xaddrs'=>array_values(array_unique($xaddrs)),'scopes'=>$scopes,'received_on'=>$entry['ip']];
            }
            if(!$receivedThisPass)usleep(80000);
        }
        foreach($sockets as $entry)@socket_close($entry['socket']);
        $count=count($found);$message=$count.' dispositivo(s) ONVIF/WS-Discovery detectado(s).';
        if($count===0)$message.=' Si la cámara está en la LAN pero no responde a multicast, agréguela manualmente por IP/URL.';
        return ['ok'=>true,'message'=>$message,'devices'=>array_values($found),'interfaces'=>$interfaces,'selected_ip'=>$localIp,'searched_ips'=>$targets,'probe_packets_sent'=>$sent,'responses_received'=>$responses];
    }

    public function cameras(): array
    {
        $this->assertEdge();
        $stmt=$this->pdo->prepare('SELECT * FROM bh_edge_cameras WHERE installation_id=? AND deleted_at IS NULL ORDER BY name,id');
        $stmt->execute([$this->installationId()]);
        $rows=$stmt->fetchAll()?:[];
        foreach($rows as &$row){
            $secret=EdgeSecretStore::decrypt((string)($row['configuration_encrypted']??''));
            $row['username_masked']=(string)($secret['username']??'');
            $row['has_password']=trim((string)($secret['password']??''))!=='';
            $row['verify_tls']=!empty($secret['verify_tls']);
            $parts=parse_url((string)($row['onvif_url']??''));
            $row['onvif_port']=(int)($parts['port']??((($parts['scheme']??'http')==='https')?443:80));
            $row['channels']=$this->videoChannels((int)$row['id']);
        }
        unset($row);return $rows;
    }

    public function videoChannels(int $cameraId,bool $enabledOnly=false): array
    {
        $this->assertEdge();$sql='SELECT * FROM bh_edge_video_channels WHERE installation_id=? AND edge_camera_id=?';
        if($enabledOnly)$sql.=' AND enabled=1 AND status<>\'disabled\'';
        $sql.=' ORDER BY channel_index,id';
        $stmt=$this->pdo->prepare($sql);$stmt->execute([$this->installationId(),$cameraId]);$rows=$stmt->fetchAll()?:[];
        foreach($rows as &$row){$meta=json_decode((string)($row['metadata_json']??''),true);$row['profiles']=is_array($meta)?(array)($meta['profiles']??[]):[];}unset($row);
        return $rows;
    }

    public function saveCamera(array $data): int
    {
        $this->assertEdge();$iid=$this->installationId();$id=max(0,(int)($data['id']??0));
        $name=trim((string)($data['name']??''));if($name==='')throw new InvalidArgumentException('Indique un nombre para la cámara, NVR o DVR.');
        $hostInput=trim((string)($data['host']??''));$port=max(1,min(65535,(int)($data['onvif_port']??80)));
        $onvifInput=trim((string)($data['onvif_url']??''));if($onvifInput==='')$onvifInput=$hostInput;$onvifUrl=OnvifClient::normalizeDeviceServiceUrl($onvifInput,$port);
        $parts=parse_url($onvifUrl);$host=(string)($parts['host']??$hostInput);if($host==='')throw new InvalidArgumentException('No fue posible determinar la IP/hostname del equipo ONVIF.');
        $username=trim((string)($data['username']??''));$password=(string)($data['password']??'');$verifyTls=!empty($data['verify_tls']);$rtsp=trim((string)($data['rtsp_url']??''));$sourceKind=trim((string)($data['source_kind']??'unknown'));if(!in_array($sourceKind,['unknown','camera','nvr','dvr','encoder'],true))$sourceKind='unknown';

        $oldRow=null;
        if($id>0){
            $stmt=$this->pdo->prepare('SELECT * FROM bh_edge_cameras WHERE id=? AND installation_id=? LIMIT 1');$stmt->execute([$id,$iid]);$oldRow=$stmt->fetch();if(!is_array($oldRow))throw new RuntimeException('La fuente de video indicada no existe.');
        }else{
            // Si se vuelve a crear una fuente que fue eliminada, reutilizamos su UUID para
            // que el Node no acumule copias históricas del mismo nombre.
            $stmt=$this->pdo->prepare('SELECT * FROM bh_edge_cameras WHERE installation_id=? AND name=? AND deleted_at IS NOT NULL ORDER BY id DESC LIMIT 1');$stmt->execute([$iid,$name]);$candidate=$stmt->fetch();
            if(is_array($candidate)){$oldRow=$candidate;$id=(int)$candidate['id'];}
        }
        if(is_array($oldRow)){
            $oldSecret=EdgeSecretStore::decrypt((string)($oldRow['configuration_encrypted']??''));
            if($username==='')$username=(string)($oldSecret['username']??'');if($password==='')$password=(string)($oldSecret['password']??'');
        }
        $enc=EdgeSecretStore::encrypt(['username'=>$username,'password'=>$password,'verify_tls'=>$verifyTls]);
        if($id>0){
            $oldName=(string)($oldRow['name']??'');
            $this->pdo->prepare("UPDATE bh_edge_cameras SET name=?,source_kind=?,host=?,onvif_url=?,rtsp_url=NULLIF(?,''),configuration_encrypted=?,status='configured',last_error=NULL,sync_status='pending',sync_error=NULL,deleted_at=NULL,deleted_synced_at=NULL WHERE id=? AND installation_id=?")
                ->execute([$name,$sourceKind,$host,$onvifUrl,$rtsp,$enc,$id,$iid]);
            if($oldName!==''&&$oldName!==$name){
                $cnt=$this->pdo->prepare('SELECT COUNT(*) FROM bh_edge_video_channels WHERE installation_id=? AND edge_camera_id=?');$cnt->execute([$iid,$id]);
                if((int)$cnt->fetchColumn()===1)$this->pdo->prepare('UPDATE bh_edge_video_channels SET channel_name=? WHERE installation_id=? AND edge_camera_id=? AND channel_name=?')->execute([$name,$iid,$id,$oldName]);
            }
            return $id;
        }
        $this->pdo->prepare("INSERT INTO bh_edge_cameras (installation_id,camera_uuid,name,source_kind,host,onvif_url,rtsp_url,configuration_encrypted,status,sync_status) VALUES (?,?,?,?,?,?,NULLIF(?,''),?,'configured','pending')")
            ->execute([$iid,self::uuid(),$name,$sourceKind,$host,$onvifUrl,$rtsp,$enc]);
        return (int)$this->pdo->lastInsertId();
    }

    public function testCamera(int $id): array
    {
        $this->assertEdge();$iid=$this->installationId();$stmt=$this->pdo->prepare('SELECT * FROM bh_edge_cameras WHERE id=? AND installation_id=? AND deleted_at IS NULL LIMIT 1');$stmt->execute([$id,$iid]);$camera=$stmt->fetch();if(!is_array($camera))throw new RuntimeException('Fuente de video no encontrada.');
        $secret=EdgeSecretStore::decrypt((string)($camera['configuration_encrypted']??''));
        $client=new OnvifClient((string)$camera['onvif_url'],(string)($secret['username']??''),(string)($secret['password']??''),(bool)($secret['verify_tls']??true),12);
        try{
            $result=$client->probe();$dev=(array)($result['device']??[]);$profiles=(array)($result['profiles']??[]);
            $channelState=$this->syncVideoChannels($id,$camera,$profiles,(array)$result);
            $selected=$this->selectWebRtcProfile($profiles);
            $rtsp=trim((string)($selected['stream_uri']??''));if($rtsp==='')$rtsp=trim((string)($result['rtsp_uri']??''));if($rtsp==='')$rtsp=trim((string)($camera['rtsp_url']??''));
            $snapshot=trim((string)($selected['snapshot_uri']??''));if($snapshot==='')$snapshot=trim((string)($result['snapshot_uri']??''));
            $codec=trim((string)($selected['encoding']??''));$webrtcStatus=$codec!==''&&in_array(strtoupper($codec),['H265','HEVC'],true)?'incompatible':'pending';
            $webrtcError=$webrtcStatus==='incompatible'?'El perfil principal detectado usa H265/HEVC. En NVR/DVR puede seleccionar por canal un perfil H264 para navegador.':'';
            $this->pdo->prepare("UPDATE bh_edge_cameras SET source_kind=?,media_service_url=?,rtsp_url=NULLIF(?,''),snapshot_url=NULLIF(?,''),manufacturer=NULLIF(?,''),model=NULLIF(?,''),firmware_version=NULLIF(?,''),serial_number=NULLIF(?,''),hardware_id=NULLIF(?,''),profile_count=?,status='online',last_test_at=NOW(),last_channel_scan_at=NOW(),last_error=NULL,metadata_json=?,sync_status='pending',sync_error=NULL,webrtc_codec=NULLIF(?,''),webrtc_status=?,webrtc_error=NULLIF(?,'') WHERE id=?")
                ->execute([(string)$channelState['source_kind'],(string)($result['media_service_url']??''),$rtsp,$snapshot,(string)($dev['manufacturer']??''),(string)($dev['model']??''),(string)($dev['firmware_version']??''),(string)($dev['serial_number']??''),(string)($dev['hardware_id']??''),(int)($result['profile_count']??0),json_encode(['profiles'=>$profiles,'webrtc_selected_profile'=>$selected,'last_latency_ms'=>$result['latency_ms']??null],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$codec,$webrtcStatus,$webrtcError,$id]);
            $rtspTest=$rtsp!==''?$this->testRtspUrl($rtsp):null;
            return $result+['camera_id'=>$id,'camera_name'=>(string)$camera['name'],'rtsp_test'=>$rtspTest,'webrtc_profile'=>$selected,'webrtc_codec'=>$codec,'webrtc_status'=>$webrtcStatus,'source_kind'=>$channelState['source_kind'],'channels'=>$channelState['channels'],'channel_count'=>count($channelState['channels'])];
        }catch(Throwable $e){
            $this->pdo->prepare("UPDATE bh_edge_cameras SET status='error',last_test_at=NOW(),last_error=? WHERE id=?")->execute([$e->getMessage(),$id]);throw $e;
        }
    }

    private function syncVideoChannels(int $cameraId,array $camera,array $profiles,array $probe): array
    {
        $groups=[];
        foreach($profiles as $profile){
            if(!is_array($profile))continue;
            $identity=$this->profileChannelIdentity($profile);
            $key=(string)$identity['key'];
            if(!isset($groups[$key]))$groups[$key]=['channel_index'=>(int)$identity['index'],'profiles'=>[],'source'=>(string)$identity['source']];
            elseif((int)$groups[$key]['channel_index']<1&&(int)$identity['index']>0)$groups[$key]['channel_index']=(int)$identity['index'];
            $groups[$key]['profiles'][]=$profile;
        }
        if($groups===[]&&trim((string)($probe['rtsp_uri']??''))!==''){
            $groups['default']=['channel_index'=>1,'source'=>'manual','profiles'=>[['token'=>'manual','name'=>'Stream principal','encoding'=>'','resolution'=>'','stream_uri'=>(string)$probe['rtsp_uri'],'snapshot_uri'=>(string)($probe['snapshot_uri']??''),'video_source_token'=>'default']]];
        }
        uasort($groups,static function(array $a,array $b): int { $ai=(int)($a['channel_index']??0);$bi=(int)($b['channel_index']??0);if($ai>0&&$bi>0&&$ai!==$bi)return $ai<=>$bi;if($ai>0&&$bi<1)return -1;if($bi>0&&$ai<1)return 1;return 0; });
        $configuredKind=(string)($camera['source_kind']??'unknown');
        if(in_array($configuredKind,['nvr','dvr','encoder'],true))$sourceKind=$configuredKind;
        elseif($configuredKind==='camera')$sourceKind='camera';
        else $sourceKind=count($groups)>1?'nvr':'camera';
        $existingStmt=$this->pdo->prepare('SELECT * FROM bh_edge_video_channels WHERE installation_id=? AND edge_camera_id=?');$existingStmt->execute([$this->installationId(),$cameraId]);$existingRows=$existingStmt->fetchAll()?:[];$existing=[];
        foreach($existingRows as $row)$existing[(string)$row['source_token']]=$row;
        $seen=[];$channels=[];$sequence=0;$total=count($groups);
        foreach($groups as $sourceToken=>$group){
            $sequence++;$channelProfiles=(array)($group['profiles']??[]);$detectedIndex=(int)($group['channel_index']??0);$index=$detectedIndex>0?$detectedIndex:$sequence;$seen[]=$sourceToken;$old=$existing[$sourceToken]??null;
            $selected=[];$wanted=trim((string)($old['selected_profile_token']??''));
            if($wanted!=='')foreach($channelProfiles as $p)if((string)($p['token']??'')===$wanted){$selected=$p;break;}
            if($selected===[])$selected=$this->selectWebRtcProfile($channelProfiles);
            $uuid=is_array($old)?(string)$old['channel_uuid']:($total===1?(string)$camera['camera_uuid']:self::uuid());
            $enabled=is_array($old)?(int)$old['enabled']:($total===1?1:0);
            $defaultName=$total===1?(string)$camera['name']:(string)$camera['name'].' · Canal '.$index;
            $channelName=is_array($old)&&trim((string)$old['channel_name'])!==''?(string)$old['channel_name']:$defaultName;
            $encoding=(string)($selected['encoding']??'');$codecUpper=strtoupper($encoding);$webrtcStatus=$enabled?($codecUpper==='H265'||$codecUpper==='HEVC'?'incompatible':'pending'):'disabled';
            $webrtcError=$webrtcStatus==='incompatible'?'El perfil seleccionado usa H265/HEVC. Seleccione un perfil H264 para WebRTC.':'';
            $meta=json_encode(['profiles'=>array_values($channelProfiles),'detected_at'=>date(DATE_ATOM),'channel_index'=>$index,'channel_detection_source'=>(string)($group['source']??'unknown')],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if(is_array($old)){
                $this->pdo->prepare("UPDATE bh_edge_video_channels SET channel_index=?,channel_name=?,selected_profile_token=NULLIF(?,''),profile_name=NULLIF(?,''),encoding=NULLIF(?,''),resolution=NULLIF(?,''),stream_uri=NULLIF(?,''),snapshot_uri=NULLIF(?,''),status='online',webrtc_codec=NULLIF(?,''),webrtc_status=CASE WHEN webrtc_enabled=1 THEN ? ELSE 'disabled' END,webrtc_error=NULLIF(?,''),metadata_json=? WHERE id=?")
                    ->execute([$index,$channelName,(string)($selected['token']??''),(string)($selected['name']??''),$encoding,(string)($selected['resolution']??''),(string)($selected['stream_uri']??''),(string)($selected['snapshot_uri']??''),$encoding,$webrtcStatus,$webrtcError,$meta,(int)$old['id']]);
                $channelId=(int)$old['id'];
            }else{
                $this->pdo->prepare("INSERT INTO bh_edge_video_channels (installation_id,edge_camera_id,channel_uuid,source_token,channel_index,channel_name,enabled,selected_profile_token,profile_name,encoding,resolution,stream_uri,snapshot_uri,status,webrtc_codec,webrtc_status,webrtc_error,metadata_json) VALUES (?,?,?,?,?,?,?,?,NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),'online',NULLIF(?,''),?,NULLIF(?,''),?)")
                    ->execute([$this->installationId(),$cameraId,$uuid,$sourceToken,$index,$channelName,$enabled,(string)($selected['token']??''),(string)($selected['name']??''),$encoding,(string)($selected['resolution']??''),(string)($selected['stream_uri']??''),(string)($selected['snapshot_uri']??''),$encoding,$webrtcStatus,$webrtcError,$meta]);
                $channelId=(int)$this->pdo->lastInsertId();
            }
            $channels[]=['id'=>$channelId,'channel_uuid'=>$uuid,'source_token'=>$sourceToken,'channel_index'=>$index,'channel_name'=>$channelName,'enabled'=>$enabled,'selected_profile'=>$selected,'profiles'=>$channelProfiles];
        }
        if($seen!==[]){$marks=implode(',',array_fill(0,count($seen),'?'));$args=[$this->installationId(),$cameraId,...$seen];$this->pdo->prepare("UPDATE bh_edge_video_channels SET enabled=0,status='disabled',webrtc_enabled=0,webrtc_status='disabled' WHERE installation_id=? AND edge_camera_id=? AND source_token NOT IN ({$marks})")->execute($args);}
        elseif($existingRows!==[])$this->pdo->prepare("UPDATE bh_edge_video_channels SET enabled=0,status='disabled',webrtc_enabled=0,webrtc_status='disabled' WHERE installation_id=? AND edge_camera_id=?")->execute([$this->installationId(),$cameraId]);
        return ['source_kind'=>$sourceKind,'channels'=>$channels];
    }

    private function profileChannelIdentity(array $profile): array
    {
        // ONVIF ideal: VideoSourceConfiguration/SourceToken. Algunos NVR Media2
        // (incluido el ECVox 46NVR.16 probado en Premier) omiten esos tokens,
        // aunque codifican el canal tanto en GetStreamUri como en Profile_N_X.
        // Nunca agrupamos todos los perfiles como un supuesto "canal 1" si hay
        // evidencia más fiable en el URI/token del perfil.
        $source=trim((string)($profile['video_source_token']??''));
        if($source==='')$source=trim((string)($profile['video_source_config_token']??''));
        if($source!==''){
            $index=0;if(preg_match('/(?:^|[_\-])(\d+)(?:$|[_\-])/',$source,$m))$index=(int)$m[1];
            return ['key'=>'onvif:'.$source,'index'=>$index,'source'=>'onvif_source_token'];
        }

        $stream=trim((string)($profile['stream_uri']??''));
        if($stream!==''&&preg_match('/(?:[?&]|^)ch(?:id)?=(\d+)/i',$stream,$m)){
            $index=max(1,(int)$m[1]);return ['key'=>'channel:'.$index,'index'=>$index,'source'=>'stream_uri_chid'];
        }

        foreach([(string)($profile['token']??''),(string)($profile['name']??'')] as $candidate){
            if(preg_match('/(?:^|[_\-])Profile[_\-]?(\d+)[_\-](\d+)(?:$|[_\-])/i',$candidate,$m)||preg_match('/^Profile_(\d+)_(\d+)$/i',$candidate,$m)){
                $index=max(1,(int)$m[1]);return ['key'=>'channel:'.$index,'index'=>$index,'source'=>'profile_token'];
            }
        }

        $snapshot=trim((string)($profile['snapshot_uri']??''));
        if($snapshot!==''&&preg_match('/\?(\d+)_\d+(?:&|$)/',$snapshot,$m)){
            $index=max(1,(int)$m[1]);return ['key'=>'channel:'.$index,'index'=>$index,'source'=>'snapshot_uri'];
        }

        return ['key'=>'default','index'=>1,'source'=>'fallback'];
    }

    private function selectWebRtcProfile(array $profiles): array
    {
        $best=[];$bestScore=PHP_INT_MAX;
        foreach($profiles as $profile){
            if(!is_array($profile)||trim((string)($profile['stream_uri']??''))==='')continue;
            $enc=strtoupper(trim((string)($profile['encoding']??'')));$res=(string)($profile['resolution']??'');$w=0;$h=0;if(preg_match('/^(\d+)[x×](\d+)$/u',$res,$m)){$w=(int)$m[1];$h=(int)$m[2];}
            $codecPenalty=$enc==='H264'?0:(in_array($enc,['VP8','VP9','AV1'],true)?1000000:5000000);
            $pixels=$w>0&&$h>0?$w*$h:2073600;$target=1280*720;$resolutionPenalty=abs($pixels-$target);$score=$codecPenalty+$resolutionPenalty;
            if($score<$bestScore){$best=$profile;$bestScore=$score;}
        }
        if($best!==[])return $best;
        foreach($profiles as $profile)if(is_array($profile)&&trim((string)($profile['stream_uri']??''))!=='')return $profile;
        return is_array($profiles[0]??null)?(array)$profiles[0]:[];
    }

    public function saveVideoChannels(int $cameraId,array $data): array
    {
        $this->assertEdge();$iid=$this->installationId();$stmt=$this->pdo->prepare('SELECT id FROM bh_edge_cameras WHERE id=? AND installation_id=? AND deleted_at IS NULL LIMIT 1');$stmt->execute([$cameraId,$iid]);if(!$stmt->fetchColumn())throw new RuntimeException('Fuente ONVIF no encontrada.');
        $enabled=array_map('intval',(array)($data['enabled_channels']??[]));$names=(array)($data['channel_name']??[]);$profiles=(array)($data['profile_token']??[]);
        $rows=$this->videoChannels($cameraId);$this->pdo->beginTransaction();
        try{
            foreach($rows as $row){$id=(int)$row['id'];$name=trim((string)($names[$id]??$row['channel_name']));if($name==='')$name=(string)$row['channel_name'];$selectedToken=trim((string)($profiles[$id]??$row['selected_profile_token']??''));$all=(array)($row['profiles']??[]);$selected=[];
                foreach($all as $p)if(is_array($p)&&(string)($p['token']??'')===$selectedToken){$selected=$p;break;}if($selected===[])$selected=$this->selectWebRtcProfile($all);
                $isEnabled=in_array($id,$enabled,true)?1:0;$enc=(string)($selected['encoding']??'');$upper=strtoupper($enc);$status=$isEnabled?($upper==='H265'||$upper==='HEVC'?'incompatible':'pending'):'disabled';$error=$status==='incompatible'?'El perfil seleccionado usa H265/HEVC. Seleccione H264 para WebRTC.':'';
                $this->pdo->prepare("UPDATE bh_edge_video_channels SET channel_name=?,enabled=?,selected_profile_token=NULLIF(?,''),profile_name=NULLIF(?,''),encoding=NULLIF(?,''),resolution=NULLIF(?,''),stream_uri=NULLIF(?,''),snapshot_uri=NULLIF(?,''),webrtc_codec=NULLIF(?,''),webrtc_status=CASE WHEN webrtc_enabled=1 THEN ? ELSE 'disabled' END,webrtc_error=NULLIF(?,'') WHERE id=? AND installation_id=? AND edge_camera_id=?")
                    ->execute([$name,$isEnabled,(string)($selected['token']??''),(string)($selected['name']??''),$enc,(string)($selected['resolution']??''),(string)($selected['stream_uri']??''),(string)($selected['snapshot_uri']??''),$enc,$status,$error,$id,$iid,$cameraId]);
            }
            $this->pdo->prepare("UPDATE bh_edge_cameras SET sync_status='pending',sync_error=NULL WHERE id=? AND installation_id=?")->execute([$cameraId,$iid]);$this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        return ['ok'=>true,'enabled'=>count($enabled),'channels'=>count($rows)];
    }

    public function activeVideoInventory(): array
    {
        $this->assertEdge();$sources=$this->cameras();$items=[];
        foreach($sources as $source){$channels=(array)($source['channels']??[]);
            if($channels!==[]){
                foreach($channels as $channel){if(empty($channel['enabled'])||(string)($channel['status']??'')==='disabled')continue;$profiles=(array)($channel['profiles']??[]);
                    $items[]=$this->inventoryItem($source,[
                        'camera_uuid'=>(string)$channel['channel_uuid'],'name'=>(string)$channel['channel_name'],'channel_id'=>(int)$channel['id'],'channel_key'=>(string)$channel['source_token'],'channel_index'=>(int)$channel['channel_index'],
                        'rtsp_url'=>(string)($channel['stream_uri']??''),'snapshot_url'=>(string)($channel['snapshot_uri']??''),'profiles'=>$profiles,'profile_count'=>count($profiles),
                        'webrtc_codec'=>(string)($channel['webrtc_codec']??$channel['encoding']??''),'webrtc_status'=>(string)($channel['webrtc_status']??'disabled'),'webrtc_error'=>(string)($channel['webrtc_error']??''),
                    ]);
                }
                continue;
            }
            $meta=json_decode((string)($source['metadata_json']??''),true);if(!is_array($meta))$meta=[];
            $items[]=$this->inventoryItem($source,['camera_uuid'=>(string)$source['camera_uuid'],'name'=>(string)$source['name'],'channel_id'=>0,'channel_key'=>'default','channel_index'=>1,'rtsp_url'=>(string)($source['rtsp_url']??''),'snapshot_url'=>(string)($source['snapshot_url']??''),'profiles'=>(array)($meta['profiles']??[]),'profile_count'=>(int)($source['profile_count']??0),'webrtc_codec'=>(string)($source['webrtc_codec']??''),'webrtc_status'=>(string)($source['webrtc_status']??'disabled'),'webrtc_error'=>(string)($source['webrtc_error']??'')]);
        }
        return $items;
    }

    private function inventoryItem(array $source,array $logical): array
    {
        return [
            'source_id'=>(int)$source['id'],'channel_id'=>(int)($logical['channel_id']??0),'camera_uuid'=>(string)$logical['camera_uuid'],'source_uuid'=>(string)$source['camera_uuid'],'source_name'=>(string)$source['name'],'source_kind'=>(string)($source['source_kind']??'camera'),'name'=>(string)$logical['name'],'host'=>(string)$source['host'],'onvif_url'=>(string)$source['onvif_url'],
            'rtsp_url'=>(string)($logical['rtsp_url']??''),'snapshot_url'=>(string)($logical['snapshot_url']??''),'manufacturer'=>(string)($source['manufacturer']??''),'model'=>(string)($source['model']??''),'firmware_version'=>(string)($source['firmware_version']??''),'serial_number'=>(string)($source['serial_number']??''),'hardware_id'=>(string)($source['hardware_id']??''),'profile_count'=>(int)($logical['profile_count']??0),'profiles'=>(array)($logical['profiles']??[]),'status'=>(string)($source['status']??'offline'),'last_test_at'=>(string)($source['last_test_at']??''),'channel_key'=>(string)($logical['channel_key']??'default'),'channel_index'=>(int)($logical['channel_index']??1),'webrtc_codec'=>(string)($logical['webrtc_codec']??''),'webrtc_status'=>(string)($logical['webrtc_status']??'disabled'),'webrtc_error'=>(string)($logical['webrtc_error']??''),
        ];
    }

    public function deletedVideoUuids(): array
    {
        $this->assertEdge();$stmt=$this->pdo->prepare('SELECT id,camera_uuid FROM bh_edge_cameras WHERE installation_id=? AND deleted_at IS NOT NULL AND deleted_synced_at IS NULL');$stmt->execute([$this->installationId()]);$rows=$stmt->fetchAll()?:[];$uuids=[];
        foreach($rows as $row){$cid=(int)$row['id'];$ch=$this->pdo->prepare('SELECT channel_uuid FROM bh_edge_video_channels WHERE installation_id=? AND edge_camera_id=?');$ch->execute([$this->installationId(),$cid]);$channelUuids=$ch->fetchAll(PDO::FETCH_COLUMN)?:[];if($channelUuids!==[])foreach($channelUuids as $u)$uuids[]=(string)$u;else $uuids[]=(string)$row['camera_uuid'];}
        return array_values(array_unique(array_filter($uuids)));
    }

    public function markDeletedVideoSynced(): void
    {
        $this->assertEdge();$this->pdo->prepare('UPDATE bh_edge_cameras SET deleted_synced_at=NOW() WHERE installation_id=? AND deleted_at IS NOT NULL AND deleted_synced_at IS NULL')->execute([$this->installationId()]);
    }

    public function captureSnapshot(int $id): array
    {
        $this->assertEdge();$stmt=$this->pdo->prepare('SELECT * FROM bh_edge_cameras WHERE id=? AND installation_id=? AND deleted_at IS NULL LIMIT 1');$stmt->execute([$id,$this->installationId()]);$source=$stmt->fetch();if(!is_array($source))throw new RuntimeException('Fuente de video no encontrada.');
        return $this->captureSnapshotFromSource($source,(string)($source['snapshot_url']??''),(string)$source['camera_uuid'],(string)$source['name']);
    }

    public function captureSnapshotForInventoryItem(array $item): array
    {
        $this->assertEdge();$sourceId=(int)($item['source_id']??0);$stmt=$this->pdo->prepare('SELECT * FROM bh_edge_cameras WHERE id=? AND installation_id=? AND deleted_at IS NULL LIMIT 1');$stmt->execute([$sourceId,$this->installationId()]);$source=$stmt->fetch();if(!is_array($source))throw new RuntimeException('Fuente de video no encontrada para el canal.');
        return $this->captureSnapshotFromSource($source,(string)($item['snapshot_url']??''),(string)($item['camera_uuid']??''),(string)($item['name']??$source['name']));
    }

    private function captureSnapshotFromSource(array $source,string $url,string $logicalUuid,string $logicalName): array
    {
        if(!extension_loaded('curl'))throw new RuntimeException('La extensión cURL no está disponible para capturar el snapshot.');$url=trim($url);if($url==='')throw new RuntimeException('El canal no publicó una URL de snapshot ONVIF.');
        $secret=EdgeSecretStore::decrypt((string)($source['configuration_encrypted']??''));$ch=curl_init($url);if($ch===false)throw new RuntimeException('No fue posible iniciar cURL para el snapshot.');
        $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>(bool)($secret['verify_tls']??true),CURLOPT_SSL_VERIFYHOST=>(bool)($secret['verify_tls']??true)?2:0,CURLOPT_HTTPHEADER=>['Accept: image/jpeg,image/png,image/webp,*/*']];
        if(trim((string)($secret['username']??''))!==''){$opts[CURLOPT_HTTPAUTH]=CURLAUTH_ANY;$opts[CURLOPT_USERPWD]=(string)$secret['username'].':'.(string)($secret['password']??'');}
        curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$mime=(string)(curl_getinfo($ch,CURLINFO_CONTENT_TYPE)?:'');$err=curl_error($ch);curl_close($ch);
        if($raw===false)throw new RuntimeException('No fue posible obtener el snapshot: '.$err);if($status===401||$status===403)throw new RuntimeException('El equipo rechazó las credenciales del snapshot (HTTP '.$status.').');if($status<200||$status>=300)throw new RuntimeException('Snapshot respondió HTTP '.$status.'.');
        $bytes=strlen((string)$raw);if($bytes<1||$bytes>10*1024*1024)throw new RuntimeException('El snapshot recibido está vacío o supera 10 MB.');$mime=strtolower(trim(explode(';',$mime)[0]??''));if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){
            if(str_starts_with((string)$raw,"\xFF\xD8"))$mime='image/jpeg';elseif(str_starts_with((string)$raw,"\x89PNG"))$mime='image/png';elseif(substr((string)$raw,0,4)==='RIFF'&&substr((string)$raw,8,4)==='WEBP')$mime='image/webp';else throw new RuntimeException('La URL de snapshot no devolvió una imagen compatible.');
        }
        $ext=$mime==='image/png'?'png':($mime==='image/webp'?'webp':'jpg');$dir=base_path('storage/uploads/edge-camera-cache');if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No se pudo crear el cache temporal de snapshots.');$path=$dir.'/'.$logicalUuid.'-'.bin2hex(random_bytes(4)).'.'.$ext;if(file_put_contents($path,(string)$raw)===false)throw new RuntimeException('No se pudo guardar temporalmente el snapshot.');
        return ['ok'=>true,'path'=>$path,'mime'=>$mime,'size_bytes'=>$bytes,'camera_uuid'=>$logicalUuid,'camera_name'=>$logicalName];
    }

    public function markCameraSync(int $id,bool $ok,string $error='',bool $snapshotSynced=false): void
    {
        $this->assertEdge();$sql="UPDATE bh_edge_cameras SET last_synced_at=NOW(),sync_status=?,sync_error=NULLIF(?,''),last_snapshot_synced_at=CASE WHEN ?=1 THEN NOW() ELSE last_snapshot_synced_at END WHERE id=? AND installation_id=?";$this->pdo->prepare($sql)->execute([$ok?'synced':'error',$error,$snapshotSynced?1:0,$id,$this->installationId()]);
    }

    public function deleteCamera(int $id): void
    {
        $this->assertEdge();$iid=$this->installationId();$stmt=$this->pdo->prepare('SELECT id FROM bh_edge_cameras WHERE id=? AND installation_id=? AND deleted_at IS NULL LIMIT 1');$stmt->execute([$id,$iid]);if(!$stmt->fetchColumn())throw new RuntimeException('La fuente de video ya no existe.');
        $this->pdo->prepare("UPDATE bh_edge_cameras SET deleted_at=NOW(),deleted_synced_at=NULL,status='disabled',sync_status='pending',sync_error=NULL,webrtc_enabled=0,webrtc_status='disabled' WHERE id=? AND installation_id=?")->execute([$id,$iid]);
        $this->pdo->prepare("UPDATE bh_edge_video_channels SET enabled=0,status='disabled',webrtc_enabled=0,webrtc_status='disabled' WHERE edge_camera_id=? AND installation_id=?")->execute([$id,$iid]);
    }

    public function testRtsp(string $host,int $port=554): array
    {
        $this->assertEdge();$host=trim($host);if($host==='')throw new InvalidArgumentException('Indique IP o hostname de la cámara/NVR.');
        return $this->testRtspUrl('rtsp://'.$host.($port!==554?':'.$port:'').'/');
    }

    public function testRtspUrl(string $url): array
    {
        $this->assertEdge();$parts=parse_url(trim($url));$host=(string)($parts['host']??'');$port=(int)($parts['port']??554);
        if($host==='')return ['ok'=>false,'message'=>'La URL RTSP no contiene un host válido.'];
        $errno=0;$err='';$start=microtime(true);$fp=@fsockopen($host,$port,$errno,$err,4);
        if(!$fp)return ['ok'=>false,'message'=>'No fue posible conectar RTSP: '.$err.' ('.$errno.')'];
        stream_set_timeout($fp,3);$safeUrl='rtsp://'.$host.($port!==554?':'.$port:'').(($parts['path']??'')?:'/');
        fwrite($fp,"OPTIONS {$safeUrl} RTSP/1.0\r\nCSeq: 1\r\nUser-Agent: Property-Hub-Edge/".(string)config('app.version','0.14.60')."\r\n\r\n");$line=trim((string)fgets($fp,512));fclose($fp);$lat=(int)round((microtime(true)-$start)*1000);
        $reachable=str_starts_with($line,'RTSP/');
        return ['ok'=>$reachable,'latency_ms'=>$lat,'status_line'=>$line,'message'=>$reachable?'Servicio RTSP accesible'.(str_contains($line,'401')?' (requiere autenticación).':'.'):'TCP abrió, pero no se recibió una respuesta RTSP reconocible.'];
    }

    public function vimarConfig(): array
    {
        $this->assertEdge();$q=$this->pdo->prepare('SELECT * FROM bh_vimar_edge_config WHERE installation_id=? LIMIT 1');$q->execute([$this->installationId()]);$r=$q->fetch()?:[];
        $secret=EdgeSecretStore::decrypt((string)($r['secret_encrypted']??''));
        return $r+[
            'enabled'=>0,'integration_mode'=>'direct_auto','base_url'=>'','inventory_path'=>'','local_ip'=>'','discovery_seconds'=>5,
            'verify_tls'=>0,'status'=>'disabled','last_endpoint'=>'','has_token'=>trim((string)($secret['token']??''))!=='',
        ];
    }

    public function saveVimar(array $data): void
    {
        $this->assertEdge();LocalIntegrationProvisioning::assertEdgeEnabled('vimar');$iid=$this->installationId();
        $allowed=['direct_auto','vimarhub_bridge','home_assistant','view_direct','byme_direct'];$mode=in_array((string)($data['integration_mode']??''),$allowed,true)?(string)$data['integration_mode']:'direct_auto';
        $default=$mode==='home_assistant'?'http://homeassistant.local:8123':($mode==='vimarhub_bridge'?'http://127.0.0.1:47821':'');$url=rtrim(trim((string)($data['base_url']??$default)),'/');
        if(in_array($mode,['vimarhub_bridge','home_assistant'],true)&&($url===''||!preg_match('#^https?://#i',$url)))throw new InvalidArgumentException('La URL del bridge debe comenzar por http:// o https://.');
        $inventoryPath=trim((string)($data['inventory_path']??''));if($inventoryPath!==''&&!str_starts_with($inventoryPath,'/'))$inventoryPath='/'.$inventoryPath;
        $localIp=trim((string)($data['local_ip']??''));if($localIp!==''&&!filter_var($localIp,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))throw new InvalidArgumentException('La interfaz Vimar debe ser una IPv4 válida.');$seconds=max(2,min(12,(int)($data['discovery_seconds']??5)));
        $q=$this->pdo->prepare('SELECT secret_encrypted FROM bh_vimar_edge_config WHERE installation_id=?');$q->execute([$iid]);$old=(string)($q->fetchColumn()?:'');$token=trim((string)($data['token']??''));$enc=$token!==''?EdgeSecretStore::encrypt(['token'=>$token]):$old;
        $this->pdo->prepare("INSERT INTO bh_vimar_edge_config (installation_id,enabled,integration_mode,base_url,inventory_path,local_ip,discovery_seconds,secret_encrypted,verify_tls,status) VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),integration_mode=VALUES(integration_mode),base_url=VALUES(base_url),inventory_path=VALUES(inventory_path),local_ip=VALUES(local_ip),discovery_seconds=VALUES(discovery_seconds),secret_encrypted=VALUES(secret_encrypted),verify_tls=VALUES(verify_tls),status=VALUES(status),last_error=NULL")
            ->execute([$iid,!empty($data['enabled'])?1:0,$mode,$url!==''?$url:null,$inventoryPath?:null,$localIp?:null,$seconds,$enc,!empty($data['verify_tls'])?1:0,!empty($data['enabled'])?'configured':'disabled']);
        $endpoint=$mode==='direct_auto'?'LAN direct':$url;$this->ensureVimarConnectorInstance($endpoint,!empty($data['enabled'])?'configured':'disabled',$mode);
    }

    public function testVimar(): array
    {
        $this->assertEdge();LocalIntegrationProvisioning::assertEdgeEnabled('vimar');$cfg=$this->vimarConfig();if(empty($cfg['enabled']))throw new RuntimeException('El conector Vimar está deshabilitado.');$mode=(string)$cfg['integration_mode'];
        if($mode==='direct_auto'){
            $client=new VimarDirectDiscoveryClient((string)($cfg['local_ip']??''),max(2,min(4,(int)($cfg['discovery_seconds']??5))));$result=$client->discover();$count=count((array)($result['gateways']??[]));$status=$count>0?'online':'configured';$err='';
            $this->pdo->prepare("UPDATE bh_vimar_edge_config SET status=?,last_test_at=NOW(),last_endpoint='LAN direct',last_error=NULL WHERE installation_id=?")->execute([$status,$this->installationId()]);$this->ensureVimarConnectorInstance('LAN direct',$status,'direct_auto');
            return $result+['message'=>$count>0?'Discovery local operativo: '.$count.' gateway(s) compatible(s) detectado(s).':'El motor de discovery local está operativo. No se identificó un gateway Vimar en esta pasada.','mode'=>'direct_auto'];
        }
        if($mode==='vimarhub_bridge'){
            $secret=EdgeSecretStore::decrypt((string)($cfg['secret_encrypted']??''));$client=new VimarHubBridgeClient((string)$cfg['base_url'],(string)($secret['token']??''),!empty($cfg['verify_tls']),(string)($cfg['inventory_path']??''));$result=$client->health();
            $status=!empty($result['ok'])?'online':'offline';$err=!empty($result['ok'])?'':(string)($result['message']??'VimarHub no respondió.');
            $this->pdo->prepare("UPDATE bh_vimar_edge_config SET status=?,last_test_at=NOW(),last_endpoint=NULLIF(?,''),last_error=NULLIF(?,'') WHERE installation_id=?")->execute([$status,(string)($result['endpoint']??''),$err,$this->installationId()]);
            $this->ensureVimarConnectorInstance((string)$cfg['base_url'],$status,'vimarhub_bridge');return $result+['mode'=>'vimarhub_bridge'];
        }
        if($mode==='home_assistant')return $this->testVimarHomeAssistant($cfg);
        throw new RuntimeException('El modo '.str_replace('_',' ',$mode).' está reservado para un adapter específico. Use Automático / directo para discovery LAN sin servicios externos.');
    }

    public function discoverVimar(): array
    {
        $this->assertEdge();LocalIntegrationProvisioning::assertEdgeEnabled('vimar');$cfg=$this->vimarConfig();if(empty($cfg['enabled']))throw new RuntimeException('El conector Vimar está deshabilitado.');
        $mode=(string)$cfg['integration_mode'];$iid=$this->installationId();
        if($mode==='direct_auto'){
            $client=new VimarDirectDiscoveryClient((string)($cfg['local_ip']??''),(int)($cfg['discovery_seconds']??5));$result=$client->discover();$endpoint='LAN direct';
        }elseif($mode==='vimarhub_bridge'){
            $secret=EdgeSecretStore::decrypt((string)($cfg['secret_encrypted']??''));$client=new VimarHubBridgeClient((string)$cfg['base_url'],(string)($secret['token']??''),!empty($cfg['verify_tls']),(string)($cfg['inventory_path']??''));$result=$client->discover();$endpoint=(string)($result['endpoint']??'');
        }else throw new RuntimeException('El inventario de esta pantalla se descubre con Automático / directo o mediante un bridge avanzado explícitamente seleccionado.');
        if(empty($result['ok'])){$msg=(string)($result['message']??'No se pudo ejecutar el discovery Vimar.');$this->pdo->prepare("UPDATE bh_vimar_edge_config SET status='error',last_test_at=NOW(),last_error=?,last_endpoint=NULLIF(?,'') WHERE installation_id=?")->execute([$msg,$endpoint,$iid]);throw new RuntimeException($msg);}
        $connectorEndpoint=$mode==='direct_auto'?'LAN direct':(string)$cfg['base_url'];$connectorId=$this->ensureVimarConnectorInstance($connectorEndpoint,'online',$mode);$gateways=(array)($result['gateways']??[]);$devices=(array)($result['devices']??[]);
        $external=[];foreach($gateways as $g){if(!is_array($g))continue;$raw=(array)($g['raw']??$g);$raw['id']='gateway:'.(string)($g['external_id']??'');$raw['name']=(string)($g['name']??'Vimar Gateway');$raw['_property_normalized_type']='gateway';$raw['_property_protocol']=(string)($g['protocol']??'');$raw['_property_ecosystem']=(string)($g['ecosystem']??'');$external[]=$raw;}
        foreach($devices as $d){if(!is_array($d))continue;$raw=(array)($d['raw']??$d);$raw['id']='device:'.(string)($d['external_id']??'');$raw['name']=(string)($d['name']??'Vimar device');$raw['_property_normalized_type']=(string)($d['normalized_type']??'other');$raw['_property_gateway_id']=(string)($d['gateway_id']??'');$raw['_property_room']=(string)($d['room']??'');$raw['_property_capabilities']=(array)($d['capabilities']??[]);$external[]=$raw;}
        $stored=$this->persistExternalObjects($connectorId,'device',$external);$materialized=0;foreach($gateways as $g)if(is_array($g)){$this->upsertVimarPhysicalDevice($connectorId,$g,$mode);$materialized++;}foreach($devices as $d)if(is_array($d)){$this->upsertVimarPhysicalDevice($connectorId,$d,$mode);$materialized++;}
        $this->archiveMissingVimarDevices($connectorId,$gateways,$devices);
        $status=count($gateways)+count($devices)>0?'online':'configured';$this->pdo->prepare("UPDATE bh_vimar_edge_config SET status=?,last_test_at=NOW(),last_discovery_at=NOW(),last_endpoint=NULLIF(?,''),last_error=NULL WHERE installation_id=?")->execute([$status,$endpoint,$iid]);
        $countStmt=$this->pdo->prepare("SELECT COUNT(*) FROM bh_devices WHERE installation_id=? AND connector_instance_id=? AND status<>'disabled'");$countStmt->execute([$iid,$connectorId]);$this->pdo->prepare("UPDATE bh_connector_instances SET device_count=?,status=?,last_health_at=NOW(),endpoint_url=? WHERE id=?")->execute([(int)$countStmt->fetchColumn(),$status,$connectorEndpoint,$connectorId]);
        $types=[];foreach($devices as $d){$t=(string)($d['normalized_type']??'other');$types[$t]=($types[$t]??0)+1;}if($gateways!==[])$types['gateway']=count($gateways);
        ErrorReporter::runtimeLog('vimar_gateway_discovery',['mode'=>$mode,'endpoint'=>$endpoint,'gateways'=>count($gateways),'devices'=>count($devices),'candidates'=>count((array)($result['candidates']??[])),'types'=>$types]);
        $prefix=$mode==='direct_auto'?'Discovery directo del Edge':'Bridge VimarHub';$result['message']=$prefix.': '.count($gateways).' gateway(s) y '.count($devices).' dispositivo(s) materializado(s). No se ejecutó ningún comando físico.';return $result+['stored_external'=>$stored,'materialized'=>$materialized,'type_counts'=>$types];
    }

    public function vimarEquipmentInventory(): array
    {
        $this->assertEdge();$iid=$this->installationId();try{$def=$this->definitionId('vimar');}catch(Throwable){return [];}
        $stmt=$this->pdo->prepare("SELECT d.*,ci.name connector_name FROM bh_devices d LEFT JOIN bh_connector_instances ci ON ci.id=d.connector_instance_id WHERE d.installation_id=? AND ci.connector_definition_id=? ORDER BY d.status='online' DESC,d.device_type,d.name,d.id");$stmt->execute([$iid,$def]);$rows=$stmt->fetchAll()?:[];
        foreach($rows as &$row){$cap=json_decode((string)($row['capabilities_json']??''),true);$row['capabilities']=is_array($cap)?$cap:[];}unset($row);return $rows;
    }

    public function vimarDiscoverySummary(): array
    {
        $this->assertEdge();$rows=$this->vimarEquipmentInventory();$types=[];$online=0;foreach($rows as $r){$t=(string)($r['capabilities']['normalized_type']??$r['device_type']??'other');$types[$t]=($types[$t]??0)+1;if((string)($r['status']??'')==='online')$online++;}
        return ['total'=>count($rows),'online'=>$online,'types'=>$types,'gateways'=>(int)($types['gateway']??0),'last_discovery_at'=>(string)($this->vimarConfig()['last_discovery_at']??'')];
    }

    public function vimarSyncInventoryPayload(): array
    {
        $items=[];foreach($this->vimarEquipmentInventory() as $row){$cap=(array)($row['capabilities']??[]);$external=(string)($row['external_id']??'');if($external==='')continue;
            $items[]=['source_uuid'=>$this->stableUuid('vimar|'.$external),'connector_code'=>'vimar','device_type'=>(string)($row['device_type']??'other'),'name'=>(string)($row['name']??'Vimar'),
                'vendor'=>(string)($row['vendor']??'Vimar'),'model'=>(string)($row['model']??''),'serial_number'=>(string)($row['serial_number']??''),'ip_address'=>(string)($row['ip_address']??''),'external_id'=>$external,
                'capabilities'=>$cap,'status'=>(string)($row['status']??'unknown'),'managed_by'=>(string)($cap['source_label']??'Property Edge · Vimar')];
        }return $items;
    }

    private function testVimarHomeAssistant(array $cfg): array
    {
        $secret=EdgeSecretStore::decrypt((string)($cfg['secret_encrypted']??''));$token=trim((string)($secret['token']??''));if($token==='')throw new RuntimeException('Indique el Long-Lived Access Token de Home Assistant.');$url=rtrim((string)$cfg['base_url'],'/').'/api/states';$ch=curl_init($url);if($ch===false)throw new RuntimeException('No fue posible iniciar cURL.');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json'],CURLOPT_SSL_VERIFYPEER=>!empty($cfg['verify_tls']),CURLOPT_SSL_VERIFYHOST=>!empty($cfg['verify_tls'])?2:0]);$start=microtime(true);$body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);if($body===false||$http<200||$http>=300){$msg='Home Assistant respondió HTTP '.$http.($err!==''?' · '.$err:'');$this->pdo->prepare("UPDATE bh_vimar_edge_config SET status='error',last_test_at=NOW(),last_error=? WHERE installation_id=?")->execute([$msg,$this->installationId()]);throw new RuntimeException($msg);} $states=json_decode((string)$body,true);if(!is_array($states))throw new RuntimeException('Home Assistant no devolvió una lista JSON válida.');$entities=[];foreach($states as $st){if(!is_array($st))continue;$eid=(string)($st['entity_id']??'');if($eid==='')continue;$domain=strtok($eid,'.')?:'';if(!in_array($domain,['light','switch','cover','climate','lock','scene','sensor','binary_sensor','button'],true))continue;$entities[]=['entity_id'=>$eid,'state'=>(string)($st['state']??''),'name'=>(string)($st['attributes']['friendly_name']??$eid),'domain'=>$domain];}$lat=(int)round((microtime(true)-$start)*1000);$this->pdo->prepare("UPDATE bh_vimar_edge_config SET status='online',last_test_at=NOW(),last_error=NULL WHERE installation_id=?")->execute([$this->installationId()]);return ['ok'=>true,'mode'=>'home_assistant','latency_ms'=>$lat,'entity_count'=>count($entities),'entities'=>array_slice($entities,0,100),'message'=>'Home Assistant respondió. Esta ruta queda disponible como bridge opcional.'];
    }

    private function ensureVimarConnectorInstance(string $endpoint,string $status='configured',string $mode='direct_auto'): int
    {
        $iid=$this->installationId();$def=$this->definitionId('vimar');$stmt=$this->pdo->prepare('SELECT id FROM bh_connector_instances WHERE installation_id=? AND connector_definition_id=? ORDER BY id LIMIT 1');$stmt->execute([$iid,$def]);$id=(int)($stmt->fetchColumn()?:0);
        $direct=$mode==='direct_auto';$product=$direct?'Vimar Universal Connector':'VimarHub Local Bridge';$integration=$direct?'edge_direct':'local_bridge';$apiFamily=$direct?'Vimar LAN discovery · mDNS / KNXnet-IP / SSDP':'VimarHub local HTTP';$name=$direct?'Vimar · Directo por Property Edge':'Vimar · VimarHub Local Bridge';
        $caps=json_encode(['source'=>'property_edge','bridge'=>$direct?null:'VimarHub','local_first'=>true,'direct_discovery'=>$direct,'discovery_protocols'=>$direct?['mDNS','HomeKit HAP advertisement','KNXnet/IP Search','SSDP']:['HTTP bridge'],'normalized_types'=>['relay','switch','light','dimmer','cover','blind','shutter','thermostat','climate','scene','sensor','binary_sensor','contact','energy_meter','camera','intercom','other']],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if($id>0){$this->pdo->prepare("UPDATE bh_connector_instances SET name=?,endpoint_url=?,platform_product=?,integration_mode=?,api_family=?,authentication_mode='edge_managed',capabilities_json=?,status=? WHERE id=?")->execute([$name,$endpoint,$product,$integration,$apiFamily,$caps,$status,$id]);return $id;}
        $this->pdo->prepare("INSERT INTO bh_connector_instances (installation_id,connector_definition_id,name,target_type,manages_downstream_assets,direct_device_policy,platform_product,integration_mode,api_family,api_version,authentication_mode,endpoint_url,device_count,capabilities_json,environment,status,last_health_at) VALUES (?,?,?,'platform_server',1,'required',?,?,?,'auto','edge_managed',?,0,?,'production',?,NULL)")
            ->execute([$iid,$def,$name,$product,$integration,$apiFamily,$endpoint,$caps,$status]);return (int)$this->pdo->lastInsertId();
    }

    private function upsertVimarPhysicalDevice(int $connectorId,array $item,string $mode='direct_auto'): void
    {
        $iid=$this->installationId();$external=trim((string)($item['external_id']??''));if($external==='')return;$normalized=(string)($item['normalized_type']??'other');$deviceType=$this->vimarDeviceType($normalized);$name=trim((string)($item['name']??''))?:('Vimar '.$external);$status=$this->vimarDeviceStatus((string)($item['status']??'unknown'));$source=$mode==='direct_auto'?'vimar_direct':'vimarhub_bridge';
        $caps=['source'=>$source,'source_label'=>$mode==='direct_auto'?'Property Edge · Vimar directo':'VimarHub Local Bridge','normalized_type'=>$normalized,'raw_type'=>(string)($item['raw_type']??''),'gateway_id'=>(string)($item['gateway_id']??''),'room'=>(string)($item['room']??''),'capabilities'=>(array)($item['capabilities']??[]),'firmware_version'=>(string)($item['firmware_version']??''),'protocol'=>(string)($item['protocol']??''),'ecosystem'=>(string)($item['ecosystem']??''),'confidence'=>(string)($item['confidence']??''),'metadata'=>(array)($item['metadata']??[])];
        $q=$this->pdo->prepare('SELECT id,name FROM bh_devices WHERE installation_id=? AND connector_instance_id=? AND external_id=? ORDER BY id LIMIT 1');$q->execute([$iid,$connectorId,$external]);$existing=$q->fetch();$id=is_array($existing)?(int)$existing['id']:0;
        if($id>0){$this->pdo->prepare("UPDATE bh_devices SET device_type=?,name=?,vendor=NULLIF(?,''),model=NULLIF(?,''),serial_number=NULLIF(?,''),ip_address=NULLIF(?,''),capabilities_json=?,status=?,last_seen_at=CASE WHEN ?='online' THEN NOW() ELSE last_seen_at END,asset_stage='installed' WHERE id=?")->execute([$deviceType,$name,(string)($item['vendor']??'Vimar'),(string)($item['model']??''),(string)($item['serial_number']??''),(string)($item['ip_address']??''),json_encode($caps,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$status,$status,$id]);return;}
        $this->pdo->prepare("INSERT INTO bh_devices (installation_id,connector_instance_id,device_type,name,vendor,model,serial_number,ip_address,external_id,capabilities_json,status,last_seen_at,asset_stage) VALUES (?,?,?,?,NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),?,?,?,CASE WHEN ?='online' THEN NOW() ELSE NULL END,'installed')")
            ->execute([$iid,$connectorId,$deviceType,$name,(string)($item['vendor']??'Vimar'),(string)($item['model']??''),(string)($item['serial_number']??''),(string)($item['ip_address']??''),$external,json_encode($caps,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$status,$status]);
    }

    private function archiveMissingVimarDevices(int $connectorId,array $gateways,array $devices): void
    {
        $seen=[];foreach(array_merge($gateways,$devices) as $item)if(is_array($item)&&trim((string)($item['external_id']??''))!=='')$seen[]=trim((string)$item['external_id']);$seen=array_values(array_unique($seen));
        if($seen===[]){return;} // Never wipe an inventory because a bridge returned an unexpected empty shape.
        $marks=implode(',',array_fill(0,count($seen),'?'));$args=[$this->installationId(),$connectorId,...$seen];$this->pdo->prepare("UPDATE bh_devices SET status='disabled' WHERE installation_id=? AND connector_instance_id=? AND external_id IS NOT NULL AND external_id NOT IN ({$marks})")->execute($args);
    }

    private function vimarDeviceType(string $normalized): string
    {
        return match($normalized){'gateway'=>'gateway','relay'=>'relay','camera'=>'camera','intercom'=>'intercom','energy_meter'=>'meter','sensor','binary_sensor','contact','thermostat'=>'sensor','switch','light','dimmer','cover','blind','shutter','climate','scene'=>'iot_actuator',default=>'other'};
    }

    private function vimarDeviceStatus(string $status): string
    {
        return match(strtolower($status)){'online'=>'online','offline'=>'offline','disabled'=>'disabled','error'=>'maintenance',default=>'unknown'};
    }

    private function stableUuid(string $seed): string
    {
        $hex=substr(hash('sha256',$seed),0,32);$hex[12]='4';$variant=hexdec($hex[16]);$hex[16]=dechex(($variant&0x3)|0x8);return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20,12);
    }

    private static function uuid(): string
    {
        $d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));
    }
}
