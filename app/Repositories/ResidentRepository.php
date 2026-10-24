<?php
declare(strict_types=1);

/**
 * Fuente persistente de la Property App.
 *
 * La aplicación móvil nunca consulta DemoRepository cuando la instalación
 * está en modo real. Todo se resuelve contra la misma base del Property Node.
 */
final class ResidentRepository
{
    private PDO $pdo;
    private int $installationId;
    private ?int $localUserId;
    private ?int $personId;

    public function __construct(?PDO $pdo = null, ?int $personId = null, ?int $localUserId = null)
    {
        $this->pdo = $pdo ?? Connection::pdo();
        $this->installationId = $this->resolveInstallationId();
        $user = current_user() ?? [];
        $previewPerson = (int)($_SESSION['resident_preview_person_id'] ?? 0);
        $this->personId = $personId
            ?? ($previewPerson > 0 ? $previewPerson : ((int)($user['person_id'] ?? 0) ?: null));
        $this->localUserId = $localUserId
            ?? (is_int($user['id'] ?? null) ? (int)$user['id'] : null);
    }

    public function isPreview(): bool
    {
        return (int)($_SESSION['resident_preview_person_id'] ?? 0) > 0
            && strtoupper((string)(current_user()['role'] ?? '')) !== 'RESIDENT';
    }

    public function personId(): ?int { return $this->personId; }
    public function localUserId(): ?int { return $this->localUserId; }

    private function resolveInstallationId(): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM bh_installations WHERE code=? LIMIT 1');
        $stmt->execute([(string)config('app.instance_code')]);
        $id = (int)$stmt->fetchColumn();
        if ($id < 1) throw new RuntimeException('No se encontró la instalación local de Property Hub.');
        return $id;
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function installation(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bh_installations WHERE id=?');
        $stmt->execute([$this->installationId]);
        return (array)($stmt->fetch() ?: []);
    }

