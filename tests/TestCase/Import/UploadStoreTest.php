<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Import;

use Cake\Utility\Filesystem;
use CsvIngest\Exception\UnreadableSourceException;
use CsvIngest\Import\UploadStore;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\TestCase;

class UploadStoreTest extends TestCase
{
    private string $dir;

    private UploadStore $store;

    protected function setUp(): void
    {
        $this->dir = TMP . 'upload_store_test' . DS;
        $this->store = new UploadStore($this->dir, ['UTF-8', 'Windows-1252'], 3600);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDir($this->dir);
    }

    public function testStoresUploadUnderOpaqueTokenWithRestrictivePermissions(): void
    {
        $upload = new UploadedFile(
            (new StreamFactory())->createStream("name;age\nAna;3\n"),
            15,
            UPLOAD_ERR_OK,
            '../../evil.csv',
        );

        $token = $this->store->store($upload);
        $path = $this->store->path($token);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $token);
        $this->assertSame($this->dir . $token . '.csv', $path);
        $this->assertSame("name;age\nAna;3\n", file_get_contents($path));
        $this->assertSame('0640', substr(sprintf('%o', fileperms($path)), -4));
    }

    public function testConvertsLegacyEncodingToUtf8AndStripsBom(): void
    {
        $latin = (string)mb_convert_encoding("nombre;año\nJosé;3\n", 'Windows-1252', 'UTF-8');
        $this->assertSame("nombre;año\nJosé;3\n", file_get_contents($this->store->path($this->store->storeContents($latin))));

        $bom = "\xEF\xBB\xBFa;b\n";
        $this->assertSame("a;b\n", file_get_contents($this->store->path($this->store->storeContents($bom))));
    }

    public function testRejectsTokensThatAreNotUuids(): void
    {
        $this->expectException(UnreadableSourceException::class);
        $this->store->path('../../etc/passwd');
    }

    public function testMissingFileThrowsAndDiscardIsSafe(): void
    {
        $token = $this->store->storeContents('a;b');
        $this->store->discard($token);
        $this->store->discard('not-a-token');

        $this->expectException(UnreadableSourceException::class);
        $this->store->path($token);
    }

    public function testPurgeExpiredRemovesOldFilesOnly(): void
    {
        $old = $this->store->storeContents('old');
        $fresh = $this->store->storeContents('fresh');
        touch($this->store->path($old), time() - 7200);

        $this->assertSame(1, $this->store->purgeExpired());
        $this->assertFileDoesNotExist($this->dir . $old . '.csv');
        $this->assertFileExists($this->store->path($fresh));
    }
}
