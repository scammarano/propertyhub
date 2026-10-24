# Repository API v1

Base:

```text
{REPOSITORY_PUBLIC_URL}/api/repository/v1/
```

## `POST register.php`

Autenticación:

```text
X-Registration-Token: BHR-...
```

Registra o renueva un nodo y devuelve la clave pública del repositorio.

## `POST heartbeat.php`

Autenticación:

```text
X-Installation-Key: secreto-del-nodo
```

Cuerpo mínimo:

```json
{
  "installation_uuid": "...",
  "app_version": "0.5.0",
  "api_version": "v1",
  "ui_version": "1.2.0"
}
```

## `POST catalog.php`

Devuelve versiones de API y manifiestos disponibles de conectores.

## `POST manifest.php`

Devuelve el manifiesto de versiones correspondiente al canal del nodo. La respuesta contiene:

- manifiesto;
- firma base64;
- algoritmo `RSA-SHA256`;
- SHA-256 canónico.

## `GET package.php`

Descarga un paquete publicado. Requiere:

```text
X-Installation-Uuid: UUID
X-Installation-Key: secreto-del-nodo
```

El archivo se entrega desde `storage/repository/packages`; esa carpeta nunca se expone directamente por Apache.
