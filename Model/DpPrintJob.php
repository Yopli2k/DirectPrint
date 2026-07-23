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
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\DpPrinter;

/**
 * A print job sent to a CUPS queue. It also acts as the result object
 * returned by the PrinterService.
 *
 * @author Jose Antonio Cuello Principal <yopli2000@gmail.com>
 */
class DpPrintJob extends ModelClass
{
    use ModelTrait;

    /** The job was cancelled before being sent. */
    public const STATUS_CANCELLED = 'cancelled';

    /** The job could not be sent. */
    public const STATUS_ERROR = 'error';

    /** The job is created but not sent yet. */
    public const STATUS_PENDING = 'pending';

    /** The job was accepted by CUPS. It does not mean it is physically printed. */
    public const STATUS_SENT = 'sent';

    /** @var ?string */
    public ?string $creation_date;

    /**
     * Job identifier returned by CUPS.
     *
     * @var ?string
     */
    public ?string $cups_job_id;

    /** @var ?string */
    public ?string $error;

    /** @var ?string */
    public ?string $filename;

    /** @var int */
    public $id;

    /** @var ?int */
    public $idprinter;

    /** @var ?string */
    public ?string $mimetype;

    /** User that requested the print. @var ?string */
    public ?string $nick;

    /**
     * Print options actually used, stored as JSON.
     *
     * @var ?string
     */
    public ?string $options;

    /** @var ?string */
    public ?string $source_id;

    /** @var ?string */
    public ?string $source_model;

    /** @var ?string */
    public ?string $source_plugin;

    /** @var ?string */
    public ?string $status;

    /**
     * Set default values for new instances.
     *
     * @return void
     */
    public function clear(): void
    {
        parent::clear();
        $this->creation_date = Tools::dateTime();
        $this->status = self::STATUS_PENDING;
    }

    /**
     * Marks the job as failed, saves it and logs the message.
     *
     * @param string $message translation key or raw error text
     * @return static
     */
    public function fail(string $message): static
    {
        $this->error = Tools::trans($message);
        $this->status = self::STATUS_ERROR;
        $this->save();

        Tools::log('DirectPrint')->warning($message);
        return $this;
    }

    /**
     * Returns the print options as an associative array.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return empty($this->options)
            ? []
            : (json_decode($this->options, true) ?? []);
    }

    /**
     * Returns the printer linked to this job, or null.
     *
     * @return ?DpPrinter
     */
    public function getPrinter(): ?DpPrinter
    {
        if (empty($this->idprinter)) {
            return null;
        }

        $printer = new DpPrinter();
        return $printer->load($this->idprinter) ? $printer : null;
    }

    /**
     * Marks the job as accepted by CUPS and saves it.
     *
     * @param mixed $cupsJobId
     * @return static
     */
    public function markSent($cupsJobId): static
    {
        $this->cups_job_id = $cupsJobId;
        $this->error = null;
        $this->status = self::STATUS_SENT;
        $this->save();
        return $this;
    }

    /**
     * Creates a new pending job with the context data (not saved).
     *
     * @param int $printerId
     * @param array $context filename, source_plugin, source_model, source_id
     * @return static
     */
    public static function newJob(int $printerId, array $context = []): static
    {
        $user = Session::user();

        $job = new static();
        $job->idprinter = $printerId;
        $job->nick = empty($user->nick) ? null : $user->nick;
        $job->filename = $context['filename'] ?? null;
        $job->source_plugin = $context['source_plugin'] ?? null;
        $job->source_model = $context['source_model'] ?? null;
        $job->source_id = isset($context['source_id']) ? (string)$context['source_id'] : null;
        return $job;
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
     * Stores the print options from an associative array.
     */
    public function setOptions(array $options): void
    {
        $this->options = empty($options) ? null : json_encode($options);
    }

    /**
     * Returns the name of the table that uses this model.
     *
     * @return string
     */
    public static function tableName(): string
    {
        return 'directprint_jobs';
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
        $this->cups_job_id = Tools::noHtml($this->cups_job_id);
        $this->error = Tools::noHtml($this->error);
        $this->filename = Tools::noHtml($this->filename);
        $this->mimetype = Tools::noHtml($this->mimetype);
        $this->source_id = Tools::noHtml($this->source_id);
        $this->source_model = Tools::noHtml($this->source_model);
        $this->source_plugin = Tools::noHtml($this->source_plugin);

        return parent::test();
    }

    /**
     * Returns the url where to see / modify the data.
     *
     * @param string $type
     * @param string $list
     * @return string
     */
    public function url(string $type = 'auto', string $list = 'ListDpPrinter?activetab=List'): string
    {
        return parent::url($type, $list);
    }
}
