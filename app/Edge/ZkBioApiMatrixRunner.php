<?php
declare(strict_types=1);

/**
 * Ejecuta una matriz de compatibilidad contra el manual oficial ZKBio.
 *
 * REGLA DE SEGURIDAD: el RUN automático nunca dispara endpoints clasificados
 * como escritura, acción física, financiera o riesgo desconocido. Todos ellos
 * sí forman parte del catálogo y del ZIP final, pero quedan registrados como
 * SKIPPED_* hasta que el operador realice una prueba explícita y controlada.
 */
final class ZkBioApiMatrixRunner
{
    private const SCHEMA='property-hub-zkbio-api-matrix-run-v1';
    private const MAX_RUNS=20;

    public function __construct(private PDO $pdo) {}

    private function assertReady(): void
    {
        if(!InstallationMode::isEdge())throw new RuntimeException('La matriz ZKBio solo se ejecuta en Property Edge Agent.');
        LocalIntegrationProvisioning::assertEdgeEnabled('zkteco');
    }

    public function catalog(): array
    {
        $path=base_path('resources/zkbio/zkbio_api_catalog_v1.json');
        if(!is_file($path))throw new RuntimeException('No se encontró el catálogo ZKBio derivado del manual oficial.');
        $data=json_decode((string)file_get_contents($path),true);
        if(!is_array($data)||!is_array($data['endpoints']??null))throw new RuntimeException('El catálogo ZKBio no es válido.');
        return $data;
    }

    public function catalogStats(): array
    {
        $catalog=$this->catalog();$methods=[];$risks=[];$groups=[];$variants=0;
        foreach((array)$catalog['endpoints'] as $e){$m=(string)($e['method']??'');$r=(string)($e['risk']??'unknown');$g=(string)($e['group']??'Other');$methods[$m]=($methods[$m]??0)+1;$risks[$r]=($risks[$r]??0)+1;$groups[$g]=($groups[$g]??0)+1;$variants+=max(1,count((array)($e['compatibility_variants']??[])));}
        ksort($methods);ksort($risks);ksort($groups);
        return ['endpoints'=>count((array)$catalog['endpoints']),'run_cases'=>$variants,'error_codes'=>count((array)($catalog['error_codes']??[])),'methods'=>$methods,'risks'=>$risks,'groups'=>$groups,'source'=>$catalog['source']??[]];
    }

    public function start(?int $userId=null): array
    {
        $this->assertReady();$catalog=$this->catalog();$runId=date('Ymd-His').'-'.bin2hex(random_bytes(4));$dir=$this->runDir($runId,true);
        $fixtures=$this->bootstrapFixtures();$queue=[];
        foreach(array_values((array)$catalog['endpoints']) as $index=>$endpoint){
            $variants=(array)($endpoint['compatibility_variants']??[]);if($variants===[])$variants=['manual'];
            foreach($variants as $variant)$queue[]=['endpoint_index'=>$index,'variant'=>(string)$variant];
        }
        $state=[
            'schema'=>self::SCHEMA,'run_id'=>$runId,'created_at'=>date(DATE_ATOM),'updated_at'=>date(DATE_ATOM),'created_by'=>$userId,
            'app_version'=>(string)config('app.version'),'status'=>'running','index'=>0,'total_cases'=>count($queue),'queue'=>$queue,
            'fixtures'=>$fixtures,'counts'=>['success'=>0,'error'=>0,'skipped'=>0],'last_result'=>null,'completed_at'=>null,
            'catalog_source'=>$catalog['source']??[],'safety_mode'=>'read_only_auto',
        ];
        $this->writeJson($dir.'/state.json',$state);$this->writeJson($dir.'/catalog.json',$catalog);
        $this->writeReadme($dir,$state);
        $this->cleanupOldRuns();
        return $this->publicState($state);
    }

