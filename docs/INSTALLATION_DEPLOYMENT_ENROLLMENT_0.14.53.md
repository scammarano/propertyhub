# Property Hub 0.14.53 · Installation Deployment & Enrollment

Central pasa a ser la fuente de verdad de despliegue de cada propiedad. La ficha de una instalación registrada muestra permanentemente `property_code`, código del Property Node, URL de Central, URL reportada por el Node, estado y Edge Agents preparados.

## Edge Agents

Una propiedad puede tener uno o varios Edge Agents. Desde el Centro de despliegue de la propiedad se genera un código Edge y un token `PHEDGE-C-*` de un solo uso y 24 horas. El instalador Edge recibe solamente su identidad, URL del Property Node y token; ya no solicita ni valida manualmente `parent_property_code`.

El Edge **no se conecta directamente a Central**. Presenta el token al Property Node; el Node, usando su vínculo autenticado con Central, reclama el enrolamiento y valida que el token pertenezca a esa propiedad y a ese código Edge. Después el Node crea la credencial operativa local del Agent y mantiene la cadena `Central → Node → Edge`.

Los tokens secretos solo se muestran al generarlos. Los identificadores, relaciones y estados de despliegue quedan visibles permanentemente desde Central y pueden regenerarse sin recrear la propiedad.

## Migración

`0043_installation_deployment_enrollment.sql` es segura en los tres roles para permitir que el actualizador monolítico la registre automáticamente. La tabla de planes Edge sólo es utilizada por Central.
