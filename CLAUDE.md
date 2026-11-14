# CLAUDE.md — CsvIngest (CakePHP plugin)

CSV import wizard plugin for **CakePHP ≥ 5.4** on **PHP ≥ 8.2**: upload → map columns → resolve
foreign keys → import with a match policy (replace / patch / skip), presets and dry runs.

## Resuming work (read in this order)
1. `.ai/PROGRESS.md` — what's done, what's next, gotchas (local only, gitignored)
2. `.ai/DECISIONS.md` — ADR-lite log; append, never rewrite
3. `.ai/ANALYSIS.md` — analysis of the reference plugin + real usage requirements
4. `.ai/ROADMAP.md`
Update `PROGRESS.md` at the end of every work block and add a `DECISIONS.md` entry for any design choice.

## Non-negotiable rules
- **Clean-room**: this is inspired by a third-party plugin we may NOT reuse. Never copy code, names,
  table/column names, config keys or routes from `gf-tic/csv-importer`. Use the names in `.ai/ANALYSIS.md §4`.
- **TDD**: write/extend the test first, run it and see it fail (red), implement (green), refactor.
- **Small commits**: one logical change per commit, Conventional Commits (`feat(scope):`, `fix(scope):`,
  `test(scope):`, `refactor:`, `docs:`, `chore:`). A step's test + implementation may share a commit
  once green; unrelated fixes go in their own commit. Never batch everything into one commit.
- **PHP 8.2 compatible**: no typed class constants, no `#[\Override]`, no `json_validate()` (8.3+).
  `composer.json` pins `config.platform.php` to 8.2.0.
- **No jQuery**. Frontend is TypeScript in `resources/ts/` compiled by esbuild to `webroot/js/csv-ingest.js`
  (IIFE, committed). Progressive enhancement only: every screen must work without JS.
- Translations: always `use function Cake\I18n\__d;` and domain `csv_ingest`. Never rely on global helpers.
- Templates are framework-neutral HTML with `ci-*` BEM classes; no host helpers/layout assumptions.

## Commands
```bash
composer test            # phpunit (SQLite in memory; DB_URL env to override)
composer stan            # phpstan
composer cs-check        # phpcs (CakePHP standard) | composer cs-fix
composer check           # all of the above
npm run build            # TS -> webroot/js/csv-ingest.js
npm run typecheck        # tsc --noEmit
```

## Architecture (src/)
- `CsvIngestPlugin` — merges `config/csv_ingest.php` defaults under app config; routes `/csv-ingest/*`.
- `IngestSettings` — typed accessor for `Configure::read('CsvIngest.*')`.
- `Import/` — pure domain: `CsvDialect`, `Delimiter`, `Enclosure`, `OnMatch`, `RowOutcome`,
  `FieldSpec`, `ColumnMap`, `RowFailure`, `IngestReport`, `ValueCaster`, `CsvSource`, `UploadStore`,
  `ForeignKeyResolver`, `IngestRunner`.
- `Model/Behavior/IngestableBehavior` — attached by host tables; config `fields`, `labels`, `matchOn`,
  `validation`, `lookupFields`; finder `ingestMatch` (host may override `findIngestMatch`).
- `Model/Table/IngestPresetsTable` (`csv_ingest_presets`), `IngestValueMapsTable` (`csv_ingest_value_maps`).
- `Controller/Component/CsvIngestComponent` + `Controller/CsvIngestActionsTrait` (`csvUpload`, `csvMapping`).
- `Controller/PresetsController`, `Controller/ValueMapsController` — admin screens.
- Events on the target table: `CsvIngest.beforeRowMarshal`, `CsvIngest.afterRowPersist`.

## Tests
- `tests/bootstrap.php` builds a standalone app (`TestApp` namespace in `tests/test_app/src`).
- `tests/schema.php` defines plugin tables + test targets `companies`, `contacts`.
- Keep `tests/schema.php` in sync with `config/Migrations`.

## Environment notes
- A local GateGuard hook asks for "facts" before the first write to each new file/dir; state them and retry.
- League CSV ≥ 9.16: use `Reader::fromPath()`.
