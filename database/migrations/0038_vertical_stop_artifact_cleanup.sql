-- Property Hub 0.14.43 — limpieza de puertas planificadas huérfanas de ascensor.
-- Versiones anteriores del Diseñador generaban un bh_access_points por parada.
-- Si luego se editaba el recorrido, la parada se eliminaba pero el acceso
-- planificado podía quedar en el árbol como si fuese una puerta real.
-- Sólo se eliminan artefactos seguros: generados por el Diseñador, sin
-- dispositivo, sin binding físico y sin ninguna parada que todavía los use.

DELETE ap
FROM bh_access_points ap
JOIN bh_structure_wizard_items wi
  ON wi.entity_type='access_point' AND wi.entity_id=ap.id
LEFT JOIN bh_vertical_transport_stops st
  ON st.access_point_id=ap.id
LEFT JOIN bh_asset_bindings ab
  ON ab.installation_id=ap.installation_id
 AND ab.logical_type='access_point'
 AND ab.logical_id=ap.id
WHERE ap.access_type='elevator'
  AND ap.device_id IS NULL
  AND ap.logical_state IN ('planned','unmapped')
  AND st.id IS NULL
  AND ab.id IS NULL
  AND NOT EXISTS (SELECT 1 FROM bh_access_point_bindings pb WHERE pb.access_point_id=ap.id)
  AND NOT EXISTS (SELECT 1 FROM bh_access_authenticators au WHERE au.access_point_id=ap.id)
  AND NOT EXISTS (SELECT 1 FROM bh_membership_access_grants mg WHERE mg.access_point_id=ap.id)
  AND NOT EXISTS (SELECT 1 FROM bh_access_execution_paths ep WHERE ep.access_point_id=ap.id)
  AND NOT EXISTS (SELECT 1 FROM bh_access_commands ac WHERE ac.access_point_id=ap.id)
  AND NOT EXISTS (SELECT 1 FROM bh_access_events ae WHERE ae.access_point_id=ap.id)
  AND NOT EXISTS (SELECT 1 FROM bh_access_point_sides aps WHERE aps.access_point_id=ap.id);

DELETE wi
FROM bh_structure_wizard_items wi
LEFT JOIN bh_access_points ap ON ap.id=wi.entity_id
WHERE wi.entity_type='access_point'
  AND ap.id IS NULL;
