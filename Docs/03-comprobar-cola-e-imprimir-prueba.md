# 03. Comprobar cola e imprimir prueba

Una vez dada de alta una impresora (ver [02. Administración de impresoras](02-administracion-de-impresoras.md)), conviene validarla antes de darla por operativa. Para eso, la ficha de la impresora incluye dos botones.

## Comprobar cola

Comprueba que el nombre escrito en el campo **Cola CUPS** corresponde a una cola que existe de verdad en el servidor en ese momento (ejecuta `lpstat -a` y busca ese nombre exacto).

- Si la cola existe, aparece un aviso confirmando que se ha encontrado.
- Si no existe, aparece una advertencia. Las causas más habituales son un nombre de cola mal escrito, una cola que se ha eliminado en CUPS, o que el usuario con el que corre PHP no tiene permiso para consultar las colas del sistema.

Este botón solo consulta el estado de CUPS: no envía nada a imprimir ni modifica la impresora.

## Imprimir prueba

Envía una página de texto sencilla a la impresora, con el mensaje de prueba de DirectPrint y la fecha y hora actuales. Es la forma más directa de confirmar que todo el circuito funciona de extremo a extremo: FacturaScripts, el servicio de impresión y CUPS.

- Si el envío tiene éxito, aparece un aviso con el identificador del trabajo asignado por CUPS.
- Si falla, aparece una advertencia y el motivo queda registrado en el historial (pestaña **Historial**, ver [04. Historial de trabajos](04-historial-de-trabajos.md)).

Un envío correcto significa que CUPS **ha aceptado** el trabajo, no que el papel haya salido ya de la impresora física: puede haber cola de impresión, la impresora puede estar sin papel o apagada, etc. Ver la nota sobre el estado "Enviado" en el documento del historial.