    public function settings(): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM bh_resident_app_settings WHERE installation_id=? LIMIT 1');
            $stmt->execute([$this->installationId]);
            $row = $stmt->fetch();
        } catch (PDOException $e) {
            // La pantalla de reparación debe poder seguir cargando antes de 0015.
            $row = false;
        }
        $features = [];
        if (is_array($row) && is_string($row['features_json'] ?? null)) {
            $decoded = json_decode((string)$row['features_json'], true);
            if (is_array($decoded)) $features = $decoded;
        }
        return [
            'app_title' => (string)($row['app_title'] ?? config('app.instance_name', 'Property Hub')),
            'welcome_message' => (string)($row['welcome_message'] ?? 'Todo tu inmueble en una sola aplicación'),
            'hero_image_url' => (string)($row['hero_image_url'] ?? ''),
            'theme_color' => (string)($row['theme_color'] ?? '#176b45'),
            'features' => array_merge([
                'visitors'=>true,'reservations'=>true,'cameras'=>true,'access'=>true,
                'bulletin'=>true,'notifications'=>true,'emergency'=>true,'vehicles'=>false,
            ], $features),
        ];
    }

    public function saveSettings(array $data): void
    {
        $color = trim((string)($data['theme_color'] ?? '#176b45'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#176b45';
        $features = [];
        foreach (['visitors','reservations','cameras','access','bulletin','notifications','emergency','vehicles'] as $feature) {
            $features[$feature] = !empty($data['feature_'.$feature]);
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO bh_resident_app_settings (installation_id,app_title,welcome_message,hero_image_url,theme_color,features_json,updated_by) '
            .'VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE app_title=VALUES(app_title),welcome_message=VALUES(welcome_message),hero_image_url=VALUES(hero_image_url),theme_color=VALUES(theme_color),features_json=VALUES(features_json),updated_by=VALUES(updated_by)'
        );
        $stmt->execute([
            $this->installationId,
            trim((string)($data['app_title'] ?? '')) ?: (string)config('app.instance_name'),
            trim((string)($data['welcome_message'] ?? '')) ?: 'Todo tu inmueble en una sola aplicación',
            trim((string)($data['hero_image_url'] ?? '')) ?: null,
            $color,
            json_encode($features, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            $this->localUserId,
        ]);
    }

    public function profile(): array
    {
        if (!$this->personId) return [];
        $stmt = $this->pdo->prepare('SELECT * FROM bh_people WHERE id=? AND installation_id=? LIMIT 1');
        $stmt->execute([$this->personId, $this->installationId]);
        return (array)($stmt->fetch() ?: []);
    }

    public function memberships(): array
    {
        if (!$this->personId) return [];
        $stmt = $this->pdo->prepare(
            "SELECT m.*,p.code property_code,p.name property_name,p.property_type,"
            ."ap.name access_profile_name,ap.code access_profile_code,"
            ."CASE WHEN ap.id IS NOT NULL THEN ap.can_invite ELSE m.can_invite END can_invite,"
            ."CASE WHEN ap.id IS NOT NULL THEN ap.can_reserve ELSE m.can_reserve END can_reserve,"
            ."CASE WHEN ap.id IS NOT NULL THEN 1 ELSE m.can_view_cameras END can_view_cameras,"
            ."CASE WHEN ap.id IS NOT NULL THEN 1 ELSE m.can_operate_access END can_operate_access,"
            ."COALESCE(parent.name,'') parent_name,COALESCE(parent.code,'') parent_code "
            ."FROM bh_property_memberships m JOIN bh_properties p ON p.id=m.property_id "
            ."LEFT JOIN bh_access_profiles ap ON ap.id=m.access_profile_id AND ap.status='active' "
            ."LEFT JOIN bh_properties parent ON parent.id=p.parent_id "
            ."WHERE m.person_id=? AND p.installation_id=? AND m.status='active' AND p.status='active' "
            ."AND (m.starts_at IS NULL OR m.starts_at<=NOW()) AND (m.ends_at IS NULL OR m.ends_at>=NOW()) "
            ."ORDER BY m.is_primary DESC,p.name"
        );
        $stmt->execute([$this->personId, $this->installationId]);
        $installation = $this->installation();
        return array_map(static function(array $row) use ($installation): array {
            $row['installation_code'] = (string)($installation['code'] ?? '');
            $row['installation_name'] = (string)($installation['name'] ?? 'Property Hub');
            $row['installation_type'] = (string)($installation['installation_type'] ?? 'other');
            $row['role'] = relationship_label((string)$row['relationship_type']);
            return $row;
        }, $stmt->fetchAll());
    }

    public function currentContext(): array
    {
        $memberships = $this->memberships();
        if ($memberships === []) {
            $installation = $this->installation();
            return [
                'installation_code'=>(string)($installation['code'] ?? ''),
                'installation_name'=>(string)($installation['name'] ?? config('app.instance_name')),
                'installation_type'=>(string)($installation['installation_type'] ?? 'other'),
                'property_id'=>0,'property_code'=>'','property_name'=>'Sin unidad vinculada',
                'property_type'=>'other','role'=>'Sin relación asignada',
                'can_invite'=>0,'can_reserve'=>0,'can_view_cameras'=>0,'can_operate_access'=>0,
            ];
        }
        $selected = (int)($_SESSION['resident_active_property_id'] ?? 0);
        foreach ($memberships as $membership) {
            if ((int)$membership['property_id'] === $selected) return $membership;
        }
        $_SESSION['resident_active_property_id'] = (int)$memberships[0]['property_id'];
        return $memberships[0];
    }

    public function setContext(int $propertyId): void
    {
        foreach ($this->memberships() as $membership) {
            if ((int)$membership['property_id'] === $propertyId) {
                $_SESSION['resident_active_property_id'] = $propertyId;
                return;
            }
        }
        throw new InvalidArgumentException('La unidad seleccionada no pertenece a este usuario.');
    }

    public function areas(bool $reservableOnly = false): array
    {
        $where = "a.installation_id=? AND a.status='active'";
        $args = [$this->installationId];
        if ($reservableOnly) $where .= ' AND a.is_reservable=1';
        $stmt = $this->pdo->prepare(
            "SELECT a.*,p.name property_name,p.code property_code FROM bh_areas a "
            ."LEFT JOIN bh_properties p ON p.id=a.property_id WHERE {$where} "
            ."ORDER BY a.is_reservable DESC,a.name"
        );
        $stmt->execute($args);
        return array_map(static function(array $area): array {
            $area['reservable'] = (bool)$area['is_reservable'];
            $area['schedule'] = ($area['opening_time'] && $area['closing_time'])
                ? substr((string)$area['opening_time'],0,5).'–'.substr((string)$area['closing_time'],0,5)
                : 'Según disponibilidad';
            $area['rate'] = (float)$area['base_rate'];
            $area['deposit'] = (float)$area['security_deposit'];
            $area['approval'] = (bool)$area['requires_approval'];
            $area['location'] = trim((string)($area['property_name'] ?? '')) ?: 'Área general';
            $area['icon'] = match ((string)$area['area_type']) {
                'reservable' => 'bi bi-calendar2-check',
                'security' => 'bi bi-shield-lock',
                'technical' => 'bi bi-hdd-rack',
                'parking' => 'bi bi-car-front',
                'circulation' => 'bi bi-signpost-split',
                default => 'bi bi-buildings',
            };
            return $area;
        }, $stmt->fetchAll());
    }

    public function reservations(): array
    {
        if (!$this->personId) return [];
        $context = $this->currentContext();
        if ((int)$context['property_id'] < 1) return [];
        $stmt = $this->pdo->prepare(
            "SELECT r.*,a.name area,a.code area_code,p.name unit FROM bh_reservations r "
            ."JOIN bh_areas a ON a.id=r.area_id JOIN bh_properties p ON p.id=r.property_id "
            ."WHERE r.installation_id=? AND r.requester_person_id=? AND r.property_id=? "
            ."ORDER BY r.starts_at DESC LIMIT 100"
        );
        $stmt->execute([$this->installationId,$this->personId,(int)$context['property_id']]);
        return array_map(static function(array $row): array {
            $row['date'] = date('Y-m-d', strtotime((string)$row['starts_at']));
            $row['start'] = date('H:i', strtotime((string)$row['starts_at']));
            $row['end'] = date('H:i', strtotime((string)$row['ends_at']));
            $row['requester'] = (string)(current_user()['name'] ?? 'Residente');
            return $row;
        }, $stmt->fetchAll());
    }

    public function createReservation(array $data): int
    {
        if (!$this->personId) throw new InvalidArgumentException('La cuenta no está vinculada a una persona.');
        $context = $this->currentContext();
        if (empty($context['can_reserve'])) throw new InvalidArgumentException('Esta relación no tiene permiso para reservar áreas.');
        $areaId = (int)($data['area_id'] ?? 0);
        $stmt = $this->pdo->prepare("SELECT * FROM bh_areas WHERE id=? AND installation_id=? AND status='active' AND is_reservable=1");
        $stmt->execute([$areaId,$this->installationId]);
        $area = $stmt->fetch();
        if (!is_array($area)) throw new InvalidArgumentException('Seleccione un área reservable válida.');
        $date = trim((string)($data['date'] ?? ''));
        $start = trim((string)($data['start'] ?? ''));
        $end = trim((string)($data['end'] ?? ''));
        $startsAt = $date.' '.$start.':00';
        $endsAt = $date.' '.$end.':00';
        if (!strtotime($startsAt) || !strtotime($endsAt) || strtotime($endsAt) <= strtotime($startsAt)) {
            throw new InvalidArgumentException('Revise la fecha y el horario de la reserva.');
        }
        if (strtotime($startsAt) < time()) throw new InvalidArgumentException('La reserva debe programarse para una fecha futura.');
        $conflict = $this->pdo->prepare(
            "SELECT COUNT(*) FROM bh_reservations WHERE area_id=? AND status NOT IN ('rejected','cancelled','completed','no_show') "
            ."AND starts_at<? AND ends_at>?"
        );
        $conflict->execute([$areaId,$endsAt,$startsAt]);
        if ((int)$conflict->fetchColumn() > 0) throw new InvalidArgumentException('El horario se solapa con otra solicitud activa.');
        $status = !empty($area['requires_approval']) ? 'pending' : 'approved';
        $stmt = $this->pdo->prepare(
            'INSERT INTO bh_reservations (public_uuid,installation_id,area_id,property_id,requester_person_id,starts_at,ends_at,purpose,attendees,amount,deposit_amount,payment_status,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            self::uuid(),$this->installationId,$areaId,(int)$context['property_id'],$this->personId,
            $startsAt,$endsAt,trim((string)($data['purpose'] ?? 'Reserva de área')) ?: 'Reserva de área',
            max(1,(int)($data['attendees'] ?? 1)),(float)$area['base_rate'],(float)$area['security_deposit'],
            ((float)$area['base_rate'] > 0 || (float)$area['security_deposit'] > 0) ? 'pending' : 'not_required',$status,
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT INTO bh_reservation_history (reservation_id,previous_status,new_status,changed_by,note) VALUES (?,NULL,?,?,?)')
            ->execute([$id,$status,$this->localUserId,'Solicitud creada desde Property App']);
        return $id;
    }

    public function invitations(): array
    {
        if (!$this->personId) return [];
        $context = $this->currentContext();
        if ((int)$context['property_id'] < 1) return [];
        $stmt = $this->pdo->prepare(
            "SELECT i.*,p.name unit FROM bh_invitations i JOIN bh_properties p ON p.id=i.property_id "
            ."WHERE i.installation_id=? AND i.host_person_id=? AND i.property_id=? "
            ."ORDER BY i.starts_at DESC LIMIT 100"
        );
        $stmt->execute([$this->installationId,$this->personId,(int)$context['property_id']]);
        return array_map(static function(array $row): array {
            $row['name'] = $row['visitor_name'];
            $row['document'] = $row['visitor_document'];
            $row['phone'] = $row['visitor_phone'];
            $row['vehicle'] = $row['vehicle_plate'];
            $row['date'] = date('Y-m-d',strtotime((string)$row['starts_at']));
            $row['start'] = date('H:i',strtotime((string)$row['starts_at']));
            $row['end'] = date('H:i',strtotime((string)$row['ends_at']));
            $row['code'] = $row['access_code'];
            return $row;
        }, $stmt->fetchAll());
    }

    public function createInvitation(array $data): int
    {
        if (!$this->personId) throw new InvalidArgumentException('La cuenta no está vinculada a una persona.');
        $context = $this->currentContext();
        if (empty($context['can_invite'])) throw new InvalidArgumentException('Esta relación no tiene permiso para crear invitaciones.');
        $name = trim((string)($data['name'] ?? $data['visitor_name'] ?? ''));
        if ($name === '') throw new InvalidArgumentException('Indique el nombre del visitante.');
        $date = trim((string)($data['date'] ?? ''));
        $start = trim((string)($data['start'] ?? ''));
        $end = trim((string)($data['end'] ?? ''));
        $startsAt = $date.' '.$start.':00';
        $endsAt = $date.' '.$end.':00';
        if (!strtotime($startsAt) || !strtotime($endsAt) || strtotime($endsAt) <= strtotime($startsAt)) {
            throw new InvalidArgumentException('Revise la fecha y horario de la invitación.');
        }
        $code = strtoupper(substr(bin2hex(random_bytes(5)),0,8));
        $uuid = self::uuid();
        $payload = json_encode(['invitation_uuid'=>$uuid,'installation'=>(string)config('app.instance_code'),'code'=>$code],JSON_UNESCAPED_SLASHES);
        $stmt = $this->pdo->prepare(
            "INSERT INTO bh_invitations (public_uuid,installation_id,property_id,host_person_id,visitor_name,visitor_document,visitor_phone,vehicle_plate,starts_at,ends_at,access_code,qr_payload,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $uuid,$this->installationId,(int)$context['property_id'],$this->personId,$name,
            trim((string)($data['document'] ?? '')) ?: null,trim((string)($data['phone'] ?? '')) ?: null,
            strtoupper(trim((string)($data['vehicle'] ?? ''))) ?: null,$startsAt,$endsAt,$code,$payload,
            strtotime($startsAt) <= time() ? 'active' : 'scheduled',
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function bulletins(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT b.*,COALESCE(u.username,u.email,'Administración') author FROM bh_bulletins b "
            ."LEFT JOIN bh_local_users u ON u.id=b.created_by WHERE b.installation_id=? AND b.status='published' "
            ."AND (b.published_at IS NULL OR b.published_at<=NOW()) AND (b.expires_at IS NULL OR b.expires_at>=NOW()) "
            ."AND b.audience_type IN ('all','residents','custom') ORDER BY b.is_pinned DESC,COALESCE(b.published_at,b.created_at) DESC"
        );
        $stmt->execute([$this->installationId]);
        return array_map(static function(array $row): array {
            $row['pinned'] = (bool)$row['is_pinned'];
            $row['published_at'] = (string)($row['published_at'] ?: $row['created_at']);
            $row['audience'] = $row['audience_type'];
            return $row;
        }, $stmt->fetchAll());
    }

    public function notifications(): array
    {
        $context = $this->currentContext();
        if (!$this->localUserId && (int)$context['property_id'] < 1) return [];
        $conditions = [];$args = [$this->installationId];
        if ($this->localUserId) { $conditions[] = 'n.local_user_id=?'; $args[] = $this->localUserId; }
        if ((int)$context['property_id'] > 0) { $conditions[] = '(n.property_id=? AND n.local_user_id IS NULL)'; $args[] = (int)$context['property_id']; }
        $where = $conditions ? '('.implode(' OR ',$conditions).')' : '1=0';
        $stmt = $this->pdo->prepare(
            "SELECT n.* FROM bh_notifications n LEFT JOIN bh_notification_campaigns c ON c.id=n.campaign_id "
            ."WHERE (c.installation_id=? OR c.installation_id IS NULL) AND {$where} ORDER BY n.created_at DESC LIMIT 100"
        );
        $stmt->execute($args);
        return array_map(static function(array $row): array {
            $row['sent_at'] = (string)($row['delivered_at'] ?: $row['created_at']);
            return $row;
        }, $stmt->fetchAll());
    }

    public function unreadNotificationCount(): int
    {
        $count = 0;
        foreach ($this->notifications() as $item) if (!in_array((string)$item['status'],['read','dismissed'],true)) $count++;
        return $count;
    }

    public function markNotificationRead(int $id): void
    {
        if (!$this->localUserId) return;
        $stmt = $this->pdo->prepare("UPDATE bh_notifications SET status='read',read_at=NOW() WHERE id=? AND local_user_id=?");
        $stmt->execute([$id,$this->localUserId]);
    }

    public function emergencyContacts(): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM bh_emergency_contacts WHERE installation_id=? AND status='active' AND is_visible_to_residents=1 ORDER BY sort_order,name");
        $stmt->execute([$this->installationId]);
        return array_map(static function(array $row): array {
            $row['number'] = $row['phone'];
            $row['available'] = (string)($row['availability'] ?: 'Consultar disponibilidad');
            $row['icon'] = match ((string)$row['category']) {
                'security' => 'bi bi-shield-check','administration' => 'bi bi-building','fire' => 'bi bi-fire',
                'medical' => 'bi bi-heart-pulse','police' => 'bi bi-shield','elevator' => 'bi bi-arrows-vertical',
                'electricity' => 'bi bi-lightning-charge','water' => 'bi bi-droplet','gas' => 'bi bi-fuel-pump',
                default => 'bi bi-telephone-forward',
            };
            return $row;
        }, $stmt->fetchAll());
    }

    private function propertyScopeContext(int $propertyId): array
    {
        $ctx=['unit'=>$propertyId,'floor'=>0,'tower'=>0,'property'=>0,'lineage'=>[]];$id=$propertyId;$seen=[];
        while($id>0&&!isset($seen[$id])&&count($seen)<20){$seen[$id]=true;$q=$this->pdo->prepare('SELECT id,parent_id,property_type FROM bh_properties WHERE id=? AND installation_id=? LIMIT 1');$q->execute([$id,$this->installationId]);$r=$q->fetch();if(!is_array($r))break;$type=(string)$r['property_type'];$ctx['lineage'][]=(int)$r['id'];if(in_array($type,['floor','ground_floor','basement','mezzanine','terrace','roof','technical_floor'],true)&&$ctx['floor']<1)$ctx['floor']=(int)$r['id'];if($type==='tower'&&$ctx['tower']<1)$ctx['tower']=(int)$r['id'];if($type==='building'&&$ctx['property']<1)$ctx['property']=(int)$r['id'];$id=(int)($r['parent_id']??0);}
        if($ctx['property']<1&&!empty($ctx['lineage']))$ctx['property']=(int)end($ctx['lineage']);if($ctx['tower']<1)$ctx['tower']=$ctx['property'];return $ctx;
    }

    private function profileAllowsResource(int $profileId,string $domain,array $resource,int $propertyId,int $day,string $time): bool
    {
        if($profileId<1)return false;$scopeCtx=$this->propertyScopeContext($propertyId);$rules=(new AccessPolicyService($this->pdo))->effectiveRules($profileId,$domain);$allowed=false;
        foreach($rules as $r){$days=(string)($r['days_of_week']??'1,2,3,4,5,6,7');if(strpos(','.$days.',',','.$day.',')===false)continue;$st=(string)($r['start_time']??'00:00:00');$en=(string)($r['end_time']??'23:59:59');if($time<$st||$time>$en)continue;$scope=(string)($r['scope_type']??'common');$resourceScope=(string)($resource['access_scope']??'common');$target=(int)($resource['access_scope_property_id']??0);if($target<1)$target=(int)($resource['area_property_id']??0);if($target<1)$target=(int)($resource['property_id']??0);$match=false;
            if($scope==='all')$match=true;
            elseif($scope==='specific')$match=(int)($r['resource_id']??0)===(int)($resource['id']??0);
            elseif($scope==='common')$match=$resourceScope==='common';
            elseif($scope==='unit')$match=$resourceScope==='unit'&&$target===(int)$scopeCtx['unit'];
            elseif($scope==='floor')$match=$resourceScope==='floor'&&$target>0&&$target===(int)$scopeCtx['floor'];
            elseif($scope==='tower')$match=$resourceScope==='tower'&&$target>0&&$target===(int)$scopeCtx['tower'];
            elseif($scope==='property')$match=$resourceScope==='property'&&($target===0||$target===(int)$scopeCtx['property']);
            elseif($scope==='custom')$match=$resourceScope==='custom'&&(((string)($r['scope_group']??''))===''||(string)($r['scope_group']??'')===(string)($resource['access_scope_group']??''));
            if(!$match)continue;if(($r['effect']??'allow')==='deny')return false;$allowed=true;
        }
        return $allowed;
    }

    public function cameras(): array
    {
        $context=$this->currentContext();$mid=(int)($context['id']??0);if($mid<1||empty($context['can_view_cameras']))return [];$profileId=(int)($context['access_profile_id']??0);$day=(int)date('N');$time=date('H:i:s');
        $grants=[];$q=$this->pdo->prepare('SELECT camera_id FROM bh_membership_camera_grants WHERE membership_id=? AND can_view=1');$q->execute([$mid]);foreach($q->fetchAll(PDO::FETCH_COLUMN)?:[] as $id)$grants[(int)$id]=true;
        $q=$this->pdo->prepare("SELECT c.*,a.name area,a.property_id area_property_id,p.name property_name,d.vendor,d.model,d.managed_by FROM bh_cameras c LEFT JOIN bh_areas a ON a.id=c.area_id LEFT JOIN bh_properties p ON p.id=COALESCE(c.property_id,a.property_id) LEFT JOIN bh_devices d ON d.id=c.device_id WHERE c.installation_id=? AND c.status<>'disabled' AND c.source_deleted_at IS NULL AND c.resident_visibility IN ('all','property_scope') ORDER BY COALESCE(NULLIF(a.name,''),NULLIF(p.name,''),'Área general'),c.display_order,COALESCE(NULLIF(c.resident_name,''),c.name),c.id");$q->execute([$this->installationId]);$rows=[];foreach($q->fetchAll()?:[] as $row){$id=(int)$row['id'];if(isset($grants[$id])||$this->profileAllowsResource($profileId,'camera',$row,(int)$context['property_id'],$day,$time)){$row['brand']=(string)($row['vendor']?:'Multimarca');$row['protocol']=(string)$row['stream_mode'];$row['visibility']=(string)$row['resident_visibility'];$row['resident_display_name']=trim((string)($row['resident_name']??''))!==''?(string)$row['resident_name']:(string)$row['name'];$row['area']=(string)($row['area']?:$row['property_name']?:'Área general');$rows[]=$row;}}
        return $rows;
    }

    public function cameraForViewing(int $id): ?array
    {
        if($id<1)return null;
        $user=current_user()??[];$restricted=strtoupper((string)($user['role']??''))==='RESIDENT'||$this->isPreview()||(($user['portal']??'')==='resident');
        if($restricted){foreach($this->cameras() as $camera)if((int)($camera['id']??0)===$id)return $camera;return null;}
        $stmt=$this->pdo->prepare("SELECT c.*,a.name area,p.name property_name,d.vendor,d.model,d.managed_by FROM bh_cameras c LEFT JOIN bh_areas a ON a.id=c.area_id LEFT JOIN bh_properties p ON p.id=COALESCE(c.property_id,a.property_id) LEFT JOIN bh_devices d ON d.id=c.device_id WHERE c.id=? AND c.installation_id=? AND c.source_deleted_at IS NULL LIMIT 1");
        $stmt->execute([$id,$this->installationId]);$row=$stmt->fetch();return is_array($row)?$row:null;
    }

    public function accessPoints(): array
    {
        $context=$this->currentContext();$mid=(int)($context['id']??0);if($mid<1||empty($context['can_operate_access']))return [];$day=(int)date('N');$time=date('H:i:s');$profileId=(int)($context['access_profile_id']??0);
        $grants=[];$q=$this->pdo->prepare('SELECT access_point_id,days_of_week,start_time,end_time FROM bh_membership_access_grants WHERE membership_id=? AND can_operate=1');$q->execute([$mid]);foreach($q->fetchAll()?:[] as $g){if(strpos(','.(string)$g['days_of_week'].',',','.$day.',')!==false&&$time>=(string)$g['start_time']&&$time<=(string)$g['end_time'])$grants[(int)$g['access_point_id']]=true;}
        $q=$this->pdo->prepare("SELECT ap.*,a.name area,a.property_id area_property_id,p.name property_name,COALESCE(ci.name,d.vendor,'Property Hub') connector,EXISTS(SELECT 1 FROM bh_access_execution_paths ep WHERE ep.access_point_id=ap.id AND ep.installation_id=ap.installation_id AND ep.enabled=1) remote_ready FROM bh_access_points ap LEFT JOIN bh_areas a ON a.id=ap.area_id LEFT JOIN bh_properties p ON p.id=COALESCE(ap.property_id,a.property_id) LEFT JOIN bh_devices d ON d.id=ap.device_id LEFT JOIN bh_connector_instances ci ON ci.id=d.connector_instance_id WHERE ap.installation_id=? AND ap.status<>'disabled' AND ap.remote_policy<>'disabled' ORDER BY COALESCE(NULLIF(a.name,''),NULLIF(p.name,''),'Área general'),ap.display_order,COALESCE(NULLIF(ap.resident_name,''),ap.name),ap.id");$q->execute([$this->installationId]);$rows=[];foreach($q->fetchAll()?:[] as $row){$id=(int)$row['id'];if(isset($grants[$id])||$this->profileAllowsResource($profileId,'access',$row,(int)$context['property_id'],$day,$time)){$row['action']=$row['operation_mode'];$row['area']=(string)($row['area']?:$row['property_name']?:'Área general');$row['resident_display_name']=trim((string)($row['resident_name']??''))!==''?(string)$row['resident_name']:(string)$row['name'];$row['remote_ready']=!empty($row['remote_ready']);$rows[]=$row;}}
        return $rows;
    }

    public function accessPointForOperation(int $id): ?array
    {
        if($id<1)return null;foreach($this->accessPoints() as $point)if((int)($point['id']??0)===$id)return $point;return null;
    }

    public function homeData(): array
    {
        $reservations = array_values(array_filter($this->reservations(), static fn(array $r): bool => strtotime((string)$r['ends_at']) >= time() && !in_array((string)$r['status'],['rejected','cancelled'],true)));
        usort($reservations, static fn(array $a,array $b): int => strcmp((string)$a['starts_at'],(string)$b['starts_at']));
        $invitations = array_values(array_filter($this->invitations(), static fn(array $i): bool => strtotime((string)$i['ends_at']) >= time() && !in_array((string)$i['status'],['cancelled','rejected','expired'],true)));
        usort($invitations, static fn(array $a,array $b): int => strcmp((string)$a['starts_at'],(string)$b['starts_at']));
        return [
            'context'=>$this->currentContext(),'profile'=>$this->profile(),'settings'=>$this->settings(),
            'bulletins'=>$this->bulletins(),'reservations'=>$reservations,'invitations'=>$invitations,
            'areas'=>$this->areas(true),'cameras'=>$this->cameras(),'access_points'=>$this->accessPoints(),
            'notifications'=>$this->notifications(),'emergency_contacts'=>$this->emergencyContacts(),
        ];
    }

    /* ------------------------- Administración ------------------------- */

    public function residentAccounts(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id,person_id,email,username,status,last_login_at FROM bh_local_users "
            ."WHERE installation_id=? AND user_type='resident' ORDER BY id"
        );
        $stmt->execute([$this->installationId]);
        $map=[];foreach($stmt->fetchAll() as $row)$map[(int)$row['person_id']]=$row;return $map;
    }

    public function saveResidentAccount(array $data): array
    {
        $personId=(int)($data['person_id']??0);
        $check=$this->pdo->prepare("SELECT id,display_name,email FROM bh_people WHERE id=? AND installation_id=? AND status='active'");
        $check->execute([$personId,$this->installationId]);$person=$check->fetch();
        if(!is_array($person))throw new InvalidArgumentException('Seleccione una persona activa de esta instalación.');
        $email=strtolower(trim((string)($data['email']??$person['email']??'')));
        $username=strtolower(trim((string)($data['username']??'')));
        if($email===''&&$username==='')throw new InvalidArgumentException('Indique un correo o nombre de usuario.');
        $status=in_array((string)($data['status']??''),['active','pending','blocked','revoked'],true)?(string)$data['status']:'active';
        $existing=$this->pdo->prepare("SELECT id,password_hash FROM bh_local_users WHERE installation_id=? AND person_id=? AND user_type='resident' ORDER BY id LIMIT 1");
        $existing->execute([$this->installationId,$personId]);$row=$existing->fetch();
        $password=(string)($data['password']??'');
        if(!is_array($row)&&strlen($password)<8)throw new InvalidArgumentException('La contraseña inicial debe tener al menos 8 caracteres.');
        $hash=$password!==''?password_hash($password,PASSWORD_DEFAULT):(string)($row['password_hash']??'');
        if(is_array($row)){
            $stmt=$this->pdo->prepare("UPDATE bh_local_users SET email=?,username=?,password_hash=?,status=? WHERE id=?");
            $stmt->execute([$email?:null,$username?:null,$hash,$status,(int)$row['id']]);$id=(int)$row['id'];
        }else{
            $stmt=$this->pdo->prepare("INSERT INTO bh_local_users (installation_id,person_id,email,username,password_hash,user_type,status) VALUES (?,?,?,?,?,'resident',?)");
            $stmt->execute([$this->installationId,$personId,$email?:null,$username?:null,$hash,$status]);$id=(int)$this->pdo->lastInsertId();
        }
        return ['id'=>$id,'person'=>$person['display_name'],'login'=>$username?:$email];
    }

    public function adminReservations(): array
    {
        $stmt=$this->pdo->prepare("SELECT r.*,a.name area,p.name unit,pe.display_name requester FROM bh_reservations r JOIN bh_areas a ON a.id=r.area_id JOIN bh_properties p ON p.id=r.property_id JOIN bh_people pe ON pe.id=r.requester_person_id WHERE r.installation_id=? ORDER BY r.starts_at DESC LIMIT 250");
        $stmt->execute([$this->installationId]);
        return array_map(static function(array $r):array{$r['date']=date('Y-m-d',strtotime((string)$r['starts_at']));$r['start']=date('H:i',strtotime((string)$r['starts_at']));$r['end']=date('H:i',strtotime((string)$r['ends_at']));return $r;},$stmt->fetchAll());
    }

    public function updateReservationStatus(int $id,string $status): void
    {
        if(!in_array($status,['approved','rejected','cancelled','completed','no_show'],true))throw new InvalidArgumentException('Estado no permitido.');
        $stmt=$this->pdo->prepare('SELECT status FROM bh_reservations WHERE id=? AND installation_id=?');$stmt->execute([$id,$this->installationId]);$previous=$stmt->fetchColumn();
        if($previous===false)throw new InvalidArgumentException('Solicitud no encontrada.');
        $this->pdo->beginTransaction();try{
            $this->pdo->prepare("UPDATE bh_reservations SET status=?,approved_by=?,approved_at=IF(?='approved',NOW(),approved_at) WHERE id=? AND installation_id=?")->execute([$status,$this->localUserId,$status,$id,$this->installationId]);
            $this->pdo->prepare('INSERT INTO bh_reservation_history (reservation_id,previous_status,new_status,changed_by,note) VALUES (?,?,?,?,?)')->execute([$id,$previous,$status,$this->localUserId,'Actualizado desde consola']);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function adminBulletins(): array { return $this->bulletins(); }

    public function createBulletin(array $data): int
    {
        $title=trim((string)($data['title']??''));$body=trim((string)($data['body']??''));if($title===''||$body==='')throw new InvalidArgumentException('Título y contenido son obligatorios.');
        $category=in_array((string)($data['category']??''),['general','community','services','maintenance','security','emergency','commercial','office','other'],true)?(string)$data['category']:'general';
        $audience=in_array((string)($data['audience']??''),['all','residents','commercial','office','security','custom'],true)?(string)$data['audience']:'all';
        $expires=trim((string)($data['expires_at']??''));if($expires!=='')$expires.=' 23:59:59';
        $stmt=$this->pdo->prepare("INSERT INTO bh_bulletins (installation_id,title,body,category,audience_type,is_pinned,status,published_at,expires_at,created_by) VALUES (?,?,?,?,?,?,'published',NOW(),?,?)");
        $stmt->execute([$this->installationId,$title,$body,$category,$audience,!empty($data['pinned'])?1:0,$expires?:null,$this->localUserId]);return (int)$this->pdo->lastInsertId();
    }

    public function adminNotifications(): array
    {
        $stmt=$this->pdo->prepare("SELECT c.*,COUNT(n.id) recipients,SUM(n.status='read') read_count FROM bh_notification_campaigns c LEFT JOIN bh_notifications n ON n.campaign_id=c.id WHERE c.installation_id=? GROUP BY c.id ORDER BY c.created_at DESC LIMIT 100");$stmt->execute([$this->installationId]);return $stmt->fetchAll();
    }

    public function createNotification(array $data): int
    {
        $title=trim((string)($data['title']??''));$message=trim((string)($data['message']??''));if($title===''||$message==='')throw new InvalidArgumentException('Título y mensaje son obligatorios.');
        $priority=in_array((string)($data['priority']??''),['low','normal','high','critical'],true)?(string)$data['priority']:'normal';
        $audience=in_array((string)($data['audience']??''),['all','residents','commercial','office','security','property','custom'],true)?(string)$data['audience']:'all';
        $this->pdo->beginTransaction();try{
            $stmt=$this->pdo->prepare("INSERT INTO bh_notification_campaigns (installation_id,title,message,priority,audience_type,channels_json,sent_at,status,created_by) VALUES (?,?,?,?,?,'[\"in_app\"]',NOW(),'sent',?)");$stmt->execute([$this->installationId,$title,$message,$priority,$audience,$this->localUserId]);$campaignId=(int)$this->pdo->lastInsertId();
            $users=$this->pdo->prepare("SELECT id FROM bh_local_users WHERE installation_id=? AND user_type='resident' AND status='active'");$users->execute([$this->installationId]);
            $insert=$this->pdo->prepare("INSERT INTO bh_notifications (campaign_id,local_user_id,title,message,priority,status,delivered_at) VALUES (?,?,?,?,?,'delivered',NOW())");
            foreach($users->fetchAll() as $user)$insert->execute([$campaignId,(int)$user['id'],$title,$message,$priority]);
            $this->pdo->commit();return $campaignId;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function adminEmergencyContacts(): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM bh_emergency_contacts WHERE installation_id=? ORDER BY sort_order,name');$stmt->execute([$this->installationId]);return $stmt->fetchAll();
    }

    public function saveEmergencyContact(array $data,?int $id=null): int
    {
        $name=trim((string)($data['name']??''));$phone=trim((string)($data['phone']??''));if($name===''||$phone==='')throw new InvalidArgumentException('Nombre y teléfono son obligatorios.');
        $category=in_array((string)($data['category']??''),['security','administration','fire','medical','police','elevator','electricity','water','gas','other'],true)?(string)$data['category']:'other';
        $values=[$category,$name,$phone,trim((string)($data['availability']??''))?:null,max(0,(int)($data['sort_order']??0)),!empty($data['is_visible_to_residents'])?1:0,in_array((string)($data['status']??''),['active','inactive'],true)?(string)$data['status']:'active'];
        if($id){$stmt=$this->pdo->prepare('UPDATE bh_emergency_contacts SET category=?,name=?,phone=?,availability=?,sort_order=?,is_visible_to_residents=?,status=? WHERE id=? AND installation_id=?');$stmt->execute([...$values,$id,$this->installationId]);return $id;}
        $stmt=$this->pdo->prepare('INSERT INTO bh_emergency_contacts (installation_id,category,name,phone,availability,sort_order,is_visible_to_residents,status) VALUES (?,?,?,?,?,?,?,?)');$stmt->execute([$this->installationId,...$values]);return (int)$this->pdo->lastInsertId();
    }

    public function deleteEmergencyContact(int $id): void { $this->pdo->prepare('DELETE FROM bh_emergency_contacts WHERE id=? AND installation_id=?')->execute([$id,$this->installationId]); }

    public function adminInvitations(): array
    {
        $stmt=$this->pdo->prepare("SELECT i.*,p.name unit,pe.display_name host FROM bh_invitations i JOIN bh_properties p ON p.id=i.property_id JOIN bh_people pe ON pe.id=i.host_person_id WHERE i.installation_id=? ORDER BY i.starts_at DESC LIMIT 250");$stmt->execute([$this->installationId]);return $stmt->fetchAll();
    }

    public function adminVisits(): array
    {
        $stmt=$this->pdo->prepare("SELECT v.*,p.name unit,COALESCE(pe.display_name,'') host FROM bh_visits v JOIN bh_properties p ON p.id=v.property_id LEFT JOIN bh_people pe ON pe.id=v.host_person_id WHERE v.installation_id=? ORDER BY COALESCE(v.check_in_at,v.created_at) DESC LIMIT 250");$stmt->execute([$this->installationId]);return $stmt->fetchAll();
    }

    public function adminAccessPoints(): array
    {
        $stmt=$this->pdo->prepare("SELECT ap.*,a.name area,p.name property_name,p.property_type,d.name device_name,ci.name connector,(SELECT s.label FROM bh_access_point_sides s WHERE s.access_point_id=ap.id AND s.side_key='A' LIMIT 1) side_a_label,(SELECT s.label FROM bh_access_point_sides s WHERE s.access_point_id=ap.id AND s.side_key='B' LIMIT 1) side_b_label FROM bh_access_points ap LEFT JOIN bh_properties p ON p.id=ap.property_id LEFT JOIN bh_areas a ON a.id=ap.area_id LEFT JOIN bh_devices d ON d.id=ap.device_id LEFT JOIN bh_connector_instances ci ON ci.id=d.connector_instance_id WHERE ap.installation_id=? ORDER BY ap.name");$stmt->execute([$this->installationId]);$rows=$stmt->fetchAll()?:[];
        $links=$this->cameraAccessLinksByAccess();foreach($rows as &$row)$row['camera_ids']=$links[(int)$row['id']]??[];unset($row);return $rows;
    }

    public function saveAccessPoint(array $data,?int $id=null): int
    {
        $code=strtoupper(trim((string)($data['code']??'')));$name=trim((string)($data['name']??''));if($code===''||$name==='')throw new InvalidArgumentException('Código y nombre son obligatorios.');
        $residentName=trim((string)($data['resident_name']??''));$displayOrder=max(0,min(9999,(int)($data['display_order']??100)));
        $propertyId=(int)($data['property_id']??0)?:null;$areaId=(int)($data['area_id']??0)?:null;
        if($areaId){$q=$this->pdo->prepare("SELECT property_id FROM bh_areas WHERE id=? AND installation_id=? AND status<>'inactive' LIMIT 1");$q->execute([$areaId,$this->installationId]);$v=$q->fetchColumn();if($v===false)throw new InvalidArgumentException('Área/espacio no válido.');$areaProperty=(int)$v?:null;if($propertyId&&$areaProperty&&$propertyId!==$areaProperty)throw new InvalidArgumentException('El área seleccionada no pertenece a la ubicación estructural indicada.');if(!$propertyId)$propertyId=$areaProperty;}
        if($propertyId&&!$areaId){$q=$this->pdo->prepare("SELECT metadata_json FROM bh_properties WHERE id=? AND installation_id=? AND status<>'inactive' LIMIT 1");$q->execute([$propertyId,$this->installationId]);$raw=$q->fetchColumn();if($raw===false)throw new InvalidArgumentException('Ubicación estructural no válida.');$m=json_decode((string)$raw,true);if(!is_array($m))$m=[];$accepts=array_key_exists('accepts_resources',$m)?!empty($m['accepts_resources']):(($m['structure_role']??'physical')!=='organizer');if(!$accepts)throw new InvalidArgumentException('Ese elemento es sólo organizativo y no acepta accesos directos.');}
        $values=[$propertyId,$areaId,(int)($data['device_id']??0)?:null,$code,$name,$residentName!==''?$residentName:null,$displayOrder,(string)($data['access_type']??'door'),(string)($data['operation_mode']??'hybrid'),(string)($data['remote_policy']??'disabled'),(string)($data['status']??'active')];
        if($id){$stmt=$this->pdo->prepare('UPDATE bh_access_points SET property_id=?,area_id=?,device_id=?,code=?,name=?,resident_name=?,display_order=?,access_type=?,operation_mode=?,remote_policy=?,status=? WHERE id=? AND installation_id=?');$stmt->execute([...$values,$id,$this->installationId]);return $id;}
        $stmt=$this->pdo->prepare('INSERT INTO bh_access_points (installation_id,property_id,area_id,device_id,code,name,resident_name,display_order,access_type,operation_mode,remote_policy,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute([$this->installationId,...$values]);return (int)$this->pdo->lastInsertId();
    }

    public function deleteAccessPoint(int $id): void { $this->pdo->prepare('DELETE FROM bh_access_points WHERE id=? AND installation_id=?')->execute([$id,$this->installationId]); }

    public function adminCameras(): array
    {
        $stmt=$this->pdo->prepare("SELECT c.*,a.name area,a.property_id area_property_id,p.name property_name,p.property_type,d.name device_name,d.vendor,d.managed_by,d.device_type FROM bh_cameras c LEFT JOIN bh_areas a ON a.id=c.area_id LEFT JOIN bh_properties p ON p.id=COALESCE(c.property_id,a.property_id) LEFT JOIN bh_devices d ON d.id=c.device_id WHERE c.installation_id=? AND c.source_deleted_at IS NULL ORDER BY COALESCE(NULLIF(a.name,''),NULLIF(p.name,''),'Sin ubicación'),c.display_order,c.name");$stmt->execute([$this->installationId]);$rows=$stmt->fetchAll()?:[];
        $links=$this->cameraAccessLinksByCamera();foreach($rows as &$row)$row['access_point_ids']=$links[(int)$row['id']]??[];unset($row);return $rows;
    }

    /** @return array<int,array<int,int>> */
    private function cameraAccessLinksByCamera(): array
    {
        $q=$this->pdo->prepare('SELECT camera_id,access_point_id FROM bh_camera_access_links WHERE installation_id=? AND viewer_enabled=1 ORDER BY camera_id,display_order,id');$q->execute([$this->installationId]);$out=[];foreach($q->fetchAll()?:[] as $r)$out[(int)$r['camera_id']][]=(int)$r['access_point_id'];return $out;
    }

    /** @return array<int,array<int,int>> */
    private function cameraAccessLinksByAccess(): array
    {
        $q=$this->pdo->prepare('SELECT access_point_id,camera_id FROM bh_camera_access_links WHERE installation_id=? AND viewer_enabled=1 ORDER BY access_point_id,display_order,id');$q->execute([$this->installationId]);$out=[];foreach($q->fetchAll()?:[] as $r)$out[(int)$r['access_point_id']][]=(int)$r['camera_id'];return $out;
    }

    /** @param array<int|string,mixed> $accessIds */
    public function saveCameraAccessLinks(int $cameraId,array $accessIds): void
    {
        if($cameraId<1)throw new InvalidArgumentException('Cámara inválida.');$iid=$this->installationId();
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM bh_cameras WHERE id=? AND installation_id=? AND source_deleted_at IS NULL');$q->execute([$cameraId,$iid]);if((int)$q->fetchColumn()!==1)throw new InvalidArgumentException('Cámara no encontrada.');
        $ids=array_values(array_unique(array_filter(array_map('intval',$accessIds),static fn(int $v):bool=>$v>0)));$valid=[];
        if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$q=$this->pdo->prepare("SELECT id FROM bh_access_points WHERE installation_id=? AND id IN ($ph) AND status<>'disabled'");$q->execute([$iid,...$ids]);$valid=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)?:[]);if(count($valid)!==count($ids))throw new InvalidArgumentException('Una o más puertas asociadas no pertenecen a esta instalación o están deshabilitadas.');}
        $this->pdo->beginTransaction();try{$this->pdo->prepare('DELETE FROM bh_camera_access_links WHERE installation_id=? AND camera_id=?')->execute([$iid,$cameraId]);$ins=$this->pdo->prepare('INSERT INTO bh_camera_access_links (installation_id,camera_id,access_point_id,viewer_enabled,display_order) VALUES (?,?,?,1,?)');foreach($valid as $i=>$accessId)$ins->execute([$iid,$cameraId,$accessId,($i+1)*10]);$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** @param array<int|string,mixed> $cameraIds */
    public function saveAccessPointCameraLinks(int $accessPointId,array $cameraIds): void
    {
        if($accessPointId<1)throw new InvalidArgumentException('Punto de acceso inválido.');$iid=$this->installationId();
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM bh_access_points WHERE id=? AND installation_id=?');$q->execute([$accessPointId,$iid]);if((int)$q->fetchColumn()!==1)throw new InvalidArgumentException('Punto de acceso no encontrado.');
        $ids=array_values(array_unique(array_filter(array_map('intval',$cameraIds),static fn(int $v):bool=>$v>0)));$valid=[];
        if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$q=$this->pdo->prepare("SELECT id FROM bh_cameras WHERE installation_id=? AND id IN ($ph) AND source_deleted_at IS NULL AND status<>'disabled'");$q->execute([$iid,...$ids]);$valid=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)?:[]);if(count($valid)!==count($ids))throw new InvalidArgumentException('Una o más cámaras asociadas no pertenecen a esta instalación o están deshabilitadas.');}
        $this->pdo->beginTransaction();try{$this->pdo->prepare('DELETE FROM bh_camera_access_links WHERE installation_id=? AND access_point_id=?')->execute([$iid,$accessPointId]);$ins=$this->pdo->prepare('INSERT INTO bh_camera_access_links (installation_id,camera_id,access_point_id,viewer_enabled,display_order) VALUES (?,?,?,1,?)');foreach($valid as $i=>$cameraId)$ins->execute([$iid,$cameraId,$accessPointId,($i+1)*10]);$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function cameraHasAccessLink(int $cameraId,int $accessPointId): bool
    {
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM bh_camera_access_links WHERE installation_id=? AND camera_id=? AND access_point_id=? AND viewer_enabled=1');$q->execute([$this->installationId(),$cameraId,$accessPointId]);return (int)$q->fetchColumn()>0;
    }

    /**
     * Puertas asociadas a una cámara para el visor. Para residentes se intersectan
     * con los permisos efectivos de su relación/unidad; para operadores se conserva
     * la asociación funcional y la autorización se resuelve por rol en el endpoint.
     * @return array<int,array<string,mixed>>
     */
    public function cameraViewerAccessPoints(int $cameraId,bool $residentRestricted): array
    {
        if($cameraId<1)return [];$iid=$this->installationId();
        $sql="SELECT ap.*,l.display_order,COALESCE(NULLIF(ap.resident_name,''),ap.name) display_name,a.name area,p.name property_name,
"
            ."EXISTS(SELECT 1 FROM bh_access_execution_paths ep WHERE ep.installation_id=ap.installation_id AND ep.access_point_id=ap.id AND ep.enabled=1) remote_ready,
"
            ."(SELECT cd.code FROM bh_access_execution_paths ep2 LEFT JOIN bh_connector_instances ci2 ON ci2.id=ep2.connector_instance_id LEFT JOIN bh_connector_definitions cd ON cd.id=ci2.connector_definition_id WHERE ep2.installation_id=ap.installation_id AND ep2.access_point_id=ap.id AND ep2.enabled=1 ORDER BY ep2.is_primary DESC,ep2.priority,ep2.id LIMIT 1) execution_connector_code
"
            ."FROM bh_camera_access_links l JOIN bh_access_points ap ON ap.id=l.access_point_id AND ap.installation_id=l.installation_id LEFT JOIN bh_areas a ON a.id=ap.area_id LEFT JOIN bh_properties p ON p.id=COALESCE(ap.property_id,a.property_id) WHERE l.installation_id=? AND l.camera_id=? AND l.viewer_enabled=1 AND ap.status<>'disabled' ORDER BY l.display_order,ap.display_order,ap.name";
        $q=$this->pdo->prepare($sql);$q->execute([$iid,$cameraId]);$rows=$q->fetchAll()?:[];
        $allowed=null;if($residentRestricted){$allowed=[];foreach($this->accessPoints() as $point)$allowed[(int)$point['id']]=$point;}
        $out=[];foreach($rows as $row){$id=(int)$row['id'];if(is_array($allowed)&&!isset($allowed[$id]))continue;if(is_array($allowed))$row=array_replace($row,$allowed[$id]);$policy=(string)($row['remote_policy']??'disabled');$connector=strtolower((string)($row['execution_connector_code']??''));$supported=in_array($connector,['zkteco','zkteco_biosecurity'],true);$row['remote_ready']=!empty($row['remote_ready']);$row['operation_supported']=$supported;$row['immediate_open_allowed']=$row['remote_ready']&&$supported&&in_array($policy,['allowed','local_only'],true);$row['viewer_reason']=$row['immediate_open_allowed']?'':(!$row['remote_ready']?'Sin ruta de apertura operativa':(!$supported?'La ruta de apertura todavía no tiene ejecutor contextual compatible':($policy==='second_factor'?'Requiere segundo factor':($policy==='approval_required'?'Requiere aprobación':'Apertura remota deshabilitada'))));$out[]=$row;}
        return $out;
    }

    private function resolveCameraLocation(array $data): array
    {
        $propertyId=(int)($data['property_id']??0)?:null;$areaId=(int)($data['area_id']??0)?:null;
        $areaProperty=null;
        if($areaId){$q=$this->pdo->prepare("SELECT property_id FROM bh_areas WHERE id=? AND installation_id=? AND status<>'inactive' LIMIT 1");$q->execute([$areaId,$this->installationId]);$v=$q->fetchColumn();if($v===false)throw new InvalidArgumentException('Área/espacio no válido.');$areaProperty=(int)$v?:null;if($propertyId&&$areaProperty&&$propertyId!==$areaProperty)throw new InvalidArgumentException('El área seleccionada no pertenece a la ubicación estructural indicada.');if(!$propertyId)$propertyId=$areaProperty;}
        if($propertyId){$q=$this->pdo->prepare("SELECT metadata_json FROM bh_properties WHERE id=? AND installation_id=? AND status<>'inactive' LIMIT 1");$q->execute([$propertyId,$this->installationId]);$raw=$q->fetchColumn();if($raw===false)throw new InvalidArgumentException('Ubicación estructural no válida.');if(!$areaId){$m=json_decode((string)$raw,true);if(!is_array($m))$m=[];$accepts=array_key_exists('accepts_resources',$m)?!empty($m['accepts_resources']):(($m['structure_role']??'physical')!=='organizer');if(!$accepts)throw new InvalidArgumentException('Ese elemento es sólo organizativo y no acepta recursos directos. Elija una ubicación física o un área dentro de ella.');}}
        return [$propertyId,$areaId];
    }

    public function saveCamera(array $data,?int $id=null): int
    {
        $name=trim((string)($data['name']??''));$residentName=trim((string)($data['resident_name']??''));$displayOrder=max(0,min(9999,(int)($data['display_order']??100)));
        $visibility=(string)($data['resident_visibility']??'none');if(!in_array($visibility,['none','all','property_scope','custom'],true))$visibility='none';
        [$propertyId,$areaId]=$this->resolveCameraLocation($data);
        if($id){
            $current=$this->pdo->prepare('SELECT source_scope,edge_name,name_sync_mode FROM bh_cameras WHERE id=? AND installation_id=? LIMIT 1');$current->execute([$id,$this->installationId]);$row=$current->fetch();$scope=is_array($row)?(string)($row['source_scope']??'node'):'node';
            if($scope==='edge'){
                $followEdge=!empty($data['follow_edge_name']);$edgeName=trim((string)($row['edge_name']??''));
                if($followEdge&&$edgeName!==''){$name=$edgeName;$nameMode='edge';}
                else{$nameMode='node';if($name==='')throw new InvalidArgumentException('Indique el nombre visible de la cámara.');}
                $liveEnabled=!empty($data['live_enabled'])?1:0;
                $stmt=$this->pdo->prepare("UPDATE bh_cameras SET property_id=?,area_id=?,name=?,resident_name=?,display_order=?,name_sync_mode=?,resident_visibility=?,allow_recordings=?,allow_audio=?,allow_ptz=?,live_enabled=?,live_transport=CASE WHEN ?=1 THEN 'webrtc' ELSE 'none' END,live_status=CASE WHEN ?=1 THEN CASE WHEN live_status='ready' THEN 'ready' ELSE 'pending' END ELSE 'disabled' END,live_updated_at=NOW() WHERE id=? AND installation_id=?");
                $stmt->execute([$propertyId,$areaId,$name,$residentName!==''?$residentName:null,$displayOrder,$nameMode,$visibility,!empty($data['allow_recordings'])?1:0,!empty($data['allow_audio'])?1:0,!empty($data['allow_ptz'])?1:0,$liveEnabled,$liveEnabled,$liveEnabled,$id,$this->installationId]);return $id;
            }
        }
        if($name==='')throw new InvalidArgumentException('Indique el nombre de la cámara.');
        $liveEnabled=!empty($data['live_enabled'])?1:0;$values=[$propertyId,$areaId,(int)($data['device_id']??0)?:null,$name,$residentName!==''?$residentName:null,$displayOrder,(string)($data['stream_mode']??'onvif'),$visibility,!empty($data['allow_recordings'])?1:0,!empty($data['allow_audio'])?1:0,!empty($data['allow_ptz'])?1:0,(string)($data['status']??'offline'),$liveEnabled,$liveEnabled?'webrtc':'none',$liveEnabled?'pending':'disabled'];
        if($id){$stmt=$this->pdo->prepare('UPDATE bh_cameras SET property_id=?,area_id=?,device_id=?,name=?,resident_name=?,display_order=?,stream_mode=?,resident_visibility=?,allow_recordings=?,allow_audio=?,allow_ptz=?,status=?,live_enabled=?,live_transport=?,live_status=?,live_updated_at=NOW() WHERE id=? AND installation_id=?');$stmt->execute([...$values,$id,$this->installationId]);return $id;}
        $stmt=$this->pdo->prepare("INSERT INTO bh_cameras (installation_id,property_id,area_id,device_id,name,resident_name,display_order,stream_mode,resident_visibility,allow_recordings,allow_audio,allow_ptz,status,live_enabled,live_transport,live_status,live_updated_at,source_scope) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),'node')");$stmt->execute([$this->installationId,...$values]);return (int)$this->pdo->lastInsertId();
    }

    public function deleteCamera(int $id): void
    {
        $stmt=$this->pdo->prepare('SELECT source_scope FROM bh_cameras WHERE id=? AND installation_id=? LIMIT 1');$stmt->execute([$id,$this->installationId]);$scope=(string)($stmt->fetchColumn()?:'');
        if($scope==='edge'){
            // Permite limpiar una copia huérfana del Node sin fingir que se eliminó el
            // equipo físico. Si el Agent todavía la reporta, reaparecerá en el próximo sync.
            $this->pdo->prepare("UPDATE bh_cameras SET status='disabled',live_enabled=0,live_transport='none',live_status='disabled',source_deleted_at=NOW() WHERE id=? AND installation_id=?")->execute([$id,$this->installationId]);return;
        }
        $this->pdo->prepare('DELETE FROM bh_cameras WHERE id=? AND installation_id=?')->execute([$id,$this->installationId]);
    }
}
