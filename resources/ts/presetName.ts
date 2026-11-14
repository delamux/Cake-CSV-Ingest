type Fetcher = (input: string, init?: RequestInit) => Promise<Response>;

/**
 * Tell the user whether saving the preset creates a new one or updates
 * their existing preset with the same name (GET JSON, debounced).
 */
export function enhancePresetName(
  input: HTMLInputElement,
  fetcher: Fetcher = (url, init) => fetch(url, init),
  delay = 300,
): void {
  const url = input.dataset['ciAvailabilityUrl'];
  const hint = document.querySelector<HTMLElement>('[data-ci-preset-hint]');
  if (!url || !hint) {
    return;
  }
  const messages = {
    created: input.dataset['ciMsgNew'] ?? '',
    updated: input.dataset['ciMsgUpdate'] ?? '',
  };
  let timer: ReturnType<typeof setTimeout> | undefined;
  let sequence = 0;

  const show = (text: string, warn: boolean): void => {
    hint.textContent = text;
    hint.classList.toggle('ci-hint--warning', warn);
  };

  const check = async (): Promise<void> => {
    const name = input.value.trim();
    const current = ++sequence;
    if (name === '') {
      show('', false);
      return;
    }
    const separator = url.includes('?') ? '&' : '?';
    try {
      const response = await fetcher(`${url}${separator}name=${encodeURIComponent(name)}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      const body: unknown = await response.json();
      if (current !== sequence || typeof body !== 'object' || body === null || !('available' in body)) {
        return;
      }
      const available = (body as { available: unknown }).available === true;
      show(available ? messages.created : messages.updated, !available);
    } catch {
      show('', false);
    }
  };

  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => void check(), delay);
  });
}
