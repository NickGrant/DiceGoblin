export interface DialogueChoice {
  readonly id: string;
  readonly label: string;
}

export interface DialogueRead {
  readonly id: string;
  readonly title: string;
  readonly body: string;
  readonly choices: readonly DialogueChoice[];
}

export interface DialogueCompletion {
  readonly dialogueId: string;
  readonly choiceId: string;
  readonly response: string;
  readonly knowledge: { readonly codexId: string; readonly outcome: 'granted' | 'already_owned' } | null;
  readonly playerRevision: number;
}

export class DialogueContractError extends Error {
  constructor(message: string) { super(message); this.name = 'DialogueContractError'; }
}

const dialogueIdPattern = /^dialogue\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/;
const codexIdPattern = /^codex\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/;
const choiceIdPattern = /^[a-z][a-z0-9_]*$/;

function record(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function exact(value: Record<string, unknown>, keys: readonly string[]): boolean {
  return Object.keys(value).sort().join('\0') === [...keys].sort().join('\0');
}

function text(value: unknown, maximum: number): value is string {
  return typeof value === 'string' && value.trim().length > 0 && value.length <= maximum;
}

export function canonicalDialogueId(id: string): boolean { return dialogueIdPattern.test(id); }
export function canonicalDialogueChoiceId(id: string): boolean { return choiceIdPattern.test(id); }

export function parseDialogueReadEnvelope(value: unknown, requestedId: string): DialogueRead {
  if (!record(value) || !exact(value, ['ok', 'data']) || value['ok'] !== true
    || !record(value['data']) || !exact(value['data'], ['id', 'title', 'body', 'choices'])) {
    throw new DialogueContractError('Dialogue read envelope is invalid.');
  }
  const data = value['data'];
  if (data['id'] !== requestedId || !canonicalDialogueId(requestedId)
    || !text(data['title'], 120) || !text(data['body'], 2000)
    || !Array.isArray(data['choices'])) {
    throw new DialogueContractError('Dialogue read is invalid.');
  }
  const ids = new Set<string>();
  const choices = data['choices'].map((choice): DialogueChoice => {
    if (!record(choice) || !exact(choice, ['id', 'label']) || typeof choice['id'] !== 'string'
      || !canonicalDialogueChoiceId(choice['id']) || ids.has(choice['id']) || !text(choice['label'], 120)) {
      throw new DialogueContractError('Dialogue choice is invalid or exposes private fields.');
    }
    ids.add(choice['id']);
    return Object.freeze({ id: choice['id'], label: choice['label'] });
  });
  return Object.freeze({ id: requestedId, title: data['title'], body: data['body'], choices: Object.freeze(choices) });
}

export function parseDialogueCompletionEnvelope(value: unknown, dialogueId: string, choiceId: string): DialogueCompletion {
  if (!record(value) || !exact(value, ['ok', 'data']) || value['ok'] !== true
    || !record(value['data']) || !exact(value['data'], ['dialogue_id', 'choice_id', 'response', 'knowledge', 'player_revision'])) {
    throw new DialogueContractError('Dialogue completion envelope is invalid.');
  }
  const data = value['data'];
  if (data['dialogue_id'] !== dialogueId || data['choice_id'] !== choiceId
    || !canonicalDialogueId(dialogueId) || !canonicalDialogueChoiceId(choiceId)
    || !text(data['response'], 2000) || !Number.isSafeInteger(data['player_revision'])
    || (data['player_revision'] as number) < 0) {
    throw new DialogueContractError('Dialogue completion is invalid.');
  }
  const knowledge = data['knowledge'];
  if (knowledge !== null && (!record(knowledge) || !exact(knowledge, ['codex_id', 'outcome'])
    || typeof knowledge['codex_id'] !== 'string' || !codexIdPattern.test(knowledge['codex_id'])
    || (knowledge['outcome'] !== 'granted' && knowledge['outcome'] !== 'already_owned'))) {
    throw new DialogueContractError('Dialogue knowledge result is invalid.');
  }
  return Object.freeze({ dialogueId, choiceId, response: data['response'],
    knowledge: knowledge === null ? null : Object.freeze({ codexId: (knowledge as Record<string, string>)['codex_id'],
      outcome: (knowledge as Record<string, 'granted' | 'already_owned'>)['outcome'] }),
    playerRevision: data['player_revision'] as number });
}
