<?php
declare(strict_types=1);

namespace TestApp\Controller;

use Cake\Controller\Controller;

/**
 * Host base controller. The plugin's admin controllers extend it.
 */
class AppController extends Controller
{
    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Flash');
        $this->loadComponent('FormProtection');
    }
}
