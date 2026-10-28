export interface ObjectiveRequirement {
  readonly id: string;
  readonly label: string;
  readonly current: number;
  readonly needed: number;
}

export interface ObjectiveEntry {
  readonly id: string;
  readonly title: string;
  readonly description: string;
  readonly category: 'objective' | 'bounty';
  readonly status: 'unstarted' | 'active' | 'completed';
  readonly requirements: readonly ObjectiveRequirement[];
}

export class ObjectiveContractError extends Error {
  constructor(message: string) { super(message); this.name = 'ObjectiveContractError'; }
}

function record(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function exact(value: Record<string, unknown>, fields: readonly string[]): boolean {
  return Object.keys(value).sort().join('\0') === [...fields].sort().join('\0');
}

function boundedText(value: unknown, maximum: number): value is string {
  return typeof value === 'string' && value.trim() !== '' && value.length <= maximum;
}

export function parseObjectivesEnvelope(value: unknown): readonly ObjectiveEntry[] {
  if (!record(value) || !exact(value, ['ok', 'data']) || value['ok'] !== true
    || !record(value['data']) || !exact(value['data'], ['objectives'])
    || !Array.isArray(value['data']['objectives'])) {
    throw new ObjectiveContractError('Objective response must be an exact successful envelope.');
  }
  let priorId = '';
  return Object.freeze(value['data']['objectives'].map((entry): ObjectiveEntry => {
    if (!record(entry) || !exact(entry, ['id', 'title', 'description', 'category', 'status', 'requirements'])
      || typeof entry['id'] !== 'string' || !/^objective\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/.test(entry['id'])
      || entry['id'] <= priorId || !boundedText(entry['title'], 120)
      || !boundedText(entry['description'], 2000)
      || (entry['category'] !== 'objective' && entry['category'] !== 'bounty')
      || !['unstarted', 'active', 'completed'].includes(entry['status'] as string)
      || !Array.isArray(entry['requirements']) || entry['requirements'].length === 0) {
      throw new ObjectiveContractError('Objective entry is malformed or exposes private fields.');
    }
    priorId = entry['id'];
    const ids = new Set<string>();
    let allMet = true;
    const requirements = entry['requirements'].map((requirement): ObjectiveRequirement => {
      if (!record(requirement) || !exact(requirement, ['id', 'label', 'current', 'needed'])
        || typeof requirement['id'] !== 'string' || !/^[a-z][a-z0-9_]*$/.test(requirement['id'])
        || ids.has(requirement['id']) || !boundedText(requirement['label'], 120)
        || !Number.isSafeInteger(requirement['current']) || !Number.isSafeInteger(requirement['needed'])
        || (requirement['current'] as number) < 0 || (requirement['needed'] as number) < 1
        || (requirement['current'] as number) > (requirement['needed'] as number)) {
        throw new ObjectiveContractError('Objective requirement is malformed or exposes private fields.');
      }
      ids.add(requirement['id']);
      if (requirement['current'] !== requirement['needed']) allMet = false;
      return Object.freeze({ id: requirement['id'], label: requirement['label'],
        current: requirement['current'] as number, needed: requirement['needed'] as number });
    });
    if ((entry['status'] === 'completed') !== allMet
      || (entry['status'] === 'unstarted' && requirements.some((requirement) => requirement.current !== 0))) {
      throw new ObjectiveContractError('Objective lifecycle is incoherent.');
    }
    return Object.freeze({ id: entry['id'], title: entry['title'], description: entry['description'],
      category: entry['category'] as ObjectiveEntry['category'], status: entry['status'] as ObjectiveEntry['status'],
      requirements: Object.freeze(requirements) });
  }));
}
