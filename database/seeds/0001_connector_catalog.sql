INSERT INTO bh_connector_definitions (code,name,vendor,domain,version,capabilities_json,status) VALUES
('zkteco_biosecurity','ZKTeco ZKBioSecurity Server','ZKTeco','access','0.1.1',JSON_ARRAY('people','credentials','events','doors','turnstiles','elevators'),'research'),
('ttlock_cloud','TTLock Open Platform','TTLock','access','0.1.0',JSON_ARRAY('locks','gateways','passcodes','ekeys','events','remote_unlock'),'planned'),
('grandstream_sip','Grandstream SIP / UCM','Grandstream','intercom','0.1.0',JSON_ARRAY('extensions','intercom','paging','call_groups','emergency_announcements'),'planned'),
('unifi_access','UniFi Access','Ubiquiti','access','0.1.0',JSON_ARRAY('users','credentials','permissions','schedules','doors','events'),'planned'),
('unifi_protect','UniFi Protect','Ubiquiti','video','0.1.0',JSON_ARRAY('cameras','live_video','events','recordings'),'planned'),
('unifi_network','UniFi Network','Ubiquiti','network','0.1.0',JSON_ARRAY('access_points','clients','guest_networks','hotspot','vouchers'),'planned'),
('hikvision','Hikvision','Hikvision','other','0.1.0',JSON_ARRAY('isapi','cameras','nvr','access','intercom'),'research'),
('dahua','Dahua','Dahua','other','0.1.0',JSON_ARRAY('dss','cgi','netsdk','cameras','access','intercom'),'research'),
('vimar','Vimar','Vimar','automation','0.1.0',JSON_ARRAY('video_intercom','relays','smart_home'),'research'),
('onvif','ONVIF','ONVIF','video','0.1.0',JSON_ARRAY('discovery','profiles','streaming','events','ptz'),'planned'),
('rtsp','RTSP Gateway','Generic','video','0.1.0',JSON_ARRAY('stream_input','webrtc_output','hls_output'),'planned'),
('generic_relay','Generic Relay','Generic','automation','0.1.0',JSON_ARRAY('pulse','on','off','status'),'planned'),
('lorawan','LoRaWAN / IoT','LoRa Alliance / multi-marca','automation','0.1.0',JSON_ARRAY('networks','gateways','sensors','telemetry','alerts','low_bandwidth_commands'),'planned');
