# 50. Arquitectura

## Piezas principales

El plugin está organizado en dos capas dentro de `Lib/DirectPrint/`:

- **`Cups`**: la única clase del plugin que sabe de CUPS. Envuelve los comandos `lp` y `lpstat` de bajo nivel. No conoce nada de FacturaScripts (ni modelos, ni traducciones, ni sesión de usuario): solo ejecuta comandos y parsea su salida.
- **`PrinterService`**: la fachada pública. Es la única clase que otros plugins deben usar. Valida, registra el trabajo (`DpPrintJob`), delega en `Cups` para el envío real y gestiona los ficheros temporales.

Esta separación es deliberada: si en el futuro se añadiera soporte para otro sistema de impresión distinto de CUPS, solo habría que sustituir `Cups` (o convivir con una clase equivalente), sin tocar la API pública que ya usan otros plugins.

El plugin es intencionadamente **concreto a CUPS**, no una capa de abstracción genérica para "cualquier sistema de impresión". Eso mantiene el código simple; si en el futuro se necesita otro backend, se podría considerar un plugin `DirectPrintXXX` distinto o una interfaz común, según lo que haga falta entonces.

## Modelos

- **`DpPrinter`**: una impresora configurada (tabla `directprint_printers`).
- **`DpPrintJob`**: un trabajo de impresión enviado (tabla `directprint_jobs`). Además de ser el registro histórico, es el objeto que devuelve `PrinterService` como resultado de cada operación de impresión.

## Por qué `proc_open` en forma de array

`Cups` ejecuta los comandos con `proc_open()` pasando el comando como **array** (`['lp', '-d', $queue, ...]`) en vez de como una cadena de texto. En este modo PHP **no invoca ninguna shell**: cada elemento del array se pasa tal cual como argumento al proceso, así que no hay forma de que datos como el nombre de una cola o una opción inyecten código de shell. Es la misma razón por la que no se usa `exec()` ni `shell_exec()` con una cadena concatenada.

Ver [52. Seguridad y validaciones](52-seguridad-y-validaciones.md) para el resto de controles (lista blanca de opciones, restricción a la carpeta temporal, etc.).

## Flujo de una impresión

Todos los métodos públicos de `PrinterService` que envían algo a imprimir convergen, al final, en `printFile()`:

1. `printText()` y `printDocument()` / `printDocumentById()` llaman a `printContents()`.
2. `printContents()` escribe el contenido en un fichero temporal seguro y llama a `printFile()`.
3. `printFile()` es el único método que valida la impresora, valida el fichero, aplica la lista blanca de opciones, registra el `DpPrintJob`, llama a `Cups::printFile()` y borra el temporal.

Ver [51. Servicio PrinterService](51-servicio-printerservice.md) para la referencia completa de la API pública.

## Administración e historial

`ListDpPrinter` (listado, con dos pestañas: impresoras e historial) y `EditDpPrinter` (ficha de una impresora, con los botones "Comprobar cola" e "Imprimir prueba") son los únicos controladores del plugin. `EditDpPrintJob` es una ficha de solo lectura para el detalle de un trabajo del historial.

## Limpieza de temporales

`PrinterService::cleanTempFiles()` recorre la carpeta temporal del plugin (`MyFiles/DirectPrint`) y elimina los ficheros más antiguos que `PrinterService::TEMP_RETENTION_HOURS`. Se ejecuta automáticamente cada hora mediante el cron del plugin (`Cron.php`). En circunstancias normales esta carpeta debería estar casi siempre vacía, porque cada fichero temporal se borra justo después de enviarse a CUPS (o al fallar la validación); este cron es una red de seguridad para los casos en los que ese borrado inmediato no llega a producirse (por ejemplo, un proceso PHP interrumpido a mitad).
