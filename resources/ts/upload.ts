import { format, formatBytes, sniffDelimiter } from './delimiter';

const SAMPLE_BYTES = 8192;

/**
 * Upload step: show file name/size, enforce the size limit client-side and
 * preselect the delimiter sniffed from the first bytes of the file.
 */
export async function enhanceUpload(root: HTMLElement): Promise<void> {
  const input = root.querySelector<HTMLInputElement>('[data-ci-file]');
  if (!input) {
    return;
  }
  const info = root.querySelector<HTMLElement>('[data-ci-file-info]');
  const delimiter = root.querySelector<HTMLSelectElement>('[data-ci-delimiter]');
  const maxSize = Number(input.dataset['ciMaxSize'] ?? '0');
  const tooLarge = input.dataset['ciMsgTooLarge'] ?? 'File too large ({0} max).';
  const defaultHint = info?.textContent ?? '';

  input.addEventListener('change', () => {
    const file = input.files?.[0];
    input.setCustomValidity('');
    info?.classList.remove('ci-hint--warning');
    if (!file) {
      if (info) {
        info.textContent = defaultHint;
      }
      return;
    }

    if (info) {
      info.textContent = `${file.name} · ${formatBytes(file.size)}`;
    }
    if (maxSize > 0 && file.size > maxSize) {
      input.setCustomValidity(format(tooLarge, formatBytes(maxSize)));
      info?.classList.add('ci-hint--warning');
      return;
    }

    if (delimiter) {
      void file
        .slice(0, SAMPLE_BYTES)
        .text()
        .then((sample) => {
          delimiter.value = sniffDelimiter(sample);
        })
        .catch(() => undefined);
    }
  });
}
