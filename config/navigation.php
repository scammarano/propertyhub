<?php
declare(strict_types=1);

/*
 * Menús separados por modalidad.
 * CENTRAL_REPOSITORY administra instalaciones, versiones, APIs, conectores y salud.
 * BUILDING_NODE opera una urbanización, edificio, conjunto, oficina o complejo y su experiencia para usuarios.
 */
if (InstallationMode::isCentral()) {
    return [
        [
            'code'=>'central-home','label'=>'Property Hub Central','icon'=>'bi bi-cloud-check','items'=>[
                ['code'=>'central-home.dashboard','route'=>'repository-dashboard','icon'=>'bi bi-speedometer2','label'=>'Vista ejecutiva','permission'=>'system.manage'],
            ],
        ],
        [
            'code'=>'portfolio','label'=>'Red de instalaciones','icon'=>'bi bi-buildings','sections'=>[
                ['code'=>'portfolio.installations','label'=>'Portafolio','icon'=>'bi bi-diagram-3','items'=>[
                    ['code'=>'portfolio.installations.list','route'=>'repository-installations','icon'=>'bi bi-buildings','label'=>'Instalaciones y conjuntos','permission'=>'system.manage'],
                    ['code'=>'portfolio.installations.cleanup','route'=>'repository-central-cleanup','icon'=>'bi bi-trash3','label'=>'Limpieza de pruebas','permission'=>'system.manage'],
                    ['code'=>'portfolio.installations.new','route'=>'repository-new-installation','icon'=>'bi bi-plus-square','label'=>'Nueva instalación','permission'=>'system.manage'],
                    ['code'=>'portfolio.health','route'=>'repository-health','icon'=>'bi bi-heart-pulse','label'=>'Salud y sincronización','permission'=>'system.manage'],
                    ['code'=>'portfolio.metrics','route'=>'repository-metrics','icon'=>'bi bi-bar-chart','label'=>'Métricas consolidadas','permission'=>'system.manage'],
                ]],
                ['code'=>'portfolio.privacy','label'=>'Privacidad y soporte','icon'=>'bi bi-shield-lock','items'=>[
                    ['code'=>'portfolio.privacy.policy','route'=>'repository-privacy','icon'=>'bi bi-shield-check','label'=>'Política de datos','permission'=>'system.manage'],
                    ['code'=>'portfolio.support','route'=>'repository-support','icon'=>'bi bi-headset','label'=>'Soporte remoto','permission'=>'system.manage'],
                ]],
            ],
        ],
        [
            'code'=>'technical-catalog','label'=>'Catálogo técnico','icon'=>'bi bi-cpu','sections'=>[
                ['code'=>'technical-catalog.platform','label'=>'Plataforma Property Hub','icon'=>'bi bi-braces','items'=>[
                    ['code'=>'technical-catalog.platform.api','route'=>'repository-api-versions','icon'=>'bi bi-braces-asterisk','label'=>'APIs de Property Hub','permission'=>'system.manage'],
                    ['code'=>'technical-catalog.connectors','route'=>'repository-connectors','icon'=>'bi bi-plug','label'=>'Conectores y versiones','permission'=>'system.manage'],
                ]],
                ['code'=>'technical-catalog.vendors','label'=>'Fabricantes','icon'=>'bi bi-boxes','items'=>[
                    ['code'=>'technical-catalog.vendor-apis','route'=>'repository-vendor-apis','icon'=>'bi bi-diagram-2','label'=>'APIs de fabricantes','permission'=>'system.manage'],
                    ['code'=>'technical-catalog.compatibility','route'=>'repository-compatibility','icon'=>'bi bi-intersect','label'=>'Matriz de compatibilidad','permission'=>'system.manage'],
                ]],
            ],
        ],
        [
            'code'=>'distribution','label'=>'Versiones y distribución','icon'=>'bi bi-cloud-arrow-down','items'=>[
                ['code'=>'distribution.releases','route'=>'repository-releases','icon'=>'bi bi-box-seam','label'=>'Paquetes y releases','permission'=>'system.manage'],
                ['code'=>'distribution.tokens','route'=>'repository-tokens','icon'=>'bi bi-key','label'=>'Tokens de instalación','permission'=>'system.manage'],
            ],
        ],
        [
            'code'=>'central-system','label'=>'Sistema central','icon'=>'bi bi-gear','items'=>[
                ['code'=>'central-system.lab','route'=>'repository-lab','icon'=>'bi bi-beaker','label'=>'Laboratorio y pruebas','permission'=>'system.manage'],
                ['code'=>'central-system.diagnostics','route'=>'system-check','icon'=>'bi bi-activity','label'=>'Diagnóstico central','permission'=>'system.manage'],
                ['code'=>'central-system.deployment','route'=>'deployment-settings','icon'=>'bi bi-hdd-network','label'=>'Identidad del repositorio','permission'=>'system.manage'],
                ['code'=>'central-system.vault','route'=>'repository-backup-vault','icon'=>'bi bi-shield-lock','label'=>'Backup Vault de instalaciones','permission'=>'system.manage'],
                ['code'=>'central-system.backup','route'=>'backup-recovery','icon'=>'bi bi-database-down','label'=>'Respaldo del Central','permission'=>'system.manage'],
            ],
        ],
    ];
}


