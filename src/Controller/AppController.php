<?php
declare(strict_types=1);

namespace CsvIngest\Controller;

use App\Controller\AppController as HostAppController;

/**
 * Base for the plugin's admin controllers. Extends the host application's
 * AppController so authentication, authorization and layout apply as usual.
 */
class AppController extends HostAppController
{
    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        if (!$this->components()->has('Flash')) {
            $this->loadComponent('Flash');
        }
    }
}