    public function step(string $runId,?int $userId=null): array
    {
        $this->assertReady();$dir=$this->runDir($runId,false);$state=$this->loadState($dir);
        if(($state['status']??'')==='completed')return $this->publicState($state)+['done'=>true];
        if(($state['status']??'')!=='running')throw new RuntimeException('La corrida ZKBio no está activa.');
        $index=(int)($state['index']??0);$queue=(array)($state['queue']??[]);if(!isset($queue[$index])){return $this->finalize($dir,$state);}
        $catalog=$this->catalog();$task=(array)$queue[$index];$epIndex=(int)($task['endpoint_index']??-1);$endpoint=(array)($catalog['endpoints'][$epIndex]??[]);if($endpoint===[])throw new RuntimeException('La corrida contiene un endpoint inválido.');
        $variant=(string)($task['variant']??'manual');$result=$this->executeCase($endpoint,$variant,(array)($state['fixtures']??[]),$userId);
        $ordinal=$index+1;$slug=$this->slug((string)($endpoint['manual_section']??'endpoint').'-'.(string)($endpoint['path']??'api').'-'.$variant);
        $file=sprintf('%03d_%s.json',$ordinal,$slug);$this->writeJson($dir.'/responses/'.$file,$result);
        $state['fixtures']=$this->harvestFixtures((array)($state['fixtures']??[]),$endpoint,$result);
        $bucket=($result['status']??'error')==='success'?'success':(str_starts_with((string)($result['status']??''),'skipped')?'skipped':'error');$state['counts'][$bucket]=(int)($state['counts'][$bucket]??0)+1;
        $state['index']=$ordinal;$state['updated_at']=date(DATE_ATOM);$state['last_result']=['file'=>'responses/'.$file,'manual_section'=>$endpoint['manual_section']??'','path'=>$endpoint['path']??'','method'=>$endpoint['method']??'','variant'=>$variant,'status'=>$result['status']??'error','message'=>$result['message']??''];
        $this->writeJson($dir.'/state.json',$state);
        if($ordinal>=count($queue))return $this->finalize($dir,$state);
        return $this->publicState($state)+['done'=>false,'last_result'=>$state['last_result']];
    }

    public function status(string $runId): array
    {
        $this->assertReady();$state=$this->loadState($this->runDir($runId,false));return $this->publicState($state)+['done'=>($state['status']??'')==='completed'];
    }

    public function latestRuns(int $limit=8): array
    {
        if(!InstallationMode::isEdge())return [];$root=$this->rootDir();if(!is_dir($root))return [];$dirs=array_filter(glob($root.'/*')?:[],'is_dir');rsort($dirs,SORT_STRING);$out=[];
        foreach(array_slice($dirs,0,max(1,min(self::MAX_RUNS,$limit))) as $dir){try{$s=$this->loadState($dir);$out[]=$this->publicState($s);}catch(Throwable){}}
        return $out;
    }

    public function zipPath(string $runId): string
    {
        $this->assertReady();$dir=$this->runDir($runId,false);$state=$this->loadState($dir);if(($state['status']??'')!=='completed')throw new RuntimeException('La corrida todavía no ha terminado.');$zip=$dir.'/ZKBIO_API_MATRIX_'.$runId.'.zip';if(!is_file($zip))$this->buildZip($dir,$zip);return $zip;
    }

    private function executeCase(array $endpoint,string $variant,array $fixtures,?int $userId): array
    {
        $base=['manual_section'=>(string)($endpoint['manual_section']??''),'group'=>(string)($endpoint['group']??''),'title'=>(string)($endpoint['title']??''),'method'=>(string)($endpoint['method']??''),'path'=>(string)($endpoint['path']??''),'risk'=>(string)($endpoint['risk']??'unknown'),'variant'=>$variant,'manual_url'=>(string)($endpoint['manual_url']??''),'documented_request'=>(string)($endpoint['request_content_text']??$endpoint['request_content_excerpt']??''),'documented_request_parameters'=>(string)($endpoint['request_parameter_description']??''),'documented_response'=>(string)($endpoint['response_result_text']??''),'documented_response_description'=>(string)($endpoint['response_result_description']??''),'observed_compatibility'=>(array)($endpoint['observed_compatibility']??[]),'started_at'=>date(DATE_ATOM)];
        if(!$this->autoSafe($endpoint))return $base+['status'=>$this->skipStatus($endpoint),'message'=>'Incluido en el catálogo, pero el RUN automático no ejecuta escrituras, acciones físicas/financieras ni riesgo no confirmado.'];
        try{$request=$this->buildRequest($endpoint,$variant,$fixtures);}catch(Throwable $e){return $base+['status'=>'skipped_missing_fixture','message'=>$e->getMessage()];}
        if(!empty($request['skip']))return $base+['status'=>'skipped_missing_fixture','message'=>(string)($request['message']??'Faltan parámetros de prueba.'),'request'=>$request];
        $started=microtime(true);
        try{
            $lab=new EdgeConnectorLab($this->pdo);$r=$lab->runZkBioDiagnosticRequest((string)$endpoint['method'],(string)$request['path'],(array)$request['query'],$request['body'],'matrix_runner',$userId,(string)($endpoint['manual_section']??'').':'.$variant);
            $status=!empty($r['ok'])?'success':'error';$message=!empty($r['ok'])?'Respuesta recibida.':(string)($r['error']??'ZKBio rechazó la operación.');
            $classification=$this->classifyResult($status,$r['response']??null,$r['vendor_code']??null,$r['http_status']??null,$message);
            return $base+['status'=>$status,'classification'=>$classification,'message'=>$message,'request'=>$r['request']??$request,'response'=>$r['response']??null,'vendor_code'=>$r['vendor_code']??null,'http_status'=>$r['http_status']??null,'latency_ms'=>$r['latency_ms']??(int)round((microtime(true)-$started)*1000),'completed_at'=>date(DATE_ATOM)];
        }catch(Throwable $e){return $base+['status'=>'error','classification'=>$this->classifyResult('error',null,null,null,$e->getMessage()),'message'=>$e->getMessage(),'request'=>$request,'latency_ms'=>(int)round((microtime(true)-$started)*1000),'completed_at'=>date(DATE_ATOM)];}
    }

