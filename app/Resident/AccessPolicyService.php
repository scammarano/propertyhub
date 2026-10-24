<?php
declare(strict_types=1);

final class AccessPolicyService
{
    private int $installationId;
    public function __construct(private PDO $pdo)
    {
        $q=$pdo->prepare('SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1');
        $q->execute([(string)config('deployment.installation_uuid')]);
        $this->installationId=(int)$q->fetchColumn();
        if($this->installationId<1) throw new RuntimeException('No se encontró la instalación local.');
    }

    public function profiles(): array
    {
        $q=$this->pdo->prepare("SELECT p.*,sp.name scope_property_name,parent.name parent_name,(SELECT COUNT(*) FROM bh_property_memberships m WHERE m.access_profile_id=p.id) membership_count FROM bh_access_profiles p LEFT JOIN bh_properties sp ON sp.id=p.scope_property_id LEFT JOIN bh_access_profiles parent ON parent.id=p.parent_profile_id WHERE p.installation_id=? ORDER BY p.scope_property_id IS NOT NULL,p.name");
        $q->execute([$this->installationId]);
        $rows=$q->fetchAll()?:[];
        foreach($rows as &$r)$r['rules']=$this->rules((int)$r['id']);
        return $rows;
    }

    public function options(): array
    {
        $q=$this->pdo->prepare("SELECT id,code,name,scope_property_id,parent_profile_id FROM bh_access_profiles WHERE installation_id=? AND status='active' ORDER BY scope_property_id IS NOT NULL,name");$q->execute([$this->installationId]);return $q->fetchAll()?:[];
    }

    public function rules(int $profileId): array
    {
        $q=$this->pdo->prepare('SELECT * FROM bh_access_profile_rules WHERE profile_id=? ORDER BY resource_domain,priority,id');$q->execute([$profileId]);return $q->fetchAll()?:[];
    }

    public function saveProfile(array $data,?int $id=null): int
    {
        $code=strtoupper(trim((string)($data['code']??'')));$code=preg_replace('/[^A-Z0-9._-]+/','-',$code)??'';
        $name=trim((string)($data['name']??''));if($code===''||$name==='')throw new InvalidArgumentException('Código y nombre son obligatorios.');
        $scope=(int)($data['scope_property_id']??0)?:null;$parent=(int)($data['parent_profile_id']??0)?:null;
        if($id&&$parent===$id)throw new InvalidArgumentException('Un perfil no puede heredarse a sí mismo.');
        $vals=[$scope,$parent,$code,$name,trim((string)($data['description']??''))?:null,trim((string)($data['relationship_hint']??''))?:null,!empty($data['can_invite'])?1:0,!empty($data['can_reserve'])?1:0,!empty($data['can_view_notifications'])?1:0,!empty($data['can_view_documents'])?1:0,!empty($data['can_use_domotics'])?1:0,in_array((string)($data['status']??''),['active','inactive'],true)?(string)$data['status']:'active'];
        $this->pdo->beginTransaction();
        try{
            if($id){$q=$this->pdo->prepare('UPDATE bh_access_profiles SET scope_property_id=?,parent_profile_id=?,code=?,name=?,description=?,relationship_hint=?,can_invite=?,can_reserve=?,can_view_notifications=?,can_view_documents=?,can_use_domotics=?,status=? WHERE id=? AND installation_id=?');$q->execute([...$vals,$id,$this->installationId]);$pid=$id;}
            else{$q=$this->pdo->prepare('INSERT INTO bh_access_profiles (installation_id,scope_property_id,parent_profile_id,code,name,description,relationship_hint,can_invite,can_reserve,can_view_notifications,can_view_documents,can_use_domotics,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');$q->execute([$this->installationId,...$vals]);$pid=(int)$this->pdo->lastInsertId();}
            $this->pdo->prepare('DELETE FROM bh_access_profile_rules WHERE profile_id=?')->execute([$pid]);
            $ins=$this->pdo->prepare('INSERT INTO bh_access_profile_rules (profile_id,resource_domain,scope_type,effect,days_of_week,start_time,end_time,priority) VALUES (?,?,?,\'allow\',?,?,?,100)');
            foreach(['camera','access'] as $domain){
                foreach((array)($data[$domain.'_scopes']??[]) as $scopeType){if(!in_array($scopeType,['all','common','property','tower','floor','unit','custom'],true))continue;$days=$domain==='access'?trim((string)($data['access_days']??'1,2,3,4,5,6,7')):'1,2,3,4,5,6,7';if(!preg_match('/^[1-7](,[1-7])*$/',$days))$days='1,2,3,4,5,6,7';$start=$domain==='access'?trim((string)($data['access_start']??'00:00')):'00:00';$end=$domain==='access'?trim((string)($data['access_end']??'23:59')):'23:59';$ins->execute([$pid,$domain,$scopeType,$days,$start.':00',$end.':00']);}
            }
            $this->queueSync('profile',$pid,$id?'profile.update':'profile.create',['code'=>$code]);
            $this->pdo->commit();return $pid;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function deleteProfile(int $id): void
    {
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM bh_property_memberships m JOIN bh_properties p ON p.id=m.property_id WHERE m.access_profile_id=? AND p.installation_id=?');$q->execute([$id,$this->installationId]);if((int)$q->fetchColumn()>0)throw new RuntimeException('El perfil está asignado a personas. Reasígnelas antes de eliminarlo.');
        $this->pdo->prepare('DELETE FROM bh_access_profiles WHERE id=? AND installation_id=?')->execute([$id,$this->installationId]);
    }

    public function assignMembership(int $membershipId,int $profileId): void
    {
        $q=$this->pdo->prepare('UPDATE bh_property_memberships m JOIN bh_people pe ON pe.id=m.person_id JOIN bh_access_profiles p ON p.id=? AND p.installation_id=pe.installation_id SET m.access_profile_id=? WHERE m.id=? AND pe.installation_id=?');$q->execute([$profileId,$profileId,$membershipId,$this->installationId]);if($q->rowCount()<1)throw new RuntimeException('No fue posible asignar el perfil a la relación.');$this->queueSync('membership',$membershipId,'membership.profile.assign',['profile_id'=>$profileId]);
    }

    public function profileChain(int $profileId): array
    {
        $chain=[];$seen=[];$id=$profileId;
        while($id>0&&!isset($seen[$id])&&count($chain)<12){$seen[$id]=true;$q=$this->pdo->prepare('SELECT * FROM bh_access_profiles WHERE id=? AND installation_id=? LIMIT 1');$q->execute([$id,$this->installationId]);$r=$q->fetch();if(!$r)break;array_unshift($chain,$r);$id=(int)($r['parent_profile_id']??0);}return $chain;
    }

    public function effectiveRules(int $profileId,string $domain): array
    {
        $effective=[];foreach($this->profileChain($profileId) as $p){$q=$this->pdo->prepare('SELECT * FROM bh_access_profile_rules WHERE profile_id=? AND resource_domain=? ORDER BY priority,id');$q->execute([(int)$p['id'],$domain]);foreach($q->fetchAll()?:[] as $r){$key=(string)$r['scope_type'].':'.(string)($r['resource_id']??0).':'.(string)($r['scope_group']??'');$effective[$key]=$r;}}return array_values($effective);
    }

    private function queueSync(string $entityType,int $entityId,string $operation,array $payload=[]): void
    {
        $q=$this->pdo->prepare('INSERT INTO bh_policy_sync_jobs (installation_id,entity_type,entity_id,operation,payload_json,status) VALUES (?,?,?,?,?,\'pending\')');$q->execute([$this->installationId,$entityType,$entityId,$operation,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    }
}
