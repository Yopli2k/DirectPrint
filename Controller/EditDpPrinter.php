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
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Model\DpPrinter;
use FacturaScripts\Dinamic\Model\DpPrintJob;
use FacturaScripts\Dinamic\Model\DpRoute;
use FacturaScripts\Plugins\DirectPrint\Lib\DirectPrint\Cups;
use FacturaScripts\Plugins\DirectPrint\Lib\DirectPrint\PrinterService;

/**
 * Controller to edit a single DpPrinter.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditDpPrinter extends EditController
{
    public array $assignedRoutes = [];

    public array $unassignedRoutes = [];

    private const VIEW_NOTES = 'EditDpPrinterNote';

    private const VIEW_ROUTES = 'DpPrinterRoutes';

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
        $this->createViewsRoutes();
        $this->createViewsNotes();
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
            case 'assign-route':
                $this->assignRouteAction();
                break;

            case 'check-queue':
                $this->checkQueueAction();
                break;

            case 'test-print':
                $this->testPrintAction();
                break;

            case 'unassign-route':
                $this->unassignRouteAction();
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
        switch ($viewName) {
            case self::VIEW_NOTES:
                $view->model = $this->getModel();
                break;

            case self::VIEW_ROUTES:
                $id = (int)$this->getModel()->id();
                $this->assignedRoutes = empty($id)
                    ? []
                    : DpRoute::all([Where::eq('idprinter', $id)], ['slug' => 'ASC']);

                $this->unassignedRoutes = empty($id)
                    ? []
                    : DpRoute::all([Where::isNull('idprinter')], ['slug' => 'ASC']);
                break;

            default:
                parent::loadData($viewName, $view);
                if ($viewName === $this->mainTabName()) {
                    $this->loadQueuesWidget($view);

                    if ($view->model->exists()) {
                        $this->addTestButtons();
                    }
                }
                break;
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
                'label' => 'check-queue',
                'type' => 'action',
            ])
            ->addButton([
                'action' => 'test-print',
                'color' => 'warning',
                'icon' => 'fa-solid fa-print',
                'label' => 'test-print',
                'type' => 'action',
            ]);
    }

    /**
     * Assigns the selected print action to the current printer.
     *
     * @return void
     */
    private function assignRouteAction(): void
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return;
        }

        if (false === $this->validateFormToken()) {
            return;
        }

        $route = new DpRoute();
        if (false === $route->load($this->request->input('idroute'))) {
            return;
        }

        $route->idprinter = (int)$this->request->input('idprinter');
        if ($route->save()) {
            Tools::log()->notice('record-updated-correctly');
        }
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
            Tools::log()->notice('queue-found', ['%queue%' => $printer->queue]);
            return;
        }

        Tools::log()->warning('queue-not-found', ['%queue%' => $printer->queue]);
    }

    /**
     * Add view for edit the notes of a configured printer.
     *
     * @return void
     */
    private function createViewsNotes(): void
    {
        $this->addEditView(self::VIEW_NOTES, 'DpPrinter', 'notes', 'fa-solid fa-sticky-note');
    }

    /**
     * Add the tab with the print actions of this printer.
     *
     * @return void
     */
    private function createViewsRoutes(): void
    {
        $this->addHtmlView(
            self::VIEW_ROUTES,
            'Tab/DpPrinterRoutes',
            'DpRoute',
            'print-actions',
            'fa-solid fa-diagram-project'
        );
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
            Tools::log()->notice('test-sent', ['%job%' => (int)$job->cups_job_id]);
            return;
        }

        Tools::log()->warning('test-error');
    }

    /**
     * Removes the printer from the selected print action.
     *
     * @return void
     */
    private function unassignRouteAction(): void
    {
        if (false === $this->permissions->allowUpdate) {
            Tools::log()->warning('not-allowed-modify');
            return;
        }

        if (false === $this->validateFormToken()) {
            return;
        }

        $route = new DpRoute();
        if (false === $route->load($this->request->input('idroute'))) {
            return;
        }

        $route->idprinter = null;
        if ($route->save()) {
            Tools::log()->notice('record-updated-correctly');
        }
    }
}
