<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$input=api_input(); $pdo=Connection::pdo(); $installation=api_auth_installation($pdo,$input);

// Reconcile official artifacts on every manifest request so an FTP deployment of a
// newer Central build becomes visible without a separate publication operation.
$sync=['ok'=>false,'message'=>'not_attempted'];
try {$sync=(new RepositorySynchronizer($pdo))->syncOfficialComponents();}
catch (Throwable $e) {$sync=['ok'=>false,'message'=>$e->getMessage()];ErrorReporter::report($e,['operation'=>'repository_manifest_auto_sync','node'=>(string)$installation['installation_code']]);}

$channel=(string)$installation['update_channel'];
$eligibleChannels=match($channel){'development'=>['stable','beta','development'],'beta'=>['stable','beta'],'pinned'=>['pinned'],default=>['stable']};
$currentCore=(string)($input['app_version']??'0.0.0');
$callerRole=in_array((string)($installation['instance_role']??'building_node'),['building_node','edge_agent'],true)?(string)($installation['instance_role']??'building_node'):'building_node';
$requestedDownstreamRole=trim((string)($input['downstream_role']??''));$targetRole=$callerRole;
if($requestedDownstreamRole!==''){
    if($callerRole!=='building_node'||$requestedDownstreamRole!=='edge_agent')api_response(['ok'=>false,'error'=>'downstream_role_not_allowed'],403);
    $targetRole='edge_agent';
}

$desiredState=DesiredConnectorPolicy::resolve($pdo,$installation,true);$desiredConnectors=[];
foreach((array)$desiredState['items'] as $item){$code=DesiredConnectorPolicy::canonical((string)($item['code']??''));if($code!=='')$desiredConnectors[$code]=$item;}

$registry=new ComponentRegistryService($pdo);
if($registry->available()){
    $rows=$pdo->query("SELECT r.id,r.release_code,r.component_id,r.component_type,r.component_code,r.display_name,r.version,r.channel,r.min_core_version,r.max_core_version,r.compatible_api_versions_json,r.target_roles_json,r.release_notes,r.package_metadata_json,r.package_path,r.package_size_bytes,r.package_hash_sha256,r.signature,r.artifact_locked_at,r.published_at,r.created_at,c.component_code canonical_component_code,c.display_name canonical_display_name,c.connector_code FROM bh_repo_releases r LEFT JOIN bh_repo_components c ON c.id=r.component_id WHERE r.status='published' AND (r.published_at IS NULL OR r.published_at<=NOW()) ORDER BY r.created_at DESC,r.id DESC")->fetchAll()?:[];
    $channelMap=$registry->channelsForReleaseIds(array_map(static fn(array $r):int=>(int)$r['id'],$rows));
}else{
    // Upgrade-safe read path until the Central applies migration 0042. New publication
    // is intentionally blocked, but already published releases continue to flow.
    $rows=$pdo->query("SELECT r.id,r.release_code,NULL component_id,r.component_type,r.component_code,r.display_name,r.version,r.channel,r.min_core_version,r.max_core_version,r.compatible_api_versions_json,r.target_roles_json,r.release_notes,NULL package_metadata_json,r.package_path,r.package_size_bytes,r.package_hash_sha256,r.signature,NULL artifact_locked_at,r.published_at,r.created_at,NULL canonical_component_code,NULL canonical_display_name,NULL connector_code FROM bh_repo_releases r WHERE r.status='published' AND (r.published_at IS NULL OR r.published_at<=NOW()) ORDER BY r.created_at DESC,r.id DESC")->fetchAll()?:[];
    $channelMap=[];
}
$assignmentMap=[];$dependencyMap=[];
try{
    foreach($pdo->query("SELECT * FROM bh_repo_assignments WHERE (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) ORDER BY id")->fetchAll()?:[] as $a)$assignmentMap[(int)$a['release_id']][]=$a;
    if($registry->available()){
        $depRows=$pdo->query("SELECT d.*,c.component_code canonical_dependency_code FROM bh_repo_release_dependencies d LEFT JOIN bh_repo_components c ON c.id=d.dependency_component_id ORDER BY d.id")->fetchAll()?:[];
        foreach($depRows as $d){$d['dependency_component_code']=(string)($d['canonical_dependency_code']?:$d['dependency_component_code']);unset($d['canonical_dependency_code']);$dependencyMap[(int)$d['release_id']][]=$d;}
    }else{
        foreach($pdo->query('SELECT * FROM bh_repo_release_dependencies ORDER BY id')->fetchAll()?:[] as $d)$dependencyMap[(int)$d['release_id']][]=$d;
    }
}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'repository_manifest_release_policy_prefetch']);}