    private function autoSafe(array $e): bool
    {
        $risk=(string)($e['risk']??'unknown');$method=strtoupper((string)($e['method']??''));$path=strtolower((string)($e['path']??''));
        if($risk!=='read_safe')return false;
        if($method==='GET')return !str_contains($path,'setpushsetting');
        if($method==='POST'){
            // La tabla de parking "addAllowDisabled" figura como lectura en una edición del
            // manual, pero el propio nombre indica modificación: nunca se autoejecuta.
            if(str_contains($path,'addallowdisabled'))return false;
            return true;
        }
        return false;
    }

    private function skipStatus(array $e): string
    {
        return match((string)($e['risk']??'unknown')){
            'mutating'=>'skipped_mutating','physical_action'=>'skipped_physical_action','financial_action'=>'skipped_financial_action',default=>'skipped_unknown_risk'
        };
    }

    private function buildRequest(array $e,string $variant,array $fixtures): array
    {
        $path=(string)($e['path']??'');$queryTemplate=(string)($e['query_template']??'');$body=$e['request_body_template']??null;$forcedQuery=null;
        if(in_array($path,['/api/person/getPersonList','/api/v2/person/getPersonList'],true)){
            $params=['pins'=>(string)($fixtures['pin']??''),'deptCodes'=>(string)($fixtures['deptCode']??''),'pageNo'=>1,'pageSize'=>20];
            if($variant==='query_only'){$forcedQuery=$params;$queryTemplate='';$body=null;}else{$queryTemplate='';$body=$params;}
        }
        if(in_array($path,['/api/transaction/getDoorTransactions','/api/v2/transaction/getDoorTransactions'],true)&&!is_array($body)){
            $body=['devSn'=>(string)($fixtures['accDeviceSn']??$fixtures['deviceSn']??$fixtures['sn']??''),'startTime'=>(string)($fixtures['startTime']??''),'endTime'=>(string)($fixtures['endTime']??''),'filter'=>'','type'=>'normal','eventNo'=>'0','personPin'=>(string)($fixtures['personPin']??$fixtures['pin']??''),'pageNo'=>'1','pageSize'=>'20'];
        }
        if($path==='/api/transaction/getDoorTransactionDetail'&&!is_array($body))$body=['id'=>(string)($fixtures['transactionId']??'')];
        if($path==='/api/visRegistration/getQrCode/{pin}'&&!is_array($body))$body=['pin'=>(string)($fixtures['visitorPin']??$fixtures['pin']??'')];
        if($path==='/api/v2/visRegistration/getTransactionList'&&$queryTemplate==='')$forcedQuery=['certNum'=>(string)($fixtures['visitorCertNum']??''),'phone'=>(string)($fixtures['visitorPhone']??''),'pageNo'=>1,'pageSize'=>20];
        if($path==='/api/v2/parkPlate/getFixedVehicle'&&!is_array($body)){if(empty($fixtures['carNumber']))return ['skip'=>true,'message'=>'No se obtuvo una matrícula real para probar esta consulta de parking.'];$body=['carNumber'=>(string)$fixtures['carNumber'],'pin'=>(string)($fixtures['pin']??'')];}
        if($path==='/api/spaceReservation/getReservationParticipants'&&!is_array($body)){if(empty($fixtures['spaceReservationId']))return ['skip'=>true,'message'=>'No se obtuvo un reservationId real para probar participantes.'];$body=['id'=>(string)$fixtures['spaceReservationId']];}
        if($path==='/api/v2/person/detectFace'&&!is_array($body))return ['skip'=>true,'message'=>'El endpoint requiere una fotografía Base64; el runner seguro no inventa ni reutiliza fotos de personas.'];
        if(strtoupper((string)$e['method'])==='POST'&&$queryTemplate===''&&$body===null&&$forcedQuery===null&&!in_array($path,['/api/department/getDepartmentList'],true))return ['skip'=>true,'message'=>'El manual exige contenido POST que no puede construirse automáticamente sin datos de negocio reales. La definición queda incluida para prueba manual.'];

        $missing=[];$path=$this->replacePlaceholders($path,$fixtures,$e,$missing,false);$queryTemplate=$this->replacePlaceholders($queryTemplate,$fixtures,$e,$missing,true);$body=$this->replaceBody($body,$fixtures,$e,$missing);
        if($missing!==[])return ['skip'=>true,'message'=>'Faltan fixtures: '.implode(', ',array_values(array_unique($missing))).'.','missing'=>$missing,'path'=>$path,'query_template'=>$queryTemplate,'body'=>$body];
        $query=[];if(is_array($forcedQuery))$query=$forcedQuery;elseif(trim($queryTemplate)!==''){parse_str(ltrim($queryTemplate,'?'),$query);unset($query['access_token'],$query['accessToken'],$query['acc_token'],$query['token']);}
        return ['path'=>$path,'query'=>$query,'body'=>is_array($body)?$body:null];
    }

