import { describe, expect, it } from 'vitest';
import { formatBytes, sniffDelimiter } from './delimiter';

describe('sniffDelimiter (parity with PHP Delimiter::sniff)', () => {
  it.each([
    ['name;email;age\nAna;a@x.test;3', 'semicolon'],
    ['name,email,age\n', 'comma'],
    ['name\temail\tage', 'tab'],
    ['name|email|age', 'pipe'],
    ['"Doe, John";"Main St, 1";x', 'semicolon'],
    ['a,b,c\r\n1,2,3', 'comma'],
    ['', 'semicolon'],
    ['name', 'semicolon'],
  ])('%j -> %s', (sample, expected) => {
    expect(sniffDelimiter(sample)).toBe(expected);
  });
});

describe('formatBytes', () => {
  it('formats human sizes', () => {
    expect(formatBytes(512)).toBe('512 B');
    expect(formatBytes(2048)).toBe('2.0 KB');
    expect(formatBytes(5 * 1024 * 1024)).toBe('5.0 MB');
  });
});
