<?php
/**
 * This file is part of DirectPrint plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DirectPrint    Copyright (C) 2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DirectPrint\Controller;

use FacturaScripts\Core\Lib\ExtendedController\ListController;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\DpPrintJob;

/**
 * Controller to list printers and the print jobs history.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class ListDpPrinter extends ListController
{
    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'printers';
        $data['icon'] = 'fa-solid fa-print';
        return $data;
    }

    protected function createViews()
    {
        $this->createViewsPrinters();
        $this->createViewsRoutes();
        $this->createViewsJobs();
    }

    protected function createViewsJobs(string $viewName = 'ListDpPrintJob'): void
    {
        $printers = $this->codeModel->all('directprint_printers', 'id', 'name');

        $this->addView($viewName, 'DpPrintJob', 'history', 'fa-solid fa-history')
            ->addSearchFields(['filename', 'cups_job_id', 'error'])
            ->addOrderBy(['creation_date', 'id'], 'date', 2)
            ->addOrderBy(['id'], 'id')
            ->setSettings('btnNew', false)
            ->addFilterSelect('idprinter', 'printer', 'idprinter', $printers)
            ->addFilterSelect('status', 'status', 'status', [
                '' => '------',
                DpPrintJob::STATUS_PENDING => Tools::trans('pending'),
                DpPrintJob::STATUS_SENT => Tools::trans('sent'),
                DpPrintJob::STATUS_ERROR => Tools::trans('common-error'),
                DpPrintJob::STATUS_CANCELLED => Tools::trans('cancelled'),
            ])
            ->addFilterAutocomplete('nick', 'user', 'nick', 'users')
            ->addFilterPeriod('creation_date', 'period', 'creation_date', true);
    }

    protected function createViewsPrinters(string $viewName = 'ListDpPrinter'): void
    {
        $this->addView($viewName, 'DpPrinter', 'printers', 'fa-solid fa-print')
            ->addSearchFields(['name', 'queue', 'notes'])
            ->addOrderBy(['name'], 'name', 1)
            ->addOrderBy(['queue'], 'queue')
            ->addFilterCheckbox('active', 'active', 'active')
            ->addFilterCheckbox('bydefault', 'default', 'bydefault');
    }

    protected function createViewsRoutes(string $viewName = 'ListDpRoute'): void
    {
        $printers = $this->codeModel->all('directprint_printers', 'id', 'name');

        $this->addView($viewName, 'DpRoute', 'routes', 'fa-solid fa-diagram-project')
            ->addSearchFields(['slug', 'name'])
            ->addOrderBy(['slug'], 'action', 1)
            ->addOrderBy(['name'], 'name')
            ->setSettings('btnNew', false)
            ->addFilterSelect('idprinter', 'printer', 'idprinter', $printers);
    }
}
