<?php
declare(strict_types=1);

/**
 * Limpieza controlada del registro de Property Hub Central.
 *
 * Solo permite eliminar físicamente expedientes marcados como Demo, Prueba o
 * Laboratorio. Las instalaciones de Producción quedan protegidas. Los backups
 * cifrados del Vault se conservan salvo confirmación explícita.
 */
final class CentralCleanupService
{
    private PDO $pdo;
    private RepositoryService $repository;

    public function __construct(?PDO $pdo=null)
    {
        $this->pdo=$pdo ?? Connection::pdo();
        $this->repository=new RepositoryService($this->pdo);
    }

    public function preview(): array
    {
        $this->assertCentral();
        $uuid=(string)config('deployment.installation_uuid','');
        $stmt=$this->pdo->prepare("SELECT * FROM bh_repo_installations WHERE status<>'retired' AND installation_uuid<>? ORDER BY CASE WHEN instance_role='building_node' THEN 0 ELSE 1 END,installation_name,installation_code");
        $stmt->execute([$uuid]);
        $rows=$stmt->fetchAll()?:[];
        $candidates=[];$protected=[];
        foreach($rows as $row){
            $row=$this->withCounts($row);
            $isCandidate=!empty($row['is_demo'])||in_array((string)($row['environment_type']??'production'),['test','lab'],true);
            if($isCandidate)$candidates[]=$row;else $protected[]=$row;
        }

        $plans=$this->pdo->query(
            "SELECT p.id,p.installation_code,p.installation_name,p.environment_type,p.status,p.created_at,t.status token_status,t.expires_at " .
            "FROM bh_repo_installation_plans p LEFT JOIN bh_repo_registration_tokens t ON t.id=p.registration_token_id " .
            "WHERE p.registered_installation_id IS NULL AND (p.environment_type IN ('test','lab') OR p.installation_code LIKE 'DEMO-%') AND p.status<>'registered' ORDER BY p.created_at DESC"
        )->fetchAll()?:[];

        $tokens=$this->pdo->query(
            "SELECT " .
            "SUM(CASE WHEN status='active' AND (expires_at IS NULL OR expires_at>=NOW()) AND used_count<max_uses THEN 1 ELSE 0 END) active_tokens," .
            "SUM(CASE WHEN status<>'active' OR (expires_at IS NOT NULL AND expires_at<NOW()) OR used_count>=max_uses THEN 1 ELSE 0 END) inactive_tokens," .
            "SUM(CASE WHEN installation_id IS NULL THEN 1 ELSE 0 END) unbound_tokens " .
            "FROM bh_repo_registration_tokens"
        )->fetch()?:[];

        return [
            'candidates'=>$candidates,
            'protected'=>$protected,
            'plans'=>$plans,
            'tokens'=>$tokens,
            'central_code'=>(string)config('app.instance_code',''),
        ];
    }

    public function cleanup(array $installationIds,string $confirmation,bool $purgeOffsite=false,bool $ackActive=false,bool $cleanupPlans=true,bool $pruneTokens=true): array
    {
        $this->assertCentral();
        $expected=(string)config('app.instance_code','');
        if($expected===''||!hash_equals($expected,trim($confirmation)))throw new InvalidArgumentException('El código de confirmación no coincide con Property Hub Central.');

        $ids=array_values(array_unique(array_filter(array_map('intval',$installationIds),static fn(int $v):bool=>$v>0)));
        $selected=[];
        if($ids){
            $marks=implode(',',array_fill(0,count($ids),'?'));
            $stmt=$this->pdo->prepare("SELECT * FROM bh_repo_installations WHERE id IN ({$marks})");
            $stmt->execute($ids);
            foreach($stmt->fetchAll()?:[] as $row)$selected[(int)$row['id']]=$row;
            if(count($selected)!==count($ids))throw new RuntimeException('Una o más instalaciones seleccionadas ya no existen. Recargue la limpieza antes de continuar.');

            foreach($selected as $row){
                $environment=(string)($row['environment_type']??'production');
                if(empty($row['is_demo'])&&!in_array($environment,['test','lab'],true)){
                    throw new RuntimeException('La instalación '.(string)$row['installation_code'].' es Producción y está protegida contra eliminación física.');
                }
                if((string)($row['instance_role']??'building_node')==='building_node'){
                    $children=$this->pdo->prepare("SELECT id,installation_code FROM bh_repo_installations WHERE parent_installation_code=? AND instance_role='edge_agent' AND status<>'retired'");
                    $children->execute([(string)$row['installation_code']]);
                    foreach($children->fetchAll()?:[] as $child){
                        if(!isset($selected[(int)$child['id']])){
                            throw new RuntimeException('Para eliminar el Property Node '.(string)$row['installation_code'].' seleccione también su Edge Agent '.(string)$child['installation_code'].' o conserve ambos.');
                        }
                    }
                }
            }
        }

        // Primero Agents, luego Nodes: evita dejar padres eliminados durante el lote.
        uasort($selected,static fn(array $a,array $b):int=>((string)($a['instance_role']??'building_node')==='edge_agent'?0:1)<=>((string)($b['instance_role']??'building_node')==='edge_agent'?0:1));
        $removed=[];$errors=[];$offsiteFiles=0;
        foreach($selected as $row){
            try{
                if((string)($row['instance_role']??'building_node')==='building_node'){
                    $remaining=$this->pdo->prepare("SELECT installation_code FROM bh_repo_installations WHERE parent_installation_code=? AND instance_role='edge_agent' AND status<>'retired' LIMIT 1");
                    $remaining->execute([(string)$row['installation_code']]);
                    $child=$remaining->fetchColumn();
                    if($child!==false)throw new RuntimeException('No se eliminó el Node porque todavía existe su Edge Agent '.(string)$child.'. Elimine/corrija primero ese Agent.');
                }
                $result=$this->repository->deleteTestInstallation((int)$row['id'],(string)$row['installation_code'],$purgeOffsite,$ackActive);
                $removed[]=(string)$result['code'];$offsiteFiles+=(int)($result['offsite_files']??0);
            }catch(Throwable $e){
                ErrorReporter::report($e,['operation'=>'central_cleanup_installation','installation_code'=>(string)($row['installation_code']??''),'installation_id'=>(int)($row['id']??0)]);
                $errors[]=['code'=>(string)($row['installation_code']??''),'message'=>$e->getMessage()];
            }
        }

        $removedPlans=0;
        if($cleanupPlans)$removedPlans=$this->cleanupUnusedTestPlans();
        $removedTokens=0;
        if($pruneTokens)$removedTokens=$this->pruneInactiveOrphanTokens();

        return [
            'removed_installations'=>$removed,
            'errors'=>$errors,
            'removed_plans'=>$removedPlans,
            'removed_tokens'=>$removedTokens,
            'removed_offsite_files'=>$offsiteFiles,
            'vault_preserved'=>!$purgeOffsite,
        ];
    }

