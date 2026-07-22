# 01. Presentación y requisitos

## Qué es DirectPrint

DirectPrint es un plugin de FacturaScripts que permite **imprimir directamente en una impresora conectada al servidor**, sin necesidad de descargar el PDF y abrirlo en el navegador para mandarlo a imprimir manualmente.

El plugin cubre dos frentes:

- Una pequeña administración donde se dan de alta las impresoras disponibles y se consulta el historial de trabajos enviados.
- Un servicio interno que usan **otros plugins** para enviar documentos, ficheros o texto a esas impresoras de forma automática (por ejemplo, imprimir una factura en cuanto se valida, o imprimir una etiqueta al cerrar un albarán).

DirectPrint no sustituye al botón de imprimir/descargar PDF habitual de FacturaScripts. Es una vía adicional para los casos en los que se necesita mandar el documento a papel sin intervención manual.

## Qué NO hace

- No imprime automáticamente ningún documento por sí solo. Es la infraestructura que otros plugins usan para hacerlo; DirectPrint por sí mismo solo ofrece la administración de impresoras, el historial y la página de prueba.
- No gestiona impresoras USB conectadas al ordenador del usuario ni impresoras de red que no estén dadas de alta en el servidor.

## Requisitos del servidor

DirectPrint necesita que el **servidor** donde corre FacturaScripts tenga instalado **CUPS** (Common Unix Printing System), el sistema de impresión estándar en Linux/Unix:

- Sistema operativo Linux (Ubuntu, Debian u otra distribución con CUPS).
- CUPS instalado y con al menos una impresora configurada (local, de red o compartida).
- Los comandos `lp` y `lpstat` deben estar disponibles y ser ejecutables por el usuario con el que corre PHP (habitualmente `www-data`).

Si el servidor es Windows o no tiene CUPS, el plugin no podrá enviar trabajos de impresión: seguirá funcionando la administración, pero cualquier intento de imprimir fallará con un error de cola no válida.

En el documento [02. Administración de impresoras](02-administracion-de-impresoras.md) se explica cómo comprobar que todo está bien configurado antes de dar de alta las impresoras en FacturaScripts.
