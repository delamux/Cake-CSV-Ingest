<?php
declare(strict_types=1);

namespace CsvIngest\Test\TestCase\Form;

use Cake\TestSuite\TestCase;
use CsvIngest\Form\UploadForm;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFile;

class UploadFormTest extends TestCase
{
    private function upload(string $name = 'people.csv', string $contents = "a;b\n1;2\n", int $error = UPLOAD_ERR_OK): UploadedFile
    {
        return new UploadedFile((new StreamFactory())->createStream($contents), strlen($contents), $error, $name);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function data(array $overrides = []): array
    {
        return $overrides + [
            'file' => $this->upload(),
            'delimiter' => 'semicolon',
            'enclosure' => 'double',
            'has_header_row' => '1',
            'preset_id' => '',
        ];
    }

    public function testAcceptsAValidCsvUpload(): void
    {
        $form = new UploadForm(1024);

        $this->assertTrue($form->validate($this->data()), json_encode($form->getErrors()) ?: '');
        $this->assertTrue($form->validate($this->data(['file' => $this->upload('PEOPLE.TXT')])));
    }

    public function testRejectsOtherExtensions(): void
    {
        $form = new UploadForm(1024);

        $this->assertFalse($form->validate($this->data(['file' => $this->upload('people.xlsx')])));
        $this->assertArrayHasKey('extension', $form->getErrors()['file']);
    }

    public function testRejectsMissingOrFailedUploads(): void
    {
        $form = new UploadForm(1024);

        $this->assertFalse($form->validate($this->data(['file' => $this->upload('', '', UPLOAD_ERR_NO_FILE)])));
        $this->assertArrayHasKey('file', $form->getErrors());
        $this->assertFalse($form->validate($this->data(['file' => 'not-a-file'])));
    }

    public function testRejectsFilesAboveTheSizeLimit(): void
    {
        $form = new UploadForm(5);

        $this->assertFalse($form->validate($this->data()));
        $this->assertArrayHasKey('maxSize', $form->getErrors()['file']);
    }

    public function testRejectsUnknownDialectValues(): void
    {
        $form = new UploadForm(1024);

        $this->assertFalse($form->validate($this->data(['delimiter' => ';', 'enclosure' => '`', 'preset_id' => 'x'])));
        $this->assertSame(['delimiter', 'enclosure', 'preset_id'], array_keys($form->getErrors()));
    }
}