    private function cleanupUnusedTestPlans(): int
    {
        $this->pdo->beginTransaction();
        try{
            $stmt=$this->pdo->query("SELECT id,registration_token_id FROM bh_repo_installation_plans WHERE registered_installation_id IS NULL AND (environment_type IN ('test','lab') OR installation_code LIKE 'DEMO-%') AND status<>'registered' FOR UPDATE");
            $rows=$stmt->fetchAll()?:[];$tokenIds=[];
            foreach($rows as $row)if((int)($row['registration_token_id']??0)>0)$tokenIds[]=(int)$row['registration_token_id'];
            $removed=(int)$this->pdo->exec("DELETE FROM bh_repo_installation_plans WHERE registered_installation_id IS NULL AND (environment_type IN ('test','lab') OR installation_code LIKE 'DEMO-%') AND status<>'registered'");
            foreach(array_unique($tokenIds) as $tokenId){
                $del=$this->pdo->prepare("DELETE FROM bh_repo_registration_tokens WHERE id=? AND installation_id IS NULL");$del->execute([$tokenId]);
            }
            $this->pdo->commit();return $removed;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    private function pruneInactiveOrphanTokens(): int
    {
        $sql="DELETE t FROM bh_repo_registration_tokens t LEFT JOIN bh_repo_installation_plans p ON p.registration_token_id=t.id " .
            "WHERE t.installation_id IS NULL AND p.id IS NULL AND (t.status IN ('revoked','expired') OR t.used_count>=t.max_uses OR (t.expires_at IS NOT NULL AND t.expires_at<NOW()))";
        return (int)$this->pdo->exec($sql);
    }

    private function withCounts(array $row): array
    {
        $id=(int)$row['id'];$uuid=(string)$row['installation_uuid'];
        $row['recently_active']=false;
        if(!empty($row['last_seen_at'])){$ts=strtotime((string)$row['last_seen_at']);$row['recently_active']=$ts!==false&&$ts>=time()-300;}
        $row['metrics_count']=$this->countById('bh_repo_installation_metrics','installation_id',$id);
        $row['connector_count']=$this->countById('bh_repo_connector_inventory','installation_id',$id);
        $row['delivery_count']=$this->countById('bh_repo_delivery_logs','installation_id',$id);
        $row['assignment_count']=$this->countById('bh_repo_assignments','installation_id',$id);
        $row['token_count']=$this->countById('bh_repo_registration_tokens','installation_id',$id);
        $row['backup_request_count']=$this->countById('bh_repo_backup_requests','installation_id',$id);
        $row['offsite_count']=$this->countByString('bh_backup_offsite_archives','installation_uuid',$uuid,"status<>'deleted'");
        return $row;
    }

    private function countById(string $table,string $column,int $value): int
    {
        if(!$this->tableExists($table))return 0;$stmt=$this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column}=?");$stmt->execute([$value]);return (int)$stmt->fetchColumn();
    }

    private function countByString(string $table,string $column,string $value,string $extra='1=1'): int
    {
        if(!$this->tableExists($table))return 0;$stmt=$this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column}=? AND {$extra}");$stmt->execute([$value]);return (int)$stmt->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt=$this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');$stmt->execute([$table]);return $stmt->fetchColumn()!==false;
    }

    private function assertCentral(): void
    {
        if(!InstallationMode::isCentral())throw new RuntimeException('Esta herramienta pertenece exclusivamente a Property Hub Central.');
    }
}