$assignmentMatches=static function(array $assignments,array $installation,string $currentCore): bool {
    if($assignments===[])return true;
    foreach($assignments as $a){
        $type=(string)($a['assignment_type']??'manual');$criteria=json_decode((string)($a['criteria_json']??''),true);if(!is_array($criteria))$criteria=[];
        if($type==='all_channel')return true;
        if(in_array($type,['installation','manual'],true)){
            if(!empty($a['installation_id'])&&(int)$a['installation_id']===(int)$installation['id'])return true;
            if(trim((string)($a['installation_code']??''))!==''&&hash_equals((string)$a['installation_code'],(string)$installation['installation_code']))return true;
        }
        if($type==='installation_type'){
            $types=array_values(array_filter(array_map('strval',(array)($criteria['installation_types']??$criteria['types']??[]))));
            if($types===[]&&!empty($criteria['installation_type']))$types=[(string)$criteria['installation_type']];
            if(in_array((string)($installation['installation_type']??''),$types,true))return true;
        }
        if($type==='version_range'){
            $min=trim((string)($criteria['min_version']??''));$max=trim((string)($criteria['max_version']??''));
            if(($min===''||version_compare($currentCore,$min,'>='))&&($max===''||version_compare($currentCore,$max,'<=')))return true;
        }
    }
    return false;
};

$releases=[];$excluded=[];$seen=[];$downstreamEdgeReleases=[];

// Build a Core-only candidate pool BEFORE min_core filtering. This lets Central
// explain the complete progressive route (for example .54 -> .55 -> .56 -> .57)
// even though only the first step is immediately installable by the current Node.
$coreCandidates=[];
foreach($rows as $candidate){
    $releaseId=(int)$candidate['id'];$channels=$channelMap[$releaseId]??[(string)$candidate['channel']];$eligible=array_values(array_intersect($channels,$eligibleChannels));
    if($eligible===[])continue;
    $componentCode=strtolower((string)($candidate['canonical_component_code']?:$candidate['component_code']));
    if($componentCode!=='property-hub-core')continue;
    $roles=json_decode((string)($candidate['target_roles_json']??'[]'),true)?:[];
    if($roles!==[]&&!in_array($targetRole,$roles,true))continue;
    if(!$assignmentMatches($assignmentMap[$releaseId]??[],$installation,$currentCore))continue;
    $effectiveChannel=$eligible[0];foreach(['stable','beta','development','pinned'] as $preferred)if(in_array($preferred,$eligible,true)){$effectiveChannel=$preferred;break;}
    $packageMeta=json_decode((string)($candidate['package_metadata_json']??''),true);if(!is_array($packageMeta))$packageMeta=[];
    $coreCandidates[]=['release_code'=>(string)$candidate['release_code'],'component_code'=>'property-hub-core','component_type'=>(string)($candidate['component_type']??'core'),'display_name'=>(string)($candidate['canonical_display_name']?:$candidate['display_name']?:'Property Hub Core'),'version'=>(string)$candidate['version'],'channel'=>$effectiveChannel,'min_core_version'=>(string)($candidate['min_core_version']??''),'max_core_version'=>(string)($candidate['max_core_version']??''),'package_hash_sha256'=>(string)($candidate['package_hash_sha256']??''),'package_size_bytes'=>(int)($candidate['package_size_bytes']??0),'release_notes'=>(string)($candidate['release_notes']??''),'repair_bridge'=>!empty($packageMeta['repair_bridge'])||((string)($packageMeta['update_path_role']??'')==='repair_bridge')];
}
usort($coreCandidates,static fn(array $a,array $b):int=>version_compare((string)$a['version'],(string)$b['version']));
$latestCorePublished=null;foreach($coreCandidates as $c)if($latestCorePublished===null||version_compare((string)$c['version'],(string)$latestCorePublished['version'],'>'))$latestCorePublished=$c;
$corePath=[];$simulatedCore=$currentCore;$guard=0;
while($guard++<100){
    $reachable=[];
    foreach($coreCandidates as $c){
        if(version_compare((string)$c['version'],$simulatedCore,'<=') )continue;
        $min=trim((string)($c['min_core_version']??''));$max=trim((string)($c['max_core_version']??''));
        if($min!==''&&version_compare($simulatedCore,$min,'<'))continue;
        if($max!==''&&version_compare($simulatedCore,$max,'>'))continue;
        $reachable[]=$c;
    }
    if($reachable===[])break;
    // Repair bridges are deliberately installed before a normal Core even when a
    // numerically higher Core is already reachable. They exist to repair the update
    // engine itself (for example 0.14.56.1) before a migration-bearing release is
    // attempted. Without this priority, the planner would skip the bridge and repeat
    // the same failure forever.
    $repairReachable=array_values(array_filter($reachable,static fn(array $c):bool=>!empty($c['repair_bridge'])));
    if($repairReachable!==[]){
        usort($repairReachable,static fn(array $a,array $b):int=>version_compare((string)$a['version'],(string)$b['version']));
        $next=$repairReachable[0];
    }else{
        // Take the highest normal release actually reachable from this simulated step.
        usort($reachable,static fn(array $a,array $b):int=>version_compare((string)$b['version'],(string)$a['version']));
        $next=$reachable[0];
    }
    $corePath[]=$next;$simulatedCore=(string)$next['version'];
    if($latestCorePublished!==null&&version_compare($simulatedCore,(string)$latestCorePublished['version'],'>='))break;
}
$nextCore=$corePath[0]??null;$corePathComplete=$latestCorePublished===null||($corePath!==[]&&version_compare((string)$corePath[array_key_last($corePath)]['version'],(string)$latestCorePublished['version'],'>='))||version_compare($currentCore,(string)$latestCorePublished['version'],'>=');