    private function replacePlaceholders(string $text,array $fixtures,array $e,array &$missing,bool $allowOptional): string
    {
        if($text==='')return '';
        $text=preg_replace_callback('/\{([^{}]+)\}|\[([^\[\]]+)\]/',function(array $m) use($fixtures,$e,&$missing,$allowOptional){$key=trim((string)($m[1]!==''?$m[1]:$m[2]));$value=$this->fixture($key,$fixtures,$e);if($value===null){if($allowOptional&&$this->optionalFixture($key))return '';$missing[]=$key;return '';}return rawurlencode((string)$value);},$text)??$text;
        return $text;
    }

    private function replaceBody(mixed $body,array $fixtures,array $e,array &$missing): mixed
    {
        if(!is_array($body))return $body;$out=[];foreach($body as $k=>$v){if(is_array($v)){$out[$k]=$this->replaceBody($v,$fixtures,$e,$missing);continue;}if(is_string($v)){$matches=[];if(preg_match('/^\{([^{}]+)\}$/',$v,$matches)){$fv=$this->fixture($matches[1],$fixtures,$e);if($fv===null){if($this->optionalFixture($matches[1]))$fv='';else{$missing[]=$matches[1];$fv='';}}$out[$k]=$fv;continue;}}$out[$k]=$v;}return $out;
    }

    private function optionalFixture(string $key): bool
    {
        $k=strtolower(preg_replace('/[^a-z0-9]/i','',$key)??'');return in_array($k,['personpin','startdate','enddate','starttime','endtime','fromtime','totime','name','names','filter','subject','spacename','phone','certnumbers','deptcode','type','eventno','alarmcodes','partitionname','pointname','devicename'],true);
    }

