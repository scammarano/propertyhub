<?php
declare(strict_types=1);

final class DemoDataCleaner
{
    public function __construct(private PDO $pdo) {}

    public function preview(): array
    {
        $installationId = $this->installationId();
        if (InstallationMode::isCentral()) {
            return [
                'mode'=>'central',
                'demo_installations'=>$this->count('bh_repo_installations', 'is_demo=1'),
                'demo_plans'=>$this->count('bh_repo_installation_plans', "installation_code LIKE 'DEMO-%'"),
                'session_items'=>$this->sessionItemCount(),
            ];
        }
        return [
            'mode'=>'node',
            'connector_instances'=>$this->countPrepared('bh_connector_instances','installation_id=? AND name IN (?,?,?,?,?,?,?,?)',[$installationId,...$this->demoConnectorNames()]),
            'devices'=>$this->countPrepared('bh_devices','installation_id=? AND serial_number IN (?,?,?,?,?,?,?,?,?,?,?)',[$installationId,...$this->demoDeviceSerials()]),
            'locations'=>$this->countPrepared('bh_site_locations',"installation_id=? AND (metadata_json LIKE '%\"demo\":true%' OR code IN (?,?,?,?,?,?,?,?,?))",[$installationId,...$this->demoLocationCodes()]),
            'connectivity_links'=>$this->countPrepared('bh_connectivity_links','installation_id=? AND code IN (?,?,?,?,?,?,?)',[$installationId,...$this->demoConnectivityCodes()]),
            'lorawan_networks'=>$this->countPrepared('bh_lorawan_networks','installation_id=? AND code=?',[$installationId,'LORA-PRIVADA']),
            'lorawan_gateways'=>$this->countPrepared('bh_lorawan_gateways','installation_id=? AND gateway_eui IN (?,?)',[$installationId,...$this->demoGatewayEuis()]),
            'lorawan_devices'=>$this->countPrepared('bh_lorawan_devices',"installation_id=? AND (metadata_json LIKE '%\"demo\":true%' OR dev_eui IN (?,?,?,?,?,?))",[$installationId,...$this->demoLoRaDeviceEuis()]),
            'iot_rules'=>$this->countPrepared('bh_iot_automation_rules','installation_id=? AND code=?',[$installationId,'TANQUE-BAJO']),
            'session_items'=>$this->sessionItemCount(),
        ];
    }

    public function clean(): array
    {
        EnvironmentEditor::set(['APP_DEMO_MODE'=>'false']);
        DemoRepository::clearSessionData();

        if (InstallationMode::isCentral()) {
            $ids=$this->pdo->query('SELECT id FROM bh_repo_installations WHERE is_demo=1')->fetchAll(PDO::FETCH_COLUMN)?:[];
            $result=(new CentralCleanupService($this->pdo))->cleanup($ids,(string)config('app.instance_code',''),false,false,true,true);
            return [
                'mode'=>'central',
                'demo_installations'=>count((array)($result['removed_installations']??[])),
                'demo_plans'=>(int)($result['removed_plans']??0),
                'orphan_tokens'=>(int)($result['removed_tokens']??0),
                'session_items'=>0,
            ];
        }

        $installationId=$this->installationId();
        $result=['mode'=>'node'];
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            $this->pdo->beginTransaction();
            $result['iot_rules']=$this->deletePrepared('bh_iot_automation_rules','installation_id=? AND code=?',[$installationId,'TANQUE-BAJO']);
            $result['lorawan_devices']=$this->deletePrepared('bh_lorawan_devices',"installation_id=? AND (metadata_json LIKE '%\"demo\":true%' OR dev_eui IN (?,?,?,?,?,?))",[$installationId,...$this->demoLoRaDeviceEuis()]);
            $result['lorawan_gateways']=$this->deletePrepared('bh_lorawan_gateways','installation_id=? AND gateway_eui IN (?,?)',[$installationId,...$this->demoGatewayEuis()]);
            $result['lorawan_networks']=$this->deletePrepared('bh_lorawan_networks','installation_id=? AND code=?',[$installationId,'LORA-PRIVADA']);
            $result['devices']=$this->deletePrepared('bh_devices','installation_id=? AND serial_number IN (?,?,?,?,?,?,?,?,?,?,?)',[$installationId,...$this->demoDeviceSerials()]);
            $result['connectivity_links']=$this->deletePrepared('bh_connectivity_links','installation_id=? AND code IN (?,?,?,?,?,?,?)',[$installationId,...$this->demoConnectivityCodes()]);
            $result['locations']=0;
            foreach(array_reverse($this->demoLocationCodes()) as $code){
                $result['locations'] += $this->deletePrepared('bh_site_locations','installation_id=? AND code=?',[$installationId,$code]);
            }
            $result['connector_instances']=$this->deletePrepared('bh_connector_instances','installation_id=? AND name IN (?,?,?,?,?,?,?,?)',[$installationId,...$this->demoConnectorNames()]);
            $result['technology_profiles']=$this->deletePrepared(
                'bh_technology_profiles',
                "installation_id=? AND profile_name='Perfil tecnológico principal' AND notes='Las credenciales y los datos personales permanecen en esta instalación Property Hub.'",
                [$installationId]
            );
            $this->pdo->commit();
        } catch(Throwable $e){
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
        $result['session_items']=0;
        return $result;
    }

