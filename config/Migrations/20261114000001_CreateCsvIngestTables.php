<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Tables owned by the CsvIngest plugin. Keep tests/schema.php in sync
 * (enforced by PluginMigrationsTest).
 */
class CreateCsvIngestTables extends BaseMigration
{
    /**
     * @return void
     */
    public function change(): void
    {
        $this->table('csv_ingest_presets')
            ->addColumn('owner_id', 'string', ['limit' => 36, 'null' => true, 'default' => null])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('target', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('settings', 'text', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['target', 'owner_id'], ['name' => 'idx_csv_ingest_presets_target_owner'])
            ->create();

        $this->table('csv_ingest_value_maps')
            ->addColumn('target', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('field', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('source_value', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('mapped_value', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(
                ['target', 'field', 'source_value'],
                ['unique' => true, 'name' => 'uq_csv_ingest_value_maps_source'],
            )
            ->create();
    }
}
