<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase;

use Cake\Core\Configure;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use CsvIngest\CsvIngestPlugin;

class CsvIngestPluginTest extends TestCase
{
    public function testApplyDefaultsKeepsApplicationValues(): void
    {
        Configure::write('CsvIngest.maxRows', 50);
        Configure::delete('CsvIngest.previewRows');

        (new CsvIngestPlugin())->applyDefaults();

        $this->assertSame(50, Configure::read('CsvIngest.maxRows'));
        $this->assertSame(5, Configure::read('CsvIngest.previewRows'));
        $this->assertTrue(Configure::read('CsvIngest.booleanLiterals.yes'));
        $this->assertFalse(Configure::read('CsvIngest.booleanLiterals.no'));
    }

    public function testRoutesAreMountedUnderCsvIngestPath(): void
    {
        $builder = Router::createRouteBuilder('/');
        (new CsvIngestPlugin())->routes($builder);

        $this->assertSame(
            '/csv-ingest/presets',
            Router::url(['plugin' => 'CsvIngest', 'controller' => 'Presets', 'action' => 'index']),
        );
        $this->assertSame(
            '/csv-ingest/value-maps/edit/3',
            Router::url(['plugin' => 'CsvIngest', 'controller' => 'ValueMaps', 'action' => 'edit', 3]),
        );
    }
}
