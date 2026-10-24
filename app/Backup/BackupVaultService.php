<?php
declare(strict_types=1);

final class BackupVaultService
{
    public function __construct(private ?PDO $pdo=null) { $this->pdo ??= Connection::pdo(); }

    public function settings(): array
    {
        $this->assertCentral();$row=$this->pdo->query("SELECT * FROM bh_repo_backup_vault_settings WHERE id=1 LIMIT 1")->fetch();return is_array($row)?$row:['enabled'=>1,'schedule_frequency'=>'daily','schedule_time'=>'03:00:00','schedule_weekday'=>0,'retention_per_installation'=>30];
    }

    public function saveSettings(array $input): void
    {
        $this->assertCentral();$frequency=in_array((string)($input['schedule_frequency']??''),['manual','daily','weekly'],true)?(string)$input['schedule_frequency']:'daily';$time=preg_match('/^\d{2}:\d{2}$/',(string)($input['schedule_time']??''))?(string)$input['schedule_time'].':00':'03:00:00';$weekday=min(6,max(0,(int)($input['schedule_weekday']??0)));$retention=min(365,max(1,(int)($input['retention_per_installation']??30)));
        $this->pdo->prepare("UPDATE bh_repo_backup_vault_settings SET enabled=?,schedule_frequency=?,schedule_time=?,schedule_weekday=?,retention_per_installation=? WHERE id=1")->execute([!empty($input['enabled'])?1:0,$frequency,$time,$weekday,$retention]);
    }