foreach($rows as $row){
    $releaseId=(int)$row['id'];$channels=$channelMap[$releaseId]??[(string)$row['channel']];$eligible=array_values(array_intersect($channels,$eligibleChannels));
    if($eligible===[]){$excluded[]=['release_code'=>$row['release_code'],'version'=>$row['version'],'reason'=>'channel','release_channels'=>$channels,'eligible_channels'=>$eligibleChannels];continue;}
    $effectiveChannel=$eligible[0];foreach(['stable','beta','development','pinned'] as $preferred)if(in_array($preferred,$eligible,true)){$effectiveChannel=$preferred;break;}
    $componentType=strtolower((string)($row['component_type']??''));$componentCode=strtolower((string)($row['canonical_component_code']?:$row['component_code']));$displayName=(string)($row['canonical_display_name']?:$row['display_name']);
    $roles=json_decode((string)($row['target_roles_json']??'[]'),true)?:[];
    if($roles!==[] && !in_array($targetRole,$roles,true)){
        $isConnector=$componentType==='connector'||str_starts_with($componentCode,'connector-');
        $edgeOnly=$isConnector&&$targetRole==='building_node'&&in_array('edge_agent',$roles,true);
        $reason=$edgeOnly?'edge_only_component':'target_role';
        if($edgeOnly){
            $connectorCode=DesiredConnectorPolicy::canonical((string)($row['connector_code']??''));if($connectorCode==='')$connectorCode=ComponentRegistryService::derivedConnectorCode($componentCode);
            $desired=$desiredConnectors[$connectorCode]??null;
            if(is_array($desired)&&!empty($desired['enabled'])&&DesiredConnectorPolicy::matchesRelease($desired,(string)$row['version'])){
                $candidate=[
                    'release_code'=>(string)$row['release_code'],'component_code'=>$componentCode,'component_type'=>$componentType,'display_name'=>$displayName,
                    'connector_code'=>$connectorCode,'version'=>(string)$row['version'],'channel'=>$effectiveChannel,'target_role'=>'edge_agent',
                    'min_core_version'=>(string)($row['min_core_version']??''),'max_core_version'=>(string)($row['max_core_version']??''),
                    'package_hash_sha256'=>(string)($row['package_hash_sha256']??''),'package_size_bytes'=>(int)($row['package_size_bytes']??0),
                    'release_notes'=>(string)($row['release_notes']??''),'status'=>'edge_distribution_candidate',
                    'version_policy'=>(string)($desired['version_policy']??'latest_compatible'),'desired_version'=>(string)($desired['version']??''),
                    'version_constraint'=>(string)($desired['version_constraint']??''),
                ];
                if(!isset($downstreamEdgeReleases[$componentCode])||version_compare((string)$candidate['version'],(string)$downstreamEdgeReleases[$componentCode]['version'],'>'))$downstreamEdgeReleases[$componentCode]=$candidate;
            }
        }
        $excluded[]=['release_code'=>$row['release_code'],'version'=>$row['version'],'reason'=>$reason,'component_code'=>$componentCode,'target_roles'=>$roles]+($edgeOnly?['downstream_candidate'=>true]:[]);continue;
    }
    if(!$assignmentMatches($assignmentMap[$releaseId]??[],$installation,$currentCore)){
        $excluded[]=['release_code'=>$row['release_code'],'version'=>$row['version'],'reason'=>'assignment_policy','component_code'=>$componentCode];continue;
    }
    if($componentType==='connector'||str_starts_with($componentCode,'connector-')){
        $connectorCode=DesiredConnectorPolicy::canonical((string)($row['connector_code']??''));if($connectorCode==='')$connectorCode=ComponentRegistryService::derivedConnectorCode($componentCode);
        $desired=$desiredConnectors[$connectorCode]??null;
        if(!is_array($desired)||empty($desired['enabled'])){
            $excluded[]=['release_code'=>$row['release_code'],'version'=>$row['version'],'reason'=>'connector_not_enabled_for_installation','connector_code'=>$connectorCode,'desired_connector_codes'=>(array)$desiredState['codes'],'desired_connector_source'=>(string)$desiredState['source']];continue;
        }
        if(!DesiredConnectorPolicy::matchesRelease($desired,(string)$row['version'])){
            $excluded[]=['release_code'=>$row['release_code'],'version'=>$row['version'],'reason'=>'desired_version_policy','connector_code'=>$connectorCode,'version_policy'=>(string)($desired['version_policy']??'latest_compatible'),'desired_version'=>(string)($desired['version']??''),'version_constraint'=>(string)($desired['version_constraint']??'')];continue;
        }
    }
    // Expose exactly one future Core step at a time. This makes the signed manifest
    // itself enforce the progressive path and, critically, lets a repair bridge win
    // over a higher migration-bearing Core. Older/current Core rows remain harmless.
    if($componentCode==='property-hub-core'&&$nextCore!==null&&version_compare((string)$row['version'],$currentCore,'>')&&!hash_equals((string)$row['release_code'],(string)$nextCore['release_code'])){
        $excluded[]=['release_code'=>$row['release_code'],'version'=>$row['version'],'reason'=>'progressive_core_step','current'=>$currentCore,'next_core_version'=>(string)$nextCore['version']];continue;
    }
    if(!empty($row['min_core_version']) && version_compare($currentCore,(string)$row['min_core_version'],'<')){$isCore=$componentCode==='property-hub-core';$excluded[]=['release_code'=>$row['release_code'],'version'=>$row['version'],'reason'=>'min_core','required'=>$row['min_core_version'],'current'=>$currentCore,'progressive_core_step'=>$isCore,'next_core_version'=>(string)($nextCore['version']??'')];continue;}
    if(!empty($row['max_core_version']) && version_compare($currentCore,(string)$row['max_core_version'],'>')){$excluded[]=['release_code'=>$row['release_code'],'version'=>$row['version'],'reason'=>'max_core','required'=>$row['max_core_version'],'current'=>$currentCore];continue;}
    $dedupe=$componentCode.'@'.(string)$row['version'];if(isset($seen[$dedupe]))continue;$seen[$dedupe]=true;
    $release=$row;$release['component_code']=$componentCode;$release['display_name']=$displayName;$release['connector_code']=DesiredConnectorPolicy::canonical((string)($row['connector_code']??''));$release['channel']=$effectiveChannel;$release['release_channels']=$channels;$release['dependencies']=array_values(array_map(static function(array $d):array{return ['component_code'=>(string)$d['dependency_component_code'],'version_constraint'=>(string)$d['version_constraint'],'required'=>!empty($d['required'])];},$dependencyMap[$releaseId]??[]));
    $release['compatible_api_versions']=json_decode((string)($release['compatible_api_versions_json']??'[]'),true)?:[];unset($release['compatible_api_versions_json'],$release['target_roles_json'],$release['canonical_component_code'],$release['canonical_display_name'],$release['created_at'],$release['id']);
    $releases[]=$release;
}

