<?php
declare(strict_types=1);

/**
 * Creates and reads the portable .propertyhub-deploy file.
 *
 * The same file can be used by the Property Node installer and by every Edge
 * Agent declared for the property. It contains one-use enrollment credentials,
 * never permanent installation/API credentials. Central signs the manifest so
 * accidental or partial edits are detected before an installer consumes it.
 */
final class PropertyDeploymentFileService
{
    public const FILE_SCHEMA = '2cam-property-deployment-file-v1';
    public const MANIFEST_SCHEMA = '2cam-property-deployment-v1';
    public const MAX_FILE_BYTES = 2_000_000;

    public function __construct(private ?PDO $pdo=null)
    {
        $this->pdo ??= Connection::pdo();
    }

    /** @return array{filename:string,content:string,bundle:array<string,mixed>} */
    public function issue(string $propertyCode,?int $userId=null,bool $ensureDefaultEdge=true): array
    {
        if(!InstallationMode::isCentral())throw new RuntimeException('El archivo de despliegue solo puede emitirse desde Property Hub Central.');
        $repository=new RepositoryService($this->pdo);$property=$repository->propertyDetail($propertyCode);
        $code=strtoupper(trim((string)$property['code']));
        $node=is_array($property['node']??null)?(array)$property['node']:null;
        $plan=is_array($property['plan']??null)?(array)$property['plan']:null;
        if($node===null&&$plan===null)throw new RuntimeException('La propiedad no tiene expediente de despliegue.');

        // Plaintext tokens are intentionally not recoverable from the database. A
        // deployment file therefore rotates/creates fresh one-use credentials.
        if($node!==null){
            $nodeToken=$repository->createRelinkToken((int)$node['id'],$userId);
            $nodePurpose='relink';
        }else{
            $planId=(int)($plan['id']??0);if($planId<1)throw new RuntimeException('El expediente de la propiedad no tiene un plan válido.');
            $nodeToken=$repository->regeneratePlanToken($planId,$userId);
            $nodePurpose='initial_registration';
        }

        $property=$repository->propertyDetail($code);
        $deployments=(array)($property['edge_deployments']??[]);$edgeCredentials=[];
        if($deployments===[]&&$ensureDefaultEdge){
            $edgeCredentials[]=$repository->createEdgeAgentRegistrationTokenForProperty($code,'',$userId,'Edge Principal');
        }else{
            foreach($deployments as $deployment){
                if(!is_array($deployment))continue;
                // Never disturb an Edge that is already claimed/operational simply
                // because somebody downloaded a deployment file. Reinstallation is
                // explicit: regenerate that Edge from Central first, then download.
                if((string)($deployment['status']??'')==='claimed')continue;
                $edgeCredentials[]=$repository->createEdgeAgentRegistrationTokenForProperty(
                    $code,(string)($deployment['edge_code']??''),$userId,(string)($deployment['edge_name']??'')
                );
            }
        }

        $property=$repository->propertyDetail($code); // refresh canonical URLs/name
        $centralUrl=rtrim((string)$property['central_url'],'/');
        $nodeUrl=rtrim((string)($property['node_url']??''),'/');
        if($nodeUrl==='')$nodeUrl=rtrim((string)($plan['planned_base_url']??''),'/');
        $publicKeyPath=base_path((string)config('deployment.repository_public_key_path'));
        $publicKey=(string)@file_get_contents($publicKeyPath);
        if(trim($publicKey)==='')throw new RuntimeException('Central no tiene disponible la clave pública para firmar el archivo de despliegue.');
        $fingerprint=self::publicKeyFingerprint($publicKey);

        $edges=[];$expiryCandidates=[];
        foreach($edgeCredentials as $credential){
            $expires=(string)($credential['expires_at']??'');if($expires!=='')$expiryCandidates[]=$expires;
            $edges[]=[
                'code'=>(string)$credential['agent_code'],
                'name'=>(string)$credential['agent_name'],
                'property_code'=>$code,
                'parent_node_code'=>(string)($property['node']['installation_code']??$code),
                'node_url'=>rtrim((string)($credential['property_node_url']??$nodeUrl),'/'),
                'enrollment'=>[
                    'token'=>(string)$credential['token'],
                    'expires_at'=>$expires,
                    'one_use'=>true,
                ],
            ];
        }
        $nodeExpires=(string)($nodeToken['expires_at']??'');if($nodeExpires!=='')$expiryCandidates[]=$nodeExpires;
        $bundleExpires=self::earliestExpiry($expiryCandidates) ?: date('Y-m-d H:i:s',time()+86400);
        $manifest=[
            'schema'=>self::MANIFEST_SCHEMA,
            'bundle_id'=>self::uuid(),
            'issued_at'=>date(DATE_ATOM),
            'expires_at'=>date(DATE_ATOM,strtotime($bundleExpires)),
            'property'=>[
                'code'=>$code,
                'name'=>(string)$property['name'],
                'installation_type'=>(string)$property['installation_type'],
                'environment_type'=>(string)$property['environment_type'],
                'update_channel'=>(string)$property['update_channel'],
            ],
            'central'=>[
                'url'=>$centralUrl,
                'public_key_fingerprint_sha256'=>$fingerprint,
            ],
            'node'=>[
                'code'=>$code,
                'name'=>(string)$property['name'],
                'public_url'=>$nodeUrl,
                'enrollment'=>[
                    'purpose'=>$nodePurpose,
                    'token'=>(string)($nodeToken['registration_token']??''),
                    'expires_at'=>$nodeExpires,
                    'one_use'=>true,
                ],
            ],
            'edges'=>$edges,
            'distribution_chain'=>'central>node>edge',
        ];
        $signed=ManifestSigner::sign($manifest);
        $file=[
            'schema'=>self::FILE_SCHEMA,
            'manifest'=>$signed['manifest'],
            'signature'=>$signed['signature'],
            'algorithm'=>$signed['algorithm'],
            'canonical_sha256'=>$signed['canonical_sha256'],
            'repository_public_key'=>$publicKey,
            'repository_public_key_fingerprint_sha256'=>$fingerprint,
        ];
        $content=json_encode($file,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
        $filename=preg_replace('/[^A-Z0-9_-]+/i','-',trim($code))?:'PROPERTY';$filename.='.propertyhub-deploy';

        if($node!==null){
            try{
                $stmt=$this->pdo->prepare("INSERT INTO bh_repo_delivery_logs (installation_id,event_type,status,remote_ip,details_json) VALUES (?,'deployment_file_issued','ok',?,?)");
                $stmt->execute([(int)$node['id'],(string)($_SERVER['REMOTE_ADDR']??''),json_encode(['bundle_id'=>$manifest['bundle_id'],'expires_at'=>$manifest['expires_at'],'edge_count'=>count($edges),'node_token_purpose'=>$nodePurpose,'created_by'=>$userId],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
            }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'deployment_file_issue_log','property_code'=>$code]);}
        }
        return ['filename'=>$filename,'content'=>$content,'bundle'=>$file];
    }

    /** @return array<string,mixed> */
    public static function readFile(string $path): array
    {
        if(!is_file($path))throw new InvalidArgumentException('No se encontró el archivo de despliegue.');
        $size=(int)filesize($path);if($size<20||$size>self::MAX_FILE_BYTES)throw new InvalidArgumentException('El archivo de despliegue tiene un tamaño no válido.');
        $raw=@file_get_contents($path);if(!is_string($raw))throw new RuntimeException('No fue posible leer el archivo de despliegue.');
        return self::parse($raw);
    }

    /** @return array<string,mixed> */
    public static function parse(string $raw): array
    {
        if(strlen($raw)>self::MAX_FILE_BYTES)throw new InvalidArgumentException('El archivo de despliegue excede el tamaño permitido.');
        try{$file=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new InvalidArgumentException('El archivo de despliegue no contiene JSON válido.',0,$e);}
        if(!is_array($file)||!hash_equals(self::FILE_SCHEMA,(string)($file['schema']??'')))throw new InvalidArgumentException('El archivo no es un despliegue Property Hub compatible.');
        $manifest=$file['manifest']??null;if(!is_array($manifest)||!hash_equals(self::MANIFEST_SCHEMA,(string)($manifest['schema']??'')))throw new InvalidArgumentException('El manifiesto de despliegue no es compatible.');
        $publicKey=trim((string)($file['repository_public_key']??''));$signature=base64_decode((string)($file['signature']??''),true);
        if($publicKey===''||$signature===false||$signature==='')throw new InvalidArgumentException('El archivo de despliegue no contiene una firma completa.');
        if(!hash_equals('RSA-SHA256',(string)($file['algorithm']??'')))throw new InvalidArgumentException('Algoritmo de firma de despliegue no soportado.');
        $canonical=ManifestSigner::canonicalJson($manifest);$hash=hash('sha256',$canonical);
        if(!hash_equals(strtolower((string)($file['canonical_sha256']??'')),strtolower($hash)))throw new RuntimeException('El contenido del archivo de despliegue fue alterado o está incompleto.');
        $ok=openssl_verify($canonical,$signature,$publicKey,OPENSSL_ALGO_SHA256);if($ok!==1)throw new RuntimeException('La firma del archivo de despliegue no es válida.');
        $fingerprint=self::publicKeyFingerprint($publicKey);
        if(!hash_equals(strtolower((string)($file['repository_public_key_fingerprint_sha256']??'')),strtolower($fingerprint)))throw new RuntimeException('La huella de la clave del archivo de despliegue no coincide.');
        if(!hash_equals(strtolower((string)($manifest['central']['public_key_fingerprint_sha256']??'')),strtolower($fingerprint)))throw new RuntimeException('El manifiesto no coincide con la clave pública de Central.');
        $expires=strtotime((string)($manifest['expires_at']??''));if($expires===false||$expires<time())throw new RuntimeException('El archivo de despliegue venció. Genere uno nuevo desde Property Hub Central.');
        $property=(array)($manifest['property']??[]);$code=strtoupper(trim((string)($property['code']??'')));
        if($code===''||!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,79}$/',$code))throw new RuntimeException('El archivo no contiene un código de propiedad válido.');
        $centralUrl=rtrim(trim((string)($manifest['central']['url']??'')),'/');if(!filter_var($centralUrl,FILTER_VALIDATE_URL))throw new RuntimeException('El archivo no contiene una URL válida de Property Hub Central.');
        return $file+['_verified'=>true,'_fingerprint'=>$fingerprint];
    }

    /**
     * Translate a verified deployment file into installer-owned fields. Local DB,
     * admin credentials and the local Edge APP_URL remain local choices.
     * @return array<string,mixed>
     */
    public static function installerFields(array $file,string $role,string $edgeCode=''): array
    {
        $manifest=(array)($file['manifest']??[]);$property=(array)($manifest['property']??[]);$node=(array)($manifest['node']??[]);
        $base=[
            'instance_role'=>$role,
            'instance_type'=>(string)($property['installation_type']??'mixed'),
            'instance_name'=>(string)($property['name']??''),
            'update_channel'=>(string)($property['update_channel']??'stable'),
            'deployment_bundle_id'=>(string)($manifest['bundle_id']??''),
            'deployment_bundle_fingerprint'=>(string)($file['_fingerprint']??$file['repository_public_key_fingerprint_sha256']??''),
            'deployment_bundle_expires_at'=>(string)($manifest['expires_at']??''),
        ];
        if($role===InstallationMode::NODE){
            $token=(string)($node['enrollment']['token']??'');if($token==='')throw new RuntimeException('El archivo no contiene credencial de enrolamiento para el Property Node.');
            $base['instance_code']=(string)($node['code']??$property['code']??'');
            $base['instance_name']=(string)($node['name']??$property['name']??'');
            $base['central_repository_url']=(string)($manifest['central']['url']??'');
            $base['central_registration_token']=$token;
            $publicUrl=rtrim((string)($node['public_url']??''),'/');if($publicUrl!==''&&filter_var($publicUrl,FILTER_VALIDATE_URL))$base['app_url']=$publicUrl;
            return $base;
        }
        if($role!==InstallationMode::EDGE)throw new InvalidArgumentException('El archivo de despliegue se importa únicamente en Property Node o Property Edge Agent.');
        $edges=array_values(array_filter((array)($manifest['edges']??[]),'is_array'));if($edges===[])throw new RuntimeException('El archivo no contiene Edge Agents preparados.');
        $edgeCode=strtoupper(trim($edgeCode));$selected=null;
        if($edgeCode===''){if(count($edges)===1)$selected=$edges[0];else throw new RuntimeException('Seleccione cuál Edge Agent de este archivo está instalando.');}
        else foreach($edges as $edge){if(hash_equals(strtoupper((string)($edge['code']??'')),$edgeCode)){$selected=$edge;break;}}
        if(!is_array($selected))throw new RuntimeException('El Edge seleccionado no pertenece a este archivo de despliegue.');
        $token=(string)($selected['enrollment']['token']??'');if($token==='')throw new RuntimeException('El Edge seleccionado no contiene un token de enrolamiento.');
        $nodeUrl=rtrim((string)($selected['node_url']??$node['public_url']??''),'/');if($nodeUrl===''||!filter_var($nodeUrl,FILTER_VALIDATE_URL))throw new RuntimeException('Central todavía no tiene una URL pública válida para el Property Node. Corríjala en la instalación y genere nuevamente el archivo.');
        $base['instance_code']=(string)$selected['code'];$base['instance_name']=(string)($selected['name']??$selected['code']);
        $base['property_node_url']=$nodeUrl;$base['edge_pairing_token']=$token;
        return $base;
    }

    public static function publicKeyFingerprint(string $publicKey): string
    {
        $resource=@openssl_pkey_get_public($publicKey);if($resource===false)throw new RuntimeException('La clave pública de Central no es válida.');
        $details=openssl_pkey_get_details($resource);if(!is_array($details)||empty($details['key']))throw new RuntimeException('No fue posible normalizar la clave pública de Central.');
        return hash('sha256',trim((string)$details['key']));
    }

    /** @param list<string> $values */
    private static function earliestExpiry(array $values): ?string
    {
        $best=null;$bestTs=null;foreach($values as $value){$ts=strtotime($value);if($ts===false)continue;if($bestTs===null||$ts<$bestTs){$bestTs=$ts;$best=$value;}}return $best;
    }

    private static function uuid(): string
    {
        $data=random_bytes(16);$data[6]=chr((ord($data[6])&0x0f)|0x40);$data[8]=chr((ord($data[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($data),4));
    }
}
