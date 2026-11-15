# Changelog

All notable changes to this project are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning: [SemVer](https://semver.org/).

## [Unreleased] — 0.1.0

### Fixed
- A row whose match key finds more than one existing record now fails with an "ambiguous match"
  message under the match column, whatever the match policy, instead of updating the first record found.

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
