<?php
declare(strict_types=1);

namespace CsvIngest\Controller;

use Cake\Http\Response;
use CsvIngest\Model\Table\IngestPresetsTable;
use CsvIngest\OwnerIdentity;

use function Cake\I18n\__d;

/**
 * Admin screen for saved presets: /csv-ingest/presets
 */
class PresetsController extends AppController
{
    /**
     * @return void
     */
    public function index(): void
    {
        $target = $this->request->getQuery('target');
        $target = is_string($target) ? $target : null;
        $query = $this->presets()->find('visible', target: $target, ownerId: $this->ownerId());

        $this->set([
            'presets' => $this->paginate($query, ['limit' => 50]),
            'target' => $target,
            'ownerId' => $this->ownerId(),
        ]);
    }

    /**
     * Delete a preset that is shared or owned by the current user.
     *
     * @param string $id Preset id.
     * @return \Cake\Http\Response|null
     */
    public function delete(string $id): ?Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $preset = $this->presets()->find('visible', ownerId: $this->ownerId())
            ->where([$this->presets()->aliasField('id') => (int)$id])
            ->firstOrFail();

        if ($this->presets()->delete($preset)) {
            $this->Flash->success(__d('csv_ingest', 'The preset has been deleted.'));
        } else {
            $this->Flash->error(__d('csv_ingest', 'The preset could not be deleted.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * GET /csv-ingest/presets/availability.json?target=X&name=Y → {"available": bool}
     *
     * "Not available" means saving will update the user's existing preset.
     *
     * @return \Cake\Http\Response
     */
    public function availability(): Response
    {
        $this->request->allowMethod(['get']);
        $target = (string)$this->request->getQuery('target', '');
        $name = trim((string)$this->request->getQuery('name', ''));
        $available = $name === '' || $this->presets()->findOwned($target, $name, $this->ownerId()) === null;

        return $this->response
            ->withType('application/json')
            ->withStringBody((string)json_encode(['available' => $available]));
    }

    /**
     * @return string|int|null
     */
    private function ownerId(): string|int|null
    {
        return OwnerIdentity::fromRequest($this->request);
    }

    /**
     * @return \CsvIngest\Model\Table\IngestPresetsTable
     */
    private function presets(): IngestPresetsTable
    {
        $table = $this->fetchTable('CsvIngest.IngestPresets');
        assert($table instanceof IngestPresetsTable);

        return $table;
    }
}