if (InstallationMode::isEdge()) {
    $edgeIntegrationItems=[];
    if(LocalIntegrationProvisioning::edgeEnabled('zkteco'))$edgeIntegrationItems[]=['code'=>'edge-integrations.zkbio','route'=>'edge-zkbio-lab','icon'=>'bi bi-shield-lock','label'=>'ZKBio API Lab','permission'=>'system.manage'];
    if(LocalIntegrationProvisioning::edgeEnabled('vimar'))$edgeIntegrationItems[]=['code'=>'edge-integrations.vimar','route'=>'edge-vimar-lab','icon'=>'bi bi-house-gear','label'=>'Vimar · VIEW / By-me / KNX','permission'=>'system.manage'];
    $edgeIntegrationItems[]=['code'=>'edge-integrations.video','route'=>'edge-video-lab','icon'=>'bi bi-camera-video','label'=>'Video local · ONVIF / RTSP','permission'=>'system.manage'];
    return [
        [
            'code'=>'edge-home','label'=>'Property Edge Agent','icon'=>'bi bi-router','items'=>[
                ['code'=>'edge-home.dashboard','route'=>'edge-dashboard','icon'=>'bi bi-speedometer2','label'=>'Estado y laboratorio','permission'=>'system.manage'],
            ],
        ],
        [
            'code'=>'edge-integrations','label'=>'Integraciones locales','icon'=>'bi bi-plug','items'=>$edgeIntegrationItems,
        ],
        [
            'code'=>'edge-system','label'=>'Sistema del Agent','icon'=>'bi bi-gear','items'=>[
                ['code'=>'edge-system.deployment','route'=>'deployment-settings','icon'=>'bi bi-hdd-network','label'=>'Vínculo del Agent','permission'=>'system.manage'],
                ['code'=>'edge-system.backup','route'=>'backup-recovery','icon'=>'bi bi-database-down','label'=>'Respaldo y recuperación','permission'=>'system.manage'],
                ['code'=>'edge-system.updates','route'=>'updates','icon'=>'bi bi-arrow-repeat','label'=>'Actualizaciones','permission'=>'system.manage'],
                ['code'=>'edge-system.diagnostics','route'=>'system-check','icon'=>'bi bi-activity','label'=>'Diagnóstico','permission'=>'system.manage'],
            ],
        ],
    ];
}

