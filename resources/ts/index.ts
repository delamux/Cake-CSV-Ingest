/**
 * CsvIngest browser enhancements. Progressive: every screen works without JS.
 * Built with `npm run build` into webroot/js/csv-ingest.js (IIFE, no deps).
 */
import { enhanceMapper } from './mapper';
import { enhancePresetName } from './presetName';
import { enhanceUpload } from './upload';
import { enhanceValueMapFilter } from './valueMapFilter';

export function init(root: ParentNode = document): void {
  root.querySelectorAll<HTMLElement>('[data-ci-upload]').forEach((el) => void enhanceUpload(el));
  root.querySelectorAll<HTMLFormElement>('form[data-ci-mapper]').forEach(enhanceMapper);
  root.querySelectorAll<HTMLInputElement>('input[data-ci-preset-name]').forEach((el) => enhancePresetName(el));
  root.querySelectorAll<HTMLFormElement>('form[data-ci-value-map-filter]').forEach(enhanceValueMapFilter);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => init(), { once: true });
} else {
  init();
}
