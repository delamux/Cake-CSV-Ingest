<?php
declare(strict_types=1);

namespace CsvIngest\Controller;

use Cake\Http\Response;
use CsvIngest\Controller\Component\CsvIngestComponent;
use LogicException;

/**
 * Adds the wizard actions to a host controller:
 * - `csvUpload` (GET/POST) — step 1
 * - `csvMapping` (GET/POST) — step 2
 *
 * The controller must load the component: `$this->loadComponent('CsvIngest.CsvIngest')`.
 *
 * @mixin \Cake\Controller\Controller
 */
trait CsvIngestActionsTrait
{
    /**
     * @return \Cake\Http\Response|null
     */
    public function csvUpload(): ?Response
    {
        return $this->csvIngestComponent()->upload();
    }

    /**
     * @return \Cake\Http\Response|null
     */
    public function csvMapping(): ?Response
    {
        return $this->csvIngestComponent()->mapping();
    }

    /**
     * @return \CsvIngest\Controller\Component\CsvIngestComponent
     */
    protected function csvIngestComponent(): CsvIngestComponent
    {
        $component = $this->components()->has('CsvIngest') ? $this->components()->get('CsvIngest') : null;
        if (!$component instanceof CsvIngestComponent) {
            throw new LogicException(sprintf(
                '%s must load the CsvIngest.CsvIngest component to use CsvIngestActionsTrait.',
                static::class,
            ));
        }

        return $component;
    }
}