return [
    [
        'code'=>'home','label'=>'Inicio','icon'=>'bi bi-speedometer2','items'=>[
            ['code'=>'home.dashboard','route'=>'dashboard','icon'=>'bi bi-grid-1x2-fill','label'=>'Dashboard operativo','permission'=>'dashboard.view'],
        ],
    ],
    [
        'code'=>'property-management','label'=>'Gestión de la propiedad','icon'=>'bi bi-buildings','sections'=>[
            ['code'=>'property-management.structure','label'=>'Estructura','icon'=>'bi bi-diagram-3','items'=>[
                ['code'=>'property-management.profile','route'=>'building-profile','icon'=>'bi bi-building-gear','label'=>'Perfil de la instalación','permission'=>'properties.view'],
                ['code'=>'property-management.properties','route'=>'properties','icon'=>'bi bi-buildings','label'=>'Estructura física','permission'=>'properties.view'],
                ['code'=>'property-management.structure-map','route'=>'structure-explorer','icon'=>'bi bi-diagram-2-fill','label'=>'Mapa jerárquico','permission'=>'properties.view'],
                ['code'=>'property-management.structure-wizard','route'=>'structure-wizard','icon'=>'bi bi-magic','label'=>'Asistente de diseño','permission'=>'properties.manage'],
                ['code'=>'property-management.areas','route'=>'areas','icon'=>'bi bi-grid-1x2','label'=>'Áreas y espacios','permission'=>'areas.view'],
                ['code'=>'property-management.vertical','route'=>'vertical-transport','icon'=>'bi bi-arrow-down-up','label'=>'Ascensores y circulación','permission'=>'properties.view'],
            ]],
            ['code'=>'property-management.community','label'=>'Personas y comunidad','icon'=>'bi bi-people','items'=>[
                ['code'=>'property-management.people','route'=>'people','icon'=>'bi bi-people','label'=>'Personas','permission'=>'people.view'],
                ['code'=>'property-management.vehicles','route'=>'vehicles','icon'=>'bi bi-car-front-fill','label'=>'Vehículos','permission'=>'people.view'],
                ['code'=>'property-management.access-profiles','route'=>'access-profiles','icon'=>'bi bi-person-gear','label'=>'Perfiles de acceso','permission'=>'people.view'],
                ['code'=>'property-management.user-activity','route'=>'user-activity','icon'=>'bi bi-person-lines-fill','label'=>'Actividad de usuarios','permission'=>'people.view'],
                ['code'=>'property-management.visitors','route'=>'visitors','icon'=>'bi bi-person-badge','label'=>'Visitantes','permission'=>'visitors.view'],
            ]],
        ],
    ],
    [
        'code'=>'community-services','label'=>'Servicios y comunidad','icon'=>'bi bi-calendar2-heart','sections'=>[
            ['code'=>'community-services.reservations','label'=>'Reservas','icon'=>'bi bi-calendar2-check','items'=>[
                ['code'=>'community-services.reservations.calendar','route'=>'reservations','icon'=>'bi bi-calendar3','label'=>'Solicitudes y calendario','permission'=>'reservations.view'],
                ['code'=>'community-services.resident-app','route'=>'resident-app-settings','icon'=>'bi bi-phone','label'=>'Property App · Residentes','permission'=>'system.manage'],
            ]],
            ['code'=>'community-services.communications','label'=>'Comunicaciones','icon'=>'bi bi-megaphone','items'=>[
                ['code'=>'community-services.bulletin','route'=>'bulletin','icon'=>'bi bi-pin-angle','label'=>'Cartelera','permission'=>'bulletin.view'],
                ['code'=>'community-services.notifications','route'=>'notifications','icon'=>'bi bi-bell','label'=>'Notificaciones','permission'=>'notifications.view'],
                ['code'=>'community-services.emergency','route'=>'emergency','icon'=>'bi bi-telephone-forward','label'=>'Emergencias','permission'=>'emergency.view'],
            ]],
        ],
    ],
    [
        'code'=>'technology','label'=>'Infraestructura y tecnología','icon'=>'bi bi-cpu','sections'=>[
            ['code'=>'technology.inventory','label'=>'Configuración técnica','icon'=>'bi bi-hdd-stack','items'=>[
                ['code'=>'technology.inventory.topology','route'=>'site-topology','icon'=>'bi bi-map','label'=>'Sedes, garitas y conectividad','permission'=>'integrations.view'],
                ['code'=>'technology.edge.agents','route'=>'edge-agents','icon'=>'bi bi-router','label'=>'Property Edge Agents','permission'=>'system.manage'],
                ...(LocalIntegrationProvisioning::nodeEnabled('zkteco')?[['code'=>'technology.integrations.zkbio-lab','route'=>'zkbio-lab','icon'=>'bi bi-shield-lock','label'=>'ZKBio API Lab','permission'=>'system.manage']]:[]),
                ['code'=>'technology.inventory.systems','route'=>'technology-inventory','icon'=>'bi bi-hdd-stack','label'=>'Equipos y activos','permission'=>'integrations.view'],
                ['code'=>'technology.inventory.connectors','route'=>'connector-instances','icon'=>'bi bi-plug-fill','label'=>'Sistemas conectados','permission'=>'integrations.view'],
                ['code'=>'technology.integrations.catalog','route'=>'integrations','icon'=>'bi bi-boxes','label'=>'Catálogo de integraciones','permission'=>'integrations.view'],
            ]],
            ['code'=>'technology.security','label'=>'Seguridad física','icon'=>'bi bi-shield-lock','items'=>[
                ['code'=>'technology.access','route'=>'access','icon'=>'bi bi-door-open','label'=>'Control de accesos','permission'=>'access.view'],
                ['code'=>'technology.cameras','route'=>'cameras','icon'=>'bi bi-camera-video','label'=>'Cámaras','permission'=>'cameras.view'],
            ]],
            ['code'=>'technology.communications','label'=>'Comunicaciones','icon'=>'bi bi-broadcast-pin','items'=>[
                ['code'=>'technology.intercom','route'=>'intercom','icon'=>'bi bi-broadcast-pin','label'=>'Intercom y perifonía','permission'=>'intercom.view'],
                ['code'=>'technology.internet','route'=>'internet','icon'=>'bi bi-wifi','label'=>'Internet y vouchers','permission'=>'internet.view'],
                ['code'=>'technology.lorawan','route'=>'lorawan','icon'=>'bi bi-broadcast','label'=>'LoRaWAN e IoT','permission'=>'integrations.view'],
            ]],
        ],
    ],
    [
        'code'=>'system','label'=>'Sistema local','icon'=>'bi bi-gear','items'=>[
            ['code'=>'system.privacy','route'=>'node-privacy','icon'=>'bi bi-shield-check','label'=>'Privacidad y telemetría','permission'=>'system.manage'],
            ['code'=>'system.diagnostics','route'=>'system-check','icon'=>'bi bi-activity','label'=>'Diagnóstico','permission'=>'system.manage'],
            ['code'=>'system.deployment','route'=>'deployment-settings','icon'=>'bi bi-hdd-network','label'=>'Vínculo con Property Hub Central','permission'=>'system.manage'],
            ['code'=>'system.updates','route'=>'updates','icon'=>'bi bi-arrow-repeat','label'=>'Actualizaciones','permission'=>'system.manage'],
            ['code'=>'system.backup','route'=>'backup-recovery','icon'=>'bi bi-database-down','label'=>'Respaldo y recuperación','permission'=>'system.manage'],
        ],
    ],
];
