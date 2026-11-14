import { beforeEach, describe, expect, it } from 'vitest';
import { enhanceMapper } from './mapper';

function row(field: string, label: string, selected: string): string {
  const options = ['', '0', '1']
    .map((v) => `<option value="${v}"${v === selected ? ' selected' : ''}>${v}</option>`)
    .join('');
  return `<tr class="ci-map__row" data-ci-field="${field}" data-ci-label="${label}">
      <td><select data-ci-column>${options}</select></td><td data-ci-sample></td></tr>`;
}

describe('enhanceMapper', () => {
  let form: HTMLFormElement;

  beforeEach(() => {
    document.body.innerHTML = `
      <form data-ci-mapper>
        <table data-ci-samples='["Ana","ana@example.test"]'><tbody>
          ${row('name', 'Name', '0')}${row('email', 'Email', '')}
        </tbody></table>
        <p data-ci-duplicates data-ci-msg-duplicates="Same column for: {0}" hidden></p>
      </form>`;
    form = document.querySelector<HTMLFormElement>('[data-ci-mapper]')!;
    enhanceMapper(form);
  });

  const select = (field: string) =>
    form.querySelector<HTMLSelectElement>(`[data-ci-field="${field}"] [data-ci-column]`)!;
  const rowOf = (field: string) => form.querySelector<HTMLElement>(`[data-ci-field="${field}"]`)!;

  it('initialises row state and samples from the current selection', () => {
    expect(rowOf('name').classList.contains('ci-map__row--off')).toBe(false);
    expect(rowOf('name').querySelector('[data-ci-sample]')!.textContent).toBe('Ana');
    expect(rowOf('email').classList.contains('ci-map__row--off')).toBe(true);
  });

  it('updates sample and state when a column is chosen', () => {
    select('email').value = '1';
    select('email').dispatchEvent(new Event('change', { bubbles: true }));

    expect(rowOf('email').classList.contains('ci-map__row--off')).toBe(false);
    expect(rowOf('email').querySelector('[data-ci-sample]')!.textContent).toBe('ana@example.test');
  });

  it('warns when the same column feeds several fields', () => {
    select('email').value = '0';
    select('email').dispatchEvent(new Event('change', { bubbles: true }));

    const warning = form.querySelector<HTMLElement>('[data-ci-duplicates]')!;
    expect(warning.hidden).toBe(false);
    expect(warning.textContent).toBe('Same column for: Name, Email');

    select('email').value = '';
    select('email').dispatchEvent(new Event('change', { bubbles: true }));
    expect(warning.hidden).toBe(true);
  });
});
