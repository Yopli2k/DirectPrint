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

/**
 * Manages the temporary files used to print: staging inside a private
 * folder, validation, mime detection and cleanup.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class TempFile
{
    /** Allowed file extensions to print. */
    public const ALLOWED_EXTENSIONS = ['pdf', 'txt'];

    /** Maximum file size allowed, in bytes (20 MB). */
    public const MAX_FILE_SIZE = 20971520;

    /** Hours a temporary file is kept before the cron removes it. */
    public const TEMP_RETENTION_HOURS = 12;

    /**
     * Removes temporary files older than the retention limit.
     */
    public static function clean(): void
    {
        $folder = self::folder();
        $limit = time() - (self::TEMP_RETENTION_HOURS * 3600);

        foreach (Tools::folderScan($folder) as $file) {
            $path = $folder . DIRECTORY_SEPARATOR . $file;
            if (is_file($path) && filemtime($path) < $limit) {
                @unlink($path);
            }
        }
    }

    /**
     * Removes a temporary file, only if it is located inside our temp folder.
     *
     * @param string $filePath
     */
    public static function delete(string $filePath): void
    {
        $real = realpath($filePath);
        if ($real === false) {
            return;
        }

        $tmp = realpath(self::folder());
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
    public static function detectMime(string $filePath): string
    {
        $map = ['pdf' => 'application/pdf', 'txt' => 'text/plain'];
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return $map[$ext] ?? 'application/octet-stream';
    }

    /**
     * Returns the private folder used to store temporary files.
     *
     * @return string
     */
    public static function folder(): string
    {
        $folder = Tools::folder('MyFiles', 'DirectPrint');
        Tools::folderCheckOrCreate($folder);
        return $folder;
    }

    /**
     * Validates the file: it must exist, be inside MyFiles, have an allowed
     * extension and not exceed the size limit. Returns '' if valid, or the
     * translation key of the error.
     *
     * @param string $filePath
     * @return string
     */
    public static function validate(string $filePath): string
    {
        $real = realpath($filePath);
        if ($real === false || false === is_file($real)) {
            return 'file-not-found';
        }

        // never allow an arbitrary path: the file must live inside MyFiles
        $base = realpath(Tools::folder('MyFiles'));
        if ($base === false || false === str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            return 'file-outside-allowed';
        }

        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        if (false === in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return 'file-type-not-allowed';
        }

        if (filesize($real) > self::MAX_FILE_SIZE) {
            return 'file-too-big';
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
    public static function write(string $contents, string $extension): string
    {
        if (false === in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return '';
        }

        $folder = self::folder();
        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        $path = $folder . DIRECTORY_SEPARATOR . $name;

        return file_put_contents($path, $contents) === false ? '' : $path;
    }
}
