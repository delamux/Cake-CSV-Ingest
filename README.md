# CsvIngest — CSV import wizard for CakePHP 5

Two-step CSV import for any CakePHP table: **upload → map columns → import**, with
foreign-key resolution, reusable presets, dry runs and a per-row report.

- PHP **8.2+**, CakePHP **5.4+**, no jQuery (tiny TypeScript bundle, works without JS)
- Composite match keys, three match policies (replace / patch / skip)
- Foreign keys resolved from CSV text through your `BelongsTo` associations, with a
  "pending values" workflow for texts that don't match anything yet
- Encoding normalization to UTF-8, delimiter sniffing, duplicate/blank headers tolerated
- Row events for side effects, CSRF + FormProtection friendly, Spanish translation included

## Install

```bash
composer require luisdelamo/cakephp-csv-ingest
bin/cake plugin load CsvIngest
bin/cake migrations migrate -p CsvIngest
```

## 1. Make a table importable

```php
// src/Model/Table/ContactsTable.php
public function initialize(array $config): void
{
    parent::initialize($config);
    $this->belongsTo('Companies');
    $this->belongsTo('Users');

    $this->addBehavior('CsvIngest.Ingestable', [
        // Columns that may receive CSV data (required). Array or Closure(Table): array.
        'fields' => ['company_id', 'user_id', 'name', 'email', 'phone', 'is_active'],
        // Columns identifying an existing record. String, list (composite) or false.
        // Default: the display field when it is importable.
        'matchOn' => 'email',
        // Validation set used when marshalling rows.
        'validation' => 'default',
        // Column used to find related records from CSV text (default: their display field).
        'lookupFields' => ['Users' => 'email'],
        // Optional label overrides.
        'labels' => ['is_active' => __('Active')],
    ]);
}
```

Need custom matching? Define `findIngestMatch(SelectQuery $query, array $values): SelectQuery`
on the table; it takes precedence over the behavior finder.

## 2. Expose the wizard on a controller

```php
// src/Controller/ContactsController.php
use CsvIngest\Controller\CsvIngestActionsTrait;

class ContactsController extends AppController
{
    use CsvIngestActionsTrait; // adds csvUpload() and csvMapping()

    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('CsvIngest.CsvIngest'); // ['target' => 'OtherAlias'] to import elsewhere
    }
}
```

Link to it: `$this->Html->link(__('Import CSV'), ['action' => 'csvUpload'])`.
Remember to allow `csvUpload` and `csvMapping` in your authorization rules.

## 3. Admin screens

- `/csv-ingest/presets` — saved column mappings (shared + the current user's)
- `/csv-ingest/value-maps` — CSV text → related record translations, filterable, editable

Plugin controllers extend your `App\Controller\AppController`, so your authentication,
authorization and layout apply.

## How importing works

| Step | Behaviour |
|---|---|
| Upload | `.csv`/`.txt` up to `maxFileSize`; converted to UTF-8; stored under an opaque token (never a client path) |
| Mapping | Columns pre-selected from a preset (by header title) or guessed from header names/labels |
| Values | Numbers in `1.234,56` / `1,234.56` formats, configurable boolean literals and date formats; blanks become `null`, never `0` |
| Foreign keys | Explicit value map → lookup on the associated table → otherwise the row **fails** and the text is listed as *pending* |
| Match | `Replace` overwrites mapped fields; `Patch` only non-blank values (keeps `0`/`false`); `Skip` leaves the record |
| Save | One transaction per row; failures reported by line and CSV column; *dry run* rolls everything back |

## Events

Dispatched on the target table's event manager:

```php
use CsvIngest\Import\IngestEvents;

// Alter data before marshalling (stopPropagation() skips the row).
$this->getEventManager()->on(IngestEvents::BEFORE_ROW_MARSHAL, function ($event, \ArrayObject $data, array $sourceRow, int $line) {
    $data['name'] = trim((string)$data['name']);
});

// React after each saved row. sourceRow is keyed by CSV header title.
$this->getEventManager()->on(IngestEvents::AFTER_ROW_PERSIST, function ($event) {
    $entity = $event->getData('entity');
    $units = (int)$event->getData('sourceRow')['Units'];
    // ...create child records, etc.
});

// Once per run, around all rows. afterIngest fires after a dry run was rolled back.
$this->getEventManager()->on(IngestEvents::AFTER_INGEST, function ($event) {
    if (!$event->getData('dryRun')) {
        $this->rebuildSearchIndex(); // one-off side effect instead of one per row
    }
});
```

Every save of a run gets the options `csvIngest => true` and `dryRun => bool`, so `beforeSave` /
`afterSave` can skip work that `afterIngest` then does once (`IngestEvents::BEFORE_INGEST` is also
available). Side effects outside the database (files, caches, mail) are **not** rolled back by a dry
run: guard them with `$options['dryRun']` or move them to `afterIngest`.

## Configuration

Set any key in your app config **before** the plugin bootstraps, e.g. `config/app.php`:

```php
'CsvIngest' => [
    'maxFileSize' => 20 * 1024 * 1024,
    'maxRows' => 50000,           // 0 = unlimited
    'timeLimit' => 300,           // set_time_limit() per run, 0 = untouched
    'dateFormats' => ['d/m/Y', 'Y-m-d'],
    'booleanLiterals' => ['sí' => true, 'no' => false],
    'ownerTable' => 'Users',      // enables the Owners association on presets
    'labelDomain' => 'default',   // translation domain for default field labels
],
```

All defaults and their meaning: [`config/csv_ingest.php`](config/csv_ingest.php).
Preset ownership uses the `identity` request attribute (cakephp/authentication) or
`CsvIngest.ownerResolver` (`Closure(ServerRequest): string|int|null`).

## Customizing the UI

Templates are plain HTML with `ci-*` classes. Override any of them in your app:
`templates/plugin/CsvIngest/Ingest/upload.php`, `.../Ingest/mapping.php`, `.../element/report.php`…
Theme by overriding the CSS custom properties (`--ci-accent`, `--ci-border`, …) declared in
`webroot/css/csv-ingest.css`.

## Development

```bash
composer install && npm install
composer check        # PHPUnit + PHPStan (level 8) + PHPCS
npm run check         # tsc + Vitest + esbuild bundle (webroot/js/csv-ingest.js is committed)
```

Translations: `resources/locales/csv_ingest.pot` (template) and `resources/locales/es/csv_ingest.po`.

## License

MIT
