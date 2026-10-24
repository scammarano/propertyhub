<?php
declare(strict_types=1);

final class ReleasePublisher
{
    private const CHANNELS=['development','beta','stable','pinned'];
    private const TYPES=['core','ui','module','connector','api','bridge','video_gateway','language','report','other'];

    public function __construct(private ?PDO $pdo=null) { $this->pdo ??= Connection::pdo(); }

    /**
     * Publishes an uploaded component. Identity/version/type/roles come from the ZIP,
     * never from duplicated form fields. The operator may only choose an existing
     * component as a guard, the release channel and optional human release notes.
     */
    public function publish(array $data, array $file, ?int $userId=null): array
    {
        if (!InstallationMode::isCentral()) throw new RuntimeException('Solo la fuente oficial puede publicar paquetes.');
        if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Seleccione un archivo ZIP válido. Código de carga: '.(string)($file['error']??'N/A'));
        if ((int)($file['size']??0)<=0 || (int)$file['size']>250*1024*1024) throw new RuntimeException('El paquete debe pesar entre 1 byte y 250 MB.');
        $original=(string)($file['name']??'package.zip');
        if (strtolower(pathinfo($original,PATHINFO_EXTENSION))!=='zip') throw new RuntimeException('Solo se aceptan paquetes ZIP.');
        $tmp=(string)($file['tmp_name']??'');
        if (!is_uploaded_file($tmp) && PHP_SAPI!=='cli') throw new RuntimeException('La carga del archivo no es válida.');
        $meta=$this->validatePackageArchive($tmp);
        if(strtolower((string)($meta['component_type']??''))==='core'||ComponentRegistryService::normalizeCode((string)($meta['component_code']??''))==='property-hub-core'){
            throw new InvalidArgumentException('El Core oficial no se publica con este formulario. Se sincroniza desde la versión instalada de Central.');
        }
        $channel=$this->normalizeChannel((string)($data['channel']??'development'));
        $selected=trim((string)($data['selected_component_code']??$data['component_code']??''));
        $registry=new ComponentRegistryService($this->pdo);
        if($selected!==''&&$selected!=='__auto__'){
            $guard=$registry->resolve($selected);
            if(!is_array($guard))throw new RuntimeException('El componente seleccionado no existe en el registro maestro.');
            $packageCode=ComponentRegistryService::normalizeCode((string)($meta['component_code']??''));
            $knownPackage=$registry->resolve($packageCode);
            $sameIdentity=hash_equals((string)$guard['component_code'],$packageCode)||(is_array($knownPackage)&&(int)$knownPackage['id']===(int)$guard['id']);
            if(!$sameIdentity)throw new RuntimeException('El ZIP declara '.$packageCode.' y no pertenece al componente seleccionado '.(string)$guard['component_code'].'. Publicación cancelada sin alterar el registro maestro.');
        }
        $component=$registry->ensureFromPackage($meta);
        $notes=trim((string)($data['release_notes']??''));
        if($notes!=='')$meta['notes']=$notes;
        return $this->publishValidatedArtifact($tmp,$meta,$channel,$userId,'upload');
    }

    /** Publica idempotentemente un componente ZIP ya incluido en resources/releases. */
    public function ensureBundledComponentRelease(string $source,string $channel='beta',?int $userId=null): array
    {
        if(!InstallationMode::isCentral())throw new RuntimeException('Solo la fuente oficial puede publicar componentes embebidos.');
        if(!is_file($source))throw new RuntimeException('No existe el componente embebido: '.basename($source).'.');
        $meta=$this->validatePackageArchive($source);
        return $this->publishValidatedArtifact($source,$meta,$this->normalizeChannel($channel),$userId,'bundled');
    }

