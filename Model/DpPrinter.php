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
use FacturaScripts\Core\Where;

/**
 * A printer configured to receive direct print jobs through a CUPS queue.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class DpPrinter extends ModelClass
{
    use ModelTrait;

    /** Allowed paper orientations (whitelist). */
    public const ORIENTATIONS = ['portrait', 'landscape'];

    /** Allowed paper sizes (whitelist). */
    public const PAPER_SIZES = ['A4', 'A5', 'Letter', 'Legal'];

    /** @var bool */
    public $active;

    /** @var bool */
    public $bydefault;

    /** @var ?int */
    public ?int $copies;

    /** @var int */
    public $id;

    /** @var ?string */
    public ?string $name;

    /** @var ?string */
    public ?string $notes;

    /** @var ?string */
    public ?string $orientation;

    /** @var ?string */
    public ?string $paper;

    /**
     * Name of the CUPS queue used to print.
     *
     * @var ?string
     */
    public ?string $queue;

    /**
     * Set default values for new instances.
     *
     * @return void
     */
    public function clear(): void
    {
        parent::clear();
        $this->active = true;
        $this->bydefault = false;
        $this->copies = 1;
        $this->orientation = self::ORIENTATIONS[0];
        $this->paper = self::PAPER_SIZES[0];
    }

    /**
     * Returns the default printer, or null if none is set.
     *
     * @return ?static
     */
    public static function getDefault(): ?static
    {
        return static::findWhereEq('bydefault', true);
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
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'directprint_printers';
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
        $this->notes = Tools::noHtml($this->notes);
        $this->queue = Tools::noHtml(trim($this->queue ?? ''));

        if (empty($this->name)) {
            Tools::log()->warning('directprint-name-required');
            return false;
        }

        // the CUPS queue name must be a safe identifier
        if (1 !== preg_match('/^[A-Za-z0-9._-]+$/', $this->queue)) {
            Tools::log()->warning('directprint-invalid-queue', ['%queue%' => $this->queue]);
            return false;
        }

        // the visible name must be unique
        foreach (self::all([Where::eq('name', $this->name)]) as $printer) {
            if ($printer->id != $this->id) {
                Tools::log()->warning('directprint-duplicate-name', ['%name%' => $this->name]);
                return false;
            }
        }

        // enforce whitelists and sane defaults
        if (false === in_array($this->paper, self::PAPER_SIZES, true)) {
            $this->paper = 'A4';
        }
        if (false === in_array($this->orientation, self::ORIENTATIONS, true)) {
            $this->orientation = 'portrait';
        }
        if ($this->copies < 1) {
            $this->copies = 1;
        }

        return parent::test();
    }

    /**
     * Insert the model data in the database.
     *
     * @return bool
     */
    protected function saveInsert(): bool
    {
        if ($this->bydefault) {
            $this->clearDefaults();
        }

        return parent::saveInsert();
    }

    /**
     * Update the model in the database.
     *
     * @return bool
     */
    protected function saveUpdate(): bool
    {
        if ($this->bydefault) {
            $this->clearDefaults();
        }

        return parent::saveUpdate();
    }

    /**
     * Removes the default flag from every other printer.
     */
    private function clearDefaults(): void
    {
        foreach (self::all([Where::eq('bydefault', true)]) as $printer) {
            if ($printer->id == $this->id) {
                continue;
            }

            $printer->bydefault = false;
            $printer->save();
        }
    }
}
