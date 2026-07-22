# 53. Integrar un plugin con DirectPrint

Esta guía explica cómo un plugin propio puede imprimir a través de DirectPrint sin acoplarse a una impresora concreta, usando el sistema de **acciones de impresión** (ver [06. Acciones de impresión](06-rutas-de-impresion.md) para la parte de usuario).

La idea: tu plugin **registra una acción** con una clave semántica y luego imprime **por esa acción**. Es el administrador quien, desde DirectPrint, decide la impresora. Tu plugin nunca guarda ni pregunta por impresoras.

## Declarar DirectPrint como compatible, no requerido

DirectPrint es opcional para tu plugin. Por eso, en tu `facturascripts.ini`, decláralo como compatible en lugar de requerirlo:

```ini
compatible = 'DirectPrint'
```

Con `compatible` el plugin funciona aunque DirectPrint no esté instalado; simplemente no imprimirá directamente. Con `require` obligarías a tenerlo, que no es lo que queremos.

## Registrar las acciones en Init::update()

Registra cada acción una sola vez, de forma idempotente, en el `update()` de tu `Init.php`. El registro crea la fila si no existe y **nunca** pisa la impresora que haya elegido el administrador; como mucho refresca la descripción.

```php
public function update(): void
{
    if (false === Plugins::isEnabled('DirectPrint')) {
        return;
    }

    PrinterService::registerRoute('preparacion-albaran', 'Albarán al cerrar preparación');
}
```

## Imprimir por acción

Cuando llegue el momento de imprimir, hazlo siempre bajo la guarda `Plugins::isEnabled('DirectPrint')`, para que tu plugin no falle si DirectPrint no está.

Para documentos de compra/venta (factura, albarán, pedido, presupuesto), usa `printForAction()`, que resuelve la impresora de la acción y genera el PDF automáticamente:

```php
if (Plugins::isEnabled('DirectPrint')) {
    PrinterService::printForAction('preparacion-albaran', $albaran);
}
```

Si necesitas imprimir un fichero o texto (por ejemplo una etiqueta de transporte), resuelve tú la impresora con `printerIdForAction()` y pásasela a `printFile()` o `printText()`:

```php
$printerId = PrinterService::printerIdForAction('etiqueta-envio');
PrinterService::printFile($printerId, $rutaEtiqueta);
```

En ambos casos, si la acción no tiene impresora asignada (o no está registrada), `printerIdForAction()` devuelve `0`, que significa "impresora predeterminada". La degradación es automática.

## Importaciones

```php
use FacturaScripts\Core\Plugins;
use FacturaScripts\Plugins\DirectPrint\Lib\DirectPrint\PrinterService;
```

## Resumen del contrato

- `PrinterService::registerRoute(string $slug, string $name = ''): DpRoute` — alta idempotente de la acción; se llama en `Init::update()`.
- `PrinterService::printerIdForAction(string $slug): int` — id de la impresora asignada, o `0` para la predeterminada.
- `PrinterService::printForAction(string $slug, $doc, array $options = [], array $context = []): DpPrintJob` — imprime un documento resolviendo la impresora por la acción.