    /**
     * Publica el Core embebido del Central. La misma versión jamás puede cambiar de
     * SHA-256: si el artefacto ya existe y los bytes son distintos, se rechaza.
     */
    public function ensureBundledCoreRelease(string $version,string $channel='stable',?int $userId=null): array
    {
        if (!InstallationMode::isCentral()) throw new RuntimeException('Solo la fuente oficial puede publicar el Core embebido.');
        $channel=$this->normalizeChannel($channel);$version=trim($version);if($version==='')throw new InvalidArgumentException('Versión del Core vacía.');
        $source=base_path('resources/releases/property-hub-core-'.$version.'.zip');
        if(!is_file($source)){
            foreach([base_path('storage/repository/packages/property-hub-core-'.$version.'.zip'),base_path('storage/repository/packages/property-hub-core-'.$version.'-stable.zip')] as $candidate){if(is_file($candidate)){$source=$candidate;break;}}
        }
        if(!is_file($source))throw new RuntimeException('No se encontró el paquete distribuible del Core para la versión '.$version.'.');
        $meta=$this->validatePackageArchive($source,$version,'property-hub-core');

        /*
         * Repository synchronization is a reconciliation operation, not a new
         * publication. If this component/version is already published, the DB
         * hash is the immutable canonical artifact. A later deployment may carry
         * another locally rebuilt/bundled ZIP with the same semantic version; it
         * must never replace the canonical bytes, but it also must not crash the
         * whole Central dashboard. Keep the published artifact, ensure it is on
         * the requested channel and report the source mismatch explicitly.
         */
        $registry=new ComponentRegistryService($this->pdo);$component=$registry->ensureFromPackage($meta);$componentId=(int)$component['id'];
        $sourceHash=strtolower((string)(hash_file('sha256',$source)?:''));
        if(!preg_match('/^[a-f0-9]{64}$/',$sourceHash))throw new RuntimeException('No fue posible calcular SHA-256 del Core embebido.');
        $stmt=$this->pdo->prepare("SELECT * FROM bh_repo_releases WHERE (component_id=? OR (component_id IS NULL AND component_code='property-hub-core')) AND version=? AND status='published' ORDER BY id");
        $stmt->execute([$componentId,$version]);$published=$stmt->fetchAll()?:[];
        if($published!==[]){
            $canonical=null;$canonicalHash='';
            foreach($published as $row){
                $dbHash=strtolower(trim((string)($row['package_hash_sha256']??'')));
                if(!preg_match('/^[a-f0-9]{64}$/',$dbHash))throw new RuntimeException('La versión publicada property-hub-core '.$version.' no conserva un SHA-256 canónico válido.');
                if($canonicalHash!==''&&!hash_equals($canonicalHash,$dbHash))throw new RuntimeException('El repositorio contiene más de un SHA-256 publicado para property-hub-core '.$version.'. Debe resolverse la ambigüedad antes de continuar.');
                $canonicalHash=$dbHash;if($canonical===null)$canonical=$row;
            }
            if(!is_array($canonical))throw new RuntimeException('No fue posible resolver el release canónico del Core '.$version.'.');
            $releaseId=(int)$canonical['id'];$registry->attachRelease($releaseId,$componentId);
            $packageRelative=trim((string)($canonical['package_path']??''));$packagePath=$packageRelative!==''?base_path($packageRelative):'';$diskHash='';
            if($packagePath!==''&&is_file($packagePath))$diskHash=strtolower((string)(hash_file('sha256',$packagePath)?:''));
            if($diskHash!==''&&!hash_equals($canonicalHash,$diskHash))throw new RuntimeException('El ZIP canónico almacenado para property-hub-core '.$version.' no coincide con su SHA-256 publicado. No se sobrescribirá automáticamente.');
            if($diskHash===''){
                if(!hash_equals($canonicalHash,$sourceHash))throw new RuntimeException('El release canónico property-hub-core '.$version.' existe, pero su ZIP no está disponible y el artefacto local tiene otro SHA-256. No es seguro reconstruirlo automáticamente.');
                $releaseCode=(string)$canonical['release_code'];$packageRelative='storage/repository/packages/'.$releaseCode.'.zip';$packagePath=base_path($packageRelative);$this->ensureDir(dirname($packagePath));
                if(!@copy($source,$packagePath))throw new RuntimeException('No fue posible restaurar el ZIP canónico de property-hub-core '.$version.'.');@chmod($packagePath,0640);
                $diskHash=$sourceHash;$this->pdo->prepare('UPDATE bh_repo_releases SET package_path=?,package_size_bytes=?,component_id=?,artifact_locked_at=COALESCE(artifact_locked_at,NOW()) WHERE id=?')->execute([$packageRelative,filesize($packagePath),$componentId,$releaseId]);
            }
            $registry->promoteRelease($releaseId,$channel,$userId);
            $sourceConflict=!hash_equals($canonicalHash,$sourceHash);
            // Do not derive or rewrite canonical release metadata/dependencies from
            // a non-canonical local ZIP. Only byte-identical sources may refresh
            // those auxiliary rows.
            if(!$sourceConflict)$this->syncDependencies($releaseId,$meta,$registry);
            if($sourceConflict){
                ErrorReporter::runtimeLog('repository_core_source_conflict_preserved',[
                    'version'=>$version,'release_code'=>(string)$canonical['release_code'],'canonical_sha256'=>$canonicalHash,'local_source_sha256'=>$sourceHash,'source'=>str_replace('\\','/',$source),
                ]);
            }
            return ['release_code'=>(string)$canonical['release_code'],'hash'=>$canonicalHash,'canonical_hash'=>$canonicalHash,'source_hash'=>$sourceHash,'package'=>$packageRelative,'existing'=>true,'promoted_channel'=>$channel,'component_code'=>'property-hub-core','component_id'=>$componentId,'version'=>$version,'immutable'=>true,'source_conflict'=>$sourceConflict,'immutable_preserved'=>true];
        }

        return $this->publishValidatedArtifact($source,$meta,$channel,$userId,'bundled_core');
    }

