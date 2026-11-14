/** Delimiter slugs, identical to the PHP `Delimiter` enum backing values. */
export type DelimiterSlug = 'semicolon' | 'comma' | 'tab' | 'pipe';

const CHARACTERS: ReadonlyArray<readonly [DelimiterSlug, string]> = [
  ['semicolon', ';'],
  ['comma', ','],
  ['tab', '\t'],
  ['pipe', '|'],
];

/**
 * Guess the delimiter from the first line, ignoring quoted sections.
 * Mirrors `CsvIngest\Import\Delimiter::sniff()`.
 */
export function sniffDelimiter(sample: string): DelimiterSlug {
  const firstLine = sample.replace(/\r\n/g, '\n').split('\n', 1)[0] ?? '';
  const unquoted = firstLine.replace(/"[^"]*"/g, '');

  let best: DelimiterSlug = 'semicolon';
  let bestCount = 0;
  for (const [slug, character] of CHARACTERS) {
    const count = unquoted.split(character).length - 1;
    if (count > bestCount) {
      best = slug;
      bestCount = count;
    }
  }

  return best;
}

export function formatBytes(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} B`;
  }
  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`;
  }

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/** Replace `{0}`, `{1}`… placeholders (same convention as CakePHP __d()). */
export function format(template: string, ...values: Array<string | number>): string {
  return template.replace(/\{(\d+)\}/g, (match, index: string) => {
    const value = values[Number(index)];
    return value === undefined ? match : String(value);
  });
}
