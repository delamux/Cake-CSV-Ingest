<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\I18n;

use Cake\I18n\I18n;
use Cake\I18n\Parser\PoFileParser;
use Cake\TestSuite\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function Cake\I18n\__d;

/**
 * Every message passed to __d('csv_ingest', ...) must be translated in es.
 */
class SpanishCatalogTest extends TestCase
{
    private const CATALOG = ROOT . DS . 'resources' . DS . 'locales' . DS . 'es' . DS . 'csv_ingest.po';

    protected function tearDown(): void
    {
        I18n::setLocale('en_US');
        parent::tearDown();
    }

    /**
     * @return list<string>
     */
    public static function sourceMessages(): array
    {
        $pattern = '/__d\(\s*\'csv_ingest\'\s*,\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/s';
        $messages = [];
        foreach (['src', 'templates'] as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT . DS . $dir));
            foreach ($files as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                preg_match_all($pattern, (string)file_get_contents($file->getPathname()), $matches);
                foreach ($matches[1] as $literal) {
                    $messages[] = self::decodeLiteral($literal);
                }
            }
        }
        $messages = array_values(array_unique($messages));
        sort($messages);

        return $messages;
    }

    /**
     * Decode a PHP string literal without eval().
     */
    private static function decodeLiteral(string $literal): string
    {
        $body = substr($literal, 1, -1);
        if ($literal[0] === "'") {
            return strtr($body, ['\\\\' => '\\', "\\'" => "'"]);
        }

        return stripcslashes($body);
    }

    public function testEveryMessageHasASpanishTranslation(): void
    {
        $this->assertFileExists(self::CATALOG);
        $catalog = (new PoFileParser())->parse(self::CATALOG);
        $messages = self::sourceMessages();

        $missing = [];
        foreach ($messages as $message) {
            $translation = $catalog[$message]['_context'][''] ?? '';
            if (!is_string($translation) || $translation === '') {
                $missing[] = $message;
            }
        }

        $this->assertGreaterThan(80, count($messages), 'extractor sanity check');
        $this->assertSame([], $missing, "Untranslated messages:\n" . implode("\n", $missing));
    }

    public function testCatalogIsLoadedFromThePluginForSpanish(): void
    {
        I18n::setLocale('es');

        $this->assertSame('Creados', __d('csv_ingest', 'Created'));
        $this->assertSame(
            'Asigna estos campos obligatorios antes de importar: Email',
            __d('csv_ingest', 'Map these required fields before importing: {0}', 'Email'),
        );
    }
}
