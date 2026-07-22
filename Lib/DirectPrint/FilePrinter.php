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
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\DpPrinter;
use FacturaScripts\Dinamic\Model\DpPrintJob;

/**
 * Prints files, binary contents and plain text to a CUPS queue.
 * This is the low-level layer that actually talks to CUPS.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class FilePrinter
{
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
        $file = TempFile::write($contents, strtolower($extension));
        if ($file === '') {
            return DpPrintJob::create($printerId, $context)->fail('temp-write-error');
        }

        return self::printFile($printerId, $file, $options, $context);
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
        $printer = DpPrinter::resolve($printerId);
        if (is_null($printer)) {
            TempFile::delete($filePath);
            return DpPrintJob::create($printerId, $context)->fail('printer-not-found');
        }

        $job = DpPrintJob::create($printer->id, $context);
        if (empty($job->filename)) {
            $job->filename = basename($filePath);
        }

        // the printer must be active
        if (false === (bool)$printer->active) {
            TempFile::delete($filePath);
            return $job->fail('printer-inactive');
        }

        // the queue name must be valid
        if (1 !== preg_match('/^[A-Za-z0-9._-]+$/', (string)$printer->queue)) {
            TempFile::delete($filePath);
            return $job->fail('invalid-queue');
        }

        // the file must be valid and inside the allowed location
        $error = TempFile::validate($filePath);
        if ($error !== '') {
            TempFile::delete($filePath);
            return $job->fail($error);
        }

        $job->mimetype = TempFile::detectMime($filePath);

        // only whitelisted options reach the command
        $safeOptions = self::sanitizeOptions($options, $printer);
        $job->setOptions($safeOptions);
        $job->save();

        // send to CUPS
        $result = Cups::printFile($printer->queue, realpath($filePath), $safeOptions);
        TempFile::delete($filePath);

        if ($result['code'] !== 0) {
            return $job->fail($result['error'] !== '' ? $result['error'] : 'cups-error');
        }

        return $job->markSent($result['job_id']);
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
            . Tools::trans('test-page') . "\n"
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
}
