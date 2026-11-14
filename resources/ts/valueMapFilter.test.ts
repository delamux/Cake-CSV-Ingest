import { describe, expect, it } from 'vitest';
import { enhanceValueMapFilter } from './valueMapFilter';

describe('enhanceValueMapFilter', () => {
  it('limits field options to the selected table and keeps a valid selection', () => {
    document.body.innerHTML = `
      <form data-ci-value-map-filter
        data-ci-fields='{"Contacts":["company_id","user_id"],"Companies":["country_id"]}'>
        <select data-ci-filter-target>
          <option value="">All</option><option value="Contacts">Contacts</option>
          <option value="Companies">Companies</option>
        </select>
        <select data-ci-filter-field>
          <option value="">All fields</option><option value="company_id">company_id</option>
          <option value="country_id" selected>country_id</option><option value="user_id">user_id</option>
        </select>
      </form>`;
    const form = document.querySelector<HTMLFormElement>('form')!;
    const target = form.querySelector<HTMLSelectElement>('[data-ci-filter-target]')!;
    const field = form.querySelector<HTMLSelectElement>('[data-ci-filter-field]')!;
    enhanceValueMapFilter(form);
    field.value = 'country_id'; // happy-dom ignores the `selected` attribute from markup

    const values = () => Array.from(field.options).map((o) => o.value);
    expect(values()).toEqual(['', 'company_id', 'country_id', 'user_id']);

    target.value = 'Contacts';
    target.dispatchEvent(new Event('change'));
    expect(values()).toEqual(['', 'company_id', 'user_id']);
    expect(field.value).toBe('');

    target.value = 'Companies';
    target.dispatchEvent(new Event('change'));
    expect(values()).toEqual(['', 'country_id']);
  });
});
