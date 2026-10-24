<?php
declare(strict_types=1);

/**
 * Reconciles the Core release bundled with Property Hub Central with the
 * distribution repository. This is intentionally independent from migrations:
 * uploading a newer official Central build by FTP is enough for nodes to see
 * the bundled Core release on their next manifest request.
 */
final class RepositorySynchronizer
{
    public function __construct(private ?PDO $pdo=null)
    {
        $this->pdo ??= Connection::pdo();
    }

    /** @return array<string,mixed> */
    public function syncOfficialCore(?int $userId=null): array
    {
        if (!InstallationMode::isCentral()) {
            throw new RuntimeException('La sincronización del repositorio oficial solo existe en Property Hub Central.');
        }

        $version = trim((string)config('app.version',''));
        if ($version === '') throw new RuntimeException('No fue posible determinar la versión instalada del Central.');

        [$sourceRelative,$source] = $this->ensureBundledCore($version);

        $result = (new ReleasePublisher($this->pdo))->ensureBundledCoreRelease($version,'stable',$userId);
        $this->reconcileLocalVersion($version);

        ErrorReporter::runtimeLog('repository_official_core_sync',[
            'version'=>$version,
            'release_code'=>(string)($result['release_code']??''),
            'existing'=>(bool)($result['existing']??false),
            'hash'=>(string)($result['hash']??''),
            'source_hash'=>(string)($result['source_hash']??''),
            'source_conflict'=>(bool)($result['source_conflict']??false),
            'immutable_preserved'=>(bool)($result['immutable_preserved']??false),
        ]);

        return $result + ['version'=>$version,'source'=>$sourceRelative,'ok'=>true];
    }

    /** @return array<string,mixed> */
    public function syncOfficialComponents(?int $userId=null): array
    {
        if(!InstallationMode::isCentral())throw new RuntimeException('La sincronización oficial solo existe en Central.');
        (new CatalogSeeder($this->pdo,base_path()))->seedConnectorDefinitions();
        (new ComponentRegistryService($this->pdo))->reconcileConnectorCatalog();

        // A Central can be upgraded directly by ZIP/FTP while some Nodes are several
        // releases behind. Publish every bundled immutable Core bridge so those Nodes
        // are always offered the next compatible step instead of being stranded.
        $installedVersion=trim((string)config('app.version',''));$bridges=[];$coreFiles=[];
        foreach(glob(base_path('resources/releases/property-hub-core-*.zip'))?:[] as $source){
            if(!preg_match('/property-hub-core-([0-9]+(?:\.[0-9]+){2,3})\.zip$/',str_replace('\\','/',$source),$m))continue;
            $coreFiles[]=['version'=>$m[1],'source'=>$source];
        }
        usort($coreFiles,static fn(array $a,array $b):int=>version_compare((string)$a['version'],(string)$b['version']));
        $publisher=new ReleasePublisher($this->pdo);
        foreach($coreFiles as $item){
            if(hash_equals($installedVersion,(string)$item['version']))continue;
            try{$bridges[]=$publisher->ensureBundledCoreRelease((string)$item['version'],'stable',$userId)+['bridge'=>true];}
            catch(Throwable $e){ErrorReporter::report($e,['operation'=>'repository_bundled_core_bridge_sync','file'=>basename((string)$item['source']),'version'=>(string)$item['version']]);$bridges[]=['ok'=>false,'bridge'=>true,'version'=>(string)$item['version'],'file'=>basename((string)$item['source']),'message'=>$e->getMessage()];}
        }
        $core=$this->syncOfficialCore($userId);$connectors=[];
        foreach(glob(base_path('resources/releases/property-hub-connector-*.zip'))?:[] as $source){
            try{$connectors[]=$publisher->ensureBundledComponentRelease($source,'stable',$userId);}
            catch(Throwable $e){ErrorReporter::report($e,['operation'=>'repository_bundled_connector_sync','file'=>basename($source)]);$connectors[]=['ok'=>false,'file'=>basename($source),'message'=>$e->getMessage()];}
        }
        // Preserve the old top-level fields used by diagnostics while adding bridge detail.
        return ['ok'=>true,'version'=>(string)($core['version']??$installedVersion),'release_code'=>(string)($core['release_code']??''),'hash'=>(string)($core['hash']??''),'core'=>$core,'core_bridges'=>$bridges,'connectors'=>$connectors];
    }