    /** Promote an already published immutable artifact to another channel without re-uploading it. */
    public function promote(string $releaseCode,string $channel,?int $userId=null): array
    {
        if(!InstallationMode::isCentral())throw new RuntimeException('Solo Central puede promover releases.');
        $channel=$this->normalizeChannel($channel);$releaseCode=trim($releaseCode);if($releaseCode==='')throw new InvalidArgumentException('Falta release.');
        $stmt=$this->pdo->prepare('SELECT id,release_code,component_code,version,package_hash_sha256 FROM bh_repo_releases WHERE release_code=? AND status=\'published\' LIMIT 1');$stmt->execute([$releaseCode]);$release=$stmt->fetch();
        if(!is_array($release))throw new RuntimeException('Release no encontrada o no publicada.');
        (new ComponentRegistryService($this->pdo))->promoteRelease((int)$release['id'],$channel,$userId);
        return ['release_code'=>(string)$release['release_code'],'component_code'=>(string)$release['component_code'],'version'=>(string)$release['version'],'channel'=>$channel,'hash'=>(string)$release['package_hash_sha256']];
    }

    /** @return array<string,mixed> */
    private function publishValidatedArtifact(string $source,array $meta,string $channel,?int $userId,string $sourceKind): array
    {
        $type=strtolower(trim((string)($meta['component_type']??'other')));$code=ComponentRegistryService::normalizeCode((string)($meta['component_code']??''));$version=trim((string)($meta['version']??''));$display=trim((string)($meta['display_name']??$code));
        if(!in_array($type,self::TYPES,true)||$code===''||!DesiredConnectorPolicy::isValidVersion($version))throw new RuntimeException('El paquete no declara una identidad/versión de componente válida.');
        $registry=new ComponentRegistryService($this->pdo);$component=$registry->ensureFromPackage($meta);$componentId=(int)$component['id'];
        $sourceHash=strtolower((string)(hash_file('sha256',$source)?:''));if(!preg_match('/^[a-f0-9]{64}$/',$sourceHash))throw new RuntimeException('No fue posible calcular SHA-256 del artefacto.');

        // A component/version pair is immutable across every channel. Existing legacy
        // duplicates are tolerated only when every row points to the exact same SHA-256.
        $check=$this->pdo->prepare("SELECT * FROM bh_repo_releases WHERE (component_id=? OR (component_id IS NULL AND component_code=? AND status='published')) AND version=? ORDER BY id");$check->execute([$componentId,$code,$version]);$sameVersion=$check->fetchAll()?:[];
        if($sameVersion!==[]){
            $canonical=null;
            foreach($sameVersion as $row){
                $dbHash=strtolower(trim((string)($row['package_hash_sha256']??'')));$path=base_path((string)($row['package_path']??''));$diskHash=is_file($path)?strtolower((string)(hash_file('sha256',$path)?:'')):'';
                if(($row['status']??'')==='published'&&!preg_match('/^[a-f0-9]{64}$/',$dbHash))throw new RuntimeException('La versión publicada '.$code.' '.$version.' no conserva un SHA-256 canónico válido. Por seguridad no puede reemplazarse ni reconstruirse automáticamente; resuelva ese release legado antes de continuar.');
                if($dbHash!==''&&!hash_equals($sourceHash,$dbHash))throw new RuntimeException('La versión '.$code.' '.$version.' ya fue publicada con otro SHA-256 ('.$dbHash.'). Un release publicado es inmutable: incremente la versión.');
                if($diskHash!==''&&!hash_equals($sourceHash,$diskHash))throw new RuntimeException('El repositorio detectó corrupción: el ZIP guardado de '.$code.' '.$version.' no coincide con su artefacto canónico. No se sobrescribirá automáticamente.');
                if(($row['status']??'')==='published'&&$canonical===null)$canonical=$row;
            }
            if(!is_array($canonical))throw new RuntimeException('La versión '.$code.' '.$version.' ya está reservada por un release no publicado. No se reutilizará automáticamente; cambie el estado de ese release o incremente la versión.');
            $releaseId=(int)$canonical['id'];$registry->attachRelease($releaseId,$componentId);
            $packageRelative=(string)($canonical['package_path']??'');$packagePath=$packageRelative!==''?base_path($packageRelative):'';
            if($packageRelative===''||!is_file($packagePath)){
                $releaseCode=(string)$canonical['release_code'];$packageRelative='storage/repository/packages/'.$releaseCode.'.zip';$packagePath=base_path($packageRelative);$this->ensureDir(dirname($packagePath));
                if(!@copy($source,$packagePath))throw new RuntimeException('El release existe pero su ZIP falta y no fue posible restaurarlo desde el artefacto byte-idéntico.');@chmod($packagePath,0640);
                $this->pdo->prepare('UPDATE bh_repo_releases SET package_path=?,package_size_bytes=?,package_hash_sha256=?,artifact_locked_at=COALESCE(artifact_locked_at,NOW()),component_id=? WHERE id=?')->execute([$packageRelative,filesize($packagePath),$sourceHash,$componentId,$releaseId]);
            }
            $registry->promoteRelease($releaseId,$channel,$userId);$this->syncDependencies($releaseId,$meta,$registry);
            return ['release_code'=>(string)$canonical['release_code'],'hash'=>$sourceHash,'package'=>$packageRelative,'existing'=>true,'promoted_channel'=>$channel,'component_code'=>$code,'component_id'=>$componentId,'version'=>$version,'immutable'=>true];
        }

        $releaseCode=strtolower($code.'-'.$version);$collision=$this->pdo->prepare('SELECT 1 FROM bh_repo_releases WHERE release_code=? LIMIT 1');$collision->execute([$releaseCode]);if($collision->fetchColumn()!==false)$releaseCode.='-'.substr(bin2hex(random_bytes(4)),0,8);
        $filename=$releaseCode.'.zip';$packageRelative='storage/repository/packages/'.$filename;$packagePath=base_path($packageRelative);$this->ensureDir(dirname($packagePath));
        if(!@copy($source,$packagePath))throw new RuntimeException('No fue posible copiar el componente al repositorio.');@chmod($packagePath,0640);
        $hash=strtolower((string)(hash_file('sha256',$packagePath)?:''));if($hash===''||!hash_equals($sourceHash,$hash)){@unlink($packagePath);throw new RuntimeException('El artefacto copiado no coincide con el ZIP validado.');}
        $roles=array_values(array_unique(array_filter(array_map('strval',(array)($meta['target_roles']??['building_node','edge_agent'])))));
        $apis=array_values(array_unique(array_filter(array_map('trim',(array)($meta['compatible_api_versions']??['v1'])))));
        if($apis===[])$apis=['v1'];
        $manifest=['schema'=>'2cam-property-release-v1','release_code'=>$releaseCode,'component_id'=>$componentId,'component_type'=>$type,'component_code'=>$code,'display_name'=>$display,'version'=>$version,'channel'=>$channel,'min_core_version'=>(string)($meta['min_core_version']??''),'max_core_version'=>(string)($meta['max_core_version']??''),'compatible_api_versions'=>$apis,'target_roles'=>$roles,'package'=>['filename'=>$filename,'size_bytes'=>filesize($packagePath),'sha256'=>$hash],'release_notes'=>(string)($meta['notes']??''),'artifact_immutable'=>true,'published_at'=>date(DATE_ATOM)];
        $signed=ManifestSigner::sign($manifest);$manifestDir=base_path('storage/repository/manifests');$signatureDir=base_path('storage/repository/signatures');$this->ensureDir($manifestDir);$this->ensureDir($signatureDir);
        $manifestRelative='storage/repository/manifests/'.$releaseCode.'.json';$signatureRelative='storage/repository/signatures/'.$releaseCode.'.sig';
        file_put_contents(base_path($manifestRelative),json_encode($signed,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX);file_put_contents(base_path($signatureRelative),(string)$signed['signature'],LOCK_EX);
        try{
            $sql="INSERT INTO bh_repo_releases (component_id,release_code,component_type,component_code,display_name,version,channel,status,min_core_version,max_core_version,compatible_api_versions_json,target_roles_json,release_notes,package_metadata_json,manifest_path,package_path,package_size_bytes,package_hash_sha256,signature,artifact_locked_at,published_at,created_by) VALUES (?,?,?,?,?,?,?,'published',?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),?)";
            $stmt=$this->pdo->prepare($sql);$stmt->execute([$componentId,$releaseCode,$type,$code,$display,$version,$channel,$manifest['min_core_version']?:null,$manifest['max_core_version']?:null,json_encode($apis),json_encode($roles),$manifest['release_notes']?:null,json_encode($meta,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$manifestRelative,$packageRelative,filesize($packagePath),$hash,$signed['signature'],$userId]);$releaseId=(int)$this->pdo->lastInsertId();
            $registry->promoteRelease($releaseId,$channel,$userId);$this->syncDependencies($releaseId,$meta,$registry);
        }catch(Throwable $e){@unlink($packagePath);@unlink(base_path($manifestRelative));@unlink(base_path($signatureRelative));throw $e;}
        return ['release_code'=>$releaseCode,'hash'=>$hash,'manifest'=>$manifestRelative,'package'=>$packageRelative,'existing'=>false,'component_code'=>$code,'component_id'=>$componentId,'version'=>$version,'channel'=>$channel,'immutable'=>true,'source'=>$sourceKind];
    }

    private function syncDependencies(int $releaseId,array $meta,ComponentRegistryService $registry): void
    {
        $deps=[];
        foreach((array)($meta['dependencies']??[]) as $dep){
            if(!is_array($dep))continue;$code=ComponentRegistryService::normalizeCode((string)($dep['component_code']??''));$constraint=trim((string)($dep['version_constraint']??''));if($code===''||$constraint==='')continue;
            $deps[$code]=['component_code'=>$code,'version_constraint'=>$constraint,'required'=>!array_key_exists('required',$dep)||!empty($dep['required'])];
        }
        $minCore=trim((string)($meta['min_core_version']??''));$self=ComponentRegistryService::normalizeCode((string)($meta['component_code']??''));
        if($minCore!==''&&$self!=='property-hub-core'&&!isset($deps['property-hub-core']))$deps['property-hub-core']=['component_code'=>'property-hub-core','version_constraint'=>'>='.$minCore,'required'=>true];
        $this->pdo->prepare('DELETE FROM bh_repo_release_dependencies WHERE release_id=?')->execute([$releaseId]);
        $stmt=$this->pdo->prepare('INSERT INTO bh_repo_release_dependencies (release_id,dependency_component_id,dependency_component_code,version_constraint,required) VALUES (?,?,?,?,?)');
        foreach($deps as $dep){$component=$registry->resolve((string)$dep['component_code']);$stmt->execute([$releaseId,is_array($component)?(int)$component['id']:null,(string)$dep['component_code'],(string)$dep['version_constraint'],!empty($dep['required'])?1:0]);}
    }

    private function normalizeChannel(string $channel): string
    {
        $channel=strtolower(trim($channel));if(!in_array($channel,self::CHANNELS,true))throw new InvalidArgumentException('Canal de publicación no válido.');return $channel;
    }

    private function ensureDir(string $dir): void
    {
        if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No se pudo crear el directorio '.$dir.'.');
    }

    public function validatePackageArchive(string $path,?string $expectedVersion=null,?string $expectedComponentCode=null): array
    {
        $meta=$this->validateZip($path);
        if($expectedVersion!==null&&trim($expectedVersion)!==''&&trim((string)($meta['version']??''))!==trim($expectedVersion)){
            throw new RuntimeException('El Core embebido declara la versión '.(string)($meta['version']??'N/D').' pero el Central instalado requiere '.trim($expectedVersion).'.');
        }
        if($expectedComponentCode!==null&&trim($expectedComponentCode)!==''&&trim((string)($meta['component_code']??''))!==trim($expectedComponentCode)){
            throw new RuntimeException('El paquete embebido declara component_code='.(string)($meta['component_code']??'N/D').' y se esperaba '.trim($expectedComponentCode).'.');
        }
        return $meta;
    }

    private function validateZip(string $path): array
    {
        if (!class_exists('ZipArchive')) throw new RuntimeException('Active la extensión ZIP de PHP para validar y publicar paquetes.');
        $zip=new ZipArchive(); if($zip->open($path)!==true)throw new RuntimeException('El archivo ZIP no puede abrirse.');
        $packageMetaEntry = null;
        for($i=0;$i<$zip->numFiles;$i++){
            $name=(string)$zip->getNameIndex($i);
            $normalized=str_replace('\\','/',$name);
            if($normalized===''||str_starts_with($normalized,'/')||preg_match('#(^|/)\.\.(/|$)#',$normalized)){ $zip->close(); throw new RuntimeException('El ZIP contiene una ruta insegura: '.$name); }

            // Nunca eliminar el punto inicial: `.env.example` debe seguir siendo `.env.example`.
            $relative=ltrim($normalized,'/');
            if ($relative==='update-package.json') $packageMetaEntry=$relative;
            elseif (preg_match('#^[^/]+/update-package\.json$#',$relative)) $packageMetaEntry=$relative;
            $candidates=[$relative];
            $parts=explode('/',$relative);
            if(count($parts)>1 && $parts[0]!=='' && !str_starts_with($parts[0],'.')){
                $candidates[]=implode('/',array_slice($parts,1));
            }
            foreach($candidates as $candidate){
                if($this->isProtectedPackagePath($candidate)){
                    $zip->close();
                    throw new RuntimeException('El ZIP intenta incluir una ruta protegida: '.$name);
                }
            }
        }
        if ($packageMetaEntry===null) {
            $zip->close();
            throw new RuntimeException('El ZIP no contiene update-package.json en la raíz del paquete. No puede publicarse como actualización.');
        }
        $raw=$zip->getFromName($packageMetaEntry);
        $meta=is_string($raw)?json_decode($raw,true):null;
        $type=strtolower(trim((string)($meta['component_type']??'')));
        $code=ComponentRegistryService::normalizeCode((string)($meta['component_code']??''));
        $version=trim((string)($meta['version']??''));
        $display=trim((string)($meta['display_name']??''));
        if(!is_array($meta)||($meta['schema']??'')!=='2cam-property-update-v1'||$code===''||!DesiredConnectorPolicy::isValidVersion($version)||$display===''||!in_array($type,self::TYPES,true)){
            $zip->close();
            throw new RuntimeException('update-package.json no cumple el contrato 2cam-property-update-v1: debe declarar component_type, component_code, display_name y version válidos.');
        }
        $meta['component_code']=$code;$meta['component_type']=$type;
        $roles=array_values(array_unique(array_filter(array_map('strval',(array)($meta['target_roles']??[])))));
        $validRoles=['central','central_repository','building_node','edge_agent'];
        foreach($roles as $role){if(!in_array($role,$validRoles,true)){$zip->close();throw new RuntimeException('update-package.json declara un target_role no soportado: '.$role.'.');}}
        if($roles===[]){$zip->close();throw new RuntimeException('update-package.json debe declarar al menos un target_role.');}
        foreach((array)($meta['dependencies']??[]) as $dependency){
            if(!is_array($dependency)||ComponentRegistryService::normalizeCode((string)($dependency['component_code']??''))===''||trim((string)($dependency['version_constraint']??''))===''){
                $zip->close();throw new RuntimeException('update-package.json contiene una dependencia inválida. Use component_code + version_constraint.');
            }
        }

        // health_files describe files delivered by the package, never updater runtime artifacts.
        // This prevents a Linux/cPanel node from being asked to prove the existence of
        // storage/updates/deferred/*, which is created only at runtime on Windows when a
        // live front controller must be replaced in a later request.
        $prefix=$packageMetaEntry==='update-package.json'?'':substr($packageMetaEntry,0,-strlen('update-package.json'));
        $entrySet=[];
        for($i=0;$i<$zip->numFiles;$i++){
            $entry=ltrim(str_replace('\\','/',(string)$zip->getNameIndex($i)),'/');
            if($entry!=='')$entrySet[$entry]=true;
        }
        foreach((array)($meta['health_files']??[]) as $healthFile){
            $health=ltrim(str_replace('\\','/',trim((string)$healthFile)),'/');
            if($health===''||str_contains($health,'..')){
                $zip->close();
                throw new RuntimeException('update-package.json declara un health_file inválido: '.(string)$healthFile);
            }
            if(str_starts_with(strtolower($health),'storage/updates/deferred/')){
                $zip->close();
                throw new RuntimeException('update-package.json no puede declarar artefactos runtime como health_files: '.$health);
            }
            if(!isset($entrySet[$prefix.$health])){
                $zip->close();
                throw new RuntimeException('update-package.json declara un health_file que no existe dentro del ZIP: '.$health);
            }
        }
        // El ledger de migraciones forma parte del release gate y debe validarse en
        // todos los paquetes normales, no solamente cuando existe un health_file malo.
        $this->validateMigrationLedger($zip,$meta,$prefix);
        $zip->close();
        return $meta;
    }


    /**
     * Las migraciones son append-only. Una vez publicadas, ni siquiera se puede
     * cambiar un comentario: MigrationRunner guarda SHA-256 y rechaza cualquier
     * mutación. El ledger hace visible ese contrato en el propio paquete y
     * permite que Central bloquee un Core roto antes de publicarlo.
     */
    private function validateMigrationLedger(ZipArchive $zip,array $meta,string $prefix): void
    {
        $ledgerEntry=$prefix.'database/migrations/MIGRATION_CHECKSUMS.json';
        $raw=$zip->getFromName($ledgerEntry);
        if(!is_string($raw))throw new RuntimeException('El Core no contiene database/migrations/MIGRATION_CHECKSUMS.json.');
        $ledger=json_decode($raw,true);
        if(!is_array($ledger)||($ledger['schema']??'')!=='2cam-property-migration-checksums-v1'||!is_array($ledger['migrations']??null)){
            throw new RuntimeException('MIGRATION_CHECKSUMS.json no cumple el contrato 2cam-property-migration-checksums-v1.');
        }
        $current=(array)$ledger['migrations'];
        $declaredMigrations=(array)($meta['migrations']??[]);
        foreach((array)($meta['role_migrations']??[]) as $roleList)foreach((array)$roleList as $migration)$declaredMigrations[]=$migration;
        foreach($declaredMigrations as $migration){
            $rel=ltrim(str_replace('\\','/',trim((string)$migration)),'/');
            if($rel===''||!str_starts_with($rel,'database/migrations/')||!str_ends_with(strtolower($rel),'.sql'))continue;
            $name=basename($rel);
            $expected=strtolower(trim((string)($current[$name]??'')));
            if(!preg_match('/^[a-f0-9]{64}$/',$expected))throw new RuntimeException('El ledger no contiene un SHA-256 válido para '.$name.'.');
            $migrationRaw=$zip->getFromName($prefix.$rel);
            if(!is_string($migrationRaw))throw new RuntimeException('El paquete declara una migración inexistente: '.$rel);
            $actual=hash('sha256',$migrationRaw);
            if(!hash_equals($expected,$actual))throw new RuntimeException('La migración '.$name.' no coincide con su checksum canónico. Esperado '.$expected.', recibido '.$actual.'.');
        }

        // A partir de 0.13.7, el último Core que ya tenga ledger actúa como ancla
        // histórica para el siguiente Core. Los componentes independientes pueden
        // transportar sólo las migraciones que necesitan y no deben heredar futuras
        // migraciones Central-only del Core (por ejemplo 0042).
        $isCore=strtolower((string)($meta['component_type']??''))==='core'||ComponentRegistryService::normalizeCode((string)($meta['component_code']??''))==='property-hub-core';
        if(!$isCore)return;
        try{
            // Historical bridge artifacts may be republished after a newer Central was
            // installed. Therefore the append-only anchor must be the highest published
            // Core STRICTLY LOWER than the candidate version, never simply the most
            // recently published row. Otherwise importing 0.14.54 after 0.14.57 would
            // incorrectly demand that 0.14.54 already contain future migrations.
            $candidateVersion=trim((string)($meta['version']??''));
            $stmt=$this->pdo?->prepare("SELECT version,package_path FROM bh_repo_releases WHERE component_code='property-hub-core' AND status='published' ORDER BY id DESC");
            if($stmt){
                $stmt->execute();$anchor=null;
                foreach($stmt->fetchAll() as $row){
                    $v=trim((string)($row['version']??''));
                    if($candidateVersion===''||$v===''||version_compare($v,$candidateVersion,'>='))continue;
                    if($anchor===null||version_compare($v,(string)$anchor['version'],'>'))$anchor=$row;
                }
                if(is_array($anchor)){
                    $previousPath=base_path((string)($anchor['package_path']??''));
                    if(is_file($previousPath)){
                        $prevZip=new ZipArchive();
                        if($prevZip->open($previousPath)===true){
                            $prevRaw=$prevZip->getFromName('database/migrations/MIGRATION_CHECKSUMS.json');$prevZip->close();
                            if(is_string($prevRaw)){
                                $previous=json_decode($prevRaw,true);
                                if(is_array($previous)&&($previous['schema']??'')==='2cam-property-migration-checksums-v1'&&is_array($previous['migrations']??null)){
                                    foreach((array)$previous['migrations'] as $name=>$hash){
                                        $old=strtolower(trim((string)$hash));
                                        if(!array_key_exists((string)$name,$current))throw new RuntimeException('El nuevo Core intentó eliminar del ledger la migración histórica '.(string)$name.'.');
                                        $new=strtolower(trim((string)$current[(string)$name]));
                                        if(!hash_equals($old,$new))throw new RuntimeException('El nuevo Core intentó modificar la migración histórica '.(string)$name.'. Cree una migración nueva en lugar de editar una ya publicada.');
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }catch(RuntimeException $e){throw $e;}catch(Throwable){/* si no hay historial accesible, la validación interna sigue siendo obligatoria */}
    }

    private function isProtectedPackagePath(string $relative): bool
    {
        $p=strtolower(ltrim(str_replace('\\','/',$relative),'/'));
        if($p==='.env'||str_starts_with($p,'.env.'))return true;
        // El Core distribuible nunca debe transportar storage. Es estado persistente/runtime
        // de cada instalación (uploads, keys, backups, logs, repository, rollback, etc.).
        // En una instalación nueva, storage se crea desde el paquete completo/instalador;
        // una actualización no debe sobrescribirlo ni siquiera con placeholders .gitkeep.
        if($p==='storage'||str_starts_with($p,'storage/'))return true;
        return false;
    }
}
