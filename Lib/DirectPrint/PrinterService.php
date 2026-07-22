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
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Dinamic\Model\DpPrinter;
use FacturaScripts\Dinamic\Model\DpPrintJob;

/**
 * Public class to send documents to a configured printer.
 * Other plugins should only depend on this class.
 *
 * Usage:
 *   $printers = PrinterService::getAvailablePrinters();
 *   $job = PrinterService::printFile($printerId, $filePath, ['copies' => 1, 'media' => 'A4']);
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class PrinterService
{
    /** Allowed file extensions to print. */
    public const ALLOWED_EXTENSIONS = ['pdf', 'txt'];

    /** Maximum file size allowed, in bytes (20 MB). */
    public const MAX_FILE_SIZE = 20971520;

    /** Business document models that printDocumentById() is allowed to load by name. */
    public const PRINTABLE_DOCUMENTS = [
        'AlbaranCliente', 'AlbaranProveedor',
        'FacturaCliente', 'FacturaProveedor',
        'PedidoCliente', 'PedidoProveedor',
        'PresupuestoCliente', 'PresupuestoProveedor',
    ];

    /** Hours a temporary file is kept before the cron removes it. */
    public const TEMP_RETENTION_HOURS = 12;

    /**
     * Removes temporary files older than the retention limit.
     * Called from the plugin cron.
     */
    public static function cleanTempFiles(): void
    {
        $folder = self::tempFolder();
        $limit = time() - (self::TEMP_RETENTION_HOURS * 3600);

        foreach (Tools::folderScan($folder) as $file) {
            $path = $folder . DIRECTORY_SEPARATOR . $file;
            if (is_file($path) && filemtime($path) < $limit) {
                @unlink($path);
            }
        }
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
     * Return the indicated printer or default.
     *
     * @param int $printerId
     * @return DpPrinter|null
     */
    public static function getPrinter(int $printerId = 0): ?DpPrinter
    {
        return $printerId > 0
            ? DpPrinter::find($printerId)
            : DpPrinter::getDefault();
    }

    /**
     * Sends binary contents to a printer, writing them first to a
     * controlled temporary file.
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
        $file = self::writeTemp($contents, strtolower($extension));
        if ($file === '') {
            return self::fail(self::newJob($printerId, $context), 'directprint-temp-write-error');
        }

        return self::printFile($printerId, $file, $options, $context);
    }

    /**
     * Prints an already loaded sales or purchase document (its PDF).
     * The document is rendered with the FacturaScripts export engine.
     *
     * @param int $printerId printer id, or 0 to use the default printer
     * @param BusinessDocument $doc a loaded business document (sales or purchase)
     * @param array $options
     * @param array $context format, source_plugin, filename...
     * @return DpPrintJob
     */
    public static function printDocument(int $printerId, $doc, array $options = [], array $context = []): DpPrintJob
    {
        // accept any sales or purchase document, without a strict type hint
        if (false === $doc instanceof BusinessDocument) {
            return self::fail(self::newJob($printerId, $context), 'directprint-document-not-printable');
        }

        // render the document PDF as a string
        $lang = $doc->getSubject()->langcode ?? '';
        $title = Tools::lang($lang)->trans($doc->modelClassName()) . ' ' . $doc->id();

        $exportManager = new ExportManager();
        $exportManager->newDoc('PDF', $title, (int)($context['format'] ?? 0), $lang);
        $exportManager->addBusinessDocPage($doc);

        $pdf = $exportManager->getDoc();
        if (empty($pdf)) {
            return self::fail(self::newJob($printerId, $context), 'directprint-document-pdf-error');
        }

        // fill the origin data automatically for the history
        $context['source_model'] = $context['source_model'] ?? $doc->modelClassName();
        $context['source_id'] = $context['source_id'] ?? (string)$doc->primaryColumnValue();
        if (empty($context['filename'])) {
            $context['filename'] = $title . '.pdf';
        }

        return self::printContents($printerId, $pdf, 'pdf', $options, $context);
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
     */
    public static function printDocumentById(int $printerId, string $modelName, $code, array $options = [], array $context = []): DpPrintJob
    {
        // never build an arbitrary class: only whitelisted documents can be loaded by name
        if (false === in_array($modelName, self::PRINTABLE_DOCUMENTS, true)) {
            return self::fail(self::newJob($printerId, $context), 'directprint-document-not-printable');
        }

        $class = '\\FacturaScripts\\Dinamic\\Model\\' . $modelName;
        $doc = new $class();
        if (false === $doc->load($code)) {
            return self::fail(self::newJob($printerId, $context), 'directprint-document-not-found');
        }

        return self::printDocument($printerId, $doc, $options, $context);
    }

    /**
     * Sends a file located inside the private MyFiles folder to a printer.
     * The file is removed after being sent to CUPS.
     *
     * @param int $printerId printer id, or 0 to use the default printer
     * @param string $filePath
     * @param array $options
     * @param array $context source_plugin, source_model, source_id, filename
     * @return DpPrintJob
     */
    public static function printFile(int $printerId, string $filePath, array $options = [], array $context = []): DpPrintJob
    {
        $printer = self::getPrinter($printerId);
        if (is_null($printer)) {
            self::deleteTemp($filePath);
            return self::fail(
                self::newJob($printerId, $context),
                'directprint-printer-not-found'
            );
        }

        $job = self::newJob($printer->id, $context);
        if (empty($job->filename)) {
            $job->filename = basename($filePath);
        }

        // the printer must be active
        if (false === (bool)$printer->active) {
            self::deleteTemp($filePath);
            return self::fail($job, 'directprint-printer-inactive');
        }

        // the queue name must be valid
        if (1 !== preg_match('/^[A-Za-z0-9._-]+$/', (string)$printer->queue)) {
            self::deleteTemp($filePath);
            return self::fail($job, 'directprint-invalid-queue');
        }

        // the file must be valid and inside the allowed location
        $error = self::validateFile($filePath);
        if ($error !== '') {
            self::deleteTemp($filePath);
            return self::fail($job, $error);
        }

        $job->mimetype = self::detectMime($filePath);

        // only whitelisted options reach the command
        $safeOptions = self::sanitizeOptions($options, $printer);
        $job->setOptions($safeOptions);
        $job->save();

        // send to CUPS
        $result = Cups::printFile($printer->queue, realpath($filePath), $safeOptions);
        self::deleteTemp($filePath);

        if ($result['code'] !== 0) {
            return self::fail($job, $result['error'] !== '' ? $result['error'] : 'directprint-cups-error');
        }

        $job->cups_job_id = $result['job_id'];
        $job->error = null;
        $job->status = DpPrintJob::STATUS_SENT;
        $job->save();

        return $job;
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
        $text = "DirectPrint - FacturaScripts\n"
            . Tools::trans('directprint-test-page') . "\n"
            . Tools::dateTime() . "\n";

        return self::printText($printerId, $text, [], [
            'filename' => 'directprint-test.txt',
            'source_plugin' => 'DirectPrint',
        ]);
    }

    /**
     * Sends plain text to a printer, writing it first to a controlled
     * temporary file.
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
        if (empty($context['filename'])) {
            $context['filename'] = 'text.txt';
        }

        return self::printContents($printerId, $text, 'txt', $options, $context);
    }

    /**
     * Returns the private folder used to store temporary files.
     *
     * @return string
     */
    public static function tempFolder(): string
    {
        $folder = Tools::folder('MyFiles', 'DirectPrint');
        Tools::folderCheckOrCreate($folder);
        return $folder;
    }

    /**
     * Removes a temporary file, only if it is located inside our temp folder.
     *
     * @param string $filePath
     */
    private static function deleteTemp(string $filePath): void
    {
        $real = realpath($filePath);
        if ($real === false) {
            return;
        }

        $tmp = realpath(self::tempFolder());
        if ($tmp !== false && str_starts_with($real, $tmp . DIRECTORY_SEPARATOR)) {
            @unlink($real);
        }
    }

    /**
     * Guesses the mime type from the file extension.
     *
     * @param string $filePath
     * @return string
     */
    private static function detectMime(string $filePath): string
    {
        $map = ['pdf' => 'application/pdf', 'txt' => 'text/plain'];
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return $map[$ext] ?? 'application/octet-stream';
    }

    /**
     * Marks the job as failed, logs the message and returns it.
     *
     * @param DpPrintJob $job
     * @param string $message
     * @return DpPrintJob
     */
    private static function fail(DpPrintJob $job, string $message): DpPrintJob
    {
        $job->error = Tools::trans($message);
        $job->status = DpPrintJob::STATUS_ERROR;
        $job->save();

        Tools::log('DirectPrint')->warning($message);
        return $job;
    }

    /**
     * Creates a new pending job with the context data.
     *
     * @param int $printerId
     * @param array $context
     * @return DpPrintJob
     */
    private static function newJob(int $printerId, array $context): DpPrintJob
    {
        $user = Session::user();

        $job = new DpPrintJob();
        $job->idprinter = $printerId;
        $job->nick = empty($user->nick) ? null : $user->nick;
        $job->filename = $context['filename'] ?? null;
        $job->source_plugin = $context['source_plugin'] ?? null;
        $job->source_model = $context['source_model'] ?? null;
        $job->source_id = isset($context['source_id']) ? (string)$context['source_id'] : null;
        return $job;
    }

    /**
     * Keeps only the whitelisted options, taking the printer defaults
     * when a value is missing or not allowed.
     *
     * @param array $options
     * @param DpPrinter $printer
     * @return array
     */
    private static function sanitizeOptions(array $options, DpPrinter $printer): array
    {
        $copies = isset($options['copies']) ? (int)$options['copies'] : (int)$printer->copies;
        if ($copies < 1) {
            $copies = 1;
        } elseif ($copies > 100) {
            $copies = 100;
        }

        $media = $options['media'] ?? $printer->paper;
        if (false === in_array($media, DpPrinter::PAPER_SIZES, true)) {
            $media = $printer->paper;
        }

        $orientation = $options['orientation'] ?? $printer->orientation;
        if (false === in_array($orientation, DpPrinter::ORIENTATIONS, true)) {
            $orientation = $printer->orientation;
        }

        return ['copies' => $copies, 'media' => $media, 'orientation' => $orientation];
    }

    /**
     * Validates the file: it must exist, be inside MyFiles, have an allowed
     * extension and not exceed the size limit. Returns '' if valid, or the
     * translation key of the error.
     *
     * @param string $filePath
     * @return string
     */
    private static function validateFile(string $filePath): string
    {
        $real = realpath($filePath);
        if ($real === false || false === is_file($real)) {
            return 'directprint-file-not-found';
        }

        // never allow an arbitrary path: the file must live inside MyFiles
        $base = realpath(Tools::folder('MyFiles'));
        if ($base === false || false === str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            return 'directprint-file-outside-allowed';
        }

        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        if (false === in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return 'directprint-file-type-not-allowed';
        }

        if (filesize($real) > self::MAX_FILE_SIZE) {
            return 'directprint-file-too-big';
        }

        return '';
    }

    /**
     * Writes contents to a temporary file with a secure random name.
     * Returns the full path, or '' on error.
     *
     * @param string $contents
     * @param string $extension
     * @return string
     * @throws Exception
     */
    private static function writeTemp(string $contents, string $extension): string
    {
        if (false === in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return '';
        }

        $folder = self::tempFolder();
        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        $path = $folder . DIRECTORY_SEPARATOR . $name;

        return file_put_contents($path, $contents) === false ? '' : $path;
    }
}
