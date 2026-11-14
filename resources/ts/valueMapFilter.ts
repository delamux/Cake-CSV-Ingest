/**
 * Value maps admin: restrict the "field" filter to fields of the chosen table.
 */
export function enhanceValueMapFilter(form: HTMLFormElement): void {
  const target = form.querySelector<HTMLSelectElement>('[data-ci-filter-target]');
  const field = form.querySelector<HTMLSelectElement>('[data-ci-filter-field]');
  if (!target || !field) {
    return;
  }
  const fieldsByTarget = parse(form.dataset['ciFields']);
  const emptyOption = Array.from(field.options)
    .find((option) => option.value === '')
    ?.cloneNode(true) as HTMLOptionElement | undefined;
  const allFields = [...new Set(Object.values(fieldsByTarget).flat())].sort();

  const rebuild = (): void => {
    const previous = field.value;
    const fields = target.value === '' ? allFields : fieldsByTarget[target.value] ?? [];
    const wanted = fields.includes(previous) ? previous : '';
    const options: HTMLOptionElement[] = [];
    if (emptyOption) {
      options.push(emptyOption.cloneNode(true) as HTMLOptionElement);
    }
    for (const name of fields) {
      const option = field.ownerDocument.createElement('option');
      option.value = name;
      option.textContent = name;
      options.push(option);
    }
    for (const option of options) {
      option.defaultSelected = option.value === wanted;
    }
    field.replaceChildren(...options);
    field.value = wanted;
  };

  target.addEventListener('change', rebuild);
}

function parse(raw: string | undefined): Record<string, string[]> {
  if (!raw) {
    return {};
  }
  try {
    const parsed: unknown = JSON.parse(raw);
    if (typeof parsed !== 'object' || parsed === null) {
      return {};
    }
    const result: Record<string, string[]> = {};
    for (const [key, value] of Object.entries(parsed)) {
      result[key] = Array.isArray(value) ? value.map(String) : [];
    }
    return result;
  } catch {
    return {};
  }
}