$base=rtrim((string)config('deployment.repository_public_url'),'/');
foreach($releases as &$release)$release['package_url']=$release['package_path']?$base.'/api/repository/v1/package.php?release='.rawurlencode((string)$release['release_code']):null;unset($release);
$downstreamEdgeReleases=array_values($downstreamEdgeReleases);
usort($downstreamEdgeReleases,static fn(array $a,array $b):int=>strcmp((string)$a['component_code'],(string)$b['component_code'])?:version_compare((string)$b['version'],(string)$a['version']));
$latestCoreCompatible=null;foreach($releases as $row){if(($row['component_code']??'')!=='property-hub-core')continue;if($latestCoreCompatible===null||version_compare((string)$row['version'],(string)$latestCoreCompatible['version'],'>'))$latestCoreCompatible=['version'=>$row['version'],'channel'=>$row['channel'],'release_code'=>$row['release_code'],'min_core_version'=>$row['min_core_version']];}
$diagnostics=['central_version'=>(string)config('app.version'),'installation_role'=>$targetRole,'caller_role'=>$callerRole,'downstream_request'=>$requestedDownstreamRole!=='','node_version'=>$currentCore,'node_channel'=>$channel,'eligible_channels'=>$eligibleChannels,'published_in_repository'=>count($rows),'compatible_releases'=>count($releases),'excluded_count'=>count($excluded),'excluded'=>$excluded,'latest_core'=>$latestCorePublished,'latest_core_published'=>$latestCorePublished,'latest_core_compatible'=>$latestCoreCompatible,'next_core'=>$nextCore,'core_update_path'=>$corePath,'core_update_path_complete'=>$corePathComplete,'batch_update_supported'=>true,'batch_update_strategy'=>'download_all_then_apply_sequentially','downstream_edge_releases'=>$downstreamEdgeReleases,'downstream_edge_candidate_count'=>count($downstreamEdgeReleases),'downstream_edge_pending_count'=>count($downstreamEdgeReleases),'downstream_edge_pending_semantics'=>'legacy_candidate_count_node_reconciles_inventory','official_core_sync'=>$sync,'desired_connectors'=>(array)$desiredState['items'],'desired_connector_codes'=>(array)$desiredState['codes'],'desired_connector_source'=>(string)$desiredState['source'],'desired_connector_materialized'=>!empty($desiredState['materialized']),'desired_connector_reconciled'=>!empty($desiredState['reconciled'])];
$manifest=['schema'=>'2cam-building-repository-manifest-v1','generated_at'=>date(DATE_ATOM),'repository_version'=>(string)config('app.version'),'installation_uuid'=>$requestedDownstreamRole!==''?(string)($input['downstream_installation_uuid']??''):(string)$installation['installation_uuid'],'installation_role'=>$targetRole,'requested_by_installation_uuid'=>(string)$installation['installation_uuid'],'requested_by_installation_code'=>(string)($installation['installation_code']??''),'distribution_mode'=>$requestedDownstreamRole!==''?'central_to_node_to_edge':'central_to_node','desired_connectors'=>array_values($desiredConnectors),'channel'=>$channel,'eligible_channels'=>$eligibleChannels,'current'=>['app_version'=>(string)($input['app_version']??''),'api_version'=>(string)($input['api_version']??''),'ui_version'=>(string)($input['ui_version']??'')],'diagnostics'=>$diagnostics,'releases'=>$releases];
$signed=ManifestSigner::sign($manifest);
$pdo->prepare("UPDATE bh_repo_installations SET last_manifest_at=NOW(),last_seen_at=NOW() WHERE id=?")->execute([(int)$installation['id']]);
$pdo->prepare("INSERT INTO bh_repo_delivery_logs (installation_id,event_type,status,remote_ip,details_json) VALUES (?,'manifest_requested','ok',?,?)")->execute([(int)$installation['id'],(string)($_SERVER['REMOTE_ADDR']??''),json_encode(['release_count'=>count($releases),'eligible_channels'=>$eligibleChannels,'installation_role'=>$targetRole,'node_app_version'=>$currentCore,'excluded'=>$excluded,'official_core_sync'=>$sync],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
api_response(['ok'=>true]+$signed);
