import { format } from './delimiter';

/**
 * Mapping step: keep row state and sample values in sync with the chosen
 * column, and warn when one CSV column feeds several fields.
 */
export function enhanceMapper(form: HTMLFormElement): void {
  const table = form.querySelector<HTMLElement>('[data-ci-samples]');
  const samples = parseSamples(table?.dataset['ciSamples']);
  const warning = form.querySelector<HTMLElement>('[data-ci-duplicates]');
  const rows = Array.from(form.querySelectorAll<HTMLElement>('[data-ci-field]'));

  const refreshRow = (row: HTMLElement): void => {
    const select = row.querySelector<HTMLSelectElement>('[data-ci-column]');
    const sample = row.querySelector<HTMLElement>('[data-ci-sample]');
    const value = select?.value ?? '';
    row.classList.toggle('ci-map__row--off', value === '');
    if (sample) {
      sample.textContent = value === '' ? '' : samples[Number(value)] ?? '';
    }
  };

  const refreshDuplicates = (): void => {
    if (!warning) {
      return;
    }
    const byColumn = new Map<string, string[]>();
    for (const row of rows) {
      const value = row.querySelector<HTMLSelectElement>('[data-ci-column]')?.value ?? '';
      if (value !== '') {
        byColumn.set(value, [...(byColumn.get(value) ?? []), row.dataset['ciLabel'] ?? '']);
      }
    }
    const duplicated = [...byColumn.values()].filter((labels) => labels.length > 1).flat();
    warning.hidden = duplicated.length === 0;
    warning.textContent = duplicated.length === 0
      ? ''
      : format(warning.dataset['ciMsgDuplicates'] ?? '{0}', duplicated.join(', '));
  };

  rows.forEach(refreshRow);
  refreshDuplicates();

  form.addEventListener('change', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLSelectElement) || !target.matches('[data-ci-column]')) {
      return;
    }
    const row = target.closest<HTMLElement>('[data-ci-field]');
    if (row) {
      refreshRow(row);
    }
    refreshDuplicates();
  });
}

function parseSamples(raw: string | undefined): string[] {
  if (!raw) {
    return [];
  }
  try {
    const parsed: unknown = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed.map((value) => String(value)) : [];
  } catch {
    return [];
  }
}
