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

/**
 * Controller to view the detail of a print job (read only).
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditDpPrintJob extends EditController
{
    public function getModelClassName(): string
    {
        return 'DpPrintJob';
    }

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'admin';
        $data['title'] = 'print-job';
        $data['icon'] = 'fa-solid fa-file-lines';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();

        // the history is read only
        $mvn = $this->getMainViewName();
        $this->setSettings($mvn, 'btnNew', false);
        $this->setSettings($mvn, 'btnSave', false);
        $this->setSettings($mvn, 'btnUndo', false);
    }
}
