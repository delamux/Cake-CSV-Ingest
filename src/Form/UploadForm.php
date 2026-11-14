<?php
declare(strict_types=1);

namespace CsvIngest\Form;

use Cake\Form\Form;
use Cake\Form\Schema;
use Cake\Validation\Validator;
use CsvIngest\Import\Delimiter;
use CsvIngest\Import\Enclosure;
use Psr\Http\Message\UploadedFileInterface;

use function Cake\I18n\__d;

/**
 * Step 1 of the wizard: the file and how to parse it.
 */
class UploadForm extends Form
{
    /**
     * @param int $maxFileSize Max upload size in bytes.
     */
    public function __construct(private readonly int $maxFileSize)
    {
        parent::__construct();
    }

    /**
     * @param \Cake\Form\Schema $schema Schema.
     * @return \Cake\Form\Schema
     */
    protected function _buildSchema(Schema $schema): Schema
    {
        return $schema
            ->addField('file', ['type' => 'file'])
            ->addField('delimiter', ['type' => 'string'])
            ->addField('enclosure', ['type' => 'string'])
            ->addField('has_header_row', ['type' => 'boolean'])
            ->addField('preset_id', ['type' => 'integer']);
    }

    /**
     * @param \Cake\Validation\Validator $validator Validator.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->requirePresence('file')
            ->add('file', 'uploaded', [
                'rule' => ['uploadedFile', ['optional' => false]],
                'message' => __d('csv_ingest', 'Choose a CSV file to upload.'),
                'last' => true,
            ])
            ->add('file', 'maxSize', [
                'rule' => fn(mixed $file): bool => $file instanceof UploadedFileInterface
                    && $file->getSize() !== null
                    && $file->getSize() <= $this->maxFileSize,
                'message' => __d(
                    'csv_ingest',
                    'The file is larger than the {0} MB limit.',
                    round($this->maxFileSize / 1048576, 1),
                ),
            ])
            ->add('file', 'extension', [
                'rule' => ['extension', ['csv', 'txt']],
                'message' => __d('csv_ingest', 'Only .csv and .txt files are accepted.'),
            ])
            ->requirePresence('delimiter')
            ->inList('delimiter', array_column(Delimiter::cases(), 'value'), __d('csv_ingest', 'Unknown delimiter.'))
            ->requirePresence('enclosure')
            ->inList('enclosure', array_column(Enclosure::cases(), 'value'), __d('csv_ingest', 'Unknown enclosure.'))
            ->boolean('has_header_row')
            ->allowEmptyString('has_header_row')
            ->naturalNumber('preset_id')
            ->allowEmptyString('preset_id');
    }

    /**
     * @return int
     */
    public function maxFileSize(): int
    {
        return $this->maxFileSize;
    }
}
