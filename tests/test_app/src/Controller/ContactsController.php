<?php
declare(strict_types=1);

namespace TestApp\Controller;

use CsvIngest\Controller\CsvIngestActionsTrait;

/**
 * Host controller exposing the import wizard: /contacts/csv-upload, /contacts/csv-mapping.
 */
class ContactsController extends AppController
{
    use CsvIngestActionsTrait;

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('CsvIngest.CsvIngest');
    }
}
