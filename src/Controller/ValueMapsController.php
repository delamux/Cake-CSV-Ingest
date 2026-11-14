<?php
declare(strict_types=1);

namespace CsvIngest\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;
use CsvIngest\Import\ForeignKeyResolver;
use CsvIngest\Model\Behavior\IngestableBehavior;
use CsvIngest\Model\Table\IngestValueMapsTable;
use function Cake\I18n\__d;

/**
 * Admin screen for CSV value → foreign key translations: /csv-ingest/value-maps
 */
class ValueMapsController extends AppController
{
    /**
     * @return void
     */
    public function index(): void
    {
        $filters = [
            'target' => $this->queryString('target'),
            'field' => $this->queryString('field'),
            'q' => $this->queryString('q'),
            'pending' => $this->queryString('pending'),
        ];
        $pending = match ($filters['pending']) {
            '1' => true,
            '0' => false,
            default => null,
        };
        $query = $this->valueMaps()->find(
            'filtered',
            target: $filters['target'],
            field: $filters['field'],
            search: $filters['q'],
            pending: $pending,
        )->orderBy(['target' => 'ASC', 'field' => 'ASC', 'source_value' => 'ASC']);

        $fieldsByTarget = [];
        $pairs = $this->valueMaps()->find()
            ->select(['target', 'field'])
            ->distinct(['target', 'field'])
            ->orderBy(['target' => 'ASC', 'field' => 'ASC'])
            ->disableHydration();
        foreach ($pairs as $pair) {
            $fieldsByTarget[(string)$pair['target']][] = (string)$pair['field'];
        }

        $this->set([
            'valueMaps' => $this->paginate($query, ['limit' => 50]),
            'filters' => $filters,
            'targets' => $this->valueMaps()->targetOptions(),
            'fieldsByTarget' => $fieldsByTarget,
        ]);
    }

    /**
     * Choose which related record a CSV value means.
     *
     * @param string $id Value map id.
     * @return \Cake\Http\Response|null
     */
    public function edit(string $id): ?Response
    {
        $valueMap = $this->valueMaps()->get((int)$id);
        $target = $this->fetchTable($valueMap->target);
        $behavior = $target->hasBehavior('Ingestable') ? $target->getBehavior('Ingestable') : null;
        $field = $behavior instanceof IngestableBehavior ? $behavior->ingestField($valueMap->field) : null;
        if ($field === null || !$field->isForeignKey()) {
            throw new BadRequestException(
                __d('csv_ingest', 'This value map does not belong to an importable relation.'),
            );
        }

        if ($this->request->is(['post', 'put', 'patch'])) {
            $valueMap = $this->valueMaps()->patchEntity($valueMap, (array)$this->request->getData(), [
                'fields' => ['mapped_value'],
            ]);
            if ($this->valueMaps()->save($valueMap)) {
                $this->Flash->success(__d('csv_ingest', 'The value map has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__d('csv_ingest', 'The value map could not be saved.'));
        }

        $this->set([
            'valueMap' => $valueMap,
            'field' => $field,
            'options' => (new ForeignKeyResolver($target))->candidates($field),
        ]);

        return null;
    }

    /**
     * @param string $id Value map id.
     * @return \Cake\Http\Response|null
     */
    public function delete(string $id): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $valueMap = $this->valueMaps()->get((int)$id);
        if ($this->valueMaps()->delete($valueMap)) {
            $this->Flash->success(__d('csv_ingest', 'The value map has been deleted.'));
        } else {
            $this->Flash->error(__d('csv_ingest', 'The value map could not be deleted.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * @param string $key Query key.
     * @return string|null
     */
    private function queryString(string $key): ?string
    {
        $value = $this->request->getQuery($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return \CsvIngest\Model\Table\IngestValueMapsTable
     */
    private function valueMaps(): IngestValueMapsTable
    {
        $table = $this->fetchTable('CsvIngest.IngestValueMaps');
        assert($table instanceof IngestValueMapsTable);

        return $table;
    }
}