    private function fixture(string $key,array $f,array $e): mixed
    {
        $raw=$key;$k=strtolower(preg_replace('/[^a-z0-9]/i','',$key)??'');$path=strtolower((string)($e['path']??''));$group=strtolower((string)($e['group']??''));
        $map=['pageno'=>1,'pagesize'=>20,'pin'=>$f['pin']??null,'pin1pin2'=>$f['pin']??null,'personpin'=>$f['personPin']??$f['pin']??null,'deptcode'=>$f['deptCode']??null,'code'=>$f['deptCode']??$f['code']??null,'sn'=>(str_contains($path,'psg')?($f['psgDeviceSn']??null):($f['accDeviceSn']??$f['sn']??null)),'devicesn'=>(str_contains($path,'psg')?($f['psgDeviceSn']??null):($f['accDeviceSn']??$f['deviceSn']??$f['sn']??null)),'doorid'=>$f['accDoorId']??$f['doorId']??null,'doorname'=>$f['accDoorName']??$f['doorName']??null,'levelid'=>$f['levelId']??null,'levelids'=>$f['levelIds']??$f['levelId']??null,'timestamp'=>$f['timestamp']??((int)round(microtime(true)*1000)),'interval'=>3,'startdate'=>$f['startDate']??null,'enddate'=>$f['endDate']??null,'starttime'=>$f['startTime']??null,'endtime'=>$f['endTime']??null,'fromtime'=>$f['startTime']??null,'totime'=>$f['endTime']??null,'carNumber'=>$f['carNumber']??null,'carnumber'=>$f['carNumber']??null,'name'=>$f['name']??null,'certtype'=>$f['certType']??null,'certnum'=>$f['certNum']??null,'certnumber'=>$f['certNum']??null,'certnumbers'=>$f['certNum']??null,'phone'=>$f['phone']??null,'floorid'=>$f['floorId']??null,'gateid'=>$f['gateId']??null,'gatename'=>$f['gateName']??null,'opentype'=>1,'spaceName'=>$f['spaceName']??null,'spacename'=>$f['spaceName']??null,'subject'=>'','type'=>'','alarmcodes'=>'','partitionname'=>'','pointname'=>'','devicename'=>''];
        if(str_contains($path,'biotemplate')&&$k==='pin')return $f['bioPin']??$f['pin']??null;
        if($k==='id'){
            if(str_contains($path,'transaction'))return $f['transactionId']??null;
            if(str_contains($path,'psgreader'))return $f['psgReaderId']??null;
            if(str_contains($path,'reader'))return $f['accReaderId']??$f['readerId']??null;
            if(str_contains($path,'acclevel')||str_contains($path,'level'))return $f['levelId']??null;
            if(str_contains($path,'door'))return $f['accDoorId']??$f['doorId']??null;
            if(str_contains($path,'spacefacility'))return $f['spaceFacilityId']??null;
            if(str_contains($path,'spaceservice'))return $f['spaceServiceId']??null;
            if(str_contains($path,'spacereservation'))return $f['spaceReservationId']??null;
            if(str_contains($path,'space'))return $f['spaceId']??null;
            if(str_contains($path,'psggate'))return $f['psgGateId']??$f['gateId']??null;
            return $f['id']??null;
        }
        return array_key_exists($k,$map)?$map[$k]:($f[$raw]??$f[$k]??null);
    }

