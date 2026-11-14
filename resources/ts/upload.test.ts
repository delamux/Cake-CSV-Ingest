import { beforeEach, describe, expect, it } from 'vitest';
import { enhanceUpload } from './upload';

function render(): HTMLElement {
  document.body.innerHTML = `
    <section data-ci-upload>
      <input type="file" data-ci-file data-ci-max-size="20"
        data-ci-msg-too-large="Too large: {0}">
      <p data-ci-file-info>default hint</p>
      <select data-ci-delimiter>
        <option value="semicolon">;</option><option value="comma">,</option>
        <option value="tab">tab</option><option value="pipe">|</option>
      </select>
    </section>`;
  return document.querySelector<HTMLElement>('[data-ci-upload]')!;
}

function choose(input: HTMLInputElement, file: File): void {
  Object.defineProperty(input, 'files', { value: [file], configurable: true });
  input.dispatchEvent(new Event('change'));
}

describe('enhanceUpload', () => {
  let root: HTMLElement;
  let input: HTMLInputElement;

  beforeEach(() => {
    root = render();
    input = root.querySelector<HTMLInputElement>('[data-ci-file]')!;
  });

  it('shows file info and preselects the sniffed delimiter', async () => {
    await enhanceUpload(root);
    choose(input, new File(['a,b\n1,2'], 'people.csv'));
    await new Promise((r) => setTimeout(r, 0));

    expect(root.querySelector('[data-ci-file-info]')!.textContent).toBe('people.csv · 7 B');
    expect(root.querySelector<HTMLSelectElement>('[data-ci-delimiter]')!.value).toBe('comma');
    expect(input.validationMessage).toBe('');
  });

  it('flags files above the limit before uploading', async () => {
    await enhanceUpload(root);
    choose(input, new File(['x'.repeat(50)], 'big.csv'));
    await new Promise((r) => setTimeout(r, 0));

    expect(input.validationMessage).toBe('Too large: 20 B');
    expect(root.querySelector('[data-ci-file-info]')!.classList.contains('ci-hint--warning')).toBe(true);
  });
});
