<?php
declare(strict_types=1);

/**
 * Test bootstrap: standalone CakePHP environment for the CsvIngest plugin.
 *
 * DB: SQLite in memory by default; override with DB_URL (e.g. mysql://user:pass@127.0.0.1/test).
 */

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Fixture\SchemaLoader;
use Cake\Utility\Security;
use CsvIngest\CsvIngestPlugin;

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
define('ROOT', $root);
define('APP_DIR', 'src');
define('TEST_APP', ROOT . DS . 'tests' . DS . 'test_app' . DS);
define('APP', TEST_APP . 'src' . DS);
define('CONFIG', TEST_APP . 'config' . DS);
define('WWW_ROOT', TEST_APP . 'webroot' . DS);
define('TMP', sys_get_temp_dir() . DS . 'csv_ingest_tests' . DS);
define('LOGS', TMP . 'logs' . DS);
define('CACHE', TMP . 'cache' . DS);
define('CAKE_CORE_INCLUDE_PATH', ROOT . DS . 'vendor' . DS . 'cakephp' . DS . 'cakephp');
define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
define('CAKE', CORE_PATH . 'src' . DS);

foreach ([TMP, LOGS, CACHE] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

require_once CORE_PATH . 'config' . DS . 'bootstrap.php';

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

Configure::write('debug', true);
Configure::write('App', [
    'namespace' => 'TestApp',
    'encoding' => 'UTF-8',
    'defaultLocale' => 'en_US',
    'base' => false,
    'baseUrl' => false,
    'dir' => APP_DIR,
    'webroot' => 'webroot',
    'wwwRoot' => WWW_ROOT,
    'fullBaseUrl' => 'http://localhost',
    'imageBaseUrl' => 'img/',
    'jsBaseUrl' => 'js/',
    'cssBaseUrl' => 'css/',
    'paths' => [
        'plugins' => [],
        'templates' => [TEST_APP . 'templates' . DS],
        'locales' => [],
    ],
]);
Configure::write('Session', ['defaults' => 'php']);
Security::setSalt('csv-ingest-test-salt-not-secret-0123456789abcdef');

Cache::setConfig([
    '_cake_translations_' => ['engine' => 'Array'],
    '_cake_model_' => ['engine' => 'Array'],
]);

if (!getenv('DB_URL')) {
    putenv('DB_URL=sqlite:///:memory:');
}
ConnectionManager::setConfig('test', ['url' => getenv('DB_URL')]);
ConnectionManager::alias('test', 'default');

// Plugin controllers extend the host's App\Controller\AppController; the test host lives in TestApp.
class_alias(TestApp\Controller\AppController::class, 'App\Controller\AppController');

Configure::write('CsvIngest.storagePath', TMP . 'uploads');
$plugin = new CsvIngestPlugin();
Plugin::getCollection()->add($plugin);
$plugin->applyDefaults();

(new SchemaLoader())->loadInternalFile(__DIR__ . DS . 'schema.php');
