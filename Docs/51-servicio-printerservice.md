# 51. Servicio PrinterService

`PrinterService` (`Lib\DirectPrint\PrinterService`) es el único punto de entrada que debe usar otro plugin para imprimir. Todos sus métodos son estáticos y todos los que imprimen algo devuelven un `DpPrintJob`, que además queda registrado en el historial.

```php
use FacturaScripts\Plugins\DirectPrint\Lib\DirectPrint\PrinterService;
```

## Elegir la impresora

Casi todos los métodos reciben un `$printerId` como primer parámetro:

- Un id concreto de `DpPrinter` para usar esa impresora.
- `0` para usar la **impresora predeterminada** (la que tenga marcado el campo "Predeterminada" en su ficha). Si no hay ninguna marcada como predeterminada, o el id indicado no existe, el trabajo se registra en estado Error con el motivo correspondiente; no se lanza ninguna excepción.

## Imprimir un documento de compra/venta

Es el caso más habitual para otros plugins: imprimir una factura, albarán, pedido o presupuesto (de cliente o de proveedor) generando su PDF automáticamente con el motor de exportación de FacturaScripts.

```php
// si ya tienes el documento cargado
$job = PrinterService::printDocument($printerId, $factura);

// si solo tienes el nombre del modelo y el código
$job = PrinterService::printDocumentById($printerId, 'FacturaCliente', $factura->idfactura);
```

`printDocument()` acepta cualquier instancia que sea un documento de compra o venta (no exige un tipo estricto en la firma, precisamente para admitir instancias cargadas desde `Dinamic\Model`; internamente comprueba con `instanceof BusinessDocument`). `printDocumentById()` solo admite los ocho tipos de documento del core (constante `PrinterService::PRINTABLE_DOCUMENTS`); es un envoltorio que carga el documento por su nombre y código y delega en `printDocument()`.

Ambos rellenan automáticamente `source_model` y `source_id` en el contexto del trabajo, para que quede identificado en el historial de qué documento procede.

El formato de impresión (`FormatoDocumento`) se puede indicar opcionalmente en el contexto:

```php
$job = PrinterService::printDocument($printerId, $factura, [], [
    'format' => 3, // opcional: id de FormatoDocumento; 0 o ausente = formato por defecto
]);
```

## Imprimir un fichero ya generado

Para un fichero PDF o de texto que ya existe en disco, dentro de la carpeta privada del plugin:

```php
$folder = PrinterService::tempFolder();
$filePath = $folder . '/mi_documento.pdf';
file_put_contents($filePath, $pdfBinario);

$job = PrinterService::printFile($printerId, $filePath, [
    'copies' => 1,
    'media' => 'A4',
    'orientation' => 'portrait',
], [
    'source_plugin' => 'MiPlugin',
    'filename' => 'Factura ' . $factura->codigo,
]);
```

`printFile()` solo acepta ficheros ubicados dentro de `MyFiles` (ver [52. Seguridad y validaciones](52-seguridad-y-validaciones.md)); usa `PrinterService::tempFolder()` para obtener la ubicación recomendada. El fichero se borra automáticamente después de enviarlo a CUPS (o al fallar cualquier validación).

## Imprimir contenido binario o texto sin gestionar el fichero

Si no quieres ocuparte tú del fichero temporal:

```php
// contenido binario (por ejemplo, un PDF generado en memoria)
$job = PrinterService::printContents($printerId, $pdfBinario, 'pdf');

// texto plano
$job = PrinterService::printText($printerId, "Hola mundo\n");
```

Ambos escriben el contenido en la carpeta temporal del plugin con un nombre aleatorio seguro y delegan en `printFile()`.

## Comprobar el resultado

```php
if ($job->status === $job::STATUS_SENT) {
    // aceptado por CUPS; el id de CUPS está en $job->cups_job_id
} else {
    // error: el motivo (ya traducido) está en $job->error
}
```

`STATUS_SENT` significa que CUPS ha **aceptado** el trabajo, no que se haya impreso físicamente (ver la nota correspondiente en [04. Historial de trabajos](04-historial-de-trabajos.md)).

## Otras utilidades

- `PrinterService::getAvailablePrinters()`: array de impresoras activas (`DpPrinter[]`), ordenadas por nombre.
- `PrinterService::getPrinter($printerId = 0)`: devuelve la impresora indicada, o la predeterminada si se pasa `0` o se omite; `null` si no existe ninguna.
- `PrinterService::getCupsQueues()`: array con los nombres de las colas detectadas en el servidor CUPS (el mismo listado que alimenta las sugerencias del campo Cola CUPS en la ficha de impresora).
- `PrinterService::printTestPage($printerId)`: envía la misma página de prueba que usa el botón "Imprimir prueba" de la ficha de impresora.

## Contexto (`$context`)

El array `$context`, presente en la mayoría de los métodos, permite indicar datos adicionales que se guardan en el `DpPrintJob` para el historial:

- `filename`: nombre descriptivo del fichero (si no se indica, se calcula uno).
- `source_plugin`: nombre del plugin que solicita la impresión.
- `source_model` / `source_id`: modelo y código del documento de origen (se rellenan solos al usar `printDocument()` / `printDocumentById()`).
- `format`: solo para `printDocument()` / `printDocumentById()`, id del `FormatoDocumento` a usar.

## Opciones de impresión (`$options`)

El array `$options` solo admite tres claves; cualquier otra se ignora, y los valores no permitidos se sustituyen por el valor por defecto de la impresora:

- `copies`: número de copias (entre 1 y 100).
- `media`: tamaño de papel, debe ser uno de `DpPrinter::PAPER_SIZES`.
- `orientation`: `portrait` o `landscape`, debe ser uno de `DpPrinter::ORIENTATIONS`.