    /** @return array<string,mixed> */
    public function status(): array
    {
        if (!InstallationMode::isCentral()) return ['ok'=>false,'message'=>'No es una instalación Central.'];
        $version=(string)config('app.version','');
        [$sourceRelative,$source]=$this->resolveBundledCore($version);
        $sourceExists=is_file($source);
        $sourceHash=$sourceExists?(hash_file('sha256',$source)?:''):'';
        $sourceValid=false;$sourceValidationError='';
        if($sourceExists){
            try{
                (new ReleasePublisher($this->pdo))->validatePackageArchive($source,$version,'property-hub-core');
                $sourceValid=true;
            }catch(Throwable $e){
                $sourceValidationError=$e->getMessage();
            }
        }
        // Release identity no longer contains the channel. Resolve the immutable
        // component/version pair and verify that the same artifact is promoted to stable.
        $release=null;$releaseCode='';
        $registry=new ComponentRegistryService($this->pdo);
        if($registry->available()){
            $stmt=$this->pdo->prepare("SELECT r.* FROM bh_repo_releases r JOIN bh_repo_components c ON c.id=r.component_id WHERE c.component_code='property-hub-core' AND r.version=? AND r.status='published' ORDER BY r.id DESC LIMIT 1");
            $stmt->execute([$version]);$release=$stmt->fetch();
            if(is_array($release)){
                $releaseCode=(string)$release['release_code'];
                $channels=$registry->channelsForReleaseIds([(int)$release['id']]);
                if(!in_array('stable',$channels[(int)$release['id']]??[],true))$release['status']='not_promoted_stable';
            }
        }else{
            // Upgrade-safe fallback while migration 0042 has not yet been applied.
            $legacy='property-hub-core-'.$version.'-stable';
            $stmt=$this->pdo->prepare('SELECT * FROM bh_repo_releases WHERE release_code=? LIMIT 1');
            $stmt->execute([$legacy]);$release=$stmt->fetch();$releaseCode=is_array($release)?(string)$release['release_code']:$legacy;
        }
        $packagePath=is_array($release)&&!empty($release['package_path'])?base_path((string)$release['package_path']):'';
        $packageExists=$packagePath!==''&&is_file($packagePath);
        $packageHash=$packageExists?(hash_file('sha256',$packagePath)?:''):'';
        $published=is_array($release)&&($release['status']??'')==='published';
        $dbHash=strtolower(trim((string)($release['package_hash_sha256']??'')));
        $repositoryReady=$published&&$packageExists&&preg_match('/^[a-f0-9]{64}$/',$dbHash)===1&&$packageHash!==''&&hash_equals($dbHash,$packageHash);
        $sourceMatchesCanonical=$sourceExists&&$sourceHash!==''&&preg_match('/^[a-f0-9]{64}$/',$dbHash)===1&&hash_equals($sourceHash,$dbHash);
        $inSync=$sourceValid&&$repositoryReady&&$sourceMatchesCanonical;
        $sourceConflict=$repositoryReady&&$sourceExists&&$sourceHash!==''&&!$sourceMatchesCanonical;

        return [
            // `ok` means the repository can safely distribute the immutable canonical
            // release. `fully_in_sync` additionally means the locally bundled source
            // is byte-identical to that canonical artifact.
            'ok'=>$repositoryReady,
            'fully_in_sync'=>$inSync,
            'source_conflict'=>$sourceConflict,
            'installed_version'=>$version,
            'release_code'=>$releaseCode,
            'source_exists'=>$sourceExists,
            'source_valid'=>$sourceValid,
            'source_validation_error'=>$sourceValidationError,
            'source_path'=>$sourceRelative,
            'source_hash'=>$sourceHash,
            'published'=>$published,
            'package_exists'=>$packageExists,
            'package_hash'=>$packageHash,
            'db_hash'=>$dbHash,
            'release'=>$release,
            'message'=>$inSync
                ?'El Core instalado está publicado y coincide byte por byte con el artefacto canónico.'
                :($sourceConflict
                    ?'El repositorio conserva correctamente el artefacto canónico inmutable, pero el ZIP local de la misma versión tiene otro SHA-256. No se sobrescribió el release; actualice Central a una versión posterior para normalizar la instalación.'
                    :($repositoryReady?'El repositorio canónico está listo para los nodos, aunque la fuente local no pudo validarse completamente.':'El Core instalado todavía no está disponible de forma íntegra en el repositorio de distribución.')),
        ];
    }


    /** @return array{0:string,1:string} */
    private function ensureBundledCore(string $version): array
    {
        [$relative,$path]=$this->resolveBundledCore($version);
        if(is_file($path))return [$relative,$path];

        // Recovery path for Central deployments where a hosting/updater layer
        // omitted the nested distributable ZIP. Rebuild it from the installed
        // immutable application tree, excluding .env, storage and nested releases.
        $relative='resources/releases/property-hub-core-'.$version.'.zip';
        $path=base_path($relative);
        $built=(new CoreDistributionArtifactBuilder())->build($version,$path);
        ErrorReporter::runtimeLog('repository_bundled_core_rebuilt',[
            'version'=>$version,
            'path'=>$relative,
            'files'=>(int)($built['files']??0),
            'sha256'=>(string)($built['sha256']??''),
        ]);
        return [$relative,$path];
    }

    /** @return array{0:string,1:string} */
    private function resolveBundledCore(string $version): array
    {
        $candidates=[
            'resources/releases/property-hub-core-'.$version.'.zip',
            'storage/repository/packages/property-hub-core-'.$version.'.zip',
            'storage/repository/packages/property-hub-core-'.$version.'-stable.zip',
        ];
        foreach($candidates as $relative){
            $path=base_path($relative);
            if(is_file($path)) return [$relative,$path];
        }
        return [$candidates[0],base_path($candidates[0])];
    }

    private function reconcileLocalVersion(string $version): void
    {
        try {
            $uuid=(string)config('deployment.installation_uuid','');
            if($uuid==='') return;
            $stmt=$this->pdo->prepare('SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1');
            $stmt->execute([$uuid]);$id=(int)($stmt->fetchColumn()?:0);
            if($id<=0) return;
            $this->pdo->prepare('UPDATE bh_installations SET app_version=?,updated_at=NOW() WHERE id=?')->execute([$version,$id]);
            $this->pdo->prepare("UPDATE bh_installed_components SET installed_version=?,updated_at=NOW() WHERE installation_id=? AND (component_code='property-hub-core' OR component_code='property-hub-central-console')")->execute([$version,$id]);
        } catch (Throwable $e) {
            ErrorReporter::report($e,['operation'=>'repository_reconcile_local_version','version'=>$version]);
        }
    }
}
