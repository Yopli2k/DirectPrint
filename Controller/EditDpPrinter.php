<?php
/**
 * This file is part of DirectPrint plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DirectPrint    Copyright (C) 2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DirectPrint\Controller;

use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\DpPrinter;
use FacturaScripts\Dinamic\Model\DpPrintJob;
use FacturaScripts\Plugins\DirectPrint\Lib\DirectPrint\Cups;
use FacturaScripts\Plugins\DirectPrint\Lib\DirectPrint\PrinterService;

/**
 * Controller to edit a single DpPrinter.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditDpPrinter extends EditController
{
    public function getModelClassName(): string
    {
        return 'DpPrinter';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'printer';
        $data['icon'] = 'fa-solid fa-print';
        return $data;
    }

    protected function checkQueueAction(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $printer = new DpPrinter();
        if (false === $printer->load($this->request->input('code'))) {
            return;
        }

        if (Cups::queueExists($printer->queue)) {
            Tools::log()->notice('directprint-queue-found', ['%queue%' => $printer->queue]);
            return;
        }

        Tools::log()->warning('directprint-queue-not-found', ['%queue%' => $printer->queue]);
    }

    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');
    }

    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'check-queue':
                $this->checkQueueAction();
                break;

            case 'test-print':
                $this->testPrintAction();
                break;
        }

        return parent::execPreviousAction($action);
    }

    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        if ($viewName === $this->getMainViewName() && $view->model->exists()) {
            $view->addButton([
                'action' => 'check-queue',
                'color' => 'info',
                'icon' => 'fa-solid fa-plug',
                'label' => 'directprint-check-queue',
                'type' => 'action',
            ]);
            $view->addButton([
                'action' => 'test-print',
                'color' => 'warning',
                'icon' => 'fa-solid fa-print',
                'label' => 'directprint-test-print',
                'type' => 'action',
            ]);
        }
    }

    protected function testPrintAction(): void
    {
        if (false === $this->validateFormToken()) {
            return;
        }

        $printer = new DpPrinter();
        if (false === $printer->load($this->request->input('code'))) {
            return;
        }

        $job = PrinterService::printTestPage($printer->id);
        if ($job->status === DpPrintJob::STATUS_SENT) {
            Tools::log()->notice('directprint-test-sent', ['%job%' => $job->cups_job_id]);
            return;
        }

        Tools::log()->warning('directprint-test-error');
    }
}
