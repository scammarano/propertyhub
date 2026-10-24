# Validación 0.10.8

- [ ] `VERSION.json` y `config/app.php` reportan 0.10.8.
- [ ] Existe `resources/releases/property-hub-core-0.10.8.zip`.
- [ ] El ZIP distribuible no contiene `.env`, `.env.*`, `storage/keys`, `storage/uploads`, `storage/backups`, `storage/logs`, `storage/repository`, `storage/updates/rollback` ni `storage/installed.lock`.
- [ ] `update-package.json` declara 0.10.8, Core `property-hub-core`, `min_core_version=0.9.1` y `min_updater_version=1.0.0`.
- [ ] Central puede sincronizar/publicar `property-hub-core-0.10.8-stable`.
- [ ] Node 0.10.4 descubre 0.10.8 sin intervención manual.
- [ ] Node crea snapshot interno y PRE_UPDATE antes de aplicar archivos.
- [ ] Node termina en 0.10.8 y el historial marca `installed`.
- [ ] El respaldo manual posterior puede descargarse y restaurarse en XAMPP como copia segura/laboratorio.
