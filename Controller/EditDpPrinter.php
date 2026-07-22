<?php
/**
 * This file is part of DirectPrint plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DirectPrint    Copyright (C) 2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DirectPrint\Controller;

use Exception;
use FacturaScripts\Core\Lib\ExtendedController\BaseView;
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
    /**
     * Returns the class name of the model to use in the editView.
     */
    public function getModelClassName(): string
    {
        return 'DpPrinter';
    }

    /**
     * Return the basic data for page.
     *
     * @return array
     */
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'printer';
        $data['icon'] = 'fa-solid fa-print';
        return $data;
    }

    /**
     * Create the view to display.
     */
    protected function createViews()
    {
        parent::createViews();
        $this->setTabsPosition('bottom');
    }

    /**
     * Run the actions that alter data before reading it.
     *
     * @param string $action
     *
     * @return bool
     * @throws Exception
     */
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

    /**
     * Loads the data to display.
     *
     * @param string $viewName
     * @param BaseView $view
     */
    protected function loadData($viewName, $view)
    {
        parent::loadData($viewName, $view);

        if ($viewName === $this->mainTabName()) {
            $this->loadQueuesWidget($view);

            if ($view->model->exists()) {
                $this->addTestButtons();
            }
        }
    }

    /**
     * Add the test buttons to main view.
     *
     * @return void
     */
    private function addTestButtons(): void
    {
        $this->mainTab()
            ->addButton([
                'action' => 'check-queue',
                'color' => 'info',
                'icon' => 'fa-solid fa-plug',
                'label' => 'directprint-check-queue',
                'type' => 'action',
            ])
            ->addButton([
                'action' => 'test-print',
                'color' => 'warning',
                'icon' => 'fa-solid fa-print',
                'label' => 'directprint-test-print',
                'type' => 'action',
            ]);
    }

    /**
     * Process for test the CUPS queue.
     *
     * @return void
     */
    private function checkQueueAction(): void
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

    /**
     * Fills the queue option select with the available CUPS queues, when readable.
     *
     * @param BaseView $view
     * @return void
     */
    private function loadQueuesWidget($view): void
    {
        $queues = Cups::queues();
        if (empty($queues)) {
            return;
        }

        $column = $view->columnForField('queue');
        if ($column && $column->widget) {
            $column->widget->setValuesFromArray($queues);
        }
    }

    /**
     * Run a test print to selected printer.
     *
     * @return void
     * @throws Exception
     */
    private function testPrintAction(): void
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
            Tools::log()->notice('directprint-test-sent', ['%job%' => (int)$job->cups_job_id]);
            return;
        }

        Tools::log()->warning('directprint-test-error');
    }
}
