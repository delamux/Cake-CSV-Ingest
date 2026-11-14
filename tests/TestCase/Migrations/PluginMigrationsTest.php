<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Migrations;

use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Migrations\Migrations;

/**
 * Runs the plugin migrations on an empty database and checks the result
 * matches tests/schema.php (the schema every other test relies on).
 */
class PluginMigrationsTest extends TestCase
{
    private const CONNECTION = 'csv_ingest_migrations';

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = TMP . 'migrations_' . uniqid() . '.sqlite';
        ConnectionManager::setConfig(self::CONNECTION, [
            'className' => Connection::class,
            'driver' => Sqlite::class,
            'database' => $this->database,
        ]);
    }

    protected function tearDown(): void
    {
        ConnectionManager::get(self::CONNECTION)->getDriver()->disconnect();
        ConnectionManager::drop(self::CONNECTION);
        if (is_file($this->database)) {
            unlink($this->database);
        }
        parent::tearDown();
    }

    public function testMigrationsCreateTheSchemaUsedByTheTests(): void
    {
        $migrations = new Migrations(['connection' => self::CONNECTION, 'plugin' => 'CsvIngest']);
        $this->assertTrue($migrations->migrate());

        /** @var list<array{table: string, columns: array<string, array<string, mixed>>, constraints?: array<string, array<string, mixed>>}> $expected */
        $expected = require ROOT . DS . 'tests' . DS . 'schema.php';
        $collection = ConnectionManager::get(self::CONNECTION)->getSchemaCollection();

        foreach ($expected as $definition) {
            if (!str_starts_with($definition['table'], 'csv_ingest_')) {
                continue;
            }
            $actual = $collection->describe($definition['table']);
            $this->assertSame(
                array_keys($definition['columns']),
                $actual->columns(),
                "columns of {$definition['table']}",
            );
            foreach ($definition['columns'] as $name => $column) {
                $this->assertSame(
                    $column['null'] ?? true,
                    $actual->isNullable($name),
                    "nullability of {$definition['table']}.{$name}",
                );
                if (isset($column['length'])) {
                    $this->assertSame($column['length'], $actual->getColumn($name)['length'] ?? null);
                }
            }
            foreach ($definition['constraints'] ?? [] as $constraint) {
                if ($constraint['type'] !== 'unique') {
                    continue;
                }
                $found = false;
                foreach (array_merge($actual->constraints(), $actual->indexes()) as $name) {
                    $info = $actual->getConstraint($name) ?? $actual->getIndex($name);
                    $found = $found || ($info['columns'] ?? null) === $constraint['columns'];
                }
                $this->assertTrue($found, "unique index on {$definition['table']}");
            }
        }

        $this->assertTrue($migrations->rollback(['target' => '0']));
        $this->assertNotContains('csv_ingest_presets', $collection->listTables());
    }
}