    private function bootstrapFixtures(): array
    {
        $now=time();$f=['pageNo'=>1,'pageSize'=>20,'timestamp'=>(int)round(microtime(true)*1000),'startDate'=>date('Y-m-d H:i:s',$now-86400),'endDate'=>date('Y-m-d H:i:s',$now),'startTime'=>date('Y-m-d H:i:s',$now-86400),'endTime'=>date('Y-m-d H:i:s',$now)];$lab=new EdgeConnectorLab($this->pdo);
        $calls=[
            ['GET','/api/device/accList',['pageNo'=>1,'pageSize'=>50]],['GET','/api/v2/door/list',['pageNo'=>1,'pageSize'=>100]],['GET','/api/v2/reader/list',['pageNo'=>1,'pageSize'=>200]],
            ['POST','/api/department/getDepartmentList',['pageNo'=>1,'pageSize'=>200]],['GET','/api/v2/accLevel/list',['pageNo'=>1,'pageSize'=>100]],['GET','/api/v2/transaction/list',['pageNo'=>1,'pageSize'=>100]],
            ['GET','/api/psgDevice/list',['pageNo'=>1,'pageSize'=>50]],['GET','/api/v2/psgGate/list',['pageNo'=>1,'pageSize'=>100]],['GET','/api/v2/psgReader/list',['pageNo'=>1,'pageSize'=>100]],
            ['GET','/api/v2/insAreaPerson/getAreaList',['pageNo'=>1,'pageSize'=>100]],['GET','/api/v2/parkBase/getParkingAreaList',['pageNo'=>1,'pageSize'=>100]],['GET','/api/v2/parkBase/getParkingEntranceList',['pageNo'=>1,'pageSize'=>100]],
            ['GET','/api/floor/list',['pageNo'=>1,'pageSize'=>100]],['GET','/api/space/list',['pageNo'=>1,'pageSize'=>100]],
        ];
        foreach($calls as [$method,$path,$query]){try{$r=$lab->runZkBioDiagnosticRequest($method,$path,$query,null,'matrix_fixture',null,'fixture');$f=$this->harvestFixtures($f,['path'=>$path,'group'=>'fixture'],['status'=>!empty($r['ok'])?'success':'error','response'=>$r['response']??null]);}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'zkbio_matrix_fixture','path'=>$path]);}}
        // Preferir una persona que realmente haya usado huella en una transacción.
        try{$r=$lab->runZkBioDiagnosticRequest('GET','/api/v2/transaction/list',['pageNo'=>1,'pageSize'=>300],null,'matrix_fixture',null,'fixture-biometric-pin');if(!empty($r['ok'])){foreach($this->rows((array)($r['response']??[])) as $row){$mode=mb_strtolower((string)($row['verifyModeName']??''));if((str_contains($mode,'huella')||str_contains($mode,'finger'))&&!empty($row['pin'])){$f['bioPin']=(string)$row['pin'];break;}}}}catch(Throwable $e){}
        $personPin=(string)($f['bioPin']??$f['pin']??'');if($personPin!==''){try{$r=$lab->runZkBioDiagnosticRequest('GET','/api/person/get/'.rawurlencode($personPin),[],null,'matrix_fixture',null,'fixture-person');$f=$this->harvestFixtures($f,['path'=>'/api/person/get/{pin}','group'=>'Personnel'],['status'=>!empty($r['ok'])?'success':'error','response'=>$r['response']??null]);$f['bioPin']=$personPin;}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'zkbio_matrix_fixture_person']);}}
        return $f;
    }

    private function harvestFixtures(array $f,array $endpoint,array $result): array
    {
        if(($result['status']??'')!=='success')return $f;$resp=(array)($result['response']??[]);$rows=$this->rows($resp);$path=strtolower((string)($endpoint['path']??''));
        if($rows===[]&&is_array($resp['data']??null)&&!array_is_list((array)$resp['data']))$rows=[(array)$resp['data']];
        $first=$rows[0]??[];
        if(str_contains($path,'psgdevice')){$f['psgDeviceSn']=$first['sn']??$f['psgDeviceSn']??null;$f['psgDeviceId']=$first['id']??$f['psgDeviceId']??null;}elseif(str_contains($path,'device')&&!str_contains($path,'transaction')){$f['sn']=$first['sn']??$f['sn']??null;$f['deviceSn']=$first['sn']??$f['deviceSn']??null;$f['accDeviceSn']=$first['sn']??$f['accDeviceSn']??null;$f['id']=$first['id']??$f['id']??null;}
        if(str_contains($path,'door/list')){$f['doorId']=$first['id']??$f['doorId']??null;$f['doorName']=$first['name']??$f['doorName']??null;$f['accDoorId']=$first['id']??$f['accDoorId']??null;$f['accDoorName']=$first['name']??$f['accDoorName']??null;}
        if(str_contains($path,'psgreader')){$f['psgReaderId']=$first['id']??$f['psgReaderId']??null;}elseif(str_contains($path,'reader/list')||str_contains($path,'reader/acclist')){$f['readerId']=$first['id']??$f['readerId']??null;$f['accReaderId']=$first['id']??$f['accReaderId']??null;}
        if(str_contains($path,'department')){foreach($rows as $r){$code=trim((string)($r['code']??''));if($code!==''&&trim((string)($r['parentCode']??''))!==''){$f['deptCode']=$code;$f['code']=$code;break;}}}
        if(str_contains($path,'acclevel/list')){$f['levelId']=$first['id']??$f['levelId']??null;$f['levelIds']=$first['id']??$f['levelIds']??null;$f['name']=$first['name']??$f['name']??null;}
        if(str_contains($path,'transaction')){foreach($rows as $r){if(empty($f['transactionId'])&&!empty($r['id']))$f['transactionId']=$r['id'];if(empty($f['pin'])&&!empty($r['pin'])){$f['pin']=$r['pin'];$f['personPin']=$r['pin'];}if(empty($f['cardNo'])&&!empty($r['cardNo']))$f['cardNo']=$r['cardNo'];if(empty($f['deviceSn'])&&!empty($r['devSn']))$f['deviceSn']=$r['devSn'];}}
        if(str_contains($path,'person/get')){$f['pin']=$first['pin']??$f['pin']??null;$f['personPin']=$first['pin']??$f['personPin']??null;$f['deptCode']=$first['deptCode']??$f['deptCode']??null;$f['levelIds']=$first['accLevelIds']??$f['levelIds']??null;if(!empty($first['accLevelIds']))$f['levelId']=explode(',',(string)$first['accLevelIds'])[0];if(!empty($first['carPlate']))$f['carNumber']=explode(',',(string)$first['carPlate'])[0];if(!empty($first['mobilePhone']))$f['phone']=$first['mobilePhone'];if(!empty($first['certType']))$f['certType']=$first['certType'];if(!empty($first['certNumber']))$f['certNum']=$first['certNumber'];}
        if(str_contains($path,'floor/list'))$f['floorId']=$first['id']??$f['floorId']??null;
        if(str_contains($path,'psggate/list')){$f['gateId']=$first['id']??$f['gateId']??null;$f['gateName']=$first['name']??$f['gateName']??null;$f['psgGateId']=$first['id']??$f['psgGateId']??null;$f['psgGateName']=$first['name']??$f['psgGateName']??null;}
        if(str_contains($path,'spacefacility/list'))$f['spaceFacilityId']=$first['id']??$f['spaceFacilityId']??null;
        if(str_contains($path,'spaceservice/list'))$f['spaceServiceId']=$first['id']??$f['spaceServiceId']??null;
        if(str_contains($path,'spacereservation/list'))$f['spaceReservationId']=$first['id']??$f['spaceReservationId']??null;
        if(preg_match('#/api/space/list$#',$path)){$f['spaceId']=$first['id']??$f['spaceId']??null;$f['spaceName']=$first['name']??$f['spaceName']??null;}
        return $f;
    }

    private function rows(array $response): array
    {
        $d=$response['data']??[];if(isset($d['data'])&&is_array($d['data']))return array_values(array_filter($d['data'],'is_array'));return is_array($d)&&array_is_list($d)?array_values(array_filter($d,'is_array')):[];
    }

    private function classifyResult(string $status,mixed $response,mixed $vendorCode,mixed $httpStatus,string $message): string
    {
        if($status==='success'){
            $r=is_array($response)?$response:[];$d=$r['data']??null;
            if($d===null)return 'WORKING_EMPTY';
            if(is_array($d)&&$d===[])return 'WORKING_EMPTY';
            if(is_array($d)&&isset($d['data'])&&is_array($d['data'])&&$d['data']===[])return 'WORKING_EMPTY';
            return 'WORKING';
        }
        $msg=mb_strtolower($message);$code=(string)$vendorCode;$http=(int)$httpStatus;
        if($http===404||str_contains($msg,'http 404')||str_contains($msg,'not found'))return 'ROUTE_NOT_FOUND';
        if($http===405||str_contains($msg,'http 405'))return 'CONTRACT_VARIANT';
        if(in_array($code,['-47','-52','-102','-257','-264'],true))return 'BAD_FIXTURE';
        if($code==='400'||str_contains($msg,'operación fallida')||str_contains($msg,'operation failed'))return 'CONTRACT_OR_FEATURE_REJECTED';
        return 'UNKNOWN';
    }

    private function finalize(string $dir,array $state): array
    {
        $state['status']='completed';$state['completed_at']=date(DATE_ATOM);$state['updated_at']=$state['completed_at'];$this->writeJson($dir.'/state.json',$state);
        $summary=$this->buildSummary($dir,$state);$this->writeJson($dir.'/summary.json',$summary);$this->writeCsv($dir.'/summary.csv',$summary['results']);$zip=$dir.'/ZKBIO_API_MATRIX_'.$state['run_id'].'.zip';$this->buildZip($dir,$zip);$state['zip_name']=basename($zip);$this->writeJson($dir.'/state.json',$state);
        return $this->publicState($state)+['done'=>true,'download_route'=>'edge-zkbio-matrix-download&run_id='.rawurlencode((string)$state['run_id'])];
    }

    private function buildSummary(string $dir,array $state): array
    {
        $rows=[];foreach(glob($dir.'/responses/*.json')?:[] as $file){$r=json_decode((string)file_get_contents($file),true);if(!is_array($r))continue;$rows[]=['file'=>'responses/'.basename($file),'manual_section'=>$r['manual_section']??'','group'=>$r['group']??'','title'=>$r['title']??'','method'=>$r['method']??'','path'=>$r['path']??'','variant'=>$r['variant']??'','risk'=>$r['risk']??'','status'=>$r['status']??'','classification'=>$r['classification']??'','http_status'=>$r['http_status']??'','vendor_code'=>$r['vendor_code']??'','latency_ms'=>$r['latency_ms']??'','message'=>$r['message']??''];}
        return ['schema'=>self::SCHEMA,'run_id'=>$state['run_id'],'created_at'=>$state['created_at'],'completed_at'=>$state['completed_at'],'app_version'=>$state['app_version'],'catalog_source'=>$state['catalog_source'],'counts'=>$state['counts'],'fixtures'=>$this->safeFixtures((array)$state['fixtures']),'results'=>$rows,'notes'=>['El access_token no se exporta.','Passwords/hashes y fotos/base64 se redactan antes de persistir respuestas.','Endpoints mutantes, físicos, financieros o de riesgo desconocido se incluyen en catálogo pero no se autoejecutan.']];
    }

    private function safeFixtures(array $f): array
    {
        foreach(['password','personPwd','selfPwd','access_token','token','secret'] as $k)unset($f[$k]);return $f;
    }

    private function writeCsv(string $path,array $rows): void
    {
        $fh=fopen($path,'wb');if(!$fh)return;$headers=['manual_section','group','title','method','path','variant','risk','status','classification','http_status','vendor_code','latency_ms','message','file'];fputcsv($fh,$headers);
        foreach($rows as $r){$line=[];foreach($headers as $h)$line[]=$r[$h]??'';fputcsv($fh,$line);}fclose($fh);
    }

    private function writeReadme(string $dir,array $state): void
    {
        $txt="# ZKBio API Matrix - Property Hub\n\n".
             "Run: `{$state['run_id']}`\n\nVersión Property Hub: `{$state['app_version']}`\n\n".
             "Fuente: ZKBio CVSecurity 3rd Party API User Manual v1.2 (November 2025), CVSecurity 6.0.0+.\n\n".
             "## Seguridad\n\nEl runner automático es de solo lectura. Todos los endpoints del manual están catalogados, pero escrituras, aperturas/cierres físicos, operaciones financieras y riesgos no confirmados se registran como SKIPPED y no se envían. El access_token nunca se exporta. Passwords/hashes y blobs de foto/base64 se redactan.\n\n".
             "## Archivos\n\n- `catalog.json`: catálogo completo del manual.\n- `state.json`: progreso y fixtures técnicos.\n- `responses/`: un JSON por endpoint/variante.\n- `summary.json` y `summary.csv`: resultado consolidado.\n";
        file_put_contents($dir.'/README.md',$txt);
    }

    private function buildZip(string $dir,string $zipPath): void
    {
        if(!class_exists('ZipArchive'))throw new RuntimeException('ZipArchive no está disponible para exportar el diagnóstico.');$zip=new ZipArchive();if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No se pudo crear el ZIP de diagnóstico.');
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));foreach($it as $file){if(!$file->isFile())continue;$path=$file->getPathname();if($path===$zipPath)continue;$rel=substr($path,strlen($dir)+1);$zip->addFile($path,$rel);}if(!$zip->close())throw new RuntimeException('No se pudo cerrar el ZIP de diagnóstico.');
    }

    private function publicState(array $s): array
    {
        return ['run_id'=>(string)($s['run_id']??''),'status'=>(string)($s['status']??''),'index'=>(int)($s['index']??0),'total_cases'=>(int)($s['total_cases']??0),'counts'=>(array)($s['counts']??[]),'created_at'=>$s['created_at']??null,'completed_at'=>$s['completed_at']??null,'last_result'=>$s['last_result']??null,'zip_name'=>$s['zip_name']??null];
    }

    private function loadState(string $dir): array
    {
        $path=$dir.'/state.json';if(!is_file($path))throw new RuntimeException('Corrida ZKBio no encontrada.');$state=json_decode((string)file_get_contents($path),true);if(!is_array($state)||($state['schema']??'')!==self::SCHEMA)throw new RuntimeException('Estado de corrida ZKBio inválido.');return $state;
    }

    private function rootDir(): string{return base_path('storage/diagnostics/zkbio-api-matrix');}
    private function runDir(string $runId,bool $create): string
    {
        if(!preg_match('/^[0-9]{8}-[0-9]{6}-[a-f0-9]{8}$/',$runId))throw new InvalidArgumentException('Identificador de corrida inválido.');$root=$this->rootDir();$dir=$root.'/'.$runId;
        if($create){foreach([$root,$dir,$dir.'/responses'] as $p)if(!is_dir($p)&&!@mkdir($p,0775,true)&&!is_dir($p))throw new RuntimeException('No se pudo crear el directorio de diagnóstico ZKBio.');}
        elseif(!is_dir($dir))throw new RuntimeException('Corrida ZKBio no encontrada.');return $dir;
    }
    private function writeJson(string $path,array $data): void{$tmp=$path.'.tmp';$json=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($json===false||file_put_contents($tmp,$json)===false)throw new RuntimeException('No se pudo guardar el diagnóstico ZKBio.');@rename($tmp,$path);}
    private function slug(string $s): string{$s=strtolower($s);$s=preg_replace('/[^a-z0-9]+/','-',$s)??'endpoint';return trim(substr($s,0,120),'-')?:'endpoint';}
    private function cleanupOldRuns(): void
    {
        $root=$this->rootDir();$dirs=array_filter(glob($root.'/*')?:[],'is_dir');rsort($dirs,SORT_STRING);foreach(array_slice($dirs,self::MAX_RUNS) as $dir)$this->removeTree($dir);
    }
    private function removeTree(string $dir): void{if(!is_dir($dir))return;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($dir);}
}
