export interface CodexEntry {
  readonly id: string;
  readonly title: string;
  readonly body: string | null;
  readonly owned: boolean;
}

export class CodexContractError extends Error {
  constructor(message: string) { super(message); this.name = 'CodexContractError'; }
}

function record(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function exact(value: Record<string, unknown>, keys: readonly string[]): boolean {
  return Object.keys(value).sort().join('\0') === [...keys].sort().join('\0');
}

export function parseCodexEnvelope(value: unknown): readonly CodexEntry[] {
  if (!record(value) || !exact(value, ['ok', 'data']) || value['ok'] !== true
    || !record(value['data']) || !exact(value['data'], ['entries']) || !Array.isArray(value['data']['entries'])) {
    throw new CodexContractError('Codex response must be an exact successful envelope.');
  }
  let prior = '';
  return Object.freeze(value['data']['entries'].map((entry): CodexEntry => {
    if (!record(entry) || !exact(entry, ['id', 'title', 'body', 'owned'])
      || typeof entry['id'] !== 'string'
      || !/^codex\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/.test(entry['id'])
      || entry['id'] <= prior || typeof entry['title'] !== 'string' || entry['title'].trim() === ''
      || entry['title'].length > 120 || typeof entry['owned'] !== 'boolean'
      || (entry['owned'] ? typeof entry['body'] !== 'string' || entry['body'].trim() === ''
        || entry['body'].length > 2000 : entry['body'] !== null)) {
      throw new CodexContractError('Codex entry is malformed or reveals undiscovered knowledge.');
    }
    prior = entry['id'];
    return Object.freeze({ id: entry['id'], title: entry['title'], body: entry['body'] as string | null,
      owned: entry['owned'] });
  }));
}
