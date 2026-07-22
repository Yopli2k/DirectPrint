# 50. Arquitectura

## Piezas principales

El plugin está organizado en clases especializadas dentro de `Lib/DirectPrint/`, con `PrinterService` como única puerta de entrada:

- **`PrinterService`**: la fachada pública. Es la única clase que otros plugins deben usar. No contiene lógica propia: expone toda la API y delega en las clases especializadas.
- **`DocumentPrinter`**: imprime documentos de compra y venta. Genera su PDF con el motor de exportación de FacturaScripts y baja a `FilePrinter` para el envío.
- **`FilePrinter`**: la capa de bajo nivel que imprime ficheros, contenidos binarios y texto. Valida la impresora y las opciones, registra el `DpPrintJob` y llama a `Cups`.
- **`TempFile`**: gestiona el ciclo de vida de los ficheros temporales (escritura con nombre aleatorio, validación, detección de mime y limpieza).
- **`Cups`**: la única clase del plugin que sabe de CUPS. Envuelve los comandos `lp` y `lpstat` de bajo nivel. No conoce nada de FacturaScripts (ni modelos, ni traducciones, ni sesión de usuario): solo ejecuta comandos y parsea su salida.

El ciclo de vida del trabajo (crear un `DpPrintJob` pendiente, marcarlo como fallido o como enviado) vive en el propio modelo `DpPrintJob` (`create()`, `fail()`, `markSent()`), que es el pegamento común a las capas de documentos y de ficheros.

Esta separación es deliberada en dos ejes: los consumidores solo dependen de `PrinterService` (aunque por dentro se reorganice), y si en el futuro se añadiera soporte para otro sistema de impresión distinto de CUPS, solo habría que sustituir `Cups` sin tocar la API pública.

El plugin es intencionadamente **concreto a CUPS**, no una capa de abstracción genérica para "cualquier sistema de impresión". Eso mantiene el código simple; si en el futuro se necesita otro backend, se podría considerar un plugin `DirectPrintXXX` distinto o una interfaz común, según lo que haga falta entonces.

## Modelos

- **`DpPrinter`**: una impresora configurada (tabla `directprint_printers`).
- **`DpPrintJob`**: un trabajo de impresión enviado (tabla `directprint_jobs`). Además de ser el registro histórico, es el objeto que devuelve `PrinterService` como resultado de cada operación de impresión.
- **`DpRoute`**: una acción de impresión (tabla `directprint_routes`) que asocia una clave semántica (`slug`) con una impresora. Un plugin registra la acción y el administrador le asigna la impresora; ver [06. Acciones de impresión](06-rutas-de-impresion.md).

## Por qué `proc_open` en forma de array

`Cups` ejecuta los comandos con `proc_open()` pasando el comando como **array** (`['lp', '-d', $queue, ...]`) en vez de como una cadena de texto. En este modo PHP **no invoca ninguna shell**: cada elemento del array se pasa tal cual como argumento al proceso, así que no hay forma de que datos como el nombre de una cola o una opción inyecten código de shell. Es la misma razón por la que no se usa `exec()` ni `shell_exec()` con una cadena concatenada.

Ver [52. Seguridad y validaciones](52-seguridad-y-validaciones.md) para el resto de controles (lista blanca de opciones, restricción a la carpeta temporal, etc.).

## Flujo de una impresión

Todos los caminos convergen, al final, en `FilePrinter::printFile()`. `PrinterService` solo delega; la lógica vive en las clases especializadas:

1. `DocumentPrinter` (documentos) genera el PDF y llama a `FilePrinter::printContents()`; `printText()` hace lo mismo con el texto.
2. `FilePrinter::printContents()` escribe el contenido en un fichero temporal seguro (`TempFile::write()`) y llama a `printFile()`.
3. `FilePrinter::printFile()` es el único método que valida la impresora, valida el fichero (`TempFile::validate()`), aplica la lista blanca de opciones, registra el `DpPrintJob`, llama a `Cups::printFile()` y borra el temporal (`TempFile::delete()`).

Ver [51. Servicio PrinterService](51-servicio-printerservice.md) para la referencia completa de la API pública.

## Administración e historial

`ListDpPrinter` (listado, con dos pestañas: impresoras e historial) y `EditDpPrinter` (ficha de una impresora, con los botones "Comprobar cola" e "Imprimir prueba") son los únicos controladores del plugin. `EditDpPrintJob` es una ficha de solo lectura para el detalle de un trabajo del historial.

## Limpieza de temporales

`PrinterService::cleanTempFiles()` recorre la carpeta temporal del plugin (`MyFiles/DirectPrint`) y elimina los ficheros más antiguos que `PrinterService::TEMP_RETENTION_HOURS`. Se ejecuta automáticamente cada hora mediante el cron del plugin (`Cron.php`). En circunstancias normales esta carpeta debería estar casi siempre vacía, porque cada fichero temporal se borra justo después de enviarse a CUPS (o al fallar la validación); este cron es una red de seguridad para los casos en los que ese borrado inmediato no llega a producirse (por ejemplo, un proceso PHP interrumpido a mitad).
