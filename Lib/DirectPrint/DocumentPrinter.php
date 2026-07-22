<?php
/**
 * This file is part of DirectPrint plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DirectPrint    Copyright (C) 2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DirectPrint\Lib\DirectPrint;

use Exception;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Model\DpPrintJob;
use FacturaScripts\Dinamic\Model\DpRoute;

/**
 * Prints sales and purchase documents, rendering their PDF with the
 * FacturaScripts export engine and delegating to send to FilePrinter.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class DocumentPrinter
{
    /** Business document models that printDocumentById() is allowed to load by name. */
    public const PRINTABLE_DOCUMENTS = [
        'AlbaranCliente', 'AlbaranProveedor',
        'FacturaCliente', 'FacturaProveedor',
        'PedidoCliente', 'PedidoProveedor',
        'PresupuestoCliente', 'PresupuestoProveedor',
    ];

    /**
     * Prints an already loaded sales or purchase document (its PDF).
     * The document is rendered with the FacturaScripts export engine.
     *
     * @param int $printerId printer id, or 0 to use the default printer
     * @param BusinessDocument $doc a loaded business document (sales or purchase)
     * @param array $options
     * @param array $context format, source_plugin, filename...
     * @return DpPrintJob
     * @throws Exception
     */
    public static function printDocument(int $printerId, $doc, array $options = [], array $context = []): DpPrintJob
    {
        // accept any sales or purchase document, without a strict type hint
        if (false === $doc instanceof BusinessDocument) {
            return DpPrintJob::create($printerId, $context)->fail('document-not-printable');
        }

        // render the document PDF as a string
        $lang = $doc->getSubject()->langcode ?? '';
        $title = Tools::lang($lang)->trans($doc->modelClassName()) . ' ' . $doc->id();

        $exportManager = new ExportManager();
        $exportManager->newDoc('PDF', $title, (int)($context['format'] ?? 0), $lang);
        $exportManager->addBusinessDocPage($doc);

        $pdf = $exportManager->getDoc();
        if (empty($pdf)) {
            return DpPrintJob::create($printerId, $context)->fail('document-pdf-error');
        }

        // fill the origin data automatically for the history
        $context['source_model'] = $context['source_model'] ?? $doc->modelClassName();
        $context['source_id'] = $context['source_id'] ?? (string)$doc->id();
        if (empty($context['filename'])) {
            $context['filename'] = $title . '.pdf';
        }

        return FilePrinter::printContents($printerId, $pdf, 'pdf', $options, $context);
    }

    /**
     * Loads a business document by model name and code, then prints it.
     * Only the core sales and purchase documents are allowed.
     *
     * @param int $printerId printer id, or 0 to use the default printer
     * @param string $modelName e.g. 'FacturaCliente', 'AlbaranProveedor'
     * @param mixed $code document primary key
     * @param array $options
     * @param array $context
     * @return DpPrintJob
     * @throws Exception
     */
    public static function printDocumentById(int $printerId, string $modelName, $code, array $options = [], array $context = []): DpPrintJob
    {
        // never build an arbitrary class: only whitelisted documents can be loaded by name
        if (false === in_array($modelName, self::PRINTABLE_DOCUMENTS, true)) {
            return DpPrintJob::create($printerId, $context)->fail('document-not-printable');
        }

        $class = '\\FacturaScripts\\Dinamic\\Model\\' . $modelName;
        $doc = new $class();
        if (false === $doc->load($code)) {
            return DpPrintJob::create($printerId, $context)->fail('document-not-found');
        }

        return self::printDocument($printerId, $doc, $options, $context);
    }

    /**
     * Prints a document choosing the printer from its action (route slug).
     * Convenience wrapper: resolves the route and delegates to printDocument().
     *
     * @param string $slug action slug registered with registerRoute()
     * @param BusinessDocument $doc a loaded business document (sales or purchase)
     * @param array $options
     * @param array $context
     * @return DpPrintJob
     * @throws Exception
     */
    public static function printForAction(string $slug, $doc, array $options = [], array $context = []): DpPrintJob
    {
        return self::printDocument(DpRoute::printerId($slug), $doc, $options, $context);
    }
}
