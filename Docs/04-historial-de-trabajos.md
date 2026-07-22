# 04. Historial de trabajos

En **Admin → Impresoras → Historial** se puede consultar todos los trabajos de impresión que se han intentado enviar, tanto los generados manualmente (por ejemplo, con el botón "Imprimir prueba") como los enviados automáticamente por otros plugins.

## Filtros disponibles

- **Impresora**: mostrar solo los trabajos enviados a una impresora concreta.
- **Estado**: ver únicamente los trabajos en un estado determinado (ver más abajo).
- **Usuario**: ver los trabajos solicitados por un usuario concreto.
- **Periodo**: acotar por fecha de creación del trabajo.

También se puede buscar por nombre de fichero, identificador de CUPS o el texto del error.

## Qué significa cada estado

- **Pendiente**: el trabajo se ha creado pero todavía no se ha intentado enviar (es un estado transitorio muy breve; normalmente no se llega a ver en la práctica).
- **Enviado**: el trabajo ha sido **aceptado por CUPS**. Esto **no** significa que el papel haya salido ya de la impresora física: solo indica que CUPS lo ha admitido en su cola. Si la impresora está apagada, sin papel o hay otros trabajos por delante, la impresión real puede tardar o no llegar a completarse sin que DirectPrint pueda saberlo, ya que CUPS no informa de vuelta cuando termina.
- **Error**: el envío ha fallado. El motivo se puede consultar en el detalle del trabajo, en el campo Error (por ejemplo: impresora inactiva, cola no válida, archivo no permitido, o un error devuelto por el propio CUPS).
- **Cancelado**: estado reservado para una futura función de cancelación manual de trabajos; en la versión actual del plugin ningún trabajo llega a este estado.

## Datos de cada trabajo

Cada línea del historial muestra, además del estado, la impresora usada, el usuario que lo solicitó, la fecha, el nombre del fichero impreso y el identificador que le ha asignado CUPS. Al abrir el detalle de un trabajo se puede consultar también las opciones de impresión utilizadas (copias, tamaño de papel, orientación) y, cuando el trabajo lo ha generado otro plugin, de qué plugin y de qué documento procede (por ejemplo, una factura de cliente concreta).

El historial es de solo lectura: no se pueden crear ni editar trabajos manualmente desde esta pantalla, solo consultarlos.
