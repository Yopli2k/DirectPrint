<?php
/**
 * This file is part of DirectPrint plugin for FacturaScripts.
 * FacturaScripts Copyright (C) 2015-2026 Carlos Garcia Gomez <carlos@facturascripts.com>
 * DirectPrint    Copyright (C) 2026 Jose Antonio Cuello Principal <yopli2000@gmail.com>
 *
 * This program and its files are under the terms of the license specified in the LICENSE file.
 */
namespace FacturaScripts\Plugins\DirectPrint\Model;

use Exception;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\DpPrinter;

/**
 * Maps a print action (slug registered by a plugin) to a printer.
 * A null printer means the default printer is used.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class DpRoute extends ModelClass
{
    use ModelTrait;

    /** @var int */
    public $id;

    /** @var ?int */
    public ?int $idprinter;

    /** @var ?string */
    public ?string $name;

    /**
     * Semantic key registered by the consumer plugin.
     *   (e.g. print-delivery-note).
     *
     * @var ?string
     */
    public ?string $slug;

    /**
     * This function is called when creating the model table. Returns the SQL
     * that will be executed after the creation of the table.
     *
     * @return string
     */
    public function install(): string
    {
        new DpPrinter();
        return parent::install();
    }

    /**
     * Returns the name of the column that is the model's primary key.
     *
     * @return string
     */
    public static function primaryColumn(): string
    {
        return 'id';
    }

    /**
     * Returns the printer id assigned to an action, or 0 (default printer)
     * when the route is missing or has no printer assigned.
     *
     * @param string $slug
     * @return int
     */
    public static function printerId(string $slug): int
    {
        $route = static::findWhereEq('slug', $slug);
        return $route && $route->idprinter ? (int)$route->idprinter : 0;
    }

    /**
     * Registers a print action (route) so the admin can assign it a printer.
     * Idempotent: it never overwrites the printer chosen by the admin, only
     * refreshes the label.
     *
     * @param string $slug unique action key (e.g. print-delivery-note)
     * @param string $name human description shown in the admin
     * @return static
     */
    public static function register(string $slug, string $name = ''): static
    {
        $route = static::findWhereEq('slug', $slug);
        if ($route) {
            if ($name !== '' && $name !== $route->name) {
                $route->name = $name;
                $route->save();
            }
            return $route;
        }

        $route = new static();
        $route->slug = $slug;
        $route->name = $name;
        $route->save();
        return $route;
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'directprint_routes';
    }

    /**
     * Returns true if there are no errors in the values of the model properties.
     * It runs inside the save method.
     *
     * @return bool
     * @throws Exception
     */
    public function test(): bool
    {
        $this->name = Tools::noHtml($this->name);
        $this->slug = Tools::noHtml(trim($this->slug ?? ''));

        if (empty($this->slug)) {
            Tools::log()->warning('print-action-required');
            return false;
        }

        if (empty($this->idprinter)) {
            $this->idprinter = null;
        }

        return parent::test();
    }

    /**
     * Returns the url where to see / modify the data.
     *
     * @param string $type
     * @param string $list
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'ListDpPrinter?activetab=ListDpRoute'): string
    {
        return parent::url($type, $list);
    }
}
