# DirectPrint

Plugin para FacturaScripts que centraliza la **impresión directa mediante CUPS**. Permite
administrar una lista de impresoras y ofrece un servicio reutilizable para que otros plugins
envíen ficheros PDF o texto a una impresora sin abrir el PDF en el navegador.

El núcleo trabaja **solo con ficheros**: no contiene lógica para generar facturas, albaranes u
otros documentos, aunque si permite imprimir documentos ya creados. 
La creación de dichos documentos debe hacerse desde los plugins que lo necesiten.

## Requisitos del servidor

- Servidor Linux (Ubuntu / Debian / MacOS) con **CUPS** instalado.
- Comandos `lp` y `lpstat` accesibles en el `PATH` del usuario que ejecuta PHP (normalmente `www-data`).

## Configurar una impresora CUPS

1. Da de alta la cola en el sistema (interfaz de CUPS en `http://localhost:631` o `lpadmin`).
2. Comprueba que la cola existe:

   ```bash
   lpstat -a
   ```

3. En FacturaScripts, ve a **Admin → Impresoras**, crea una impresora e indica en el campo
   *Cola CUPS* el nombre exacto que aparece en `lpstat -a`.
4. Usa el botón **Comprobar cola** para validar que existe y **Imprimir prueba** para enviar una
   página de prueba.

## Comprobar que el usuario de PHP puede imprimir

Ejecuta estos comandos **como el usuario de PHP** (habitualmente `www-data`):

```bash
# listar colas disponibles
sudo -u www-data lpstat -a

# enviar un archivo de prueba a una cola concreta
sudo -u www-data lp -d NOMBRE_DE_LA_COLA /usr/share/cups/data/testprint
```

Si estos comandos funcionan como `www-data`, el plugin podrá imprimir.

## Usar el servicio desde otro plugin

El único punto de entrada es la clase `PrinterService`:

```php
use FacturaScripts\Plugins\DirectPrint\Lib\DirectPrint\PrinterService;

// 1) obtener las impresoras activas
$printers = PrinterService::getAvailablePrinters();
$printerId = $printers[0]->id;

// 2a) imprimir un fichero ya generado (debe estar dentro de MyFiles)
//     pide la carpeta temporal controlada, genera ahí tu PDF y pásalo al servicio
$folder = PrinterService::tempFolder();
$filePath = $folder . '/mi_documento.pdf';
file_put_contents($filePath, $pdfBinario);

$job = PrinterService::printFile($printerId, $filePath, [
    'copies' => 1,
    'media' => 'A4',
    'orientation' => 'portrait',
], [
    'source_plugin' => 'MiPlugin',
    'source_model' => 'FacturaCliente',
    'source_id' => $factura->idfactura,
    'filename' => 'Factura ' . $factura->codigo,
]);

// 2b) o imprimir contenido directamente, sin gestionar el fichero
$job = PrinterService::printContents($printerId, $pdfBinario, 'pdf');

// 2c) o imprimir texto plano
$job = PrinterService::printText($printerId, "Hola mundo\n");

// 2d) o imprimir un documento de compra/venta ya cargado (genera su PDF)
$job = PrinterService::printDocument($printerId, $factura);

// 2e) o imprimir un documento por modelo + código, sin cargarlo tú
$job = PrinterService::printDocumentById($printerId, 'FacturaCliente', $factura->idfactura, [], [
    'format' => 0, // opcional: id de FormatoDocumento (0 = formato por defecto)
]);

// 3) comprobar el resultado
if ($job->status === $job::STATUS_SENT) {
    // aceptado por CUPS (id del trabajo en $job->cups_job_id)
} else {
    // error en $job->error
}
```

### Notas importantes

- El servicio devuelve siempre un `DpPrintJob`, que además queda registrado en el historial.
- El estado `STATUS_SENT` significa **"Enviado a CUPS"**, no que el papel haya salido físicamente.
- `printDocument()` acepta cualquier documento de compra o venta ya cargado (factura, albarán,
  pedido o presupuesto, de cliente o proveedor). `printDocumentById()` solo carga por nombre los
  8 tipos del core (lista blanca `PRINTABLE_DOCUMENTS`); rellena `source_model` y `source_id` del
  trabajo automáticamente.
- `printFile()` solo acepta ficheros ubicados dentro de `MyFiles` (carpeta privada). Usa
  `PrinterService::tempFolder()` para obtener la ubicación recomendada.
- Solo se admiten las extensiones `pdf` y `txt`, con un tamaño máximo de 20 MB.
- Las opciones se filtran por una lista blanca: `copies`, `media` y `orientation`. Cualquier otra
  clave se ignora.
- Los ficheros temporales se borran tras enviarlos a CUPS; un cron limpia además los que hayan
  quedado por errores o interrupciones.

## Imprimir por acción (recomendado)

Para no tener que elegir una impresora concreta en el código de cada plugin, DirectPrint ofrece
un mapeo **acción → impresora**. Tu plugin registra una **acción** con una clave semántica y luego
imprime *por esa acción*; es el administrador quien, desde **Admin → Impresoras → Acciones de
impresión**, decide la impresora de cada acción. Si no asigna ninguna, se usa la impresora
predeterminada.

Declara DirectPrint como **compatible** (no requerido) en tu `facturascripts.ini`, para que tu
plugin funcione aunque DirectPrint no esté instalado:

```ini
compatible = 'DirectPrint'
```

Registra las acciones una sola vez en el `update()` de tu `Init.php` (es idempotente y nunca pisa
la impresora que haya elegido el administrador):

```php
use FacturaScripts\Core\Plugins;
use FacturaScripts\Plugins\DirectPrint\Lib\DirectPrint\PrinterService;

public function update(): void
{
    if (Plugins::isEnabled('DirectPrint')) {
        PrinterService::registerRoute('print-delivery-note', 'Albarán al cerrar preparación');
    }
}
```

Imprime siempre bajo la guarda `Plugins::isEnabled('DirectPrint')`. Para documentos de compra/venta
usa `printForAction()`, que resuelve la impresora y genera el PDF:

```php
if (Plugins::isEnabled('DirectPrint')) {
    PrinterService::printForAction('print-delivery-note', $albaran);
}
```

Si necesitas imprimir un fichero o texto (por ejemplo una etiqueta), resuelve tú la impresora con
`printerIdForAction()` (devuelve `0` = impresora predeterminada cuando la acción no tiene impresora
asignada) y pásasela a `printFile()` o `printText()`:

```php
$printerId = PrinterService::printerIdForAction('print-shipping-label');
PrinterService::printFile($printerId, $rutaEtiqueta);
```

Ver la guía de usuario en `Docs/06-rutas-de-impresion.md` y la técnica de integración en
`Docs/53-integrar-un-plugin.md`.
