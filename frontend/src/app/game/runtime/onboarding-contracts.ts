export interface OnboardingStatus {
  readonly status: 'required' | 'in_progress' | 'ready_to_provision' | 'complete';
  readonly region: { readonly id: string; readonly display_name: string };
  readonly dialogue_id: string | null;
}

export class OnboardingContractError extends Error {
  constructor() { super('Onboarding response is malformed.'); this.name = 'OnboardingContractError'; }
}

function record(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function exact(value: Record<string, unknown>, keys: readonly string[]): boolean {
  return Object.keys(value).sort().join('\0') === [...keys].sort().join('\0');
}

export function parseOnboardingEnvelope(value: unknown): OnboardingStatus {
  if (!record(value) || !exact(value, ['ok', 'data']) || value['ok'] !== true
    || !record(value['data']) || !exact(value['data'], ['status', 'region', 'dialogue_id'])) {
    throw new OnboardingContractError();
  }
  const data = value['data'];
  const region = data['region'];
  const status = data['status'];
  const dialogueId = data['dialogue_id'];
  if (!record(region) || !exact(region, ['id', 'display_name'])
    || typeof region['id'] !== 'string' || !/^region\.[a-z][a-z0-9_]*$/.test(region['id'])
    || typeof region['display_name'] !== 'string' || region['display_name'].trim() === ''
    || !['required', 'in_progress', 'ready_to_provision', 'complete'].includes(String(status))
    || (status === 'required' || status === 'in_progress'
      ? typeof dialogueId !== 'string' || !/^dialogue\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/.test(dialogueId)
      : dialogueId !== null)) {
    throw new OnboardingContractError();
  }
  return Object.freeze({ status: status as OnboardingStatus['status'],
    region: Object.freeze({ id: region['id'], display_name: region['display_name'] }),
    dialogue_id: dialogueId as string | null });
}
