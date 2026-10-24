# Property Hub 0.14.27 — Hotfix de reclasificación Persona → Vehículo

## Problema

La validación conservadora previa a reclasificar una Persona como Vehículo consultaba `bh_account_invites`, pero el esquema real creado desde `0027_resident_identity_audit_vimar.sql` utiliza `bh_account_invitations`.

Esto provocaba `SQLSTATE[42S02]` y la reclasificación masiva reportaba la identidad como omitida aunque no existiera un bloqueo funcional real.

## Corrección

`NodeRepository::reclassifyPersonToVehicle()` consulta ahora:

```sql
SELECT COUNT(*) FROM bh_account_invitations WHERE person_id=?
```

La lógica sigue siendo conservadora: si existe una invitación de cuenta para esa persona, la conversión se rechaza para evitar transformar accidentalmente una identidad humana con flujo de acceso a Property App.

## Base y migraciones

- Base acumulativa: 0.14.26.
- Migraciones nuevas: ninguna.
- Migraciones históricas: sin cambios.
- Se conserva la topología Torre/Piso/Ascensor de 0.14.26.
