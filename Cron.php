<?php
/**
 * This file is part of DirectPrint plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DirectPrint    Copyright (C) 2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DirectPrint;

use FacturaScripts\Core\Template\CronClass;
use FacturaScripts\Plugins\DirectPrint\Lib\DirectPrint\PrinterService;

/**
 * Periodic tasks for the DirectPrint plugin.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class Cron extends CronClass
{
    private const JOB_NAME = 'directprint-clean-temp';

    private const JOB_PERIOD = '1 hour';

    public function run(): void
    {
        $this->job(self::JOB_NAME)
            ->every(self::JOB_PERIOD)
            ->run(function () {
                PrinterService::cleanTempFiles();
            });
    }
}
