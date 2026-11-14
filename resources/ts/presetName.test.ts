import { beforeEach, describe, expect, it, vi } from 'vitest';
import { enhancePresetName } from './presetName';

describe('enhancePresetName', () => {
  let input: HTMLInputElement;
  let hint: HTMLElement;

  beforeEach(() => {
    vi.useFakeTimers();
    document.body.innerHTML = `
      <input data-ci-preset-name
        data-ci-availability-url="/csv-ingest/presets/availability.json?target=Contacts"
        data-ci-msg-new="New preset" data-ci-msg-update="Will update">
      <p data-ci-preset-hint></p>`;
    input = document.querySelector<HTMLInputElement>('[data-ci-preset-name]')!;
    hint = document.querySelector<HTMLElement>('[data-ci-preset-hint]')!;
  });

  function type(value: string): void {
    input.value = value;
    input.dispatchEvent(new Event('input'));
  }

  it('debounces and queries availability with a GET request', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response(JSON.stringify({ available: false })));
    enhancePresetName(input, fetcher, 200);

    type('Mi');
    type('Mine & co');
    await vi.advanceTimersByTimeAsync(200);

    expect(fetcher).toHaveBeenCalledTimes(1);
    expect(fetcher.mock.calls[0]![0]).toBe(
      '/csv-ingest/presets/availability.json?target=Contacts&name=Mine%20%26%20co',
    );
    expect(fetcher.mock.calls[0]![1]).toMatchObject({ headers: { Accept: 'application/json' } });
    expect(hint.textContent).toBe('Will update');
    expect(hint.classList.contains('ci-hint--warning')).toBe(true);
  });

  it('shows the new-preset hint when the name is free and clears it when empty', async () => {
    const fetcher = vi.fn().mockResolvedValue(new Response(JSON.stringify({ available: true })));
    enhancePresetName(input, fetcher, 10);

    type('Weekly');
    await vi.advanceTimersByTimeAsync(10);
    expect(hint.textContent).toBe('New preset');

    type('   ');
    await vi.advanceTimersByTimeAsync(10);
    expect(hint.textContent).toBe('');
    expect(fetcher).toHaveBeenCalledTimes(1);
  });

  it('stays silent when the request fails', async () => {
    const fetcher = vi.fn().mockRejectedValue(new Error('offline'));
    enhancePresetName(input, fetcher, 10);

    type('Weekly');
    await vi.advanceTimersByTimeAsync(10);

    expect(hint.textContent).toBe('');
  });
});
