<?php
declare(strict_types=1);

final class CatalogSeeder
{
    public function __construct(private PDO $pdo, private string $root) {}

    public function seedConnectorDefinitions(): int
    {
        $count = 0;
        foreach (glob($this->root.'/connectors/*/manifest.json') ?: [] as $file) {
            $manifest = json_decode((string)file_get_contents($file), true);
            if (!is_array($manifest) || empty($manifest['code']) || empty($manifest['name'])) continue;
            $domain = $this->normalizeDomain((array)($manifest['domains'] ?? []));
            $status = in_array((string)($manifest['status'] ?? ''), ['planned','research','beta','stable','deprecated'], true)
                ? (string)$manifest['status'] : 'planned';
            $stmt = $this->pdo->prepare(
                'INSERT INTO bh_connector_definitions (code,name,vendor,domain,version,capabilities_json,status) '
                .'VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),vendor=VALUES(vendor),domain=VALUES(domain),version=VALUES(version),capabilities_json=VALUES(capabilities_json),status=VALUES(status)'
            );
            $stmt->execute([
                (string)$manifest['code'], (string)$manifest['name'], (string)($manifest['vendor'] ?? $manifest['name']), $domain,
                (string)($manifest['connector_version'] ?? '0.0.0'),
                json_encode([
                    'domains'=>(array)($manifest['domains'] ?? []),
                    'integration_modes'=>(array)($manifest['integration_modes'] ?? []),
                    'capabilities'=>(array)($manifest['capabilities'] ?? []),
                    'requires_core'=>(string)($manifest['requires_core'] ?? ''),
                    'target_types'=>(array)($manifest['target_types'] ?? []),
                    'manages_downstream_assets'=>(bool)($manifest['manages_downstream_assets'] ?? false),
                    'direct_device_policy'=>(string)($manifest['direct_device_policy'] ?? 'not_applicable'),
                ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                $status,
            ]);
            $count++;
        }
        return $count;
    }

    public function seedVendorApis(): int
    {
        $rows = [
            ['zkteco','ZKTeco','zkbiosecurity-api','ZKBioSecurity / ZKBio CVSecurity API','Servidor de control de acceso','rest',null,'research','zkteco','Property Hub se integra con el servidor ZKBioSecurity; ese servidor administra paneles, personas, credenciales, niveles, eventos, puertas, torniquetes y ascensores.','No se presupone conexión directa a paneles inBio. La versión, licencia y alcance real del API se determinan por cada instalación.'],
            ['hikvision','Hikvision','isapi','Hikvision ISAPI','Video, acceso e intercom','rest',null,'research','hikvision','Dispositivos, eventos, video, acceso e intercom según modelo.','Puede coexistir con HikCentral OpenAPI, ONVIF y RTSP.'],
            ['hikvision','Hikvision','hikcentral-openapi','HikCentral OpenAPI','Plataforma centralizada','openapi',null,'planned','hikvision','Integración mediante plataforma HikCentral cuando esté instalada.','Requiere confirmar edición, licencia y versión.'],
            ['dahua','Dahua','dss-openapi','Dahua DSS OpenAPI','Video, acceso e intercom','openapi',null,'research','dahua','Eventos, dispositivos, cámaras y control de acceso mediante DSS.','La modalidad directa puede usar CGI, NetSDK, ONVIF o RTSP.'],
            ['ttlock','TTLock','ttlock-open-platform','TTLock Open Platform','Cerraduras inteligentes','cloud',null,'planned','ttlock','Cerraduras, eKeys, PIN temporales, gateways, vigencias y registros.','Las credenciales se almacenan exclusivamente en el nodo.'],
            ['grandstream','Grandstream','sip-ucm-gds','Grandstream SIP / UCM / GDS','Telefonía, intercom y perifonía','protocol',null,'planned','grandstream','Extensiones, grupos de llamada, videoportero, zonas de perifonía y anuncios.','La integración concreta depende de UCM, GDS y altavoces GSC instalados.'],
            ['unifi','Ubiquiti UniFi','unifi-access','UniFi Access API','Control de acceso','local',null,'planned','unifi','Puertas, credenciales, eventos y controladores UniFi Access.','El controlador y sus secretos permanecen en el edificio.'],
            ['unifi','Ubiquiti UniFi','unifi-protect','UniFi Protect API','Video','local',null,'planned','unifi','Cámaras, eventos, grabaciones y estado de Protect.','Se prioriza integración local.'],
            ['unifi','Ubiquiti UniFi','unifi-network','UniFi Network / Hotspot API','Red e Internet','local',null,'planned','unifi','AP, clientes invitados, SSID y vouchers de Internet.','Puede usar controlador local, Site Manager o External Hotspot según escenario.'],
            ['onvif','ONVIF','onvif-profiles','ONVIF Profiles','Video IP','protocol','T / G / S legado','planned','onvif','Descubrimiento, perfiles de stream, eventos, PTZ y grabaciones cuando estén disponibles.','Profile T será preferido para nuevas instalaciones.'],
            ['rtsp','RTSP','rtsp','RTSP','Video IP','protocol',null,'planned','rtsp','Entrada de video para conversión a WebRTC/HLS mediante gateway local.','No se exponen credenciales RTSP al navegador.'],
            ['vimar','Vimar','vimar-model-specific','Vimar / View / integración por modelo','Videoportero y automatización','other',null,'conditional','vimar','Intercom, apertura, directorio, relés y PIN cuando el modelo lo permita.','Debe levantarse modelo, gateway, software y tecnología exactos.'],
            ['sonoff','Sonoff / eWeLink','ewelink','eWeLink Cloud / CUBE','Automatización y relés','cloud',null,'planned','sonoff','Relés, pulsos, escenas y sensores.','Se prioriza integración local mediante CUBE/Home Assistant cuando sea posible.'],
            ['lorawan','LoRaWAN / IoT','lorawan-network','LoRaWAN, ChirpStack y MQTT','Sensores, telemetría y automatización','protocol',null,'planned','lorawan','Gateways de radio, sensores, batería, telemetría, alarmas y comandos de bajo volumen.','No requiere equipos Property Hub adicionales: el servidor principal consume la red LoRaWAN mediante API, MQTT o webhook.'],
        ];
        $stmt = $this->pdo->prepare(
            'INSERT INTO bh_repo_vendor_apis (vendor_code,vendor_name,api_code,display_name,domain,api_type,current_version,lifecycle_status,connector_code,capabilities_text,compatibility_notes,secrets_location,last_reviewed_at) '
            .'VALUES (?,?,?,?,?,?,?,?,?,?,?,\'node_only\',CURRENT_DATE) ON DUPLICATE KEY UPDATE vendor_name=VALUES(vendor_name),display_name=VALUES(display_name),domain=VALUES(domain),api_type=VALUES(api_type),current_version=VALUES(current_version),lifecycle_status=VALUES(lifecycle_status),connector_code=VALUES(connector_code),capabilities_text=VALUES(capabilities_text),compatibility_notes=VALUES(compatibility_notes),last_reviewed_at=CURRENT_DATE'
        );
        foreach ($rows as $row) $stmt->execute($row);
        return count($rows);
    }

    private function normalizeDomain(array $domains): string
    {
        foreach ($domains as $domain) {
            if (in_array($domain, ['access','video','intercom','automation','network','identity','notification','other'], true)) return (string)$domain;
        }
        return 'other';
    }
}