    private function installationId(): int
    {
        $stmt=$this->pdo->prepare('SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1');
        $stmt->execute([(string)config('deployment.installation_uuid')]);
        $id=(int)$stmt->fetchColumn();
        if($id<1)throw new RuntimeException('No se encontró la instalación local.');
        return $id;
    }

    private function tableExists(string $table): bool
    {
        $stmt=$this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
        $stmt->execute([$table]);
        return $stmt->fetchColumn()!==false;
    }

    private function count(string $table,string $where): int
    {
        if(!$this->tableExists($table))return 0;
        return (int)$this->pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    }

    private function countPrepared(string $table,string $where,array $params): int
    {
        if(!$this->tableExists($table))return 0;
        $stmt=$this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function deletePrepared(string $table,string $where,array $params): int
    {
        if(!$this->tableExists($table))return 0;
        $stmt=$this->pdo->prepare("DELETE FROM {$table} WHERE {$where}");
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    private function executeDelete(string $sql): int
    {
        return (int)$this->pdo->exec($sql);
    }

    private function sessionItemCount(): int
    {
        $count=0;
        foreach(['demo_reservations','demo_bulletins','demo_notifications','demo_invitations'] as $key){
            $value=$_SESSION[$key]??[];
            if(is_array($value))$count+=count($value);
        }
        return $count;
    }

    private function demoConnectorNames(): array
    {
        return ['ZKBioSecurity servidor principal','CCTV Hikvision','Cámaras Bimar y multimarca','Telefonía, intercom y perifonía','UniFi Network / Protect','Cerraduras TTLock','LoRaWAN e IoT','Relés y dispositivos IP'];
    }

    private function demoDeviceSerials(): array
    {
        return ['ZK-GP-001','ZK-GN-001','ZK-GS-001','HK-NVR-001','HK-NVR-GN','GS-UCM-001','UF-UCG-001','TTL-GW-001','LORA-GW-001','LORA-GW-002','REL-PARQUE-01'];
    }

    private function demoLocationCodes(): array
    {
        return ['SITE','HQ','G-PRI','G-NOR','G-SUR','PARQUE','CLUB','PER-ESTE','BOMBAS'];
    }

    private function demoConnectivityCodes(): array
    {
        return ['FIB-GPRI','FIB-GNOR','UTP-GSUR','LTE-GSUR','WIFI-CLUB','LORA-PARK','INET-CLOUD'];
    }

    private function demoGatewayEuis(): array
    {
        return ['A84041FFFF000001','A84041FFFF000002'];
    }

    private function demoLoRaDeviceEuis(): array
    {
        return ['70B3D57ED0000001','70B3D57ED0000002','70B3D57ED0000003','70B3D57ED0000004','70B3D57ED0000005','70B3D57ED0000006'];
    }
}
