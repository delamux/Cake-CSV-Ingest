<?php
declare(strict_types=1);

namespace CsvIngest;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;
use Cake\Utility\Hash;

/**
 * CsvIngest plugin: CSV import wizard for CakePHP tables.
 */
class CsvIngestPlugin extends BasePlugin
{
    /**
     * @var string|null
     */
    protected ?string $name = 'CsvIngest';

    /**
     * @param \Cake\Core\PluginApplicationInterface $app Application.
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        $this->applyDefaults();
    }

    /**
     * Merge `config/csv_ingest.php` defaults *under* values set by the application.
     *
     * @return void
     */
    public function applyDefaults(): void
    {
        /** @var array<string, mixed> $defaults */
        $defaults = require $this->getConfigPath() . 'csv_ingest.php';
        $current = Configure::read('CsvIngest');

        Configure::write('CsvIngest', Hash::merge($defaults, is_array($current) ? $current : []));
    }

    /**
     * Admin screens: /csv-ingest/presets and /csv-ingest/value-maps.
     *
     * @param \Cake\Routing\RouteBuilder $routes Builder.
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin('CsvIngest', ['path' => '/csv-ingest'], static function (RouteBuilder $builder): void {
            $builder->setExtensions(['json']);
            $builder->fallbacks(DashedRoute::class);
        });
    }
}
