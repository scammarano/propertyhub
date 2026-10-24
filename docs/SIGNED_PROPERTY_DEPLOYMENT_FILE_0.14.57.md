# Property Hub 0.14.57 · Archivo firmado de despliegue

## Objetivo

Central emite un único archivo `*.propertyhub-deploy` por propiedad. El mismo archivo se usa primero en el instalador del **Property Node** y después en cada **Property Edge Agent**, eliminando la copia manual de códigos, URLs y tokens.

## Contenido

Incluye identidad de la propiedad, URL de Central, identidad/URL del Node, todos los Edge preparados y **tokens temporales de un solo uso**. No contiene contraseñas de administradores, claves de base de datos ni credenciales permanentes Node/Edge.

## Seguridad

- Central firma el manifiesto con RSA-SHA256.
- El instalador verifica firma, hash canónico, huella de la clave y vencimiento antes de usarlo.
- Al completar el enrolamiento en línea, Node/Edge comprueban que la clave pública recibida desde la cadena real coincide con la huella del archivo.
- Generar un archivo nuevo rota los tokens pendientes incluidos en el anterior, pero nunca altera un Edge ya enrolado/claimed.
- Para reinstalar un Edge ya activo se debe regenerar explícitamente ese Edge en Central antes de descargar un archivo nuevo.
- Los tokens se consumen individualmente; usar el token del Node no consume los tokens Edge.

## Flujo recomendado

1. Crear la propiedad en Central.
2. Preparar uno o varios Edge Agents. Si no existe ninguno, la descarga crea `Edge Principal` automáticamente.
3. Descargar `<PROPERTY>.propertyhub-deploy`.
4. Importar el archivo en el instalador del Property Node. Identidad, Central URL y token se autocompletan.
5. Importar **el mismo archivo** en cada servidor Edge. Si contiene varios Edge, se selecciona cuál se está instalando; no se escriben códigos ni URLs.
6. Cada destino intercambia su token temporal por sus credenciales operativas normales.

## Edge antes del Node

La migración `0045_property_deployment_file_pre_node_edges.sql` agrega en Central la identidad `property_code` a `bh_repo_edge_deployments` y permite preparar Edge Agents aunque el Node todavía no se haya registrado. Cuando el Node se registra, Central enlaza automáticamente esos Edge con su registro.

## Compatibilidad

El flujo manual permanece disponible para soporte. En Node/Edge 0045 es un no-op seguro para que el actualizador 0.14.56 pueda aplicarla automáticamente; el código 0.14.57 además detecta si 0045 todavía está pendiente en Central y mantiene el flujo histórico cuando ya existe un Node, evitando romper Central durante la ventana entre actualización y reparación de esquema.
