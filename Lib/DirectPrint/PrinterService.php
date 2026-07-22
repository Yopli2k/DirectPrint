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
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\Base\BusinessDocument;
use FacturaScripts\Dinamic\Model\DpPrinter;
use FacturaScripts\Dinamic\Model\DpPrintJob;
use FacturaScripts\Dinamic\Model\DpRoute;

/**
 * Public class other plugins depend on to print through DirectPrint.
 * It only exposes the API and delegates to the specialized classes:
 * DocumentPrinter (documents), FilePrinter (files and CUPS) and TempFile
 * (temporary files). Consumers should only reference this class.
 *
 * Usage:
 *   $printers = PrinterService::getAvailablePrinters();
 *   $job = PrinterService::printFile($printerId, $filePath, ['copies' => 1, 'media' => 'A4']);
 *   $job = PrinterService::printForAction('print-delivery-note', $document);
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PrinterService
{
    /** Allowed file extensions to print. */
    public const ALLOWED_EXTENSIONS = TempFile::ALLOWED_EXTENSIONS;

    /** Maximum file size allowed, in bytes (20 MB). */
    public const MAX_FILE_SIZE = TempFile::MAX_FILE_SIZE;

    /** Business document models that printDocumentById() is allowed to load by name. */
    public const PRINTABLE_DOCUMENTS = DocumentPrinter::PRINTABLE_DOCUMENTS;

    /** Hours a temporary file is kept before the cron removes it. */
    public const TEMP_RETENTION_HOURS = TempFile::TEMP_RETENTION_HOURS;

    /**
     * Removes temporary files older than the retention limit. Called from the cron.
     */
    public static function cleanTempFiles(): void
    {
        TempFile::clean();
    }

    /**
     * Returns the active printers configured in this installation.
     *
     * @return DpPrinter[]
     */
    public static function getAvailablePrinters(): array
    {
        return DpPrinter::all(
            [Where::eq('active', true)],
            ['name' => 'ASC']
        );
    }

    /**
     * Returns the printer queue names detected on the CUPS server.
     *
     * @return array
     */
    public static function getCupsQueues(): array
    {
        return Cups::queues();
    }

    /**
     * Returns the indicated printer, or the default one when id is 0.
     *
     * @param int $printerId
     * @return DpPrinter|null
     */
    public static function getPrinter(int $printerId = 0): ?DpPrinter
    {
        return DpPrinter::resolve($printerId);
    }

    /**
     * Sends binary contents to a printer.
     *
     * @param int $printerId printer id, or 0 to use the default printer
     * @param string $contents
     * @param string $extension
     * @param array $options
     * @param array $context
     * @return DpPrintJob
     * @throws Exception
     */
    public static function printContents(int $printerId, string $contents, string $extension = 'pdf', array $options = [], array $context = []): DpPrintJob
    {
        return FilePrinter::printContents($printerId, $contents, $extension, $options, $context);
    }

    /**
     * Prints an already loaded sales or purchase document (its PDF).
     *
     * @param int $printerId printer id, or 0 to use the default printer
     * @param BusinessDocument $doc a loaded business document (sales or purchase)
     * @param array $options
     * @param array $context
     * @return DpPrintJob
     * @throws Exception
     */
    public static function printDocument(int $printerId, $doc, array $options = [], array $context = []): DpPrintJob
    {
        return DocumentPrinter::printDocument($printerId, $doc, $options, $context);
    }

    /**
     * Loads a business document by model name and code, then prints it.
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
        return DocumentPrinter::printDocumentById($printerId, $modelName, $code, $options, $context);
    }

    /**
     * Resolves the printer assigned to a print action, or 0 (default printer).
     *
     * @param string $slug action slug registered with registerRoute()
     * @return int
     */
    public static function printerIdForAction(string $slug): int
    {
        return DpRoute::printerId($slug);
    }

    /**
     * Sends a file located inside the private MyFiles folder to a printer.
     *
     * @param int $printerId printer id, or 0 to use the default printer
     * @param string $filePath
     * @param array $options
     * @param array $context
     * @return DpPrintJob
     */
    public static function printFile(int $printerId, string $filePath, array $options = [], array $context = []): DpPrintJob
    {
        return FilePrinter::printFile($printerId, $filePath, $options, $context);
    }

    /**
     * Prints a document choosing the printer from its action (route slug).
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
        return DocumentPrinter::printForAction($slug, $doc, $options, $context);
    }

    /**
     * Prints a small test page to check the printer configuration.
     *
     * @param int $printerId
     * @return DpPrintJob
     * @throws Exception
     */
    public static function printTestPage(int $printerId): DpPrintJob
    {
        return FilePrinter::printTestPage($printerId);
    }

    /**
     * Sends plain text to a printer.
     *
     * @param int $printerId printer id, or 0 to use the default printer
     * @param string $text
     * @param array $options
     * @param array $context
     * @return DpPrintJob
     * @throws Exception
     */
    public static function printText(int $printerId, string $text, array $options = [], array $context = []): DpPrintJob
    {
        return FilePrinter::printText($printerId, $text, $options, $context);
    }

    /**
     * Registers a print action (route) so the admin can assign it a printer.
     * Idempotent: call it from the consumer plugin Init::update().
     *
     * @param string $slug unique action key (e.g. print-delivery-note)
     * @param string $name human description shown in the admin
     * @return DpRoute
     */
    public static function registerRoute(string $slug, string $name = ''): DpRoute
    {
        return DpRoute::register($slug, $name);
    }

    /**
     * Returns the private folder used to store temporary files.
     *
     * @return string
     */
    public static function tempFolder(): string
    {
        return TempFile::folder();
    }
}
