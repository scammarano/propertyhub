<?php
declare(strict_types=1);

/**
 * Repositorio operativo persistente de un Property Node.
 *
 * Esta capa concentra los primeros CRUD reales del producto. No contiene
 * datos demo y siempre limita las consultas al UUID de la instalación actual.
 */
final class NodeRepository
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Connection::pdo();
    }

    public function installationId(): int
    {
        static $id = null;
        if (is_int($id) && $id > 0) return $id;
        $stmt = $this->pdo->prepare('SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1');
        $stmt->execute([(string) config('deployment.installation_uuid')]);
        $id = (int) $stmt->fetchColumn();
        if ($id < 1) throw new RuntimeException('No se encontró la instalación local en la base de datos.');
        return $id;
    }

    public function installationProfile(): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM bh_installations WHERE id=? LIMIT 1');
        $stmt->execute([$this->installationId()]);
        return $stmt->fetch() ?: [];
    }

    public function saveInstallationProfile(array $data): void
    {
        $name=trim((string)($data['name']??''));
        if($name==='')throw new InvalidArgumentException('El nombre de la instalación es obligatorio.');
        $type=(string)($data['installation_type']??'mixed');
        $allowed=['residential','urbanization','residential_complex','office','office_building','commercial','shopping_center','mixed','campus','industrial','other'];
        if(!in_array($type,$allowed,true))throw new InvalidArgumentException('Tipo de instalación no válido.');
        $timezone=trim((string)($data['timezone']??'America/Caracas'))?:'America/Caracas';
        if(!in_array($timezone,timezone_identifiers_list(),true))throw new InvalidArgumentException('Zona horaria no válida.');
        $stmt=$this->pdo->prepare('UPDATE bh_installations SET name=?,installation_type=?,timezone=?,address_line=?,city=?,state_region=?,country_code=?,contact_name=?,contact_email=?,contact_phone=?,notes=? WHERE id=?');
        $stmt->execute([$name,$type,$timezone,trim((string)($data['address_line']??''))?:null,trim((string)($data['city']??''))?:null,trim((string)($data['state_region']??''))?:null,strtoupper(substr(trim((string)($data['country_code']??'VE')),0,2))?:'VE',trim((string)($data['contact_name']??''))?:null,trim((string)($data['contact_email']??''))?:null,trim((string)($data['contact_phone']??''))?:null,trim((string)($data['notes']??''))?:null,$this->installationId()]);
        EnvironmentEditor::set(['INSTANCE_NAME'=>$name,'INSTANCE_TYPE'=>$type,'APP_TIMEZONE'=>$timezone]);
        $this->audit('installation.update','installation',$this->installationId(),['name'=>$name,'type'=>$type]);
    }

    public function dashboardMetrics(): array
    {
        $id = $this->installationId();
        $tables = [
            'properties' => 'bh_properties',
            'people' => 'bh_people',
            'areas' => 'bh_areas',
            'connectors' => 'bh_connector_instances',
            'devices' => 'bh_devices',
            'locations' => 'bh_site_locations',
        ];
        $metrics = [];
        foreach ($tables as $key => $table) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE installation_id=?");
            $stmt->execute([$id]);
            $metrics[$key] = (int) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bh_visits WHERE installation_id=? AND status='inside'");
        $stmt->execute([$id]);
        $metrics['visitors_active'] = (int) $stmt->fetchColumn();
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bh_reservations WHERE installation_id=? AND status='pending'");
        $stmt->execute([$id]);
        $metrics['reservations_pending'] = (int) $stmt->fetchColumn();
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM bh_notifications n JOIN bh_notification_campaigns c ON c.id=n.campaign_id WHERE c.installation_id=? AND n.status IN ('queued','delivered')");
        $stmt->execute([$id]);
        $metrics['notifications_open'] = (int) $stmt->fetchColumn();
        return $metrics;
    }

    public function recentActivity(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT action,entity_type,result,reason,created_at FROM bh_audit_logs '
            .'WHERE installation_id=? ORDER BY created_at DESC LIMIT '.max(1, min(50, $limit))
        );
        $stmt->execute([$this->installationId()]);
        return $stmt->fetchAll();
    }

    public function properties(array $filters = []): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $type = trim((string)($filters['type'] ?? ''));
        $status = trim((string)($filters['status'] ?? ''));
        [$page, $perPage, $offset] = $this->pageData($filters);
        $where = ['p.installation_id=?'];
        $args = [$this->installationId()];
        if ($search !== '') {
            $where[] = '(p.code LIKE ? OR p.name LIKE ? OR parent.name LIKE ?)';
            $like = '%'.$search.'%';
            array_push($args, $like, $like, $like);
        }
        if ($type !== '') { $where[] = 'p.property_type=?'; $args[] = $type; }
        if ($status !== '') { $where[] = 'p.status=?'; $args[] = $status; }
        $sqlWhere = implode(' AND ', $where);
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM bh_properties p LEFT JOIN bh_properties parent ON parent.id=p.parent_id WHERE {$sqlWhere}");
        $count->execute($args);
        $total = (int)$count->fetchColumn();
        $stmt = $this->pdo->prepare(
            "SELECT p.*,parent.name parent_name,(SELECT COUNT(*) FROM bh_properties c WHERE c.parent_id=p.id) child_count,"
            ."(SELECT COUNT(*) FROM bh_property_memberships m WHERE m.property_id=p.id AND m.status='active') membership_count,"
            ."(SELECT COUNT(*) FROM bh_areas a WHERE a.property_id=p.id) area_count,"
            ."(SELECT COUNT(*) FROM bh_devices d WHERE d.property_id=p.id) device_count,"
            ."(SELECT COUNT(*) FROM bh_access_points ap LEFT JOIN bh_areas a2 ON a2.id=ap.area_id WHERE COALESCE(ap.property_id,a2.property_id)=p.id) access_count,"
            ."(SELECT COUNT(*) FROM bh_cameras c2 LEFT JOIN bh_areas a3 ON a3.id=c2.area_id WHERE COALESCE(c2.property_id,a3.property_id)=p.id) camera_count "
            ."FROM bh_properties p LEFT JOIN bh_properties parent ON parent.id=p.parent_id WHERE {$sqlWhere} "
            ."ORDER BY COALESCE(parent.name,''),p.name LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($args);
        $items=$stmt->fetchAll()?:[];
        // Enriquecer la página con el orden físico efectivo y con los dependientes
        // exactos. La UI puede así explicar por qué un contenedor no se elimina y
        // permitir reubicar sus hijos sin obligar al operador a adivinar dónde están.
        foreach($items as &$item){
            $meta=$this->jsonObject($item['metadata_json']??null);
            $item['level_order']=$this->levelOrder($item,$meta);
            $item['structure_role']=(string)($meta['structure_role']??'physical');
            $item['accepts_resources']=array_key_exists('accepts_resources',$meta)?(!empty($meta['accepts_resources'])):$item['structure_role']!=='organizer';
            $item['operations_visible']=array_key_exists('operations_visible',$meta)?(!empty($meta['operations_visible'])):true;
            $item['resident_visible']=array_key_exists('resident_visible',$meta)?(!empty($meta['resident_visible'])):$item['structure_role']!=='organizer';
            $item['child_items']=[];$item['area_items']=[];
        }unset($item);
        if($items){
            $ids=array_map(static fn(array $r):int=>(int)$r['id'],$items);
            $ph=implode(',',array_fill(0,count($ids),'?'));
            $child=$this->pdo->prepare("SELECT id,parent_id,property_type,code,name,floor_label,metadata_json,status FROM bh_properties WHERE installation_id=? AND parent_id IN ({$ph})");
            $child->execute([$this->installationId(),...$ids]);
            $childrenByParent=[];foreach($child->fetchAll()?:[] as $r){$m=$this->jsonObject($r['metadata_json']??null);$r['level_order']=$this->levelOrder($r,$m);$childrenByParent[(int)$r['parent_id']][]=$r;}
            foreach($childrenByParent as &$siblings)usort($siblings,fn($a,$b)=>$this->compareStructuralSiblings($a,$b));unset($siblings);
            $area=$this->pdo->prepare("SELECT id,property_id,code,name,area_type,scope_type,usage_type,status FROM bh_areas WHERE installation_id=? AND property_id IN ({$ph}) ORDER BY name");
            $area->execute([$this->installationId(),...$ids]);$areasByProperty=[];foreach($area->fetchAll()?:[] as $r)$areasByProperty[(int)$r['property_id']][]=$r;
            foreach($items as &$item){$id=(int)$item['id'];$item['child_items']=$childrenByParent[$id]??[];$item['area_items']=$areasByProperty[$id]??[];}unset($item);
        }
        return $this->pageResult($items, $total, $page, $perPage, $search, ['type'=>$type,'status'=>$status]);
    }

    public function propertyOptions(?int $excludeId = null): array
    {
        $rows=$this->structuralLocationOptions();
        if(!$excludeId)return $rows;
        return array_values(array_filter($rows,fn($r)=>(int)$r['id']!==$excludeId));
    }

    /** Ubicaciones estructurales que aceptan recursos directamente. */
    public function structuralResourceOptions(): array
    {
        return array_values(array_filter($this->structuralLocationOptions(),static function(array $r): bool {
            $m=is_array($r['metadata']??null)?$r['metadata']:[];
            if(array_key_exists('accepts_resources',$m))return !empty($m['accepts_resources']);
            return (($m['structure_role']??'physical')!=='organizer');
        }));
    }

    /**
     * Selector jerárquico reutilizable para ubicar cámaras, puertas, IoT y otros activos
     * sin obligar a crear primero un área. Conserva la jerarquía real del gemelo digital.
     */
    public function structuralLocationOptions(): array
    {
        $stmt=$this->pdo->prepare("SELECT id,parent_id,property_type,code,name,floor_label,metadata_json FROM bh_properties WHERE installation_id=? AND status<>'inactive'");
        $stmt->execute([$this->installationId()]);$rows=$stmt->fetchAll()?:[];
        foreach($rows as &$r){$m=$this->jsonObject($r['metadata_json']??null);$r['level_order']=$this->levelOrder($r,$m);$r['metadata']=$m;}unset($r);
        $byParent=[];foreach($rows as $r)$byParent[(int)($r['parent_id']??0)][]=$r;
        foreach($byParent as &$children)usort($children,fn($a,$b)=>$this->compareStructuralSiblings($a,$b));unset($children);
        $out=[];$walk=function(int $parent,int $depth,string $path) use (&$walk,&$byParent,&$out): void {
            foreach($byParent[$parent]??[] as $r){$name=(string)$r['name'];$current=$path===''?$name:$path.' › '.$name;$r['depth']=$depth;$r['path']=$current;$r['label']=str_repeat('— ',$depth).$name;$out[]=$r;$walk((int)$r['id'],$depth+1,$current);}
        };$walk(0,0,'');
        return $out;
    }

    public function saveProperty(array $data, ?int $id = null): int
    {
        $code = $this->code((string)($data['code'] ?? ''));
        $name = trim((string)($data['name'] ?? ''));
        $type = (string)($data['property_type'] ?? 'other');
        $allowedTypes = ['site','urbanization','sector','street','block','parcel','building','tower','floor','ground_floor','basement','mezzanine','terrace','roof','technical_floor','apartment','house','townhouse','office','commercial_unit','warehouse','storage','parking','parking_zone','garage','guard_post','park','common','technical','other'];
        if ($code === '' || $name === '') throw new InvalidArgumentException('Código y nombre son obligatorios.');
        if (!in_array($type, $allowedTypes, true)) throw new InvalidArgumentException('Tipo de estructura no válido.');
        $parentId = (int)($data['parent_id'] ?? 0) ?: null;
        if ($id && $parentId === $id) throw new InvalidArgumentException('Un elemento no puede depender de sí mismo.');
        if ($parentId) $this->assertOwned('bh_properties',$parentId);
        $status = in_array((string)($data['status'] ?? ''), ['active','inactive','maintenance'], true) ? (string)$data['status'] : 'active';
        $levelTypes=['floor','ground_floor','basement','mezzanine','terrace','roof','technical_floor'];
        $existingMeta=[];if($id){$this->assertOwned('bh_properties',$id);$q=$this->pdo->prepare('SELECT metadata_json FROM bh_properties WHERE id=? AND installation_id=?');$q->execute([$id,$this->installationId()]);$existingMeta=$this->jsonObject($q->fetchColumn());}
        $metadata=$existingMeta;
        $structureRole=(string)($data['structure_role']??($metadata['structure_role']??'physical'));
        if(!in_array($structureRole,['physical','organizer'],true))$structureRole='physical';
        $metadata['structure_role']=$structureRole;
        if(array_key_exists('structure_role',$data)||array_key_exists('accepts_resources',$data))$metadata['accepts_resources']=!empty($data['accepts_resources']);
        elseif(!array_key_exists('accepts_resources',$metadata))$metadata['accepts_resources']=$structureRole!=='organizer';
        if(array_key_exists('structure_role',$data)||array_key_exists('operations_visible',$data))$metadata['operations_visible']=!empty($data['operations_visible']);
        elseif(!array_key_exists('operations_visible',$metadata))$metadata['operations_visible']=true;
        if(array_key_exists('structure_role',$data)||array_key_exists('resident_visible',$data))$metadata['resident_visible']=!empty($data['resident_visible']);
        elseif(!array_key_exists('resident_visible',$metadata))$metadata['resident_visible']=$structureRole!=='organizer';
        if($id && empty($metadata['accepts_resources'])){
            $directChecks=[
                ['Áreas/Espacios','SELECT COUNT(*) FROM bh_areas WHERE property_id=?'],
                ['cámaras directas','SELECT COUNT(*) FROM bh_cameras WHERE property_id=? AND area_id IS NULL'],
                ['accesos directos','SELECT COUNT(*) FROM bh_access_points WHERE property_id=? AND area_id IS NULL'],
                ['activos directos','SELECT COUNT(*) FROM bh_devices WHERE property_id=? AND area_id IS NULL'],
            ];$blocking=[];
            foreach($directChecks as [$label,$sql]){$q=$this->pdo->prepare($sql);$q->execute([$id]);$n=(int)$q->fetchColumn();if($n>0)$blocking[]=$n.' '.$label;}
            if($blocking)throw new InvalidArgumentException('Este elemento todavía contiene recursos directos ('.implode(', ',$blocking).'). Reubícalos antes de marcarlo como contenedor que no admite recursos.');
        }
        if(in_array($type,$levelTypes,true)){
            $raw=trim((string)($data['level_order']??''));
            if($raw!=='')$metadata['logical_number']=(int)$raw;
            elseif(!array_key_exists('logical_number',$metadata))$metadata['logical_number']=$this->defaultLevelOrder($type,trim((string)($data['floor_label']??'')));
            $metadata['level_model']='physical';
        }else{unset($metadata['logical_number'],$metadata['level_model']);}
        $metaJson=json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if ($id) {
            $stmt = $this->pdo->prepare('UPDATE bh_properties SET parent_id=?,property_type=?,code=?,name=?,floor_label=?,status=?,metadata_json=? WHERE id=? AND installation_id=?');
            $stmt->execute([$parentId,$type,$code,$name,trim((string)($data['floor_label']??''))?:null,$status,$metaJson,$id,$this->installationId()]);
            $this->audit('property.update','property',$id,['code'=>$code,'name'=>$name,'level_order'=>$metadata['logical_number']??null,'structure_role'=>$metadata['structure_role']??'physical','accepts_resources'=>!empty($metadata['accepts_resources'])]);
            return $id;
        }
        $stmt = $this->pdo->prepare('INSERT INTO bh_properties (installation_id,parent_id,property_type,code,name,floor_label,status,metadata_json) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$this->installationId(),$parentId,$type,$code,$name,trim((string)($data['floor_label']??''))?:null,$status,$metaJson]);
        $newId = (int)$this->pdo->lastInsertId();
        $this->audit('property.create','property',$newId,['code'=>$code,'name'=>$name,'level_order'=>$metadata['logical_number']??null,'structure_role'=>$metadata['structure_role']??'physical','accepts_resources'=>!empty($metadata['accepts_resources'])]);
        return $newId;
    }

    public function deleteProperty(int $id): string
    {
        $this->assertOwned('bh_properties', $id);
        $dependencyQueries = [
            'SELECT COUNT(*) FROM bh_properties WHERE parent_id=?',
            'SELECT COUNT(*) FROM bh_property_memberships WHERE property_id=?',
            'SELECT COUNT(*) FROM bh_areas WHERE property_id=?',
            'SELECT COUNT(*) FROM bh_devices WHERE property_id=?',
            'SELECT COUNT(*) FROM bh_access_points WHERE property_id=?',
            'SELECT COUNT(*) FROM bh_cameras WHERE property_id=?',
            'SELECT COUNT(*) FROM bh_reservations WHERE property_id=?',
        ];
        foreach ($dependencyQueries as $sql) {
            $stmt = $this->pdo->prepare($sql); $stmt->execute([$id]);
            if ((int)$stmt->fetchColumn() > 0) {
                $this->pdo->prepare("UPDATE bh_properties SET status='inactive' WHERE id=?")->execute([$id]);
                $this->audit('property.deactivate','property',$id,[]);
                return 'deactivated';
            }
        }
        $this->pdo->prepare('DELETE FROM bh_properties WHERE id=?')->execute([$id]);
        $this->audit('property.delete','property',$id,[]);
        return 'deleted';
    }

    /**
     * Ejecuta acciones masivas seguras sobre la estructura seleccionada.
     *
     * Las acciones se limitan siempre a la instalación actual. El movimiento
     * conserva la jerarquía interna: si se seleccionan una torre y sus pisos,
     * solo se mueve la torre y los pisos continúan dependiendo de ella.
     */
    public function bulkPropertyAction(array $data): array
    {
        $rawIds = is_array($data['selected_ids'] ?? null) ? $data['selected_ids'] : [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn(int $id): bool => $id > 0)));
        if (!$ids) throw new InvalidArgumentException('Seleccione al menos un elemento de la estructura.');
        if (count($ids) > 500) throw new InvalidArgumentException('La edición masiva admite un máximo de 500 elementos por operación.');

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id,parent_id,property_type,code,name,status FROM bh_properties "
            ."WHERE installation_id=? AND id IN ({$placeholders})"
        );
        $stmt->execute([$this->installationId(), ...$ids]);
        $rows = $stmt->fetchAll();
        if (count($rows) !== count($ids)) throw new RuntimeException('Uno o más elementos seleccionados no pertenecen a esta instalación.');
        $byId = [];
        foreach ($rows as $row) $byId[(int)$row['id']] = $row;

        $action = (string)($data['bulk_action'] ?? '');
        $result = ['action'=>$action,'selected'=>count($ids),'updated'=>0,'moved'=>0,'nested_preserved'=>0,'deleted'=>0,'deactivated'=>0];

        $this->pdo->beginTransaction();
        try {
            if ($action === 'move') {
                $targetId = (int)($data['target_parent_id'] ?? 0) ?: null;
                if ($targetId !== null) {
                    $this->assertOwned('bh_properties', $targetId);
                    if (in_array($targetId, $ids, true)) throw new InvalidArgumentException('El destino no puede formar parte de la selección.');
                    $cursor = $targetId;
                    $visited = [];
                    while ($cursor) {
                        if (isset($visited[$cursor])) throw new RuntimeException('Se detectó una relación circular existente en la estructura.');
                        $visited[$cursor] = true;
                        if (in_array($cursor, $ids, true)) throw new InvalidArgumentException('No se puede mover un elemento dentro de uno de sus descendientes.');
                        $parent = $this->pdo->prepare('SELECT parent_id FROM bh_properties WHERE id=? AND installation_id=?');
                        $parent->execute([$cursor,$this->installationId()]);
                        $value = $parent->fetchColumn();
                        $cursor = $value === false || $value === null ? 0 : (int)$value;
                    }
                }

                // Si un seleccionado depende de otro seleccionado, se conserva esa
                // relación y solo se mueve el elemento superior de la selección.
                $topLevel = [];
                foreach ($rows as $row) {
                    $parentId = (int)($row['parent_id'] ?? 0);
                    if ($parentId > 0 && isset($byId[$parentId])) {
                        $result['nested_preserved']++;
                        continue;
                    }
                    $topLevel[] = (int)$row['id'];
                }
                $update = $this->pdo->prepare('UPDATE bh_properties SET parent_id=? WHERE id=? AND installation_id=?');
                foreach ($topLevel as $id) {
                    $update->execute([$targetId,$id,$this->installationId()]);
                    $result['moved'] += $update->rowCount();
                }
            } elseif ($action === 'status') {
                $status = (string)($data['bulk_status'] ?? '');
                if (!in_array($status,['active','maintenance','inactive'],true)) throw new InvalidArgumentException('Seleccione un estado válido.');
                $update = $this->pdo->prepare("UPDATE bh_properties SET status=? WHERE installation_id=? AND id IN ({$placeholders})");
                $update->execute([$status,$this->installationId(),...$ids]);
                $result['updated'] = $update->rowCount();
            } elseif ($action === 'type') {
                $type = (string)($data['bulk_type'] ?? '');
                $allowedTypes = ['site','urbanization','sector','street','block','parcel','building','tower','floor','ground_floor','basement','mezzanine','terrace','roof','technical_floor','apartment','house','townhouse','office','commercial_unit','warehouse','storage','parking','parking_zone','garage','guard_post','park','common','technical','other'];
                if (!in_array($type,$allowedTypes,true)) throw new InvalidArgumentException('Seleccione un tipo de estructura válido.');
                $update = $this->pdo->prepare("UPDATE bh_properties SET property_type=? WHERE installation_id=? AND id IN ({$placeholders})");
                $update->execute([$type,$this->installationId(),...$ids]);
                $result['updated'] = $update->rowCount();
            } elseif ($action === 'rename') {
                $codePrefix = trim((string)($data['code_prefix'] ?? ''));
                $codeSuffix = trim((string)($data['code_suffix'] ?? ''));
                $codeSearch = (string)($data['code_search'] ?? '');
                $codeReplace = (string)($data['code_replace'] ?? '');
                $namePrefix = trim((string)($data['name_prefix'] ?? ''));
                $nameSuffix = trim((string)($data['name_suffix'] ?? ''));
                $nameSearch = (string)($data['name_search'] ?? '');
                $nameReplace = (string)($data['name_replace'] ?? '');
                if ($codePrefix==='' && $codeSuffix==='' && $codeSearch==='' && $namePrefix==='' && $nameSuffix==='' && $nameSearch==='') {
                    throw new InvalidArgumentException('Indique al menos una regla de renombrado.');
                }

                $proposed = [];
                $newCodes = [];
                foreach ($rows as $row) {
                    $newCode = (string)$row['code'];
                    if ($codeSearch !== '') $newCode = str_replace($codeSearch,$codeReplace,$newCode);
                    $newCode = $this->code($codePrefix.$newCode.$codeSuffix);
                    if ($newCode === '') throw new InvalidArgumentException('Una regla produjo un código vacío.');
                    if (isset($newCodes[$newCode])) throw new InvalidArgumentException('Las reglas producen el código repetido '.$newCode.'.');
                    $newCodes[$newCode] = true;

                    $newName = (string)$row['name'];
                    if ($nameSearch !== '') $newName = str_replace($nameSearch,$nameReplace,$newName);
                    $newName = trim(($namePrefix !== '' ? $namePrefix.' ' : '').$newName.($nameSuffix !== '' ? ' '.$nameSuffix : ''));
                    if ($newName === '') throw new InvalidArgumentException('Una regla produjo un nombre vacío.');
                    $proposed[(int)$row['id']] = ['code'=>$newCode,'name'=>$newName];
                }

                $codeList = array_keys($newCodes);
                $codePlaceholders = implode(',',array_fill(0,count($codeList),'?'));
                $conflict = $this->pdo->prepare(
                    "SELECT code FROM bh_properties WHERE installation_id=? AND code IN ({$codePlaceholders}) "
                    ."AND id NOT IN ({$placeholders}) LIMIT 1"
                );
                $conflict->execute([$this->installationId(),...$codeList,...$ids]);
                $conflictingCode = $conflict->fetchColumn();
                if ($conflictingCode !== false) throw new InvalidArgumentException('El código '.$conflictingCode.' ya existe fuera de la selección.');

                // Se usan códigos temporales para admitir intercambios sin violar
                // el índice único (por ejemplo A-101 <-> A-102).
                $temporary = $this->pdo->prepare('UPDATE bh_properties SET code=? WHERE id=? AND installation_id=?');
                $token = strtoupper(substr(bin2hex(random_bytes(6)),0,10));
                foreach ($ids as $id) $temporary->execute(['TMP-'.$token.'-'.$id,$id,$this->installationId()]);
                $update = $this->pdo->prepare('UPDATE bh_properties SET code=?,name=? WHERE id=? AND installation_id=?');
                foreach ($proposed as $id=>$values) {
                    $update->execute([$values['code'],$values['name'],$id,$this->installationId()]);
                    $result['updated'] += $update->rowCount();
                }
            } elseif ($action === 'delete') {
                if (empty($data['confirm_delete'])) throw new InvalidArgumentException('Confirme la eliminación o desactivación de los elementos seleccionados.');
                // Hijos seleccionados primero; así un padre puede eliminarse si al
                // final ya no conserva dependencias. Las relaciones reales siempre
                // fuerzan desactivación en vez de borrado físico.
                $depth = function(int $id) use (&$depth,$byId): int {
                    $row = $byId[$id] ?? null;
                    if (!$row) return 0;
                    $parentId = (int)($row['parent_id'] ?? 0);
                    return $parentId > 0 && isset($byId[$parentId]) ? 1 + $depth($parentId) : 0;
                };
                usort($ids, static fn(int $a,int $b): int => $depth($b) <=> $depth($a));
                foreach ($ids as $id) {
                    $hasDependencies = false;
                    foreach ([
                        'SELECT COUNT(*) FROM bh_properties WHERE parent_id=?',
                        'SELECT COUNT(*) FROM bh_property_memberships WHERE property_id=?',
                        'SELECT COUNT(*) FROM bh_areas WHERE property_id=?',
                        'SELECT COUNT(*) FROM bh_devices WHERE property_id=?',
                        'SELECT COUNT(*) FROM bh_reservations WHERE property_id=?',
                    ] as $sql) {
                        $check=$this->pdo->prepare($sql);$check->execute([$id]);
                        if ((int)$check->fetchColumn()>0) {$hasDependencies=true;break;}
                    }
                    if ($hasDependencies) {
                        $this->pdo->prepare("UPDATE bh_properties SET status='inactive' WHERE id=? AND installation_id=?")->execute([$id,$this->installationId()]);
                        $result['deactivated']++;
                    } else {
                        $this->pdo->prepare('DELETE FROM bh_properties WHERE id=? AND installation_id=?')->execute([$id,$this->installationId()]);
                        $result['deleted']++;
                    }
                }
            } else {
                throw new InvalidArgumentException('Seleccione una acción masiva válida.');
            }

            $this->audit('property.bulk_'.$action,'property_bulk',0,[
                'selected'=>$result['selected'],'updated'=>$result['updated'],'moved'=>$result['moved'],
                'nested_preserved'=>$result['nested_preserved'],'deleted'=>$result['deleted'],'deactivated'=>$result['deactivated'],
            ]);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }


    /**
     * Duplica una torre, edificio, piso o cualquier rama completa del diseño.
     * Los activos físicos se copian como elementos planificados y se limpian
     * serial, IP e identificadores externos para no clonar identidades reales.
     */
    public function duplicatePropertySubtree(int $sourceId, array $data): array
    {
        $this->assertOwned('bh_properties', $sourceId);
        $sourceStmt=$this->pdo->prepare('SELECT * FROM bh_properties WHERE id=? AND installation_id=?');
        $sourceStmt->execute([$sourceId,$this->installationId()]);
        $source=$sourceStmt->fetch();
        if(!$source)throw new RuntimeException('No se encontró el elemento origen.');

        $newCode=$this->code((string)($data['new_code']??''));
        $newName=trim((string)($data['new_name']??''));
        if($newCode===''||$newName==='')throw new InvalidArgumentException('Indique el código y nombre de la nueva estructura.');
        $targetParent=(int)($data['target_parent_id']??0)?:null;
        if($targetParent)$this->assertOwned('bh_properties',$targetParent);

        $allStmt=$this->pdo->prepare('SELECT * FROM bh_properties WHERE installation_id=? ORDER BY id');
        $allStmt->execute([$this->installationId()]);
        $all=$allStmt->fetchAll();
        $byId=[];$children=[];
        foreach($all as $row){$id=(int)$row['id'];$byId[$id]=$row;$children[(int)($row['parent_id']??0)][]=$id;}
        $ordered=[];
        $walk=function(int $id,int $depth=0)use(&$walk,&$ordered,$children):void{$ordered[]=['id'=>$id,'depth'=>$depth];foreach($children[$id]??[] as $child)$walk((int)$child,$depth+1);};
        $walk($sourceId);
        $sourceIds=array_column($ordered,'id');
        if($targetParent&&in_array($targetParent,$sourceIds,true))throw new InvalidArgumentException('No se puede duplicar una estructura dentro de sí misma.');

        $copyAreas=!empty($data['copy_areas']);
        $copyAssets=!empty($data['copy_assets']);
        $copyAccess=!empty($data['copy_access_points']);
        $copyCameras=!empty($data['copy_cameras']);
        $copyVertical=!empty($data['copy_vertical_transport']);
        $existing=array_fill_keys($this->existingCodes('bh_properties'),true);
        if(isset($existing[$newCode]))throw new InvalidArgumentException('El código '.$newCode.' ya existe.');
        $sourceCode=(string)$source['code'];$sourceName=(string)$source['name'];
        $plannedCodes=[];
        $makeCode=function(string $old)use($sourceCode,$newCode,&$existing,&$plannedCodes):string{
            $candidate=str_starts_with($old,$sourceCode)?$newCode.substr($old,strlen($sourceCode)):$newCode.'-'.$old;
            $candidate=$this->code($candidate);$base=$candidate;$n=2;
            while(isset($existing[$candidate])||isset($plannedCodes[$candidate])){$candidate=$this->code($base.'-'.$n);$n++;}
            $plannedCodes[$candidate]=true;return $candidate;
        };

        $this->pdo->beginTransaction();
        try{
            $propertyMap=[];$areaMap=[];$deviceMap=[];$accessMap=[];
            $insertProperty=$this->pdo->prepare('INSERT INTO bh_properties (installation_id,parent_id,property_type,code,name,floor_label,status,metadata_json) VALUES (?,?,?,?,?,?,?,?)');
            foreach($ordered as $entry){
                $old=$byId[(int)$entry['id']];$oldId=(int)$old['id'];
                if($oldId===$sourceId){$code=$newCode;$name=$newName;$parent=$targetParent;}
                else{$code=$makeCode((string)$old['code']);$name=str_contains((string)$old['name'],$sourceName)?str_replace($sourceName,$newName,(string)$old['name']):(string)$old['name'];$parent=$propertyMap[(int)$old['parent_id']]??null;}
                $metadata=json_decode((string)($old['metadata_json']??''),true);if(!is_array($metadata))$metadata=[];
                $metadata['duplicated_from_property_id']=$oldId;$metadata['design_copy']=true;
                $insertProperty->execute([$this->installationId(),$parent,$old['property_type'],$code,$name,$old['floor_label'],'active',json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
                $propertyMap[$oldId]=(int)$this->pdo->lastInsertId();
            }

            $summary=['properties'=>count($propertyMap),'areas'=>0,'devices'=>0,'access_points'=>0,'cameras'=>0,'vertical_transports'=>0,'vertical_stops'=>0];
            if($copyAreas){
                $ph=implode(',',array_fill(0,count($sourceIds),'?'));
                $q=$this->pdo->prepare("SELECT * FROM bh_areas WHERE installation_id=? AND property_id IN ({$ph}) ORDER BY id");$q->execute([$this->installationId(),...$sourceIds]);$areas=$q->fetchAll();
                $areaExisting=array_fill_keys($this->existingCodes('bh_areas'),true);$areaPlanned=[];
                $areaInsert=$this->pdo->prepare('INSERT INTO bh_areas (installation_id,property_id,code,name,area_type,scope_type,usage_type,is_reservable,is_security_zone,capacity,opening_time,closing_time,base_rate,security_deposit,requires_approval,minimum_minutes,maximum_minutes,advance_days,cancellation_hours,rules_text,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                foreach($areas as $area){
                    $base=$newCode.'-'.$this->code((string)$area['code']);$code=$base;$n=2;while(isset($areaExisting[$code])||isset($areaPlanned[$code])){$code=$this->code($base.'-'.$n++);}$areaPlanned[$code]=true;
                    $areaInsert->execute([$this->installationId(),$propertyMap[(int)$area['property_id']]??$targetParent,$code,(string)$area['name'],$area['area_type'],$area['scope_type']??'common',$area['usage_type']??'general',$area['is_reservable'],$area['is_security_zone'],$area['capacity'],$area['opening_time'],$area['closing_time'],$area['base_rate'],$area['security_deposit'],$area['requires_approval'],$area['minimum_minutes'],$area['maximum_minutes'],$area['advance_days'],$area['cancellation_hours'],$area['rules_text'],'active']);
                    $areaMap[(int)$area['id']]=(int)$this->pdo->lastInsertId();$summary['areas']++;
                }
            }

            if($copyAssets){
                $where=[];$args=[$this->installationId()];
                if($sourceIds){$ph=implode(',',array_fill(0,count($sourceIds),'?'));$where[]="property_id IN ({$ph})";array_push($args,...$sourceIds);}
                if($areaMap){$oldAreaIds=array_keys($areaMap);$ph=implode(',',array_fill(0,count($oldAreaIds),'?'));$where[]="area_id IN ({$ph})";array_push($args,...$oldAreaIds);}
                if($where){
                    $q=$this->pdo->prepare('SELECT * FROM bh_devices WHERE installation_id=? AND ('.implode(' OR ',$where).') ORDER BY id');$q->execute($args);
                    $insert=$this->pdo->prepare('INSERT INTO bh_devices (installation_id,site_location_id,property_id,area_id,connector_instance_id,asset_role,asset_stage,connection_path,connection_notes,device_type,name,vendor,model,firmware_version,serial_number,ip_address,network_zone,managed_by,connectivity_mode,direct_to_core,external_id,capabilities_json,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    foreach($q->fetchAll() as $d){
                        $insert->execute([$this->installationId(),null,$propertyMap[(int)($d['property_id']??0)]??null,$areaMap[(int)($d['area_id']??0)]??null,$d['connector_instance_id'],$d['asset_role'],'planned',$d['connection_path'],'Duplicado como diseño planificado desde '.$sourceName,$d['device_type'],str_replace($sourceName,$newName,(string)$d['name']),$d['vendor'],$d['model'],$d['firmware_version'],null,null,$d['network_zone'],$d['managed_by'],$d['connectivity_mode'],$d['direct_to_core'],null,$d['capabilities_json'],'unknown']);
                        $deviceMap[(int)$d['id']]=(int)$this->pdo->lastInsertId();$summary['devices']++;
                    }
                }
            }

            if($copyAccess){
                $where=[];$args=[$this->installationId()];
                if($sourceIds){$ph=implode(',',array_fill(0,count($sourceIds),'?'));$where[]="property_id IN ({$ph})";array_push($args,...$sourceIds);}
                if($areaMap){$oldAreaIds=array_keys($areaMap);$ph=implode(',',array_fill(0,count($oldAreaIds),'?'));$where[]="area_id IN ({$ph})";array_push($args,...$oldAreaIds);}
                if($where){
                    $q=$this->pdo->prepare('SELECT * FROM bh_access_points WHERE installation_id=? AND ('.implode(' OR ',$where).') ORDER BY id');$q->execute($args);
                    $existingCodes=[];$s=$this->pdo->prepare('SELECT code FROM bh_access_points WHERE installation_id=?');$s->execute([$this->installationId()]);foreach($s->fetchAll(PDO::FETCH_COLUMN) as $c)$existingCodes[(string)$c]=true;
                    $insert=$this->pdo->prepare('INSERT INTO bh_access_points (installation_id,property_id,area_id,device_id,code,name,access_type,operation_mode,remote_policy,status) VALUES (?,?,?,?,?,?,?,?,?,?)');
                    foreach($q->fetchAll() as $ap){$base=$this->code($newCode.'-'.$ap['code']);$code=$base;$n=2;while(isset($existingCodes[$code]))$code=$this->code($base.'-'.$n++);$existingCodes[$code]=true;$insert->execute([$this->installationId(),$propertyMap[(int)($ap['property_id']??0)]??null,$areaMap[(int)($ap['area_id']??0)]??null,$deviceMap[(int)($ap['device_id']??0)]??null,$code,(string)$ap['name'],$ap['access_type'],$ap['operation_mode'],$ap['remote_policy'],'maintenance']);$accessMap[(int)$ap['id']]=(int)$this->pdo->lastInsertId();$summary['access_points']++;}
                }
            }

            if($copyCameras){
                $where=[];$args=[$this->installationId()];
                if($sourceIds){$ph=implode(',',array_fill(0,count($sourceIds),'?'));$where[]="property_id IN ({$ph})";array_push($args,...$sourceIds);}
                if($areaMap){$oldAreaIds=array_keys($areaMap);$ph=implode(',',array_fill(0,count($oldAreaIds),'?'));$where[]="area_id IN ({$ph})";array_push($args,...$oldAreaIds);}
                if($where){
                    $q=$this->pdo->prepare('SELECT * FROM bh_cameras WHERE installation_id=? AND ('.implode(' OR ',$where).') ORDER BY id');$q->execute($args);
                    $insert=$this->pdo->prepare("INSERT INTO bh_cameras (installation_id,property_id,area_id,device_id,name,resident_name,display_order,stream_mode,stream_config_encrypted,resident_visibility,allow_recordings,allow_audio,allow_ptz,status) VALUES (?,?,?,?,?,?,?,?,NULL,?,?,?,?, 'offline')");
                    foreach($q->fetchAll() as $cam){$newAreaId=$areaMap[(int)($cam['area_id']??0)]??null;$newPropertyId=$propertyMap[(int)($cam['property_id']??0)]??null;if($newAreaId){$ar=$this->pdo->prepare('SELECT property_id FROM bh_areas WHERE id=?');$ar->execute([$newAreaId]);$newPropertyId=(int)($ar->fetchColumn()?:0)?:$newPropertyId;}$insert->execute([$this->installationId(),$newPropertyId,$newAreaId,$deviceMap[(int)($cam['device_id']??0)]??null,(string)$cam['name'],$cam['resident_name']??null,(int)($cam['display_order']??100),$cam['stream_mode'],'none',0,0,0]);$summary['cameras']++;}
                }
            }

            if($copyVertical&&$sourceIds){
                $ph=implode(',',array_fill(0,count($sourceIds),'?'));
                $q=$this->pdo->prepare("SELECT DISTINCT vt.* FROM bh_vertical_transports vt JOIN bh_vertical_transport_towers vtt ON vtt.vertical_transport_id=vt.id WHERE vt.installation_id=? AND vtt.tower_property_id IN ({$ph}) ORDER BY vt.id");
                $q->execute([$this->installationId(),...$sourceIds]);
                $existingVertical=[];$ev=$this->pdo->prepare('SELECT code FROM bh_vertical_transports WHERE installation_id=?');$ev->execute([$this->installationId()]);foreach($ev->fetchAll(PDO::FETCH_COLUMN) as $c)$existingVertical[(string)$c]=true;
                $insertTransport=$this->pdo->prepare("INSERT INTO bh_vertical_transports (installation_id,public_uuid,code,name,transport_type,status,metadata_json) VALUES (?,?,?,?,?,'planned',?)");
                $insertTower=$this->pdo->prepare('INSERT INTO bh_vertical_transport_towers (vertical_transport_id,tower_property_id) VALUES (?,?)');
                $insertStop=$this->pdo->prepare('INSERT INTO bh_vertical_transport_stops (vertical_transport_id,level_property_id,access_point_id,stop_order,is_restricted,notes) VALUES (?,?,?,?,?,?)');
                $towerQuery=$this->pdo->prepare('SELECT tower_property_id FROM bh_vertical_transport_towers WHERE vertical_transport_id=?');
                $stopQuery=$this->pdo->prepare('SELECT st.*,ap.code access_code,ap.name access_name,ap.operation_mode,ap.remote_policy FROM bh_vertical_transport_stops st LEFT JOIN bh_access_points ap ON ap.id=st.access_point_id WHERE st.vertical_transport_id=? ORDER BY st.stop_order');
                $insertDoor=$this->pdo->prepare("INSERT INTO bh_access_points (installation_id,property_id,area_id,device_id,code,name,access_type,operation_mode,remote_policy,status) VALUES (?,?,NULL,NULL,?,?,'elevator',?,?,'maintenance')");
                foreach($q->fetchAll() as $vt){
                    $base=$this->code($newCode.'-'.$vt['code']);$code=$base;$n=2;while(isset($existingVertical[$code]))$code=$this->code($base.'-'.$n++);$existingVertical[$code]=true;
                    $meta=json_decode((string)($vt['metadata_json']??''),true);if(!is_array($meta))$meta=[];$meta['duplicated_from_vertical_transport_id']=(int)$vt['id'];$meta['design_copy']=true;
                    $insertTransport->execute([$this->installationId(),self::uuid(),$code,str_replace($sourceName,$newName,(string)$vt['name']),$vt['transport_type'],json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);$newTransportId=(int)$this->pdo->lastInsertId();$summary['vertical_transports']++;
                    $towerQuery->execute([(int)$vt['id']]);foreach($towerQuery->fetchAll(PDO::FETCH_COLUMN) as $oldTowerId)if(isset($propertyMap[(int)$oldTowerId]))$insertTower->execute([$newTransportId,$propertyMap[(int)$oldTowerId]]);
                    $stopQuery->execute([(int)$vt['id']]);foreach($stopQuery->fetchAll() as $stop){$oldLevel=(int)$stop['level_property_id'];if(!isset($propertyMap[$oldLevel]))continue;$newAccessId=null;if($copyAccess&&!empty($stop['access_point_id'])){$oldAccessId=(int)$stop['access_point_id'];if(isset($accessMap[$oldAccessId])){$newAccessId=$accessMap[$oldAccessId];}else{$doorBase=$this->code($newCode.'-'.($stop['access_code']?:'PUERTA'));$doorCode=$doorBase;$dn=2;$check=$this->pdo->prepare('SELECT COUNT(*) FROM bh_access_points WHERE installation_id=? AND code=?');while(true){$check->execute([$this->installationId(),$doorCode]);if((int)$check->fetchColumn()===0)break;$doorCode=$this->code($doorBase.'-'.$dn++);}$insertDoor->execute([$this->installationId(),$propertyMap[$oldLevel],$doorCode,str_replace($sourceName,$newName,(string)($stop['access_name']?:'Puerta de ascensor')),$stop['operation_mode']?:'manual',$stop['remote_policy']?:'disabled']);$newAccessId=(int)$this->pdo->lastInsertId();$accessMap[$oldAccessId]=$newAccessId;$summary['access_points']++;}}$insertStop->execute([$newTransportId,$propertyMap[$oldLevel],$newAccessId,(int)$stop['stop_order'],(int)$stop['is_restricted'],$stop['notes']]);$summary['vertical_stops']++;}
                }
            }

            $user=current_user()??[];$createdBy=is_numeric($user['id']??null)?(int)$user['id']:null;
            $run=$this->pdo->prepare('INSERT INTO bh_structure_clone_runs (installation_id,public_uuid,source_property_id,target_property_id,options_json,summary_json,created_by) VALUES (?,?,?,?,?,?,?)');
            $run->execute([$this->installationId(),self::uuid(),$sourceId,$propertyMap[$sourceId],json_encode(['copy_areas'=>$copyAreas,'copy_assets'=>$copyAssets,'copy_access_points'=>$copyAccess,'copy_cameras'=>$copyCameras,'copy_vertical_transport'=>$copyVertical],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($summary),$createdBy]);
            $this->audit('property.duplicate','property',$propertyMap[$sourceId],['source_id'=>$sourceId,'summary'=>$summary]);
            $this->pdo->commit();return ['target_id'=>$propertyMap[$sourceId],'summary'=>$summary];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function areas(array $filters = []): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $type = trim((string)($filters['type'] ?? '')); // compatibilidad de URL antigua
        $scope = trim((string)($filters['scope'] ?? ''));
        $usage = trim((string)($filters['usage'] ?? ''));
        $status = trim((string)($filters['status'] ?? ''));
        [$page,$perPage,$offset] = $this->pageData($filters);
        $where = ['a.installation_id=?']; $args = [$this->installationId()];
        if ($search !== '') { $where[]='(a.code LIKE ? OR a.name LIKE ? OR p.name LIKE ?)'; $like='%'.$search.'%'; array_push($args,$like,$like,$like); }
        if ($type !== '') { $where[]='a.area_type=?'; $args[]=$type; }
        if ($scope !== '') { $where[]='a.scope_type=?'; $args[]=$scope; }
        if ($usage !== '') { $where[]='a.usage_type=?'; $args[]=$usage; }
        if ($status !== '') { $where[]='a.status=?'; $args[]=$status; }
        $sqlWhere=implode(' AND ',$where);
        $count=$this->pdo->prepare("SELECT COUNT(*) FROM bh_areas a LEFT JOIN bh_properties p ON p.id=a.property_id WHERE {$sqlWhere}");$count->execute($args);$total=(int)$count->fetchColumn();
        $stmt=$this->pdo->prepare("SELECT a.*,p.name property_name,(SELECT COUNT(*) FROM bh_areas a2 WHERE a2.installation_id=a.installation_id AND COALESCE(a2.property_id,0)=COALESCE(a.property_id,0) AND LOWER(TRIM(a2.name))=LOWER(TRIM(a.name))) duplicate_name_count FROM bh_areas a LEFT JOIN bh_properties p ON p.id=a.property_id WHERE {$sqlWhere} ORDER BY COALESCE(p.name,''),a.name LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($args);
        return $this->pageResult($stmt->fetchAll(),$total,$page,$perPage,$search,['type'=>$type,'scope'=>$scope,'usage'=>$usage,'status'=>$status]);
    }

    public function areaOptions(bool $reservableOnly=false): array
    {
        $sql='SELECT a.id,a.property_id,a.code,a.name,a.area_type,a.scope_type,a.usage_type,a.is_reservable,p.name property_name,p.code property_code,p.property_type FROM bh_areas a LEFT JOIN bh_properties p ON p.id=a.property_id WHERE a.installation_id=? AND a.status=\'active\'';
        if($reservableOnly)$sql.=' AND a.is_reservable=1';
        $sql.=' ORDER BY COALESCE(p.name,\'\'),a.name';
        $stmt=$this->pdo->prepare($sql);$stmt->execute([$this->installationId()]);return $stmt->fetchAll();
    }

    public function saveArea(array $data, ?int $id=null): int
    {
        $code=$this->code((string)($data['code']??''));$name=trim((string)($data['name']??''));
        if($code===''||$name==='')throw new InvalidArgumentException('Código y nombre son obligatorios.');
        $scope=(string)($data['scope_type']??'common');
        $allowedScopes=['common','private','technical','service','restricted','public'];
        if(!in_array($scope,$allowedScopes,true))throw new InvalidArgumentException('Ámbito del área no válido.');
        $usage=(string)($data['usage_type']??'general');
        $allowedUsage=['general','lobby','circulation','parking','office','commercial','technical','security','recreation','amenity','reservable','storage','residential','other'];
        if(!in_array($usage,$allowedUsage,true))$usage='other';
        // area_type se conserva como clasificación histórica para compatibilidad con módulos previos.
        $type=match(true){
            $scope==='technical'=>'technical',
            $scope==='restricted'=>'security',
            $usage==='parking'=>'parking',
            $usage==='circulation'=>'circulation',
            $usage==='office'=>'office',
            $usage==='commercial'=>'commercial',
            $usage==='technical'=>'technical',
            $usage==='security'=>'security',
            $usage==='reservable'=>'reservable',
            default=>'common',
        };
        $propertyId=(int)($data['property_id']??0)?:null;
        if($propertyId)$this->assertOwned('bh_properties',$propertyId);
        // Un mismo nombre en la misma ubicación estructural termina mostrando dos espacios indistinguibles.
        $checkDuplicate=true;
        if($id){
            $this->assertOwned('bh_areas',$id);
            $cur=$this->pdo->prepare('SELECT property_id,name FROM bh_areas WHERE id=? AND installation_id=?');$cur->execute([$id,$this->installationId()]);$current=$cur->fetch();
            if(is_array($current))$checkDuplicate=((int)($current['property_id']??0)!==(int)($propertyId??0))||strcasecmp(trim((string)$current['name']),$name)!==0;
        }
        if($checkDuplicate){
            $dup=$this->pdo->prepare('SELECT id,code,name,scope_type,usage_type FROM bh_areas WHERE installation_id=? AND COALESCE(property_id,0)=? AND LOWER(TRIM(name))=LOWER(TRIM(?))'.($id?' AND id<>?':'').' LIMIT 1');
            $dupArgs=[$this->installationId(),(int)($propertyId??0),$name];if($id)$dupArgs[]=$id;$dup->execute($dupArgs);$existing=$dup->fetch();
            if(is_array($existing))throw new InvalidArgumentException('Ya existe un área llamada "'.$existing['name'].'" en esta misma ubicación (código '.$existing['code'].' · '.area_scope_label((string)$existing['scope_type']).' · '.area_usage_label((string)$existing['usage_type']).'). Edítala o consolida el duplicado existente.');
        }
        $values=[
            $propertyId,$code,$name,$type,$scope,$usage,!empty($data['is_reservable'])?1:0,!empty($data['is_security_zone'])?1:0,
            max(0,(int)($data['capacity']??0))?:null,trim((string)($data['opening_time']??''))?:null,trim((string)($data['closing_time']??''))?:null,
            max(0,(float)($data['base_rate']??0)),max(0,(float)($data['security_deposit']??0)),!empty($data['requires_approval'])?1:0,
            max(15,(int)($data['minimum_minutes']??60)),max(0,(int)($data['maximum_minutes']??0))?:null,max(0,(int)($data['advance_days']??90)),
            max(0,(int)($data['cancellation_hours']??24)),trim((string)($data['rules_text']??''))?:null,
            in_array((string)($data['status']??''),['active','inactive','maintenance'],true)?(string)$data['status']:'active',
        ];
        if($id){
            $stmt=$this->pdo->prepare('UPDATE bh_areas SET property_id=?,code=?,name=?,area_type=?,scope_type=?,usage_type=?,is_reservable=?,is_security_zone=?,capacity=?,opening_time=?,closing_time=?,base_rate=?,security_deposit=?,requires_approval=?,minimum_minutes=?,maximum_minutes=?,advance_days=?,cancellation_hours=?,rules_text=?,status=? WHERE id=? AND installation_id=?');
            $stmt->execute([...$values,$id,$this->installationId()]);
            $this->syncAreaStructuralLocation($id,$propertyId);
            $this->audit('area.update','area',$id,['code'=>$code,'name'=>$name,'scope'=>$scope,'usage'=>$usage,'property_id'=>$propertyId]);return $id;
        }
        $stmt=$this->pdo->prepare('INSERT INTO bh_areas (installation_id,property_id,code,name,area_type,scope_type,usage_type,is_reservable,is_security_zone,capacity,opening_time,closing_time,base_rate,security_deposit,requires_approval,minimum_minutes,maximum_minutes,advance_days,cancellation_hours,rules_text,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$this->installationId(),...$values]);$newId=(int)$this->pdo->lastInsertId();$this->syncAreaStructuralLocation($newId,$propertyId);$this->audit('area.create','area',$newId,['code'=>$code,'name'=>$name,'scope'=>$scope,'usage'=>$usage,'property_id'=>$propertyId]);return $newId;
    }

    public function deleteArea(int $id): string
    {
        $this->assertOwned('bh_areas',$id);
        foreach(['SELECT COUNT(*) FROM bh_reservations WHERE area_id=?','SELECT COUNT(*) FROM bh_access_points WHERE area_id=?','SELECT COUNT(*) FROM bh_cameras WHERE area_id=?','SELECT COUNT(*) FROM bh_devices WHERE area_id=?'] as $sql){$s=$this->pdo->prepare($sql);$s->execute([$id]);if((int)$s->fetchColumn()>0){$this->pdo->prepare("UPDATE bh_areas SET status='inactive' WHERE id=?")->execute([$id]);$this->audit('area.deactivate','area',$id,[]);return 'deactivated';}}
        $this->pdo->prepare('DELETE FROM bh_areas WHERE id=?')->execute([$id]);$this->audit('area.delete','area',$id,[]);return 'deleted';
    }


    /** Acciones masivas para áreas y espacios, incluyendo duplicación de diseño. */
    public function bulkAreaAction(array $data): array
    {
        $raw=is_array($data['selected_ids']??null)?$data['selected_ids']:[];
        $ids=array_values(array_unique(array_filter(array_map('intval',$raw),static fn(int $id):bool=>$id>0)));
        if(!$ids)throw new InvalidArgumentException('Seleccione al menos un área.');
        if(count($ids)>500)throw new InvalidArgumentException('La operación admite un máximo de 500 áreas.');
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $q=$this->pdo->prepare("SELECT * FROM bh_areas WHERE installation_id=? AND id IN ({$ph}) ORDER BY id");$q->execute([$this->installationId(),...$ids]);$rows=$q->fetchAll();
        if(count($rows)!==count($ids))throw new RuntimeException('Una o más áreas no pertenecen a la instalación.');
        $action=(string)($data['bulk_action']??'');$result=['action'=>$action,'selected'=>count($ids),'updated'=>0,'moved'=>0,'deleted'=>0,'deactivated'=>0,'duplicated'=>0,'components'=>0];
        $this->pdo->beginTransaction();
        try{
            if($action==='move'){
                $target=(int)($data['target_property_id']??0)?:null;if($target)$this->assertOwned('bh_properties',$target);
                $u=$this->pdo->prepare("UPDATE bh_areas SET property_id=? WHERE installation_id=? AND id IN ({$ph})");$u->execute([$target,$this->installationId(),...$ids]);$result['moved']=$u->rowCount();
                foreach($ids as $areaId)$this->syncAreaStructuralLocation($areaId,$target);
            }elseif($action==='status'){
                $status=(string)($data['bulk_status']??'');if(!in_array($status,['active','maintenance','inactive'],true))throw new InvalidArgumentException('Seleccione un estado válido.');
                $u=$this->pdo->prepare("UPDATE bh_areas SET status=? WHERE installation_id=? AND id IN ({$ph})");$u->execute([$status,$this->installationId(),...$ids]);$result['updated']=$u->rowCount();
            }elseif($action==='scope'){
                $scope=(string)($data['bulk_scope']??'');if(!in_array($scope,['common','private','technical','service','restricted','public'],true))throw new InvalidArgumentException('Seleccione un ámbito válido.');
                $u=$this->pdo->prepare("UPDATE bh_areas SET scope_type=? WHERE installation_id=? AND id IN ({$ph})");$u->execute([$scope,$this->installationId(),...$ids]);$result['updated']=$u->rowCount();
                $this->refreshLegacyAreaType($ids);
            }elseif($action==='usage'){
                $usage=(string)($data['bulk_usage']??'');if(!in_array($usage,['general','lobby','circulation','parking','office','commercial','technical','security','recreation','amenity','reservable','storage','residential','other'],true))throw new InvalidArgumentException('Seleccione un uso válido.');
                $u=$this->pdo->prepare("UPDATE bh_areas SET usage_type=? WHERE installation_id=? AND id IN ({$ph})");$u->execute([$usage,$this->installationId(),...$ids]);$result['updated']=$u->rowCount();
                $this->refreshLegacyAreaType($ids);
            }elseif($action==='delete'){
                if(empty($data['confirm_delete']))throw new InvalidArgumentException('Confirme la eliminación o desactivación de las áreas seleccionadas.');
                foreach($ids as $id){$dependent=false;foreach(['SELECT COUNT(*) FROM bh_reservations WHERE area_id=?','SELECT COUNT(*) FROM bh_access_points WHERE area_id=?','SELECT COUNT(*) FROM bh_cameras WHERE area_id=?','SELECT COUNT(*) FROM bh_devices WHERE area_id=?'] as $sql){$c=$this->pdo->prepare($sql);$c->execute([$id]);if((int)$c->fetchColumn()>0){$dependent=true;break;}}
                    if($dependent){$u=$this->pdo->prepare("UPDATE bh_areas SET status='inactive' WHERE id=? AND installation_id=?");$u->execute([$id,$this->installationId()]);$result['deactivated']+=$u->rowCount();}
                    else{$u=$this->pdo->prepare('DELETE FROM bh_areas WHERE id=? AND installation_id=?');$u->execute([$id,$this->installationId()]);$result['deleted']+=$u->rowCount();}
                }
            }elseif($action==='duplicate'){
                $target=(int)($data['target_property_id']??0)?:null;if($target)$this->assertOwned('bh_properties',$target);
                $prefix=$this->code((string)($data['code_prefix']??'COPIA'))?:'COPIA';$copyComponents=!empty($data['copy_components']);
                $existing=array_fill_keys($this->existingCodes('bh_areas'),true);
                $areaInsert=$this->pdo->prepare('INSERT INTO bh_areas (installation_id,property_id,code,name,area_type,scope_type,usage_type,is_reservable,is_security_zone,capacity,opening_time,closing_time,base_rate,security_deposit,requires_approval,minimum_minutes,maximum_minutes,advance_days,cancellation_hours,rules_text,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                foreach($rows as $area){$base=$this->code($prefix.'-'.$area['code']);$code=$base;$n=2;while(isset($existing[$code]))$code=$this->code($base.'-'.$n++);$existing[$code]=true;
                    $areaInsert->execute([$this->installationId(),$target??$area['property_id'],$code,(string)$area['name'].' (copia)',$area['area_type'],$area['scope_type']??'common',$area['usage_type']??'general',$area['is_reservable'],$area['is_security_zone'],$area['capacity'],$area['opening_time'],$area['closing_time'],$area['base_rate'],$area['security_deposit'],$area['requires_approval'],$area['minimum_minutes'],$area['maximum_minutes'],$area['advance_days'],$area['cancellation_hours'],$area['rules_text'],'active']);
                    $newArea=(int)$this->pdo->lastInsertId();$result['duplicated']++;
                    if($copyComponents){
                        $d=$this->pdo->prepare('SELECT * FROM bh_devices WHERE installation_id=? AND area_id=?');$d->execute([$this->installationId(),$area['id']]);$deviceMap=[];
                        $di=$this->pdo->prepare('INSERT INTO bh_devices (installation_id,site_location_id,property_id,area_id,connector_instance_id,asset_role,asset_stage,connection_path,connection_notes,device_type,name,vendor,model,firmware_version,serial_number,ip_address,network_zone,managed_by,connectivity_mode,direct_to_core,external_id,capabilities_json,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                        foreach($d->fetchAll() as $device){$di->execute([$this->installationId(),null,$target??$device['property_id'],$newArea,$device['connector_instance_id'],$device['asset_role'],'planned',$device['connection_path'],'Duplicado como componente planificado',$device['device_type'],$device['name'].' (copia)',$device['vendor'],$device['model'],$device['firmware_version'],null,null,$device['network_zone'],$device['managed_by'],$device['connectivity_mode'],$device['direct_to_core'],null,$device['capabilities_json'],'unknown']);$deviceMap[(int)$device['id']]=(int)$this->pdo->lastInsertId();$result['components']++;}
                        $a=$this->pdo->prepare('SELECT * FROM bh_access_points WHERE installation_id=? AND area_id=?');$a->execute([$this->installationId(),$area['id']]);$ai=$this->pdo->prepare('INSERT INTO bh_access_points (installation_id,property_id,area_id,device_id,code,name,resident_name,display_order,access_type,operation_mode,remote_policy,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                        foreach($a->fetchAll() as $ap){$apCode=$this->code($prefix.'-'.$ap['code']);$k=2;while($this->codeExists('bh_access_points',$apCode))$apCode=$this->code($prefix.'-'.$ap['code'].'-'.$k++);$ai->execute([$this->installationId(),$target??$area['property_id'],$newArea,$deviceMap[(int)($ap['device_id']??0)]??null,$apCode,$ap['name'].' (copia)',$ap['resident_name']??null,(int)($ap['display_order']??100),$ap['access_type'],$ap['operation_mode'],$ap['remote_policy'],'maintenance']);$result['components']++;}
                        $c=$this->pdo->prepare('SELECT * FROM bh_cameras WHERE installation_id=? AND area_id=?');$c->execute([$this->installationId(),$area['id']]);$ci=$this->pdo->prepare("INSERT INTO bh_cameras (installation_id,property_id,area_id,device_id,name,stream_mode,stream_config_encrypted,resident_visibility,allow_recordings,allow_audio,allow_ptz,status) VALUES (?,?,?,?,?,?,NULL,'none',0,0,0,'offline')");
                        foreach($c->fetchAll() as $cam){$ci->execute([$this->installationId(),$target??$area['property_id'],$newArea,$deviceMap[(int)($cam['device_id']??0)]??null,$cam['name'].' (copia)',$cam['stream_mode']]);$result['components']++;}
                    }
                }
            }else throw new InvalidArgumentException('Seleccione una acción masiva válida.');
            $this->audit('area.bulk_'.$action,'area_bulk',0,$result);$this->pdo->commit();return$result;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
    }

    /** Mantiene la invariante: si un recurso precisa un área, su estructura es la estructura de esa área. */
    private function syncAreaStructuralLocation(int $areaId, ?int $propertyId): void
    {
        $iid=$this->installationId();
        $this->pdo->prepare('UPDATE bh_cameras SET property_id=? WHERE installation_id=? AND area_id=?')->execute([$propertyId,$iid,$areaId]);
        $this->pdo->prepare('UPDATE bh_access_points SET property_id=? WHERE installation_id=? AND area_id=?')->execute([$propertyId,$iid,$areaId]);
        $this->pdo->prepare('UPDATE bh_devices SET property_id=? WHERE installation_id=? AND area_id=?')->execute([$propertyId,$iid,$areaId]);
    }

    /** Recalcula la clasificación histórica a partir de las nuevas dimensiones ámbito + uso. */
    private function refreshLegacyAreaType(array $ids): void
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));if(!$ids)return;
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $sql="UPDATE bh_areas SET area_type=CASE WHEN scope_type='technical' THEN 'technical' WHEN scope_type='restricted' THEN 'security' WHEN usage_type='parking' THEN 'parking' WHEN usage_type='circulation' THEN 'circulation' WHEN usage_type='office' THEN 'office' WHEN usage_type='commercial' THEN 'commercial' WHEN usage_type='technical' THEN 'technical' WHEN usage_type='security' THEN 'security' WHEN usage_type='reservable' THEN 'reservable' ELSE 'common' END WHERE installation_id=? AND id IN ({$ph})";
        $this->pdo->prepare($sql)->execute([$this->installationId(),...$ids]);
    }

    public function people(array $filters=[]): array
    {
        $search=trim((string)($filters['search']??''));$status=trim((string)($filters['status']??''));
        [$page,$perPage,$offset]=$this->pageData($filters);
        $where=['pe.installation_id=?'];$args=[$this->installationId()];
        if($search!==''){$where[]='(pe.display_name LIKE ? OR pe.document_number LIKE ? OR pe.email LIKE ? OR pe.phone LIKE ? OR pr.name LIKE ?)';$like='%'.$search.'%';array_push($args,$like,$like,$like,$like,$like);}
        if($status!==''){$where[]='pe.status=?';$args[]=$status;}
        $sqlWhere=implode(' AND ',$where);
        $count=$this->pdo->prepare("SELECT COUNT(DISTINCT pe.id) FROM bh_people pe LEFT JOIN bh_property_memberships m ON m.person_id=pe.id LEFT JOIN bh_properties pr ON pr.id=m.property_id WHERE {$sqlWhere}");$count->execute($args);$total=(int)$count->fetchColumn();
        $stmt=$this->pdo->prepare(
            "SELECT pe.*,GROUP_CONCAT(DISTINCT CONCAT(pr.name,' · ',m.relationship_type) ORDER BY pr.name SEPARATOR '||') memberships,COUNT(DISTINCT m.id) membership_count "
            ."FROM bh_people pe LEFT JOIN bh_property_memberships m ON m.person_id=pe.id AND m.status='active' LEFT JOIN bh_properties pr ON pr.id=m.property_id "
            ."WHERE {$sqlWhere} GROUP BY pe.id ORDER BY pe.display_name LIMIT {$perPage} OFFSET {$offset}"
        );$stmt->execute($args);
        return $this->pageResult($stmt->fetchAll(),$total,$page,$perPage,$search,['status'=>$status]);
    }

    public function personOptions(): array
    {
        $stmt=$this->pdo->prepare("SELECT id,display_name,document_number FROM bh_people WHERE installation_id=? AND status='active' ORDER BY display_name");$stmt->execute([$this->installationId()]);return $stmt->fetchAll();
    }

    public function memberships(?int $personId=null): array
    {
        $sql='SELECT m.*,p.display_name,pr.name property_name,pr.code property_code,ap.name access_profile_name,ap.code access_profile_code FROM bh_property_memberships m JOIN bh_people p ON p.id=m.person_id JOIN bh_properties pr ON pr.id=m.property_id LEFT JOIN bh_access_profiles ap ON ap.id=m.access_profile_id WHERE p.installation_id=?';$args=[$this->installationId()];
        if($personId){$sql.=' AND m.person_id=?';$args[]=$personId;}$sql.=' ORDER BY p.display_name,pr.name';$stmt=$this->pdo->prepare($sql);$stmt->execute($args);return $stmt->fetchAll();
    }

    public function savePerson(array $data, ?int $id=null): int
    {
        $first=normalize_person_name_part((string)($data['first_name']??''));$last=normalize_person_name_part((string)($data['last_name']??''));$display=normalize_person_display_name($first,$last,(string)($data['display_name']??''));
        if($display==='')throw new InvalidArgumentException('Indique el nombre de la persona.');
        $status=in_array((string)($data['status']??''),['active','inactive','blocked','deceased'],true)?(string)$data['status']:'active';
        $values=[$first?:null,$last?:null,$display,trim((string)($data['document_type']??''))?:null,trim((string)($data['document_number']??''))?:null,trim((string)($data['email']??''))?:null,trim((string)($data['phone']??''))?:null,$status];
        $this->pdo->beginTransaction();
        try{
            if($id){$this->assertOwned('bh_people',$id);$stmt=$this->pdo->prepare('UPDATE bh_people SET first_name=?,last_name=?,display_name=?,document_type=?,document_number=?,email=?,phone=?,status=? WHERE id=? AND installation_id=?');$stmt->execute([...$values,$id,$this->installationId()]);$personId=$id;$action='person.update';}
            else{$stmt=$this->pdo->prepare("INSERT INTO bh_people (installation_id,public_uuid,person_kind,first_name,last_name,display_name,document_type,document_number,email,phone,status) VALUES (?,?,'natural',?,?,?,?,?,?,?,?)");$stmt->execute([$this->installationId(),self::uuid(),...$values]);$personId=(int)$this->pdo->lastInsertId();$action='person.create';}
            $propertyId=(int)($data['property_id']??0);
            if($propertyId>0){$this->saveMembershipInternal(['person_id'=>$personId,'property_id'=>$propertyId,'relationship_type'=>(string)($data['relationship_type']??'resident'),'access_profile_id'=>(int)($data['access_profile_id']??0),'is_primary'=>!empty($data['is_primary']),'can_invite'=>!empty($data['can_invite']),'can_reserve'=>!empty($data['can_reserve']),'can_view_cameras'=>!empty($data['can_view_cameras']),'can_operate_access'=>!empty($data['can_operate_access']),'status'=>'active'],null);}
            $this->audit($action,'person',$personId,['display_name'=>$display]);$this->pdo->commit();return $personId;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /**
     * Normaliza nombres de personas ya existentes sin alterar su identidad ni sus relaciones.
     * Está pensada para adopciones/importaciones donde la persona podía existir previamente
     * con mayúsculas/minúsculas inconsistentes. No modifica el objeto fuente del fabricante.
     */
    public function normalizePersonNames(array $personIds): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$personIds),static fn(int $v):bool=>$v>0)));
        if(!$ids)return ['updated'=>0,'unchanged'=>0,'skipped'=>0];
        $iid=$this->installationId();$result=['updated'=>0,'unchanged'=>0,'skipped'=>0];
        $sel=$this->pdo->prepare('SELECT id,first_name,last_name,display_name FROM bh_people WHERE id=? AND installation_id=? LIMIT 1');
        $upd=$this->pdo->prepare('UPDATE bh_people SET first_name=?,last_name=?,display_name=? WHERE id=? AND installation_id=?');
        $map=$this->pdo->prepare("UPDATE bh_external_mappings SET external_name=? WHERE installation_id=? AND local_entity_type='person' AND local_entity_id=?");
        $this->pdo->beginTransaction();
        try{
            foreach($ids as $id){
                $sel->execute([$id,$iid]);$p=$sel->fetch();if(!is_array($p)){$result['skipped']++;continue;}
                $first=normalize_person_name_part((string)($p['first_name']??''));$last=normalize_person_name_part((string)($p['last_name']??''));$display=normalize_person_display_name($first,$last,(string)($p['display_name']??''));
                $oldFirst=trim((string)($p['first_name']??''));$oldLast=trim((string)($p['last_name']??''));$oldDisplay=trim((string)($p['display_name']??''));
                if($first===$oldFirst&&$last===$oldLast&&$display===$oldDisplay){$result['unchanged']++;continue;}
                $upd->execute([$first!==''?$first:null,$last!==''?$last:null,$display,$id,$iid]);$map->execute([$display,$iid,$id]);
                $this->audit('person.name_normalized','person',$id,['from'=>$oldDisplay,'to'=>$display]);$result['updated']++;
            }
            $this->pdo->commit();return $result;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function saveMembership(array $data, ?int $id=null): int
    {
        $this->pdo->beginTransaction();try{$result=$this->saveMembershipInternal($data,$id);$this->audit($id?'membership.update':'membership.create','membership',$result,[]);$this->pdo->commit();return $result;}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    private function saveMembershipInternal(array $data, ?int $id): int
    {
        $personId=(int)($data['person_id']??0);$propertyId=(int)($data['property_id']??0);if($personId<1||$propertyId<1)throw new InvalidArgumentException('Seleccione persona y unidad.');
        $this->assertOwned('bh_people',$personId);$this->assertOwned('bh_properties',$propertyId);
        if($id){$check=$this->pdo->prepare('SELECT COUNT(*) FROM bh_property_memberships m JOIN bh_people p ON p.id=m.person_id WHERE m.id=? AND p.installation_id=?');$check->execute([$id,$this->installationId()]);if((int)$check->fetchColumn()!==1)throw new RuntimeException('La vinculación no pertenece a esta instalación.');}
        $relationship=(string)($data['relationship_type']??'resident');$allowed=['owner','tenant','resident','family','representative','employee','domestic_staff','driver','security','maintenance','authorized','other'];if(!in_array($relationship,$allowed,true))throw new InvalidArgumentException('Relación no válida.');
        $profileId=(int)($data['access_profile_id']??0)?:null;if($profileId){$cp=$this->pdo->prepare("SELECT COUNT(*) FROM bh_access_profiles WHERE id=? AND installation_id=? AND status='active'");$cp->execute([$profileId,$this->installationId()]);if((int)$cp->fetchColumn()!==1)throw new InvalidArgumentException('Perfil de acceso no válido.');}
        if(!$profileId){$cp=$this->pdo->prepare("SELECT id FROM bh_access_profiles WHERE installation_id=? AND scope_property_id IS NULL AND relationship_hint=? AND status='active' ORDER BY id LIMIT 1");$cp->execute([$this->installationId(),$relationship]);$profileId=(int)$cp->fetchColumn()?:null;}
        $values=[$propertyId,$personId,$relationship,$profileId,!empty($data['is_primary'])?1:0,!empty($data['can_invite'])?1:0,!empty($data['can_reserve'])?1:0,!empty($data['can_view_cameras'])?1:0,!empty($data['can_operate_access'])?1:0,trim((string)($data['starts_at']??''))?:null,trim((string)($data['ends_at']??''))?:null,in_array((string)($data['status']??''),['active','pending','expired','revoked'],true)?(string)$data['status']:'active'];
        if($id){$stmt=$this->pdo->prepare('UPDATE bh_property_memberships m JOIN bh_people p ON p.id=m.person_id SET m.property_id=?,m.person_id=?,m.relationship_type=?,m.access_profile_id=?,m.is_primary=?,m.can_invite=?,m.can_reserve=?,m.can_view_cameras=?,m.can_operate_access=?,m.starts_at=?,m.ends_at=?,m.status=? WHERE m.id=? AND p.installation_id=?');$stmt->execute([...$values,$id,$this->installationId()]);return $id;}
        $stmt=$this->pdo->prepare('INSERT INTO bh_property_memberships (property_id,person_id,relationship_type,access_profile_id,is_primary,can_invite,can_reserve,can_view_cameras,can_operate_access,starts_at,ends_at,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute($values);return (int)$this->pdo->lastInsertId();
    }

    public function bulkUpdateMembershipRelationships(array $ids,string $relationship,?int $profileId=null): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $v):bool=>$v>0)));
        if(!$ids)throw new InvalidArgumentException('Seleccione al menos una relación.');
        $allowed=['owner','tenant','resident','family','representative','employee','domestic_staff','driver','security','maintenance','authorized','other'];
        if(!in_array($relationship,$allowed,true))throw new InvalidArgumentException('Seleccione una relación válida.');
        if($profileId){$q=$this->pdo->prepare("SELECT COUNT(*) FROM bh_access_profiles WHERE id=? AND installation_id=? AND status='active'");$q->execute([$profileId,$this->installationId()]);if((int)$q->fetchColumn()!==1)throw new InvalidArgumentException('Perfil de acceso no válido.');}
        if(!$profileId){$q=$this->pdo->prepare("SELECT id FROM bh_access_profiles WHERE installation_id=? AND scope_property_id IS NULL AND relationship_hint=? AND status='active' ORDER BY id LIMIT 1");$q->execute([$this->installationId(),$relationship]);$profileId=(int)$q->fetchColumn()?:null;}
        $updated=0;$skipped=0;$this->pdo->beginTransaction();
        try{$check=$this->pdo->prepare('SELECT COUNT(*) FROM bh_property_memberships m JOIN bh_people p ON p.id=m.person_id WHERE m.id=? AND p.installation_id=?');$upd=$this->pdo->prepare('UPDATE bh_property_memberships SET relationship_type=?,access_profile_id=? WHERE id=?');
            foreach($ids as $id){$check->execute([$id,$this->installationId()]);if((int)$check->fetchColumn()!==1){$skipped++;continue;}$upd->execute([$relationship,$profileId,$id]);$updated++;$this->audit('membership.bulk_relationship_update','membership',$id,['relationship_type'=>$relationship,'access_profile_id'=>$profileId]);}
            $this->pdo->commit();return ['updated'=>$updated,'skipped'=>$skipped,'relationship_type'=>$relationship,'access_profile_id'=>$profileId];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /**
     * Convierte una identidad creada por error como Persona en un Vehicle real.
     * Es deliberadamente conservador: si la persona ya tiene cuenta, credenciales o historial
     * funcional, se rechaza la conversión para evitar perder una identidad humana real.
     */
    public function reclassifyPersonToVehicle(int $personId,array $data=[]): array
    {
        $this->assertOwned('bh_people',$personId);$iid=$this->installationId();
        $q=$this->pdo->prepare('SELECT * FROM bh_people WHERE id=? AND installation_id=? LIMIT 1');$q->execute([$personId,$iid]);$person=$q->fetch();if(!is_array($person))throw new InvalidArgumentException('Persona no encontrada.');
        $blockers=[];
        $checks=[
            'cuenta de Property App'=>['SELECT COUNT(*) FROM bh_local_users WHERE person_id=?',[$personId]],
            'vínculo de identidad global'=>['SELECT COUNT(*) FROM bh_global_user_links WHERE local_person_id=?',[$personId]],
            'credenciales de persona'=>['SELECT COUNT(*) FROM bh_credentials WHERE person_id=?',[$personId]],
            'documentos adjuntos'=>['SELECT COUNT(*) FROM bh_person_documents WHERE person_id=?',[$personId]],
            'reservas'=>['SELECT COUNT(*) FROM bh_reservations WHERE requester_person_id=?',[$personId]],
            'invitaciones como anfitrión'=>['SELECT COUNT(*) FROM bh_invitations WHERE host_person_id=?',[$personId]],
            'visitas'=>['SELECT COUNT(*) FROM bh_visits WHERE visitor_person_id=? OR host_person_id=?',[$personId,$personId]],
            'auditoría como persona'=>['SELECT COUNT(*) FROM bh_audit_logs WHERE actor_person_id=?',[$personId]],
            'invitaciones de cuenta'=>['SELECT COUNT(*) FROM bh_account_invitations WHERE person_id=?',[$personId]],
        ];
        foreach($checks as $label=>[$sql,$args]){$x=$this->pdo->prepare($sql);$x->execute($args);if((int)$x->fetchColumn()>0)$blockers[]=$label;}
        if($blockers)throw new RuntimeException('No se reclasificó: esta identidad tiene '.implode(', ',$blockers).'. Revise el caso manualmente para no convertir una persona real en vehículo.');

        $m=$this->pdo->prepare("SELECT m.*,pr.name property_name FROM bh_property_memberships m JOIN bh_properties pr ON pr.id=m.property_id WHERE m.person_id=? AND m.status='active' ORDER BY m.is_primary DESC,m.id");$m->execute([$personId]);$memberships=$m->fetchAll()?:[];
        $propertyId=max(0,(int)($data['property_id']??0));
        if($propertyId<1){if(count($memberships)!==1)throw new RuntimeException('Seleccione la unidad destino. Esta identidad tiene '.count($memberships).' vinculaciones activas.');$propertyId=(int)$memberships[0]['property_id'];}
        $valid=false;foreach($memberships as $mm)if((int)$mm['property_id']===$propertyId){$valid=true;break;}if(!$valid)throw new RuntimeException('La unidad seleccionada no pertenece a las vinculaciones activas de esta persona.');

        $raw=[];$externalPin='';$map=$this->pdo->prepare("SELECT m.external_id,eo.payload_json FROM bh_external_mappings m LEFT JOIN bh_external_objects eo ON eo.connector_instance_id=m.connector_instance_id AND eo.object_type=m.external_object_type AND (eo.external_id=m.external_id OR eo.external_code=m.external_id) WHERE m.installation_id=? AND m.local_entity_type='person' AND m.local_entity_id=? ORDER BY m.id LIMIT 1");$map->execute([$iid,$personId]);$mr=$map->fetch();if(is_array($mr)){$externalPin=trim((string)($mr['external_id']??''));$raw=json_decode((string)($mr['payload_json']??''),true);if(!is_array($raw))$raw=[];}
        $display=trim((string)($data['display_name']??$person['display_name']??''));if($display==='')$display=trim((string)($raw['name']??'').' '.(string)($raw['lastName']??''));if($display==='')$display='Vehículo';
        $plate=mb_strtoupper(trim((string)($data['plate']??$raw['carPlate']??'')));$make=trim((string)($data['make']??$raw['name']??''));$model=trim((string)($data['model']??$raw['lastName']??''));$color=trim((string)($data['color']??''));$rfid=trim((string)($data['rfid_card_no']??$raw['cardNo']??''));$levels=trim((string)($data['zk_access_level_ids']??$raw['accLevelIds']??''));
        // Reclasificación masiva debe ser conservadora: nunca crear un segundo Vehicle si la misma
        // credencial RFID o placa ya existe. En ese caso se omite y se exige revisión/merge manual.
        if($rfid!==''){$x=$this->pdo->prepare("SELECT v.id,v.display_name,pr.name property_name FROM bh_vehicle_credentials vc JOIN bh_vehicles v ON v.id=vc.vehicle_id LEFT JOIN bh_properties pr ON pr.id=v.property_id WHERE vc.installation_id=? AND vc.provider='zkbio' AND vc.credential_type IN ('rfid','card') AND vc.credential_value=? AND vc.status='active' LIMIT 1");$x->execute([$iid,$rfid]);if($ev=$x->fetch())throw new RuntimeException('No se reclasificó: el RFID ya pertenece al vehículo '.((string)($ev['display_name']??'')?:('#'.(int)$ev['id'])).' en '.((string)($ev['property_name']??'otra unidad')).'. Revise/merge el caso manualmente.');}
        if($plate!==''){$x=$this->pdo->prepare("SELECT v.id,v.display_name,pr.name property_name FROM bh_vehicles v LEFT JOIN bh_properties pr ON pr.id=v.property_id WHERE v.installation_id=? AND v.plate=? AND v.status<>'inactive' LIMIT 1");$x->execute([$iid,$plate]);if($ev=$x->fetch())throw new RuntimeException('No se reclasificó: la placa ya pertenece al vehículo '.((string)($ev['display_name']??'')?:('#'.(int)$ev['id'])).' en '.((string)($ev['property_name']??'otra unidad')).'. Revise/merge el caso manualmente.');}
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare("INSERT INTO bh_vehicles (public_uuid,installation_id,property_id,person_id,display_name,plate,make,model,color,notes,zk_access_level_ids,status,external_provider,external_sync_status,external_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,'active',?,?,?)")->execute([self::uuid(),$iid,$propertyId,null,$display,$plate!==''?$plate:null,$make?:null,$model?:null,$color?:null,'Reclasificado desde Persona Property #'.$personId,$levels?:null,$externalPin!==''?'zkbio':null,$externalPin!==''?'synced':'not_requested',$externalPin!==''?$externalPin:null]);$vehicleId=(int)$this->pdo->lastInsertId();
            if($rfid!==''){$this->pdo->prepare("INSERT INTO bh_vehicle_credentials (public_uuid,installation_id,vehicle_id,credential_type,credential_value,provider,is_primary,status) VALUES (?,?,?,'rfid',?,'zkbio',1,'active') ON DUPLICATE KEY UPDATE vehicle_id=VALUES(vehicle_id),is_primary=1,status='active'")->execute([self::uuid(),$iid,$vehicleId,$rfid]);}
            // Preserva cualquier binding externo: el mismo PIN/tag de fabricante pasa a apuntar al Vehicle.
            $this->pdo->prepare("UPDATE bh_external_mappings SET local_entity_type='vehicle',local_entity_id=?,external_name=? WHERE installation_id=? AND local_entity_type='person' AND local_entity_id=?")->execute([$vehicleId,$display,$iid,$personId]);
            $this->pdo->prepare('DELETE FROM bh_property_memberships WHERE person_id=?')->execute([$personId]);
            $this->pdo->prepare('DELETE FROM bh_people WHERE id=? AND installation_id=?')->execute([$personId,$iid]);
            $this->audit('person.reclassified_to_vehicle','vehicle',$vehicleId,['former_person_id'=>$personId,'display_name'=>$display,'property_id'=>$propertyId,'plate'=>$plate?:null,'rfid_present'=>$rfid!=='']);
            $this->pdo->commit();return ['vehicle_id'=>$vehicleId,'former_person_id'=>$personId,'display_name'=>$display,'property_id'=>$propertyId];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function bulkReclassifyPeopleToVehicles(array $personIds): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$personIds),static fn(int $v):bool=>$v>0)));if(!$ids)throw new InvalidArgumentException('Seleccione al menos una persona.');
        $result=['converted'=>0,'skipped'=>0,'errors'=>[]];foreach($ids as $id){try{$this->reclassifyPersonToVehicle($id,[]);$result['converted']++;}catch(Throwable $e){$result['skipped']++;$result['errors'][]='#'.$id.': '.$e->getMessage();}}return $result;
    }

    public function deleteMembership(int $id): void
    {
        $stmt=$this->pdo->prepare('DELETE m FROM bh_property_memberships m JOIN bh_people p ON p.id=m.person_id WHERE m.id=? AND p.installation_id=?');$stmt->execute([$id,$this->installationId()]);$this->audit('membership.delete','membership',$id,[]);
    }

    public function deletePerson(int $id): string
    {
        $this->assertOwned('bh_people',$id);
        foreach(['SELECT COUNT(*) FROM bh_credentials WHERE person_id=?','SELECT COUNT(*) FROM bh_visits WHERE visitor_person_id=?','SELECT COUNT(*) FROM bh_invitations WHERE visitor_person_id=?','SELECT COUNT(*) FROM bh_reservations WHERE requester_person_id=?'] as $sql){$s=$this->pdo->prepare($sql);$s->execute([$id]);if((int)$s->fetchColumn()>0){$this->pdo->prepare("UPDATE bh_people SET status='inactive' WHERE id=?")->execute([$id]);$this->audit('person.deactivate','person',$id,[]);return 'deactivated';}}
        $this->pdo->beginTransaction();try{$this->pdo->prepare('DELETE FROM bh_property_memberships WHERE person_id=?')->execute([$id]);$this->pdo->prepare('DELETE FROM bh_global_user_links WHERE local_person_id=?')->execute([$id]);$this->pdo->prepare('UPDATE bh_local_users SET person_id=NULL WHERE person_id=?')->execute([$id]);$this->pdo->prepare('DELETE FROM bh_people WHERE id=?')->execute([$id]);$this->audit('person.delete','person',$id,[]);$this->pdo->commit();return 'deleted';}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /**
     * Contexto y catálogos del asistente de generación masiva.
     */
    public function structureWizardContext(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id,code,name,property_type FROM bh_properties "
            ."WHERE installation_id=? AND parent_id IS NULL AND status<>'inactive' "
            ."ORDER BY name"
        );
        $stmt->execute([$this->installationId()]);

        $history = $this->pdo->prepare(
            "SELECT r.*,u.username created_by_name FROM bh_structure_wizard_runs r "
            ."LEFT JOIN bh_local_users u ON u.id=r.created_by "
            ."WHERE r.installation_id=? ORDER BY r.created_at DESC LIMIT 10"
        );
        $history->execute([$this->installationId()]);

        $existingAreas = $this->pdo->prepare(
            "SELECT id,code,name,area_type,status FROM bh_areas "
            ."WHERE installation_id=? ORDER BY code"
        );
        $existingAreas->execute([$this->installationId()]);
        $existingAreaMap = [];
        foreach ($existingAreas->fetchAll() as $area) {
            $existingAreaMap[(string)$area['code']] = $area;
        }

        return [
            'profile' => $this->installationProfile(),
            'roots' => $stmt->fetchAll(),
            'standard_areas' => self::standardAreaCatalog(),
            'existing_areas' => $existingAreaMap,
            'existing_properties' => $this->propertyOptions(),
            'history' => $history->fetchAll(),
        ];
    }

    /**
     * Genera una estructura completa dentro de una sola transacción.
     * El servidor vuelve a validar y construir todo el plan; no confía en la
     * vista previa del navegador.
     */
    public function generateStructureFromWizard(array $input): array
    {
        $raw=(string)($input['wizard_payload']??'');
        $plan=json_decode($raw,true);
        if(!is_array($plan)||($plan['schema_version']??null)!==2)throw new InvalidArgumentException('No se recibió una configuración válida del diseñador topológico.');

        $root=is_array($plan['root']??null)?$plan['root']:[];
        $towers=is_array($plan['towers']??null)?array_values($plan['towers']):[];
        $naming=is_array($plan['naming']??null)?$plan['naming']:[];
        $parkingZones=is_array($plan['parking_zones']??null)?array_values($plan['parking_zones']):[];
        $storageZones=is_array($plan['storage_zones']??null)?array_values($plan['storage_zones']):[];
        $areas=is_array($plan['areas']??null)?array_values($plan['areas']):[];
        $vertical=is_array($plan['vertical_transports']??null)?array_values($plan['vertical_transports']):[];
        if(!$towers||count($towers)>50)throw new InvalidArgumentException('Indique entre 1 y 50 torres o edificios.');

        $separator=(string)($plan['separator']??'-');
        if(!in_array($separator,['','-','_','.'],true))$separator='-';
        $render=static function(string $template,array $tokens):string{
            uksort($tokens,static fn(string $a,string $b):int=>strlen($b)<=>strlen($a));
            return strtr($template,$tokens);
        };
        $floorNaming=is_array($naming['floor']??null)?$naming['floor']:[];
        $aptNaming=is_array($naming['apartment']??null)?$naming['apartment']:[];
        $parkingNaming=is_array($naming['parking']??null)?$naming['parking']:[];
        $storageNaming=is_array($naming['storage']??null)?$naming['storage']:[];
        $validNamingSources=['condominium_document','internal_rule','mixed','pending'];
        $floorNamingSource=in_array((string)($floorNaming['source']??''),$validNamingSources,true)?(string)$floorNaming['source']:'pending';
        $aptNamingSource=in_array((string)($aptNaming['source']??''),$validNamingSources,true)?(string)$aptNaming['source']:'pending';
        $parkingNamingSource=in_array((string)($parkingNaming['source']??''),$validNamingSources,true)?(string)$parkingNaming['source']:'pending';
        $storageNamingSource=in_array((string)($storageNaming['source']??''),$validNamingSources,true)?(string)$storageNaming['source']:'pending';
        $floorCodeTemplate=trim((string)($floorNaming['code_template']??'{TORRE}{SEP}{NIVEL}'));
        $floorNameTemplate=trim((string)($floorNaming['name_template']??'{TIPO_NIVEL} {NIVEL} · {TORRE_NOMBRE}'));
        $aptCodeTemplate=trim((string)($aptNaming['code_template']??'{TORRE}{SEP}{NIVEL_NUM}{APTO}'));
        $aptNameTemplate=trim((string)($aptNaming['name_template']??'Apartamento {CODIGO}'));
        $unitSequence=in_array((string)($aptNaming['unit_sequence']??''),['numbers','letters'],true)?(string)$aptNaming['unit_sequence']:'letters';
        $unitDigits=min(4,max(1,(int)($aptNaming['unit_digits']??2)));
        $floorDigits=min(3,max(1,(int)($aptNaming['floor_digits']??2)));
        $letterCase=(string)($aptNaming['letter_case']??'upper')==='lower'?'lower':'upper';
        if($floorCodeTemplate===''||!preg_match('/\{(?:NIVEL|NIVEL_NUM|PISO|PISO_NUM)\}/',$floorCodeTemplate))throw new InvalidArgumentException('La plantilla de niveles debe incluir una variable de nivel.');
        if($aptCodeTemplate===''||!preg_match('/\{(?:APTO|LETRA|N|UNIDAD)\}/',$aptCodeTemplate))throw new InvalidArgumentException('La plantilla de apartamentos debe incluir una variable que distinga la unidad.');

        $rootMode=(string)($root['mode']??'create');$rootId=null;
        $rootCode=$this->code((string)($root['code']??config('app.instance_code')));$rootName=trim((string)($root['name']??config('app.instance_name')));
        $rootType=(string)($root['property_type']??'site');if(!in_array($rootType,['site','urbanization','building','other'],true))$rootType='site';
        if($rootMode==='existing'){$rootId=(int)($root['existing_id']??0);if($rootId<1)throw new InvalidArgumentException('Seleccione la estructura raíz existente.');$this->assertOwned('bh_properties',$rootId);}elseif($rootCode===''||$rootName===''){throw new InvalidArgumentException('Código y nombre de la estructura raíz son obligatorios.');}

        $propertyPlan=[];$areaPlan=[];$verticalPlan=[];$generatedPropertyCodes=[];$generatedAreaCodes=[];$generatedAccessCodes=[];$generatedVerticalCodes=[];
        $summary=['roots'=>0,'towers'=>0,'floors'=>0,'apartments'=>0,'parking_zones'=>0,'parkings'=>0,'storage_zones'=>0,'storages'=>0,'areas'=>0,'areas_existing'=>0,'vertical_transports'=>0,'vertical_stops'=>0,'access_doors'=>0];
        if($rootMode!=='existing'){$this->appendUniqueCode($generatedPropertyCodes,$rootCode,'estructura');$propertyPlan[]=['kind'=>'root','ref'=>'root','parent_ref'=>null,'code'=>$rootCode,'name'=>$rootName,'type'=>$rootType,'floor_label'=>null,'depth'=>0,'metadata'=>['kind'=>'root']];$summary['roots']=1;}

        $levelTypeMap=['floor'=>'floor','ground_floor'=>'ground_floor','basement'=>'basement','mezzanine'=>'mezzanine','terrace'=>'terrace','roof'=>'roof','technical_floor'=>'technical_floor'];
        $levelLabelMap=['floor'=>'Piso','ground_floor'=>'Planta baja','basement'=>'Sótano','mezzanine'=>'Mezzanina','terrace'=>'Terraza','roof'=>'Azotea','technical_floor'=>'Piso técnico'];
        $knownRefs=['root'=>true];$externalRefs=[];
        $existingPropertyStmt=$this->pdo->prepare("SELECT id FROM bh_properties WHERE installation_id=? AND status<>'inactive'");$existingPropertyStmt->execute([$this->installationId()]);
        foreach($existingPropertyStmt->fetchAll(PDO::FETCH_COLUMN) as $existingPropertyId){$ref='existing:'.(int)$existingPropertyId;$knownRefs[$ref]=true;$externalRefs[$ref]=(int)$existingPropertyId;}
        foreach($towers as $ti=>$tower){
            if(!is_array($tower))throw new InvalidArgumentException('Configuración de torre no válida.');
            $towerCode=$this->code((string)($tower['code']??''));$towerName=trim((string)($tower['name']??''));
            if($towerCode===''||$towerName==='')throw new InvalidArgumentException('Cada torre debe tener código y nombre.');
            $towerRef='tower:'.$ti;$knownRefs[$towerRef]=true;$this->appendUniqueCode($generatedPropertyCodes,$towerCode,'estructura');
            $propertyPlan[]=['kind'=>'tower','ref'=>$towerRef,'parent_ref'=>'root','code'=>$towerCode,'name'=>$towerName,'type'=>'tower','floor_label'=>null,'depth'=>1,'metadata'=>['kind'=>'tower']];$summary['towers']++;
            $levels=is_array($tower['levels']??null)?array_values($tower['levels']):[];if(!$levels)throw new InvalidArgumentException($towerName.' no tiene niveles habilitados.');
            foreach($levels as $li=>$level){
                if(!is_array($level))continue;$kind=(string)($level['kind']??'floor');if(!isset($levelTypeMap[$kind]))$kind='floor';
                $label=trim((string)($level['label']??''));if($label==='')throw new InvalidArgumentException('Todos los niveles de '.$towerName.' deben tener etiqueta.');
                $number=(int)($level['number']??0);$units=min(100,max(0,(int)($level['units']??0)));$levelUid=preg_replace('/[^A-Za-z0-9_-]+/','',(string)($level['uid']??''))?:'L'.$li;
                $levelRef=$towerRef.':level:'.$levelUid;$knownRefs[$levelRef]=true;
                $levelNumber=$kind==='basement'?(string)abs($number):($kind==='ground_floor'?'0':str_pad((string)$number,$floorDigits,'0',STR_PAD_LEFT));
                $tokens=['{SEP}'=>$separator,'{TORRE}'=>$towerCode,'{TORRE_NOMBRE}'=>$towerName,'{TORRE_LETRA}'=>self::letters($ti),'{TORRE_N}'=>(string)($ti+1),'{NIVEL}'=>$label,'{PISO}'=>$label,'{NIVEL_NUM}'=>$levelNumber,'{PISO_NUM}'=>$levelNumber,'{TIPO_NIVEL}'=>$levelLabelMap[$kind]];
                $floorCode=$this->code($render($floorCodeTemplate,$tokens));if($floorCode==='')throw new InvalidArgumentException('La nomenclatura produjo un código de nivel vacío.');$this->appendUniqueCode($generatedPropertyCodes,$floorCode,'estructura');$tokens['{CODIGO}']=$floorCode;
                $floorName=trim($render($floorNameTemplate,$tokens))?:$levelLabelMap[$kind].' '.$label.' · '.$towerName;
                $propertyPlan[]=['kind'=>'level','ref'=>$levelRef,'parent_ref'=>$towerRef,'code'=>$floorCode,'name'=>$floorName,'type'=>$levelTypeMap[$kind],'floor_label'=>$label,'depth'=>2,'metadata'=>['kind'=>'level','level_kind'=>$kind,'logical_number'=>$number,'naming_source'=>$floorNamingSource,'naming_preset'=>(string)($floorNaming['preset']??'custom')]];$summary['floors']++;
                for($u=1;$u<=$units;$u++){
                    $letter=self::letters($u-1);if($letterCase==='lower')$letter=strtolower($letter);$numeric=str_pad((string)$u,$unitDigits,'0',STR_PAD_LEFT);
                    $unitTokens=$tokens+['{APTO}'=>$unitSequence==='letters'?$letter:$numeric,'{UNIDAD}'=>$unitSequence==='letters'?$letter:$numeric,'{LETRA}'=>$letter,'{N}'=>(string)$u,'{NUMERO}'=>$numeric];
                    $aptCode=$this->code($render($aptCodeTemplate,$unitTokens));if($aptCode==='')throw new InvalidArgumentException('La nomenclatura produjo un código de apartamento vacío.');$this->appendUniqueCode($generatedPropertyCodes,$aptCode,'estructura');$unitTokens['{CODIGO}']=$aptCode;
                    $propertyPlan[]=['kind'=>'apartment','parent_ref'=>$levelRef,'code'=>$aptCode,'name'=>trim($render($aptNameTemplate,$unitTokens))?:'Apartamento '.$aptCode,'type'=>'apartment','floor_label'=>$label,'depth'=>3,'metadata'=>['kind'=>'apartment','sequence'=>$u,'naming_source'=>$aptNamingSource,'naming_preset'=>(string)($aptNaming['preset']??'custom')]];$summary['apartments']++;
                }
            }
        }

        $buildZone=function(array $zones,string $kind,array $defaultNaming)use(&$propertyPlan,&$generatedPropertyCodes,&$summary,$knownRefs,$separator,$render,$parkingNamingSource,$storageNamingSource,$parkingNaming,$storageNaming):void{
            foreach($zones as $zi=>$zone){if(!is_array($zone))continue;$code=$this->code((string)($zone['code']??''));$name=trim((string)($zone['name']??''));$parentRef=(string)($zone['location_ref']??'root');if(!isset($knownRefs[$parentRef]))throw new InvalidArgumentException('Una zona apunta a una ubicación inexistente.');if($code===''||$name==='')throw new InvalidArgumentException('Las zonas deben tener código y nombre.');$this->appendUniqueCode($generatedPropertyCodes,$code,'estructura');$ref=$kind.'-zone:'.$zi;$propertyPlan[]=['kind'=>$kind.'_zone','ref'=>$ref,'parent_ref'=>$parentRef,'code'=>$code,'name'=>$name,'type'=>$kind==='parking'?'parking_zone':'storage','floor_label'=>null,'depth'=>3,'metadata'=>['kind'=>$kind.'_zone','zone_type'=>(string)($zone['zone_type']??'other'),'naming_source'=>$kind==='parking'?$parkingNamingSource:$storageNamingSource,'naming_preset'=>(string)(($kind==='parking'?$parkingNaming:$storageNaming)['preset']??'custom')]];$summary[$kind.'_zones']++;
                $count=min(5000,max(0,(int)($zone['count']??0)));$digits=min(5,max(1,(int)($zone['digits']??3)));$codeTemplate=trim((string)($zone['code_template']??$defaultNaming['code']));$nameTemplate=trim((string)($zone['name_template']??$defaultNaming['name']));
                for($n=1;$n<=$count;$n++){$formatted=str_pad((string)$n,$digits,'0',STR_PAD_LEFT);$tokens=['{SEP}'=>$separator,'{N}'=>$formatted,'{NUMERO}'=>(string)$n,'{ZONA}'=>$code,'{ZONA_NOMBRE}'=>$name];$itemCode=$this->code($render($codeTemplate,$tokens));$this->appendUniqueCode($generatedPropertyCodes,$itemCode,'estructura');$tokens['{CODIGO}']=$itemCode;$propertyPlan[]=['kind'=>$kind,'parent_ref'=>$ref,'code'=>$itemCode,'name'=>trim($render($nameTemplate,$tokens))?:$name.' '.$formatted,'type'=>$kind==='parking'?'parking':'storage','floor_label'=>null,'depth'=>4,'metadata'=>['kind'=>$kind,'sequence'=>$n,'zone_type'=>(string)($zone['zone_type']??'other'),'naming_source'=>$kind==='parking'?$parkingNamingSource:$storageNamingSource,'naming_preset'=>(string)(($kind==='parking'?$parkingNaming:$storageNaming)['preset']??'custom')]];$summary[$kind==='parking'?'parkings':'storages']++;}
            }
        };
        $buildZone($parkingZones,'parking',['code'=>(string)($parkingNaming['code_template']??'EST{SEP}{N}'),'name'=>(string)($parkingNaming['name_template']??'Estacionamiento {N}')]);
        $buildZone($storageZones,'storage',['code'=>(string)($storageNaming['code_template']??'MAL{SEP}{N}'),'name'=>(string)($storageNaming['name_template']??'Maletero {N}')]);

        $areaCatalog=self::standardAreaCatalog();$existingAreaRows=$this->existingAreaMap();
        foreach($areas as $ai=>$area){if(!is_array($area))continue;$code=$this->code((string)($area['code']??''));$name=trim((string)($area['name']??''));$locationRef=(string)($area['location_ref']??'root');if(!isset($knownRefs[$locationRef]))throw new InvalidArgumentException('El área '.$name.' apunta a una ubicación inexistente.');if($code===''||$name==='')throw new InvalidArgumentException('Todas las áreas deben tener código y nombre.');$this->appendUniqueCode($generatedAreaCodes,$code,'áreas');if(isset($existingAreaRows[$code])){$summary['areas_existing']++;continue;}$catalogKey=(string)($area['catalog_key']??'custom');$catalog=$areaCatalog[$catalogKey]??[];$areaType=(string)($area['area_type']??($catalog['area_type']??'common'));if(!in_array($areaType,['common','reservable','security','technical','commercial','office','parking','circulation','other'],true))$areaType='common';$scopeType=(string)($area['scope_type']??($catalog['scope_type']??match($areaType){'technical'=>'technical','security'=>'restricted',default=>'common'}));if(!in_array($scopeType,['common','private','technical','service','restricted','public'],true))$scopeType='common';$usageType=(string)($area['usage_type']??($catalog['usage_type']??match($areaType){'reservable'=>'reservable','security'=>'security','technical'=>'technical','commercial'=>'commercial','office'=>'office','parking'=>'parking','circulation'=>'circulation',default=>'general'}));if(!in_array($usageType,['general','lobby','circulation','parking','office','commercial','technical','security','recreation','amenity','reservable','storage','residential','other'],true))$usageType='other';$areaPlan[]=['code'=>$code,'name'=>$name,'property_ref'=>$locationRef,'area_type'=>$areaType,'scope_type'=>$scopeType,'usage_type'=>$usageType,'is_reservable'=>!empty($area['is_reservable'])?1:(int)($catalog['is_reservable']??0),'is_security_zone'=>!empty($area['is_security_zone'])?1:(int)($catalog['is_security_zone']??0),'capacity'=>$catalog['capacity']??null,'opening_time'=>$catalog['opening_time']??null,'closing_time'=>$catalog['closing_time']??null,'requires_approval'=>$catalog['requires_approval']??1,'maximum_minutes'=>$catalog['maximum_minutes']??null,'rules_text'=>$catalog['rules_text']??null,'metadata'=>['catalog_key'=>$catalogKey,'location_ref'=>$locationRef]];$summary['areas']++;}

        foreach($vertical as $vi=>$transport){if(!is_array($transport))continue;$code=$this->code((string)($transport['code']??''));$name=trim((string)($transport['name']??''));$type=(string)($transport['transport_type']??'passenger_elevator');if(!in_array($type,['passenger_elevator','service_elevator','freight_elevator','escalator','stair','other'],true))$type='other';$towerRefs=is_array($transport['tower_refs']??null)?array_values(array_unique(array_map('strval',$transport['tower_refs']))):[];$stopRefs=is_array($transport['stop_refs']??null)?array_values(array_unique(array_map('strval',$transport['stop_refs']))):[];if($code===''||$name===''||!$towerRefs||!$stopRefs)throw new InvalidArgumentException('Cada ascensor debe tener código, nombre, torre y paradas.');foreach([...$towerRefs,...$stopRefs] as $ref)if(!isset($knownRefs[$ref]))throw new InvalidArgumentException('Un ascensor apunta a una ubicación inexistente.');foreach($stopRefs as $stopRef){$belongs=false;foreach($towerRefs as $towerRef)if(str_starts_with($stopRef,$towerRef.':level:')){$belongs=true;break;}if(!$belongs)throw new InvalidArgumentException('Una parada del ascensor '.$name.' pertenece a una torre no seleccionada.');}$this->appendUniqueCode($generatedVerticalCodes,$code,'ascensores');$doors=[];if(!empty($transport['access_doors']))foreach($stopRefs as $stopRef){$doorStopKey=$this->code(str_replace(':','-',$stopRef));$doorCode=$this->code($code.'-'.$doorStopKey.'-PUERTA');if(strlen($doorCode)>60)$doorCode=substr($this->code($code.'-'.substr(hash('sha256',$stopRef),0,10).'-PUERTA'),0,60);$this->appendUniqueCode($generatedAccessCodes,$doorCode,'accesos');$doors[$stopRef]=$doorCode;$summary['access_doors']++;}$verticalPlan[]=['code'=>$code,'name'=>$name,'transport_type'=>$type,'tower_refs'=>$towerRefs,'stop_refs'=>$stopRefs,'access_doors'=>!empty($transport['access_doors']),'door_codes'=>$doors];$summary['vertical_transports']++;$summary['vertical_stops']+=count($stopRefs);}

        $existingProperties=$this->existingCodes('bh_properties');$conflicts=array_values(array_intersect(array_keys($generatedPropertyCodes),$existingProperties));if($conflicts)throw new InvalidArgumentException('Ya existen códigos de estructura: '.implode(', ',array_slice($conflicts,0,12)).'.');
        if($generatedVerticalCodes){$ph=implode(',',array_fill(0,count($generatedVerticalCodes),'?'));$q=$this->pdo->prepare("SELECT code FROM bh_vertical_transports WHERE installation_id=? AND code IN ({$ph})");$q->execute([$this->installationId(),...array_keys($generatedVerticalCodes)]);$c=$q->fetchAll(PDO::FETCH_COLUMN);if($c)throw new InvalidArgumentException('Ya existen ascensores con estos códigos: '.implode(', ',$c));}
        if($generatedAccessCodes){$ph=implode(',',array_fill(0,count($generatedAccessCodes),'?'));$q=$this->pdo->prepare("SELECT code FROM bh_access_points WHERE installation_id=? AND code IN ({$ph})");$q->execute([$this->installationId(),...array_keys($generatedAccessCodes)]);$c=$q->fetchAll(PDO::FETCH_COLUMN);if($c)throw new InvalidArgumentException('Ya existen accesos con estos códigos: '.implode(', ',$c));}
        $total=array_sum($summary)-$summary['areas_existing'];if($total<1)throw new InvalidArgumentException('El diseñador no generó elementos nuevos.');if($total>15000)throw new InvalidArgumentException('La generación supera 15.000 elementos. Divida el proceso.');

        $configuration=$plan;$configuration['existing_area_policy']='reuse_without_changes';
        $this->pdo->beginTransaction();
        try{
            $user=current_user()??[];$createdBy=is_numeric($user['id']??null)?(int)$user['id']:null;$runUuid=self::uuid();$runName='Diseño topológico '.date('Y-m-d H:i');
            $run=$this->pdo->prepare("INSERT INTO bh_structure_wizard_runs (installation_id,public_uuid,run_name,status,configuration_json,summary_json,created_by) VALUES (?,?,?,'completed',?,?,?)");$run->execute([$this->installationId(),$runUuid,$runName,json_encode($configuration,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($summary),$createdBy]);$runId=(int)$this->pdo->lastInsertId();
            $propertyInsert=$this->pdo->prepare("INSERT INTO bh_properties (installation_id,parent_id,property_type,code,name,floor_label,status,metadata_json) VALUES (?,?,?,?,?,?,'active',?)");$itemInsert=$this->pdo->prepare('INSERT INTO bh_structure_wizard_items (run_id,entity_type,entity_id,entity_code,hierarchy_depth) VALUES (?,?,?,?,?)');$refs=$externalRefs;if($rootId)$refs['root']=$rootId;
            foreach($propertyPlan as $item){$parentId=null;if($item['parent_ref']!==null){if(!isset($refs[$item['parent_ref']]))throw new RuntimeException('No se pudo resolver la jerarquía del diseñador.');$parentId=$refs[$item['parent_ref']];}$metadata=$item['metadata']+['generated_by'=>'topology_designer','run_uuid'=>$runUuid];$propertyInsert->execute([$this->installationId(),$parentId,$item['type'],$item['code'],$item['name'],$item['floor_label'],json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);$id=(int)$this->pdo->lastInsertId();if(!empty($item['ref']))$refs[$item['ref']]=$id;if($item['kind']==='root'){$refs['root']=$id;$rootId=$id;}$itemInsert->execute([$runId,'property',$id,$item['code'],$item['depth']]);}
            if(!$rootId)throw new RuntimeException('No se pudo determinar la estructura raíz.');
            $areaInsert=$this->pdo->prepare("INSERT INTO bh_areas (installation_id,property_id,code,name,area_type,scope_type,usage_type,is_reservable,is_security_zone,capacity,opening_time,closing_time,base_rate,security_deposit,requires_approval,minimum_minutes,maximum_minutes,advance_days,cancellation_hours,rules_text,metadata_json,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0,0,?,60,?,90,24,?,?,'active')");
            foreach($areaPlan as $area){$propertyId=$refs[$area['property_ref']]??null;$areaInsert->execute([$this->installationId(),$propertyId,$area['code'],$area['name'],$area['area_type'],$area['scope_type'],$area['usage_type'],$area['is_reservable'],$area['is_security_zone'],$area['capacity'],$area['opening_time'],$area['closing_time'],$area['requires_approval'],$area['maximum_minutes'],$area['rules_text'],json_encode($area['metadata'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);$areaId=(int)$this->pdo->lastInsertId();$itemInsert->execute([$runId,'area',$areaId,$area['code'],3]);}
            $transportInsert=$this->pdo->prepare("INSERT INTO bh_vertical_transports (installation_id,public_uuid,code,name,transport_type,status,metadata_json) VALUES (?,?,?,?,?,'planned',?)");$towerLink=$this->pdo->prepare('INSERT INTO bh_vertical_transport_towers (vertical_transport_id,tower_property_id) VALUES (?,?)');$stopInsert=$this->pdo->prepare('INSERT INTO bh_vertical_transport_stops (vertical_transport_id,level_property_id,access_point_id,stop_order,is_restricted,notes) VALUES (?,?,?,?,0,NULL)');$accessInsert=$this->pdo->prepare("INSERT INTO bh_access_points (installation_id,property_id,area_id,device_id,code,name,access_type,operation_mode,remote_policy,status) VALUES (?,?,NULL,NULL,?,?,'elevator','manual','disabled','maintenance')");
            foreach($verticalPlan as $v){$transportInsert->execute([$this->installationId(),self::uuid(),$v['code'],$v['name'],$v['transport_type'],json_encode(['generated_by'=>'topology_designer','run_uuid'=>$runUuid],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);$transportId=(int)$this->pdo->lastInsertId();$itemInsert->execute([$runId,'vertical_transport',$transportId,$v['code'],2]);foreach($v['tower_refs'] as $towerRef)$towerLink->execute([$transportId,$refs[$towerRef]]);foreach($v['stop_refs'] as $order=>$stopRef){$accessId=null;if($v['access_doors']){$doorCode=$v['door_codes'][$stopRef];$accessInsert->execute([$this->installationId(),$refs[$stopRef],$doorCode,'Puerta de acceso · '.$v['name']]);$accessId=(int)$this->pdo->lastInsertId();$itemInsert->execute([$runId,'access_point',$accessId,$doorCode,4]);}$stopInsert->execute([$transportId,$refs[$stopRef],$accessId,$order+1]);}}
            $this->pdo->prepare('UPDATE bh_structure_wizard_runs SET root_property_id=?,summary_json=? WHERE id=?')->execute([$rootId,json_encode($summary),$runId]);$this->audit('topology_designer.generate','structure_wizard_run',$runId,['summary'=>$summary,'run_uuid'=>$runUuid]);$this->pdo->commit();return['run_id'=>$runId,'run_uuid'=>$runUuid,'summary'=>$summary,'total'=>$total];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }


    /**
     * Deshace una ejecución siempre que sus registros no tengan actividad real.
     */
    public function rollbackStructureWizardRun(int $runId): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM bh_structure_wizard_runs WHERE id=? AND installation_id=? LIMIT 1');
        $stmt->execute([$runId,$this->installationId()]);
        $run=$stmt->fetch();
        if(!$run)throw new RuntimeException('No se encontró la ejecución del diseñador.');
        if(!in_array($run['status'],['completed','rollback_blocked'],true))throw new InvalidArgumentException('Esta ejecución ya no puede deshacerse.');

        $items=$this->pdo->prepare("SELECT * FROM bh_structure_wizard_items WHERE run_id=? ORDER BY CASE entity_type WHEN 'access_point' THEN 0 WHEN 'vertical_transport' THEN 1 WHEN 'area' THEN 2 ELSE 3 END,hierarchy_depth DESC,id DESC");
        $items->execute([$runId]);$rows=$items->fetchAll();
        $runPropertyIds=[];$runAreaIds=[];$runAccessIds=[];$runTransportIds=[];
        foreach($rows as $row){
            $id=(int)$row['entity_id'];
            if($row['entity_type']==='property')$runPropertyIds[]=$id;
            elseif($row['entity_type']==='area')$runAreaIds[]=$id;
            elseif($row['entity_type']==='access_point')$runAccessIds[]=$id;
            elseif($row['entity_type']==='vertical_transport')$runTransportIds[]=$id;
        }
        $runPropertyLookup=array_fill_keys($runPropertyIds,true);$blockers=[];
        foreach($rows as $row){
            $id=(int)$row['entity_id'];
            if($row['entity_type']==='area'){
                foreach(['bh_reservations'=>'area_id','bh_cameras'=>'area_id'] as $table=>$column){$q=$this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column}=?");$q->execute([$id]);if((int)$q->fetchColumn()>0)$blockers[]=$row['entity_code'].' tiene actividad en '.$table;}
                if($runAccessIds){$ph=implode(',',array_fill(0,count($runAccessIds),'?'));$q=$this->pdo->prepare("SELECT COUNT(*) FROM bh_access_points WHERE area_id=? AND id NOT IN ({$ph})");$q->execute([$id,...$runAccessIds]);}else{$q=$this->pdo->prepare('SELECT COUNT(*) FROM bh_access_points WHERE area_id=?');$q->execute([$id]);}if((int)$q->fetchColumn()>0)$blockers[]=$row['entity_code'].' tiene accesos creados después';
            }elseif($row['entity_type']==='property'){
                foreach(['bh_property_memberships'=>'property_id','bh_reservations'=>'property_id','bh_devices'=>'property_id'] as $table=>$column){$q=$this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column}=?");$q->execute([$id]);if((int)$q->fetchColumn()>0)$blockers[]=$row['entity_code'].' tiene actividad en '.$table;}
                if($runAccessIds){$ph=implode(',',array_fill(0,count($runAccessIds),'?'));$q=$this->pdo->prepare("SELECT COUNT(*) FROM bh_access_points WHERE property_id=? AND id NOT IN ({$ph})");$q->execute([$id,...$runAccessIds]);}else{$q=$this->pdo->prepare('SELECT COUNT(*) FROM bh_access_points WHERE property_id=?');$q->execute([$id]);}if((int)$q->fetchColumn()>0)$blockers[]=$row['entity_code'].' tiene accesos creados después';
                if($runTransportIds){$ph=implode(',',array_fill(0,count($runTransportIds),'?'));$q=$this->pdo->prepare("SELECT COUNT(*) FROM bh_vertical_transport_stops WHERE level_property_id=? AND vertical_transport_id NOT IN ({$ph})");$q->execute([$id,...$runTransportIds]);}else{$q=$this->pdo->prepare('SELECT COUNT(*) FROM bh_vertical_transport_stops WHERE level_property_id=?');$q->execute([$id]);}if((int)$q->fetchColumn()>0)$blockers[]=$row['entity_code'].' tiene paradas de ascensor externas';
                $q=$this->pdo->prepare('SELECT id,code FROM bh_properties WHERE parent_id=?');$q->execute([$id]);foreach($q->fetchAll() as $child)if(!isset($runPropertyLookup[(int)$child['id']]))$blockers[]=$row['entity_code'].' contiene '.$child['code'].' creado fuera del diseñador';
                if($runAreaIds){$ph=implode(',',array_fill(0,count($runAreaIds),'?'));$q=$this->pdo->prepare("SELECT COUNT(*) FROM bh_areas WHERE property_id=? AND id NOT IN ({$ph})");$q->execute([$id,...$runAreaIds]);}else{$q=$this->pdo->prepare('SELECT COUNT(*) FROM bh_areas WHERE property_id=?');$q->execute([$id]);}if((int)$q->fetchColumn()>0)$blockers[]=$row['entity_code'].' tiene áreas creadas después';
            }
        }
        if($blockers){$this->pdo->prepare("UPDATE bh_structure_wizard_runs SET status='rollback_blocked' WHERE id=?")->execute([$runId]);throw new RuntimeException('No se puede deshacer porque existen datos relacionados: '.implode('; ',array_slice(array_unique($blockers),0,8)));}

        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare('UPDATE bh_structure_wizard_runs SET root_property_id=NULL WHERE id=?')->execute([$runId]);
            foreach($rows as $row)if($row['entity_type']==='access_point')$this->pdo->prepare('DELETE FROM bh_access_points WHERE id=? AND installation_id=?')->execute([(int)$row['entity_id'],$this->installationId()]);
            foreach($rows as $row)if($row['entity_type']==='vertical_transport')$this->pdo->prepare('DELETE FROM bh_vertical_transports WHERE id=? AND installation_id=?')->execute([(int)$row['entity_id'],$this->installationId()]);
            foreach($rows as $row)if($row['entity_type']==='area')$this->pdo->prepare('DELETE FROM bh_areas WHERE id=? AND installation_id=?')->execute([(int)$row['entity_id'],$this->installationId()]);
            foreach($rows as $row)if($row['entity_type']==='property')$this->pdo->prepare('DELETE FROM bh_properties WHERE id=? AND installation_id=?')->execute([(int)$row['entity_id'],$this->installationId()]);
            $this->pdo->prepare("UPDATE bh_structure_wizard_runs SET status='rolled_back',rolled_back_at=NOW() WHERE id=?")->execute([$runId]);$this->audit('topology_designer.rollback','structure_wizard_run',$runId,['items'=>count($rows)]);$this->pdo->commit();return['items'=>count($rows)];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }


    public function verticalTransports(): array
    {
        $stmt=$this->pdo->prepare(
            "SELECT vt.*,"
            ." GROUP_CONCAT(DISTINCT tw.id ORDER BY tw.name SEPARATOR ',') tower_ids,"
            ." GROUP_CONCAT(DISTINCT tw.name ORDER BY tw.name SEPARATOR ', ') tower_names,"
            ." COUNT(DISTINCT st.id) stop_count, COUNT(DISTINCT st.access_point_id) access_door_count "
            ."FROM bh_vertical_transports vt "
            ."LEFT JOIN bh_vertical_transport_towers vtt ON vtt.vertical_transport_id=vt.id "
            ."LEFT JOIN bh_properties tw ON tw.id=vtt.tower_property_id "
            ."LEFT JOIN bh_vertical_transport_stops st ON st.vertical_transport_id=vt.id "
            ."WHERE vt.installation_id=? GROUP BY vt.id ORDER BY vt.name"
        );
        $stmt->execute([$this->installationId()]);
        $rows=$stmt->fetchAll();
        $stopStmt=$this->pdo->prepare(
            "SELECT st.vertical_transport_id,st.level_property_id,st.access_point_id,st.stop_order,st.is_restricted,st.notes,p.code,p.name,p.floor_label,ap.code access_code,ap.name access_name "
            ."FROM bh_vertical_transport_stops st JOIN bh_properties p ON p.id=st.level_property_id "
            ."LEFT JOIN bh_access_points ap ON ap.id=st.access_point_id "
            ."JOIN bh_vertical_transports vt ON vt.id=st.vertical_transport_id "
            ."WHERE vt.installation_id=? ORDER BY st.vertical_transport_id,st.stop_order"
        );
        $stopStmt->execute([$this->installationId()]);$by=[];$opt=$this->verticalTransportOptions();$levelMeta=[];foreach($opt['levels']??[] as $l)$levelMeta[(int)$l['id']]=$l;
        foreach($stopStmt->fetchAll() as $stop){$lm=$levelMeta[(int)$stop['level_property_id']]??[];$stop['tower_id']=$lm['tower_id']??null;$stop['tower_name']=$lm['tower_name']??null;$stop['building_id']=$lm['building_id']??null;$stop['building_name']=$lm['building_name']??null;$stop['is_shared']=$lm['is_shared']??false;$stop['level_order']=$lm['level_order']??0;$by[(int)$stop['vertical_transport_id']][]=$stop;}
        foreach($rows as &$row){$row['stops']=$by[(int)$row['id']]??[];$row['restricted_stop_count']=count(array_filter($row['stops'],fn($x)=>!empty($x['is_restricted'])));}
        return $rows;
    }

    /**
     * Digital twin contextual explorer. A single dataset feeds the hierarchical
     * structure view so the same relationships are visible from several angles.
     */
    public function structureExplorer(): array
    {
        $iid=$this->installationId();
        $q=$this->pdo->prepare("SELECT id,parent_id,property_type,code,name,floor_label,status,metadata_json FROM bh_properties WHERE installation_id=?");$q->execute([$iid]);$properties=$q->fetchAll()?:[];foreach($properties as &$p){$m=$this->jsonObject($p['metadata_json']??null);$p['level_order']=$this->levelOrder($p,$m);}unset($p);usort($properties,function($a,$b){$pa=(int)($a['parent_id']??0);$pb=(int)($b['parent_id']??0);if($pa!==$pb)return$pa<=>$pb;return$this->compareStructuralSiblings($a,$b);});
        $q=$this->pdo->prepare("SELECT id,property_id,code,name,area_type,scope_type,usage_type,status FROM bh_areas WHERE installation_id=? ORDER BY name");$q->execute([$iid]);$areas=$q->fetchAll()?:[];
        $q=$this->pdo->prepare("SELECT c.id,c.property_id,c.area_id,c.name,c.status,c.resident_visibility,a.property_id area_property_id FROM bh_cameras c LEFT JOIN bh_areas a ON a.id=c.area_id WHERE c.installation_id=? ORDER BY c.name");$q->execute([$iid]);$cameras=$q->fetchAll()?:[];
        $q=$this->pdo->prepare("SELECT ap.id,ap.property_id,ap.area_id,ap.code,ap.name,ap.access_type,ap.remote_policy,ap.status,ap.logical_state,ap.device_id,CASE WHEN vst.access_point_id IS NULL THEN 0 ELSE 1 END is_vertical_stop FROM bh_access_points ap LEFT JOIN (SELECT DISTINCT access_point_id FROM bh_vertical_transport_stops WHERE access_point_id IS NOT NULL) vst ON vst.access_point_id=ap.id WHERE ap.installation_id=? ORDER BY ap.name");$q->execute([$iid]);$access=$q->fetchAll()?:[];
        $q=$this->pdo->prepare("SELECT id,property_id,area_id,name,device_type,vendor,model,status,asset_stage FROM bh_devices WHERE installation_id=? ORDER BY name");$q->execute([$iid]);$devices=$q->fetchAll()?:[];
        $q=$this->pdo->prepare("SELECT m.id membership_id,m.property_id,m.relationship_type,m.status,p.id person_id,p.display_name FROM bh_property_memberships m JOIN bh_people p ON p.id=m.person_id WHERE p.installation_id=? AND m.status='active' ORDER BY p.display_name");$q->execute([$iid]);$people=$q->fetchAll()?:[];
        $vehicles=[];
        try{$q=$this->pdo->prepare("SELECT id,property_id,person_id,display_name,plate,make,model,status FROM bh_vehicles WHERE installation_id=? AND status<>'inactive' ORDER BY plate");$q->execute([$iid]);$vehicles=$q->fetchAll()?:[];}catch(Throwable $ignore){}
        $q=$this->pdo->prepare("SELECT vt.id,vt.code,vt.name,vt.transport_type,vt.status,vt.metadata_json,st.level_property_id,st.id stop_id,st.is_restricted,st.access_point_id FROM bh_vertical_transports vt LEFT JOIN bh_vertical_transport_stops st ON st.vertical_transport_id=vt.id WHERE vt.installation_id=? ORDER BY vt.name,st.stop_order");$q->execute([$iid]);$vertical=$q->fetchAll()?:[];
        return compact('properties','areas','cameras','access','devices','people','vehicles','vertical');
    }

    public function propertyContext(int $propertyId): array
    {
        $this->assertOwned('bh_properties',$propertyId);$all=$this->structureExplorer();
        $property=null;$byId=[];foreach($all['properties'] as $p){$byId[(int)$p['id']]=$p;if((int)$p['id']===$propertyId)$property=$p;}
        if(!is_array($property))throw new RuntimeException('Elemento de estructura no encontrado.');
        $breadcrumbs=[];$cursor=$property;$guard=0;while(is_array($cursor)&&$guard++<30){array_unshift($breadcrumbs,$cursor);$pid=(int)($cursor['parent_id']??0);$cursor=$pid>0?($byId[$pid]??null):null;}
        $children=array_values(array_filter($all['properties'],fn($r)=>(int)($r['parent_id']??0)===$propertyId));
        $areas=array_values(array_filter($all['areas'],fn($r)=>(int)($r['property_id']??0)===$propertyId));$areaIds=array_map(fn($r)=>(int)$r['id'],$areas);
        $cameras=array_values(array_filter($all['cameras'],fn($r)=>(int)($r['property_id']??0)===$propertyId||in_array((int)($r['area_id']??0),$areaIds,true)));
        $access=array_values(array_filter($all['access'],fn($r)=>(int)($r['property_id']??0)===$propertyId||in_array((int)($r['area_id']??0),$areaIds,true)));
        $devices=array_values(array_filter($all['devices'],fn($r)=>(int)($r['property_id']??0)===$propertyId||in_array((int)($r['area_id']??0),$areaIds,true)));
        $people=array_values(array_filter($all['people'],fn($r)=>(int)$r['property_id']===$propertyId));
        $vehicles=array_values(array_filter($all['vehicles'],fn($r)=>(int)$r['property_id']===$propertyId));
        $vertical=array_values(array_filter($all['vertical'],fn($r)=>(int)($r['level_property_id']??0)===$propertyId));
        return compact('property','breadcrumbs','children','areas','cameras','access','devices','people','vehicles','vertical');
    }

    public function areaContext(int $areaId): array
    {
        $q=$this->pdo->prepare("SELECT a.*,p.name property_name,p.code property_code,p.property_type FROM bh_areas a LEFT JOIN bh_properties p ON p.id=a.property_id WHERE a.id=? AND a.installation_id=? LIMIT 1");$q->execute([$areaId,$this->installationId()]);$area=$q->fetch();if(!is_array($area))throw new RuntimeException('Área no encontrada.');
        $q=$this->pdo->prepare("SELECT c.*,d.vendor,d.model FROM bh_cameras c LEFT JOIN bh_devices d ON d.id=c.device_id WHERE c.installation_id=? ORDER BY c.name");$q->execute([$this->installationId()]);$cameraOptions=$q->fetchAll()?:[];
        $q=$this->pdo->prepare("SELECT ap.*,d.name device_name FROM bh_access_points ap LEFT JOIN bh_devices d ON d.id=ap.device_id WHERE ap.installation_id=? ORDER BY ap.name");$q->execute([$this->installationId()]);$accessOptions=$q->fetchAll()?:[];
        $q=$this->pdo->prepare("SELECT * FROM bh_devices WHERE installation_id=? ORDER BY name");$q->execute([$this->installationId()]);$deviceOptions=$q->fetchAll()?:[];
        $cameras=array_values(array_filter($cameraOptions,fn($r)=>(int)($r['area_id']??0)===$areaId));$access=array_values(array_filter($accessOptions,fn($r)=>(int)($r['area_id']??0)===$areaId));$devices=array_values(array_filter($deviceOptions,fn($r)=>(int)($r['area_id']??0)===$areaId));
        $people=[];$vehicles=[];$propertyId=(int)($area['property_id']??0);if($propertyId>0){$people=$this->membershipsByProperty($propertyId);try{$op=new ZkBioOperationalService($this->pdo);$vehicles=$op->vehicles(null,$propertyId);}catch(Throwable $ignore){}}
        return compact('area','cameras','access','devices','people','vehicles','cameraOptions','accessOptions','deviceOptions');
    }

    private function membershipsByProperty(int $propertyId): array
    {
        $q=$this->pdo->prepare("SELECT m.*,p.display_name,p.id person_id,ap.name access_profile_name FROM bh_property_memberships m JOIN bh_people p ON p.id=m.person_id LEFT JOIN bh_access_profiles ap ON ap.id=m.access_profile_id WHERE m.property_id=? AND p.installation_id=? AND m.status='active' ORDER BY p.display_name");$q->execute([$propertyId,$this->installationId()]);return $q->fetchAll()?:[];
    }

    public function saveAreaContextLinks(int $areaId,array $cameraIds,array $accessIds,array $deviceIds): void
    {
        $q=$this->pdo->prepare('SELECT property_id FROM bh_areas WHERE id=? AND installation_id=?');$q->execute([$areaId,$this->installationId()]);$propertyId=(int)$q->fetchColumn();if($q->rowCount()<1)throw new RuntimeException('Área no encontrada.');
        $cameraIds=array_values(array_unique(array_filter(array_map('intval',$cameraIds))));$accessIds=array_values(array_unique(array_filter(array_map('intval',$accessIds))));$deviceIds=array_values(array_unique(array_filter(array_map('intval',$deviceIds))));
        $this->pdo->beginTransaction();try{
            $this->pdo->prepare('UPDATE bh_cameras SET area_id=NULL WHERE installation_id=? AND area_id=?')->execute([$this->installationId(),$areaId]);
            if($cameraIds){$ph=implode(',',array_fill(0,count($cameraIds),'?'));$this->pdo->prepare("UPDATE bh_cameras SET area_id=?,property_id=COALESCE(?,property_id) WHERE installation_id=? AND id IN ({$ph})")->execute([$areaId,$propertyId?:null,$this->installationId(),...$cameraIds]);}
            $this->pdo->prepare('UPDATE bh_access_points SET area_id=NULL WHERE installation_id=? AND area_id=?')->execute([$this->installationId(),$areaId]);
            if($accessIds){$ph=implode(',',array_fill(0,count($accessIds),'?'));$this->pdo->prepare("UPDATE bh_access_points SET area_id=?,property_id=COALESCE(?,property_id) WHERE installation_id=? AND id IN ({$ph})")->execute([$areaId,$propertyId?:null,$this->installationId(),...$accessIds]);}
            $this->pdo->prepare('UPDATE bh_devices SET area_id=NULL WHERE installation_id=? AND area_id=?')->execute([$this->installationId(),$areaId]);
            if($deviceIds){$ph=implode(',',array_fill(0,count($deviceIds),'?'));$this->pdo->prepare("UPDATE bh_devices SET area_id=?,property_id=COALESCE(?,property_id) WHERE installation_id=? AND id IN ({$ph})")->execute([$areaId,$propertyId?:null,$this->installationId(),...$deviceIds]);}
            $this->audit('area.relationships.update','area',$areaId,['cameras'=>$cameraIds,'access_points'=>$accessIds,'devices'=>$deviceIds]);$this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function verticalTransportOptions(): array
    {
        $iid=$this->installationId();$q=$this->pdo->prepare("SELECT id,code,name,property_type,parent_id,floor_label,metadata_json FROM bh_properties WHERE installation_id=? AND status<>'inactive'");$q->execute([$iid]);$all=$q->fetchAll()?:[];
        $byId=[];$children=[];foreach($all as &$r){$m=$this->jsonObject($r['metadata_json']??null);$r['metadata']=$m;$r['level_order']=$this->levelOrder($r,$m);$byId[(int)$r['id']]=$r;$children[(int)($r['parent_id']??0)][]=(int)$r['id'];}unset($r);
        $actualTowerIds=[];foreach($all as $r)if((string)$r['property_type']==='tower')$actualTowerIds[]=(int)$r['id'];
        $buildingHasTowers=[];foreach($actualTowerIds as $tid){$bid=$this->nearestAncestorOfTypes((int)$tid,$byId,['building']);if($bid)$buildingHasTowers[$bid]=true;}
        $scopes=[];foreach($all as $r){$id=(int)$r['id'];$type=(string)$r['property_type'];if($type==='tower'){$buildingId=$this->nearestAncestorOfTypes($id,$byId,['building']);$scopes[]=['id'=>$id,'code'=>$r['code'],'name'=>$r['name'],'property_type'=>'tower','building_id'=>$buildingId,'scope_label'=>'Torre'];}elseif($type==='building'){$scopes[]=['id'=>$id,'code'=>$r['code'],'name'=>$r['name'],'property_type'=>'building','building_id'=>$id,'scope_label'=>!empty($buildingHasTowers[$id])?'Edificio · sólo niveles compartidos':'Edificio / torre única'];}}
        usort($scopes,fn($a,$b)=>strnatcasecmp((string)$a['name'],(string)$b['name']));
        $levels=[];$levelTypes=['floor','ground_floor','basement','mezzanine','terrace','roof','technical_floor'];
        foreach($all as $r){if(!in_array((string)$r['property_type'],$levelTypes,true))continue;$id=(int)$r['id'];$towerId=$this->nearestAncestorOfTypes($id,$byId,['tower']);$buildingId=$this->nearestAncestorOfTypes($id,$byId,['building']);$r['tower_id']=$towerId;$r['building_id']=$buildingId;$r['is_shared']=$towerId===null&&$buildingId!==null;$r['tower_name']=$towerId&&isset($byId[$towerId])?$byId[$towerId]['name']:null;$r['building_name']=$buildingId&&isset($byId[$buildingId])?$byId[$buildingId]['name']:null;$levels[]=$r;}
        usort($levels,function($a,$b){$ba=strnatcasecmp((string)($a['building_name']??''),(string)($b['building_name']??''));if($ba!==0)return $ba;$ta=strnatcasecmp((string)($a['tower_name']??''),(string)($b['tower_name']??''));if($ta!==0)return $ta;$oa=(int)($a['level_order']??0);$ob=(int)($b['level_order']??0);if($oa!==$ob)return $oa<=>$ob;return strnatcasecmp((string)$a['name'],(string)$b['name']);});
        $summary=[];foreach($scopes as $scope){$sid=(int)$scope['id'];$bid=(int)($scope['building_id']??0);$owned=array_values(array_filter($levels,fn($l)=>($scope['property_type']==='tower'?(int)($l['tower_id']??0)===$sid:((int)($l['building_id']??0)===$sid&&(int)($l['tower_id']??0)===0))));$shared=array_values(array_filter($levels,fn($l)=>!empty($l['is_shared'])&&(int)($l['building_id']??0)===$bid));$summary[]=$scope+['level_count'=>count($owned),'shared_level_count'=>count($shared),'levels'=>$owned,'shared_levels'=>$shared];}
        return ['towers'=>$scopes,'levels'=>$levels,'tower_summaries'=>$summary];
    }

    public function saveVerticalTransport(array $data,?int $id=null): int
    {
        $code=$this->code((string)($data['code']??''));$name=trim((string)($data['name']??''));if($code===''||$name==='')throw new InvalidArgumentException('Código y nombre del ascensor son obligatorios.');
        $type=(string)($data['transport_type']??'passenger_elevator');$types=['passenger_elevator','service_elevator','freight_elevator','escalator','stair','other'];if(!in_array($type,$types,true))$type='other';
        $status=(string)($data['status']??'active');if(!in_array($status,['planned','active','maintenance','inactive'],true))$status='active';
        $towerIds=array_values(array_unique(array_filter(array_map('intval',(array)($data['tower_ids']??[])))));$stopIds=array_values(array_unique(array_filter(array_map('intval',(array)($data['stop_ids']??[])))));$restrictedIds=array_values(array_unique(array_filter(array_map('intval',(array)($data['restricted_stop_ids']??[])))));
        if(!$towerIds)throw new InvalidArgumentException('Selecciona al menos una torre o edificio atendido por el ascensor.');if(!$stopIds)throw new InvalidArgumentException('Selecciona al menos una parada real.');
        $opts=$this->verticalTransportOptions();$scopeById=[];foreach($opts['towers'] as $x)$scopeById[(int)$x['id']]=$x;$levelById=[];foreach($opts['levels'] as $x)$levelById[(int)$x['id']]=$x;
        foreach($towerIds as $tid)if(!isset($scopeById[$tid]))throw new InvalidArgumentException('La torre seleccionada no pertenece a esta instalación.');
        $buildings=[];foreach($towerIds as $tid)$buildings[(int)($scopeById[$tid]['building_id']??$tid)]=1;if(count($buildings)>1)throw new InvalidArgumentException('Un mismo ascensor no puede atender torres de edificios diferentes. Crea un ascensor por edificio o núcleo vertical.');
        $selectedBuilding=(int)array_key_first($buildings);
        foreach($stopIds as $sid){if(!isset($levelById[$sid]))throw new InvalidArgumentException('Una parada seleccionada no corresponde a un nivel real.');$l=$levelById[$sid];$lt=(int)($l['tower_id']??0);$lb=(int)($l['building_id']??0);$compatible=$lt>0?in_array($lt,$towerIds,true):($lb>0&&$lb===$selectedBuilding);if(!$compatible)throw new InvalidArgumentException('La parada '.$l['name'].' no pertenece a la torre/edificio seleccionado.');}
        usort($stopIds,fn($a,$b)=>((int)($levelById[$a]['level_order']??0)<=> (int)($levelById[$b]['level_order']??0)) ?: strnatcasecmp((string)$levelById[$a]['name'],(string)$levelById[$b]['name']));
        $metadata=['access_control_enabled'=>!empty($data['access_control_enabled']),'access_provider'=>trim((string)($data['access_provider']??'')),'usage'=>trim((string)($data['usage']??'')),'notes'=>trim((string)($data['notes']??'')),'tower_model'=>'real_levels','multi_tower'=>count($towerIds)>1];
        $iid=$this->installationId();$this->pdo->beginTransaction();try{
            if($id){$q=$this->pdo->prepare('SELECT COUNT(*) FROM bh_vertical_transports WHERE id=? AND installation_id=?');$q->execute([$id,$iid]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Ascensor no encontrado.');$this->pdo->prepare('UPDATE bh_vertical_transports SET code=?,name=?,transport_type=?,status=?,metadata_json=? WHERE id=? AND installation_id=?')->execute([$code,$name,$type,$status,json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$id,$iid]);$vtId=$id;}else{$this->pdo->prepare('INSERT INTO bh_vertical_transports (installation_id,public_uuid,code,name,transport_type,status,metadata_json) VALUES (?,?,?,?,?,?,?)')->execute([$iid,self::uuid(),$code,$name,$type,$status,json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);$vtId=(int)$this->pdo->lastInsertId();}
            $old=[];$q=$this->pdo->prepare('SELECT level_property_id,access_point_id,is_restricted,notes FROM bh_vertical_transport_stops WHERE vertical_transport_id=?');$q->execute([$vtId]);foreach($q->fetchAll() as $r)$old[(int)$r['level_property_id']]=$r;
            $removedGeneratedAccessIds=[];foreach($old as $oldLevelId=>$oldStop){if(!in_array((int)$oldLevelId,$stopIds,true)&&(int)($oldStop['access_point_id']??0)>0)$removedGeneratedAccessIds[]=(int)$oldStop['access_point_id'];}
            $this->pdo->prepare('DELETE FROM bh_vertical_transport_towers WHERE vertical_transport_id=?')->execute([$vtId]);$insT=$this->pdo->prepare('INSERT INTO bh_vertical_transport_towers (vertical_transport_id,tower_property_id) SELECT ?,id FROM bh_properties WHERE id=? AND installation_id=?');foreach($towerIds as $tid)$insT->execute([$vtId,$tid,$iid]);
            $this->pdo->prepare('DELETE FROM bh_vertical_transport_stops WHERE vertical_transport_id=?')->execute([$vtId]);$insS=$this->pdo->prepare('INSERT INTO bh_vertical_transport_stops (vertical_transport_id,level_property_id,access_point_id,stop_order,is_restricted,notes) SELECT ?,id,?,?,?,? FROM bh_properties WHERE id=? AND installation_id=?');$order=1;foreach($stopIds as $sid){$o=$old[$sid]??[];$restricted=in_array($sid,$restrictedIds,true)?1:0;$insS->execute([$vtId,$o['access_point_id']??null,$order++,$restricted,$o['notes']??null,$sid,$iid]);}
            $removedGeneratedDoors=$this->cleanupGeneratedVerticalStopAccessPoints($removedGeneratedAccessIds);
            $this->audit($id?'vertical_transport.update':'vertical_transport.create','vertical_transport',$vtId,['code'=>$code,'tower_ids'=>$towerIds,'stops'=>$stopIds,'restricted_stops'=>$restrictedIds,'access_control'=>$metadata['access_control_enabled'],'removed_generated_stop_doors'=>$removedGeneratedDoors]);$this->pdo->commit();return $vtId;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function deleteVerticalTransport(int $id): void
    {
        $iid=$this->installationId();
        $q=$this->pdo->prepare('SELECT st.access_point_id FROM bh_vertical_transport_stops st JOIN bh_vertical_transports vt ON vt.id=st.vertical_transport_id WHERE vt.id=? AND vt.installation_id=? AND st.access_point_id IS NOT NULL');$q->execute([$id,$iid]);$accessIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)?:[]);
        $q=$this->pdo->prepare('DELETE FROM bh_vertical_transports WHERE id=? AND installation_id=?');$q->execute([$id,$iid]);if($q->rowCount()<1)throw new RuntimeException('Ascensor no encontrado.');
        $removed=$this->cleanupGeneratedVerticalStopAccessPoints($accessIds);
        $this->audit('vertical_transport.delete','vertical_transport',$id,['removed_generated_stop_doors'=>$removed]);
    }

    /**
     * El diseñador histórico generaba un bh_access_points por cada parada de
     * ascensor. Al quitar una parada el stop desaparecía, pero ese acceso
     * planificado quedaba huérfano y seguía apareciendo en el árbol como una
     * puerta real. Sólo borramos artefactos generados, sin dispositivo ni
     * binding físico, y que ya no estén ligados a ninguna parada.
     */
    private function cleanupGeneratedVerticalStopAccessPoints(array $accessIds): int
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$accessIds),static fn(int $v):bool=>$v>0)));
        if(!$ids)return 0;
        $ph=implode(',',array_fill(0,count($ids),'?'));$iid=$this->installationId();
        $q=$this->pdo->prepare("SELECT ap.id FROM bh_access_points ap WHERE ap.installation_id=? AND ap.id IN ({$ph}) AND ap.access_type='elevator' AND ap.device_id IS NULL AND ap.logical_state IN ('planned','unmapped') AND EXISTS(SELECT 1 FROM bh_structure_wizard_items wi WHERE wi.entity_type='access_point' AND wi.entity_id=ap.id) AND NOT EXISTS(SELECT 1 FROM bh_vertical_transport_stops st WHERE st.access_point_id=ap.id) AND NOT EXISTS(SELECT 1 FROM bh_asset_bindings ab WHERE ab.installation_id=ap.installation_id AND ab.logical_type='access_point' AND ab.logical_id=ap.id) AND NOT EXISTS(SELECT 1 FROM bh_access_point_bindings pb WHERE pb.access_point_id=ap.id) AND NOT EXISTS(SELECT 1 FROM bh_access_authenticators au WHERE au.access_point_id=ap.id) AND NOT EXISTS(SELECT 1 FROM bh_membership_access_grants mg WHERE mg.access_point_id=ap.id) AND NOT EXISTS(SELECT 1 FROM bh_access_execution_paths ep WHERE ep.access_point_id=ap.id) AND NOT EXISTS(SELECT 1 FROM bh_access_commands ac WHERE ac.access_point_id=ap.id) AND NOT EXISTS(SELECT 1 FROM bh_access_events ae WHERE ae.access_point_id=ap.id) AND NOT EXISTS(SELECT 1 FROM bh_access_point_sides aps WHERE aps.access_point_id=ap.id)");
        $q->execute([$iid,...$ids]);$safe=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)?:[]);if(!$safe)return 0;
        $safePh=implode(',',array_fill(0,count($safe),'?'));
        $this->pdo->prepare("DELETE FROM bh_structure_wizard_items WHERE entity_type='access_point' AND entity_id IN ({$safePh})")->execute($safe);
        $d=$this->pdo->prepare("DELETE FROM bh_access_points WHERE installation_id=? AND id IN ({$safePh})");$d->execute([$iid,...$safe]);
        return $d->rowCount();
    }

    private function jsonObject(mixed $value): array
    {
        if(is_array($value))return $value;if(!is_string($value)||trim($value)==='')return[];$d=json_decode($value,true);return is_array($d)?$d:[];
    }

    private function defaultLevelOrder(string $type,string $label): int
    {
        $u=strtoupper(trim($label));
        if(preg_match('/^-?\d+$/',$u))return (int)$u;
        if(preg_match('/^(?:S|B)\s*(\d+)$/',$u,$m))return -(int)$m[1];
        // E1/E2/... se usa en estacionamientos elevados. Antes caía en el
        // fallback de basement=-1, provocando que E1..E6 empataran con S1 y
        // que el árbol terminara colocando S1 después de todos los "Elevado".
        if(preg_match('/^E\s*(\d+)$/',$u,$m))return (int)$m[1];
        if(preg_match('/^ELEVAD[OA]\s*(\d+)$/u',$u,$m))return (int)$m[1];
        if(in_array($u,['PB','GROUND','LOBBY'],true))return 0;
        if($type==='basement')return -1;if($type==='ground_floor')return 0;if($type==='terrace')return 1000;if($type==='roof')return 1100;if($type==='technical_floor')return 900;return 1;
    }

    private function levelOrder(array $row,array $metadata=[]): int
    {
        $label=(string)($row['floor_label']??'');$name=(string)($row['name']??'');
        $u=strtoupper(trim($label));$n=strtoupper(trim($name));
        $elevated=null;
        if(preg_match('/^E\s*(\d+)$/',$u,$m))$elevated=(int)$m[1];
        elseif(preg_match('/^ELEVAD[OA]\s*(\d+)$/u',$n,$m))$elevated=(int)$m[1];
        if(isset($metadata['logical_number'])&&is_numeric($metadata['logical_number'])){
            $stored=(int)$metadata['logical_number'];
            // Reparación compatible para datos creados por versiones anteriores:
            // los niveles "Elevado N" de tipo basement quedaban guardados como -1.
            // Sólo corregimos ese patrón inequívoco; cualquier orden explícito
            // distinto de -1 sigue respetándose.
            if($elevated!==null&&(string)($row['property_type']??'')==='basement'&&$stored===-1)return $elevated;
            return $stored;
        }
        if($elevated!==null)return $elevated;
        return $this->defaultLevelOrder((string)($row['property_type']??''),$label!==''?$label:$name);
    }

    private function compareStructuralSiblings(array $a,array $b): int
    {
        $levels=['basement'=>1,'ground_floor'=>1,'floor'=>1,'mezzanine'=>1,'technical_floor'=>1,'terrace'=>1,'roof'=>1];$al=isset($levels[(string)($a['property_type']??'')]);$bl=isset($levels[(string)($b['property_type']??'')]);if($al&&$bl){$c=((int)($a['level_order']??0))<=>((int)($b['level_order']??0));if($c!==0)return$c;}elseif($al!==$bl)return $al?-1:1;return strnatcasecmp((string)($a['name']??''),(string)($b['name']??''));
    }

    private function nearestAncestorOfTypes(int $id,array $byId,array $types): ?int
    {
        $guard=0;$cursor=$byId[$id]??null;while(is_array($cursor)&&$guard++<40){if(in_array((string)($cursor['property_type']??''),$types,true))return(int)$cursor['id'];$pid=(int)($cursor['parent_id']??0);$cursor=$pid>0?($byId[$pid]??null):null;}return null;
    }

    private function existingAreaMap(): array
    {
        $stmt=$this->pdo->prepare('SELECT id,code,name,area_type,status FROM bh_areas WHERE installation_id=?');
        $stmt->execute([$this->installationId()]);
        $rows=[];
        foreach($stmt->fetchAll() as $row)$rows[(string)$row['code']]=$row;
        return $rows;
    }

    private function codeExists(string $table,string $code): bool
    {
        if(!in_array($table,['bh_properties','bh_areas','bh_access_points'],true))throw new InvalidArgumentException('Tabla de códigos no válida.');
        $stmt=$this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE installation_id=? AND code=?");$stmt->execute([$this->installationId(),$code]);return (int)$stmt->fetchColumn()>0;
    }

    private function existingCodes(string $table): array
    {
        if(!in_array($table,['bh_properties','bh_areas'],true))throw new InvalidArgumentException('Tabla de códigos no válida.');
        $stmt=$this->pdo->prepare("SELECT code FROM {$table} WHERE installation_id=?");
        $stmt->execute([$this->installationId()]);
        return array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function appendUniqueCode(array &$codes,string $code,string $scope): void
    {
        if($code==='')throw new InvalidArgumentException('Se generó un código vacío en '.$scope.'.');
        if(strlen($code)>60)throw new InvalidArgumentException('El código '.$code.' supera los 60 caracteres permitidos.');
        if(isset($codes[$code]))throw new InvalidArgumentException('La nomenclatura genera el código duplicado '.$code.'.');
        $codes[$code]=true;
    }

    public static function standardAreaCatalog(): array
    {
        return [
            'lobby'=>['scope_type'=>'common','usage_type'=>'lobby','code'=>'LOBBY','name'=>'Lobby principal','area_type'=>'common','is_reservable'=>0,'is_security_zone'=>0,'capacity'=>null,'opening_time'=>null,'closing_time'=>null,'requires_approval'=>0,'maximum_minutes'=>null,'rules_text'=>null,'icon'=>'bi-door-open'],
            'reception'=>['scope_type'=>'restricted','usage_type'=>'security','code'=>'RECEPCION','name'=>'Recepción y vigilancia','area_type'=>'security','is_reservable'=>0,'is_security_zone'=>1,'capacity'=>null,'opening_time'=>null,'closing_time'=>null,'requires_approval'=>0,'maximum_minutes'=>null,'rules_text'=>null,'icon'=>'bi-shield-check'],
            'administration'=>['scope_type'=>'restricted','usage_type'=>'office','code'=>'ADMINISTRACION','name'=>'Administración','area_type'=>'office','is_reservable'=>0,'is_security_zone'=>0,'capacity'=>null,'opening_time'=>'08:00','closing_time'=>'17:00','requires_approval'=>0,'maximum_minutes'=>null,'rules_text'=>null,'icon'=>'bi-briefcase'],
            'party_room'=>['scope_type'=>'common','usage_type'=>'reservable','code'=>'SALON-EVENTOS','name'=>'Salón de eventos','area_type'=>'reservable','is_reservable'=>1,'is_security_zone'=>0,'capacity'=>100,'opening_time'=>'08:00','closing_time'=>'23:00','requires_approval'=>1,'maximum_minutes'=>720,'rules_text'=>'Sujeto a reglamento, aforo y aprobación de administración.','icon'=>'bi-balloon'],
            'cowork'=>['scope_type'=>'common','usage_type'=>'reservable','code'=>'COWORK','name'=>'Cowork','area_type'=>'reservable','is_reservable'=>1,'is_security_zone'=>0,'capacity'=>20,'opening_time'=>'07:00','closing_time'=>'21:00','requires_approval'=>1,'maximum_minutes'=>480,'rules_text'=>'Uso sujeto a disponibilidad y normas de convivencia.','icon'=>'bi-laptop'],
            'barbecue'=>['scope_type'=>'common','usage_type'=>'reservable','code'=>'PARRILLERA','name'=>'Parrillera','area_type'=>'reservable','is_reservable'=>1,'is_security_zone'=>0,'capacity'=>25,'opening_time'=>'09:00','closing_time'=>'22:00','requires_approval'=>1,'maximum_minutes'=>480,'rules_text'=>'Debe entregarse limpia y dentro del horario autorizado.','icon'=>'bi-fire'],
            'gym'=>['scope_type'=>'common','usage_type'=>'amenity','code'=>'GIMNASIO','name'=>'Gimnasio','area_type'=>'common','is_reservable'=>0,'is_security_zone'=>0,'capacity'=>30,'opening_time'=>'05:00','closing_time'=>'22:00','requires_approval'=>0,'maximum_minutes'=>null,'rules_text'=>null,'icon'=>'bi-heart-pulse'],
            'pool'=>['scope_type'=>'common','usage_type'=>'amenity','code'=>'PISCINA','name'=>'Piscina','area_type'=>'common','is_reservable'=>0,'is_security_zone'=>0,'capacity'=>50,'opening_time'=>'08:00','closing_time'=>'20:00','requires_approval'=>0,'maximum_minutes'=>null,'rules_text'=>null,'icon'=>'bi-water'],
            'laundry'=>['scope_type'=>'common','usage_type'=>'amenity','code'=>'LAVANDERIA','name'=>'Lavandería','area_type'=>'common','is_reservable'=>0,'is_security_zone'=>0,'capacity'=>10,'opening_time'=>'07:00','closing_time'=>'20:00','requires_approval'=>0,'maximum_minutes'=>null,'rules_text'=>null,'icon'=>'bi-basket'],
            'multipurpose'=>['scope_type'=>'common','usage_type'=>'reservable','code'=>'SALON-USOS-MULTIPLES','name'=>'Salón de usos múltiples','area_type'=>'reservable','is_reservable'=>1,'is_security_zone'=>0,'capacity'=>40,'opening_time'=>'08:00','closing_time'=>'22:00','requires_approval'=>1,'maximum_minutes'=>480,'rules_text'=>null,'icon'=>'bi-grid'],
            'court'=>['scope_type'=>'common','usage_type'=>'recreation','code'=>'CANCHA','name'=>'Cancha deportiva','area_type'=>'reservable','is_reservable'=>1,'is_security_zone'=>0,'capacity'=>30,'opening_time'=>'06:00','closing_time'=>'22:00','requires_approval'=>1,'maximum_minutes'=>180,'rules_text'=>null,'icon'=>'bi-dribbble'],
            'playground'=>['scope_type'=>'common','usage_type'=>'recreation','code'=>'PARQUE-INFANTIL','name'=>'Parque infantil','area_type'=>'common','is_reservable'=>0,'is_security_zone'=>0,'capacity'=>30,'opening_time'=>'07:00','closing_time'=>'20:00','requires_approval'=>0,'maximum_minutes'=>null,'rules_text'=>null,'icon'=>'bi-emoji-smile'],
            'green_area'=>['scope_type'=>'common','usage_type'=>'recreation','code'=>'AREAS-VERDES','name'=>'Áreas verdes','area_type'=>'common','is_reservable'=>0,'is_security_zone'=>0,'capacity'=>null,'opening_time'=>null,'closing_time'=>null,'requires_approval'=>0,'maximum_minutes'=>null,'rules_text'=>null,'icon'=>'bi-tree'],
            'pet_area'=>['scope_type'=>'common','usage_type'=>'amenity','code'=>'ZONA-MASCOTAS','name'=>'Zona de mascotas','area_type'=>'common','is_reservable'=>0,'is_security_zone'=>0,'capacity'=>20,'opening_time'=>'06:00','closing_time'=>'21:00','requires_approval'=>0,'maximum_minutes'=>null,'rules_text'=>null,'icon'=>'bi-heart'],
            'terrace'=>['scope_type'=>'common','usage_type'=>'reservable','code'=>'TERRAZA','name'=>'Terraza común','area_type'=>'reservable','is_reservable'=>1,'is_security_zone'=>0,'capacity'=>40,'opening_time'=>'08:00','closing_time'=>'22:00','requires_approval'=>1,'maximum_minutes'=>480,'rules_text'=>null,'icon'=>'bi-sun'],
            'garbage'=>['scope_type'=>'service','usage_type'=>'technical','code'=>'CUARTO-BASURA','name'=>'Cuarto de basura','area_type'=>'technical','is_reservable'=>0,'is_security_zone'=>0,'capacity'=>null,'opening_time'=>null,'closing_time'=>null,'requires_approval'=>0,'maximum_minutes'=>null,'rules_text'=>null,'icon'=>'bi-trash'],
            'pump_room'=>['scope_type'=>'technical','usage_type'=>'technical','code'=>'CUARTO-BOMBAS','name'=>'Cuarto de bombas','area_type'=>'technical','is_reservable'=>0,'is_security_zone'=>1,'capacity'=>null,'opening_time'=>null,'closing_time'=>null,'requires_approval'=>0,'maximum_minutes'=>null,'rules_text'=>null,'icon'=>'bi-droplet'],
            'electrical_room'=>['scope_type'=>'technical','usage_type'=>'technical','code'=>'CUARTO-ELECTRICO','name'=>'Cuarto eléctrico','area_type'=>'technical','is_reservable'=>0,'is_security_zone'=>1,'capacity'=>null,'opening_time'=>null,'closing_time'=>null,'requires_approval'=>0,'maximum_minutes'=>null,'rules_text'=>null,'icon'=>'bi-lightning'],
            'security_room'=>['scope_type'=>'restricted','usage_type'=>'security','code'=>'CENTRO-SEGURIDAD','name'=>'Centro de seguridad','area_type'=>'security','is_reservable'=>0,'is_security_zone'=>1,'capacity'=>null,'opening_time'=>null,'closing_time'=>null,'requires_approval'=>0,'maximum_minutes'=>null,'rules_text'=>null,'icon'=>'bi-camera-video'],
        ];
    }


    public function membershipPermissionData(int $membershipId): array
    {
        $q=$this->pdo->prepare("SELECT m.*,p.display_name,pr.name property_name,pr.code property_code,ap.name access_profile_name FROM bh_property_memberships m JOIN bh_people p ON p.id=m.person_id JOIN bh_properties pr ON pr.id=m.property_id LEFT JOIN bh_access_profiles ap ON ap.id=m.access_profile_id WHERE m.id=? AND p.installation_id=? LIMIT 1");$q->execute([$membershipId,$this->installationId()]);$m=$q->fetch();if(!$m)throw new RuntimeException('Vinculación no encontrada.');
        // Las excepciones deben respetar la ubicación en dos capas. "Sin área" ya no significa "global":
        // una cámara/puerta puede estar ubicada directamente en la estructura.
        $cams=$this->pdo->prepare("SELECT c.id,c.name,c.resident_visibility,c.status,a.name area,p.name property_name,IF(g.id IS NULL,0,g.can_view) granted FROM bh_cameras c LEFT JOIN bh_areas a ON a.id=c.area_id LEFT JOIN bh_properties p ON p.id=COALESCE(c.property_id,a.property_id) LEFT JOIN bh_membership_camera_grants g ON g.camera_id=c.id AND g.membership_id=? WHERE c.installation_id=? AND c.status<>'disabled' AND c.source_deleted_at IS NULL AND (c.resident_visibility='all' OR (c.resident_visibility='property_scope' AND COALESCE(c.property_id,a.property_id)=?)) ORDER BY COALESCE(a.name,p.name,''),c.name");$cams->execute([$membershipId,$this->installationId(),(int)$m['property_id']]);
        $aps=$this->pdo->prepare("SELECT ap.id,ap.name,ap.remote_policy,ap.status,a.name area,p.name property_name,g.can_operate,g.days_of_week,g.start_time,g.end_time FROM bh_access_points ap LEFT JOIN bh_areas a ON a.id=ap.area_id LEFT JOIN bh_properties p ON p.id=COALESCE(ap.property_id,a.property_id) LEFT JOIN bh_membership_access_grants g ON g.access_point_id=ap.id AND g.membership_id=? WHERE ap.installation_id=? AND ap.status<>'disabled' AND ap.remote_policy<>'disabled' ORDER BY COALESCE(a.name,p.name,''),ap.name");$aps->execute([$membershipId,$this->installationId()]);
        $cameraRules=[];$accessRules=[];$profileId=(int)($m['access_profile_id']??0);if($profileId>0){$policy=new AccessPolicyService($this->pdo);$cameraRules=$policy->effectiveRules($profileId,'camera');$accessRules=$policy->effectiveRules($profileId,'access');}
        return ['membership'=>$m,'cameras'=>$cams->fetchAll()?:[],'access_points'=>$aps->fetchAll()?:[],'profile_camera_rules'=>$cameraRules,'profile_access_rules'=>$accessRules];
    }
    public function saveMembershipPermissions(int $membershipId,array $data): void
    {
        $bundle=$this->membershipPermissionData($membershipId);$cameraIds=array_map('intval',(array)($data['camera_ids']??[]));$accessIds=array_map('intval',(array)($data['access_ids']??[]));$this->pdo->beginTransaction();try{$this->pdo->prepare('DELETE FROM bh_membership_camera_grants WHERE membership_id=?')->execute([$membershipId]);$ic=$this->pdo->prepare('INSERT INTO bh_membership_camera_grants (membership_id,camera_id,can_view) VALUES (?,?,1)');foreach($bundle['cameras'] as $c)if(in_array((int)$c['id'],$cameraIds,true))$ic->execute([$membershipId,(int)$c['id']]);$this->pdo->prepare('DELETE FROM bh_membership_access_grants WHERE membership_id=?')->execute([$membershipId]);$ia=$this->pdo->prepare('INSERT INTO bh_membership_access_grants (membership_id,access_point_id,can_operate,days_of_week,start_time,end_time) VALUES (?,?,1,?,?,?)');foreach($bundle['access_points'] as $a){$id=(int)$a['id'];if(!in_array($id,$accessIds,true))continue;$days=trim((string)($data['access_days'][$id]??'1,2,3,4,5,6,7'));if(!preg_match('/^[1-7](,[1-7])*$/',$days))$days='1,2,3,4,5,6,7';$start=trim((string)($data['access_start'][$id]??'00:00'));$end=trim((string)($data['access_end'][$id]??'23:59'));$ia->execute([$membershipId,$id,$days,$start.':00',$end.':00']);}$this->pdo->prepare('UPDATE bh_property_memberships SET can_view_cameras=?,can_operate_access=? WHERE id=?')->execute([$cameraIds?1:0,$accessIds?1:0,$membershipId]);$this->pdo->commit();$this->audit('membership.permissions.update','membership',$membershipId,['cameras'=>$cameraIds,'access_points'=>$accessIds]);}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    private function pageData(array $filters): array
    {
        $page=max(1,(int)($filters['page']??1));$perPage=(int)($filters['per_page']??25);if(!in_array($perPage,[10,25,50,100],true))$perPage=25;return[$page,$perPage,($page-1)*$perPage];
    }

    private function pageResult(array $items,int $total,int $page,int $perPage,string $search,array $filters=[]): array
    {
        return ['items'=>$items,'total'=>$total,'page'=>$page,'per_page'=>$perPage,'pages'=>max(1,(int)ceil($total/$perPage)),'search'=>$search,'filters'=>$filters];
    }

    private function assertOwned(string $table,int $id): void
    {
        if(!preg_match('/^bh_[a-z_]+$/',$table))throw new InvalidArgumentException('Tabla no válida.');
        $stmt=$this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id=? AND installation_id=?");$stmt->execute([$id,$this->installationId()]);if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('El registro no pertenece a esta instalación.');
    }

    private function audit(string $action,string $entityType,int $entityId,array $payload): void
    {
        try{
            $user=current_user()??[];$localId=is_numeric($user['id']??null)?(int)$user['id']:null;
            $stmt=$this->pdo->prepare('INSERT INTO bh_audit_logs (installation_id,actor_local_user_id,action,entity_type,entity_id,result,ip_address,user_agent,payload_json) VALUES (?,?,?,?,?,\'success\',?,?,?)');
            $stmt->execute([$this->installationId(),$localId,$action,$entityType,$entityId,(string)($_SERVER['REMOTE_ADDR']??''),(string)($_SERVER['HTTP_USER_AGENT']??''),json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        }catch(Throwable){/* La operación principal no debe fallar por el registro de auditoría. */}
    }

    private function code(string $value): string
    {
        $value=strtoupper(trim($value));$value=preg_replace('/[^A-Z0-9._-]+/','-',$value)??'';return trim($value,'-');
    }

    private static function letters(int $index): string
    {
        $n=$index+1;$out='';while($n>0){$n--; $out=chr(65+($n%26)).$out; $n=intdiv($n,26);}return$out;
    }

    private static function uuid(): string
    {
        $data=random_bytes(16);$data[6]=chr((ord($data[6])&0x0f)|0x40);$data[8]=chr((ord($data[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($data),4));
    }
}
