# 52. Seguridad y validaciones

DirectPrint ejecuta comandos del sistema operativo (`lp`, `lpstat`) a partir de datos que, en última instancia, pueden venir de una petición HTTP o de otro plugin. El diseño parte de no confiar en ningún dato de entrada y de reducir al mínimo lo que se guarda y lo que se ejecuta.

## Nada de shell, nada de comandos guardados

`Cups` (ver [50. Arquitectura](50-arquitectura.md)) ejecuta los comandos con `proc_open()` pasando el comando como **array de argumentos**, nunca como una cadena de texto. En este modo no se invoca ninguna shell, así que no existe la posibilidad de que un valor (nombre de cola, opción, ruta de fichero) se interprete como código de shell o encadene comandos adicionales.

En la base de datos **no se guarda ningún script, comando ni argumento libre**: solo se guardan datos estructurados (nombre de impresora, cola, opciones ya validadas, etc.), nunca la línea de comando que se va a ejecutar.

## Ninguna ruta de servidor llega directamente desde una petición

Ni la pantalla de administración ni el servicio `PrinterService` aceptan una ruta de fichero arbitraria escrita por el usuario o enviada en una petición HTTP. `printFile()` exige que el fichero exista **físicamente dentro de la carpeta privada del plugin** (`MyFiles/DirectPrint`, obtenida con `PrinterService::tempFolder()`): la ruta se resuelve con `realpath()` y se comprueba que empieza por la ruta real de esa carpeta antes de aceptarla. Cualquier otra ubicación se rechaza.

## Lista blanca de opciones de impresión

Las opciones que llegan hasta el comando `lp` están limitadas a tres claves: `copies`, `media` y `orientation`. Cualquier otra clave del array `$options` se ignora sin más. Además:

- `copies` se fuerza a un entero entre 1 y 100.
- `media` debe ser uno de los valores de `DpPrinter::PAPER_SIZES`; si no lo es, se usa el tamaño de papel configurado en la impresora.
- `orientation` debe ser uno de los valores de `DpPrinter::ORIENTATIONS`; si no lo es, se usa la orientación configurada en la impresora.

No es posible, por tanto, inyectar una opción de `lp` distinta de estas tres a través de la API del servicio.

## Validaciones antes de imprimir

Antes de enviar nada a CUPS, `printFile()` comprueba, en este orden:

1. Que la impresora indicada existe y está activa.
2. Que el nombre de la cola CUPS es un identificador seguro (expresión regular `^[A-Za-z0-9._-]+$`, sin espacios ni caracteres especiales).
3. Que el fichero existe, está dentro de la carpeta temporal del plugin, tiene una extensión permitida y no supera el tamaño máximo.

Si cualquiera de estas comprobaciones falla, el trabajo se registra en estado Error con el motivo correspondiente y el fichero temporal implicado se borra; no se llega a invocar ningún comando del sistema.

## Tipos y tamaño de fichero permitidos

Solo se admiten las extensiones definidas en `PrinterService::ALLOWED_EXTENSIONS` (`pdf` y `txt`), con un tamaño máximo de `PrinterService::MAX_FILE_SIZE` (20 MB). Estas dos comprobaciones se hacen sobre el fichero real en disco (extensión y `filesize()`), no sobre datos declarados por quien pide la impresión.

## Ficheros temporales

Los ficheros temporales se escriben con un nombre aleatorio impredecible (`bin2hex(random_bytes(16))`), nunca a partir del nombre original enviado por el navegador o por otro plugin. Se eliminan justo después de enviarse a CUPS (o al fallar cualquier validación), y el cron de limpieza (ver [50. Arquitectura](50-arquitectura.md)) borra cualquiera que quede huérfano pasadas `PrinterService::TEMP_RETENTION_HOURS` horas.

`deleteTemp()` solo borra ficheros que estén realmente dentro de la carpeta temporal del plugin: aunque se le pasara por error la ruta de un fichero ajeno, nunca lo eliminaría.

## Modelo `DpPrinter`: mismas validaciones al guardar

El modelo `DpPrinter` aplica sus propias comprobaciones en el método `test()`, independientemente de que el servicio de impresión las repita después: nombre obligatorio y único, cola con formato de identificador seguro, y tamaño de papel / orientación forzados a la lista blanca correspondiente si llegara un valor no permitido.
