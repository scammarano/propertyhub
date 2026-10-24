<?php
declare(strict_types=1);
return [
    'access' => ['zkteco_biosecurity','zkteco_direct','hikvision_isapi','hikcentral','dahua_direct','dahua_dss','unifi_access','ttlock','generic_relay'],
    'video' => ['onvif','rtsp','hikvision','dahua','unifi_protect','bimar','nvr_generic'],
    'intercom' => ['grandstream_sip','grandstream_paging','vimar','hikvision_intercom','dahua_intercom','sip_generic'],
    'automation' => ['vimar','sonoff','ewelink','home_assistant','mqtt','modbus_tcp','generic_relay','lorawan'],
    'iot' => ['lorawan','mqtt','modbus_tcp','home_assistant'],
    'internet' => ['unifi_network','unifi_hotspot_vouchers'],
];