    public function scheduleDue(): array
    {
        $this->assertCentral();$due=false;$settings=[];
        $this->pdo->beginTransaction();
        try{
            $row=$this->pdo->query("SELECT * FROM bh_repo_backup_vault_settings WHERE id=1 FOR UPDATE")->fetch();$settings=is_array($row)?$row:[];
            if(!empty($settings['enabled'])&&($settings['schedule_frequency']??'manual')!=='manual'){
                $now=new DateTimeImmutable('now');$scheduled=(string)($settings['schedule_time']??'03:00:00');[$h,$m]=array_map('intval',array_slice(array_pad(explode(':',$scheduled),2,'0'),0,2));$after=((int)$now->format('G')>$h)||((int)$now->format('G')===$h&&(int)$now->format('i')>=$m);$weeklyOk=($settings['schedule_frequency']??'daily')!=='weekly'||(int)$now->format('w')===(int)($settings['schedule_weekday']??0);$last=!empty($settings['last_scheduled_at'])?new DateTimeImmutable((string)$settings['last_scheduled_at']):null;$already=$last&&$last->format('Y-m-d')===$now->format('Y-m-d');$due=$after&&$weeklyOk&&!$already;
                if($due)$this->pdo->exec("UPDATE bh_repo_backup_vault_settings SET last_scheduled_at=NOW() WHERE id=1");
            }
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        if(!$due)return ['due'=>false];$queued=$this->queueAll(null);return ['due'=>true]+$queued;
    }

    public function overview(): array
    {
        $this->assertCentral();
        $rows=$this->pdo->query(
            "SELECT i.id,i.installation_uuid,i.installation_code,i.installation_name,i.installation_type,i.instance_role,i.parent_installation_code,i.environment_type,i.status,i.health_status,i.last_seen_at,i.app_version,"
            ."(SELECT COUNT(*) FROM bh_backup_offsite_archives a WHERE a.installation_uuid=i.installation_uuid AND a.status='stored') archive_count,"
            ."(SELECT COALESCE(SUM(a.size_bytes),0) FROM bh_backup_offsite_archives a WHERE a.installation_uuid=i.installation_uuid AND a.status='stored') archive_bytes,"
            ."(SELECT a.received_at FROM bh_backup_offsite_archives a WHERE a.installation_uuid=i.installation_uuid AND a.status='stored' ORDER BY a.id DESC LIMIT 1) last_archive_at,"
            ."(SELECT a.filename FROM bh_backup_offsite_archives a WHERE a.installation_uuid=i.installation_uuid AND a.status='stored' ORDER BY a.id DESC LIMIT 1) last_archive_filename,"
            ."(SELECT r.status FROM bh_repo_backup_requests r WHERE r.installation_id=i.id ORDER BY r.id DESC LIMIT 1) last_request_status,"
            ."(SELECT r.requested_at FROM bh_repo_backup_requests r WHERE r.installation_id=i.id ORDER BY r.id DESC LIMIT 1) last_request_at "
            ."FROM bh_repo_installations i WHERE i.status<>'retired' AND i.is_demo=0 ORDER BY i.installation_name"
        )->fetchAll()?:[];
        $archives=$this->pdo->query(
            "SELECT a.*,i.id installation_id,i.installation_name,i.instance_role registry_role,i.parent_installation_code registry_parent_code,i.environment_type FROM bh_backup_offsite_archives a LEFT JOIN bh_repo_installations i ON i.installation_uuid=a.installation_uuid WHERE a.status='stored' ORDER BY a.received_at DESC LIMIT 100"
        )->fetchAll()?:[];
        $requests=$this->pdo->query(
            "SELECT r.*,i.installation_code,i.installation_name,i.instance_role,i.parent_installation_code FROM bh_repo_backup_requests r JOIN bh_repo_installations i ON i.id=r.installation_id ORDER BY r.requested_at DESC LIMIT 80"
        )->fetchAll()?:[];
        $stats=$this->pdo->query(
            "SELECT COUNT(*) archives,COALESCE(SUM(size_bytes),0) bytes,COUNT(DISTINCT installation_uuid) protected_installations FROM bh_backup_offsite_archives WHERE status='stored'"
        )->fetch()?:[];
        $stats['pending_requests']=(int)$this->pdo->query("SELECT COUNT(*) FROM bh_repo_backup_requests WHERE status IN ('queued','delivered','running')")->fetchColumn();
        return ['installations'=>$rows,'archives'=>$archives,'requests'=>$requests,'stats'=>$stats,'settings'=>$this->settings()];
    }

    public function installationSummary(int $installationId): array
    {
        $this->assertCentral();
        $stmt=$this->pdo->prepare("SELECT i.installation_uuid,i.installation_code,(SELECT COUNT(*) FROM bh_backup_offsite_archives a WHERE a.installation_uuid=i.installation_uuid AND a.status='stored') archive_count,(SELECT COALESCE(SUM(a.size_bytes),0) FROM bh_backup_offsite_archives a WHERE a.installation_uuid=i.installation_uuid AND a.status='stored') archive_bytes,(SELECT a.received_at FROM bh_backup_offsite_archives a WHERE a.installation_uuid=i.installation_uuid AND a.status='stored' ORDER BY a.id DESC LIMIT 1) last_archive_at,(SELECT r.status FROM bh_repo_backup_requests r WHERE r.installation_id=i.id ORDER BY r.id DESC LIMIT 1) last_request_status FROM bh_repo_installations i WHERE i.id=? LIMIT 1");
        $stmt->execute([$installationId]);return (array)($stmt->fetch()?:[]);
    }

    public function queueBackup(int $installationId,?int $userId=null): array
    {
        $this->assertCentral();
        $stmt=$this->pdo->prepare("SELECT id,installation_code,installation_name,status,last_seen_at FROM bh_repo_installations WHERE id=? AND status NOT IN ('retired','suspended') LIMIT 1");$stmt->execute([$installationId]);$row=$stmt->fetch();
        if(!is_array($row))throw new RuntimeException('Instalación no disponible para solicitar respaldo.');
        $dup=$this->pdo->prepare("SELECT request_uuid,status FROM bh_repo_backup_requests WHERE installation_id=? AND status IN ('queued','delivered','running') ORDER BY id DESC LIMIT 1");$dup->execute([$installationId]);$existing=$dup->fetch();
        if(is_array($existing))return ['queued'=>false,'duplicate'=>true,'request_uuid'=>(string)$existing['request_uuid'],'status'=>(string)$existing['status'],'installation_code'=>(string)$row['installation_code']];
        $uuid=self::uuid();
        $this->pdo->prepare("INSERT INTO bh_repo_backup_requests (request_uuid,installation_id,requested_by) VALUES (?,?,?)")->execute([$uuid,$installationId,$userId]);
        return ['queued'=>true,'duplicate'=>false,'request_uuid'=>$uuid,'status'=>'queued','installation_code'=>(string)$row['installation_code']];
    }

    public function queueAll(?int $userId=null): array
    {
        $this->assertCentral();
        $ids=$this->pdo->query("SELECT id FROM bh_repo_installations WHERE status NOT IN ('retired','suspended') AND is_demo=0 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN)?:[];
        $queued=0;$existing=0;$items=[];
        foreach($ids as $id){$r=$this->queueBackup((int)$id,$userId);$items[]=$r;!empty($r['queued'])?$queued++:$existing++;}
        return ['total'=>count($ids),'queued'=>$queued,'existing'=>$existing,'items'=>$items];
    }

    public function pendingTasks(int $installationId,int $limit=2): array
    {
        $this->assertCentral();
        $this->pdo->beginTransaction();
        try{
            $retry=$this->pdo->prepare("UPDATE bh_repo_backup_requests SET status='queued',delivered_at=NULL,message='Reencolada automáticamente: el Node no confirmó la entrega anterior.' WHERE installation_id=? AND status='delivered' AND delivered_at<DATE_SUB(NOW(),INTERVAL 1 HOUR)");$retry->execute([$installationId]);
            $stmt=$this->pdo->prepare("SELECT id,request_uuid,request_type,requested_at FROM bh_repo_backup_requests WHERE installation_id=? AND status='queued' ORDER BY id LIMIT ".max(1,min(5,$limit))." FOR UPDATE");$stmt->execute([$installationId]);$rows=$stmt->fetchAll()?:[];
            if($rows){$ids=array_map(static fn(array $r):int=>(int)$r['id'],$rows);$this->pdo->exec("UPDATE bh_repo_backup_requests SET status='delivered',delivered_at=NOW() WHERE id IN (".implode(',',$ids).")");}
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        return array_map(static fn(array $r):array=>['request_uuid'=>(string)$r['request_uuid'],'type'=>'backup_to_central','requested_at'=>(string)$r['requested_at']],$rows??[]);
    }

    public function completeTask(int $installationId,string $requestUuid,string $status,array $details=[]): void
    {
        $this->assertCentral();
        $status=$status==='completed'?'completed':'failed';
        $stmt=$this->pdo->prepare("UPDATE bh_repo_backup_requests SET status=?,completed_at=NOW(),message=?,details_json=? WHERE installation_id=? AND request_uuid=? AND status IN ('queued','delivered','running')");
        $stmt->execute([$status,(string)($details['message']??($status==='completed'?'Respaldo recibido por Central.':'El nodo informó un error al generar o enviar el respaldo.')),json_encode($details,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$installationId,$requestUuid]);
    }

    public function linkUploadedArchive(int $installationId,string $requestUuid,int $archiveId,array $details=[]): void
    {
        if($requestUuid==='')return;
        $stmt=$this->pdo->prepare("UPDATE bh_repo_backup_requests SET status='completed',source_archive_id=?,completed_at=NOW(),message='Respaldo cifrado recibido y verificado por Central.',details_json=? WHERE installation_id=? AND request_uuid=? AND status IN ('queued','delivered','running')");
        $stmt->execute([$archiveId,json_encode($details,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$installationId,$requestUuid]);
    }

    public function issueRecoveryToken(int $archiveId,?int $userId=null,int $hours=24): array
    {
        $this->assertCentral();
        $stmt=$this->pdo->prepare("SELECT * FROM bh_backup_offsite_archives WHERE id=? AND status='stored' LIMIT 1");$stmt->execute([$archiveId]);$archive=$stmt->fetch();
        if(!is_array($archive)||!is_file((string)$archive['storage_path']))throw new RuntimeException('La copia seleccionada no está disponible en el Vault.');
        $plain='PHREC-'.strtoupper(bin2hex(random_bytes(18)));$hash=hash('sha256',$plain);$expires=date('Y-m-d H:i:s',time()+max(1,min(72,$hours))*3600);
        $this->pdo->prepare("INSERT INTO bh_repo_recovery_tokens (token_hash,archive_id,installation_uuid,expires_at,created_by) VALUES (?,?,?,?,?)")->execute([$hash,$archiveId,(string)$archive['installation_uuid'],$expires,$userId]);
        return ['token'=>$plain,'expires_at'=>$expires,'archive'=>$archive];
    }

    public function consumeRecoveryToken(string $plain,string $remoteIp=''): array
    {
        $this->assertCentral();$hash=hash('sha256',trim($plain));
        $this->pdo->beginTransaction();
        try{
            $stmt=$this->pdo->prepare("SELECT t.*,a.filename,a.storage_path,a.size_bytes,a.sha256,a.installation_code FROM bh_repo_recovery_tokens t JOIN bh_backup_offsite_archives a ON a.id=t.archive_id WHERE t.token_hash=? FOR UPDATE");$stmt->execute([$hash]);$row=$stmt->fetch();
            if(!is_array($row))throw new RuntimeException('Token de recuperación inválido.');
            if((string)$row['status']!=='active')throw new RuntimeException('El token de recuperación ya no está activo.');
            if(strtotime((string)$row['expires_at'])<time()){$this->pdo->prepare("UPDATE bh_repo_recovery_tokens SET status='expired' WHERE id=?")->execute([(int)$row['id']]);throw new RuntimeException('El token de recuperación expiró.');}
            if(!is_file((string)$row['storage_path']))throw new RuntimeException('El archivo de recuperación ya no está disponible en Central.');
            $this->pdo->prepare("UPDATE bh_repo_recovery_tokens SET status='used',used_at=NOW(),used_ip=? WHERE id=?")->execute([$remoteIp,(int)$row['id']]);
            $this->pdo->commit();return $row;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function pruneInstallationArchives(string $installationUuid): int
    {
        $this->assertCentral();$keep=(int)($this->settings()['retention_per_installation']??30);$stmt=$this->pdo->prepare("SELECT id,storage_path FROM bh_backup_offsite_archives WHERE installation_uuid=? AND status='stored' ORDER BY received_at DESC,id DESC");$stmt->execute([$installationUuid]);$rows=$stmt->fetchAll()?:[];$remove=array_slice($rows,max(1,$keep));$count=0;
        if($remove){$up=$this->pdo->prepare("UPDATE bh_backup_offsite_archives SET status='deleted' WHERE id=?");foreach($remove as $r){$up->execute([(int)$r['id']]);$path=(string)($r['storage_path']??'');if($path!==''&&is_file($path))@unlink($path);$count++;}}
        return $count;
    }

    private function assertCentral(): void { if(!InstallationMode::isCentral())throw new RuntimeException('Backup Vault solo está disponible en Property Hub Central.'); }
    private static function uuid(): string { $d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4)); }
}
