# Changelog

All notable changes to this project are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning: [SemVer](https://semver.org/).

## [Unreleased] — 0.1.0

### Added (run hooks)
- `CsvIngest.beforeIngest` / `CsvIngest.afterIngest` events, once per run (`afterIngest` after a dry run
  was rolled back, with the report), and the save options `csvIngest` / `dryRun` on every row save, so
  hosts can defer side effects such as file or cache rebuilds to the end of a real run.

### Fixed
- The mapping screen failed with a missing route when the wizard ran on a prefixed host controller
  (e.g. `Admin/Contacts`): the link to the plugin's preset check now drops the host prefix.
- A row whose match key finds more than one existing record now fails with an "ambiguous match"
  message under the match column, whatever the match policy, instead of updating the first record found.
- Foreign-key text that matches more than one related record is no longer resolved to the first one:
  the row fails and the text is listed as pending, where a value map picks the right record.

### Added
- `Ingestable` behavior: importable `fields` (array or closure), `labels`, composite `matchOn`,
  `validation` set, `lookupFields`, overridable `ingestMatch` finder.
- Import engine: `CsvSource` (positional, BOM/duplicate-header tolerant), `UploadStore`
  (UUID tokens, UTF-8 normalization, TTL purge), `ValueCaster` (locale numbers, booleans, dates),
  `ForeignKeyResolver` (value maps → association lookup, cached), `IngestRunner`
  (Replace/Patch/Skip, per-row transactions, dry run, row limit, events).
- Wizard: `CsvIngestComponent` + `CsvIngestActionsTrait` (`csvUpload`, `csvMapping`), presets
  (shared/personal), pending value assignment, run report.
- Admin screens: presets (`/csv-ingest/presets`, JSON availability check) and value maps
  (`/csv-ingest/value-maps`).
- Migration for `csv_ingest_presets` and `csv_ingest_value_maps`.
- TypeScript enhancements (no jQuery): delimiter sniffing, size check, live samples,
  duplicate-column warning, preset-name hint, value-map field filter. Framework-neutral CSS.
- Spanish translation and `.pot` template.
- Quality gates: PHPUnit (unit + integration with CSRF/FormProtection), PHPStan level 8, PHPCS,
  Vitest + strict tsc.
