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
 * Controller to assign a printer to a single print route.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class EditDpRoute extends EditController
{
    /**
     * Returns the class name of the model to use in the editView.
     */
    public function getModelClassName(): string
    {
        return 'DpRoute';
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
        $data['title'] = 'print-action';
        $data['icon'] = 'fa-solid fa-diagram-project';
        return $data;
    }
}
