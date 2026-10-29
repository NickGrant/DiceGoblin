import { parseOnboardingEnvelope } from './onboarding-contracts';

describe('Onboarding read contract', () => {
  const valid = { ok: true, data: { status: 'required',
    region: { id: 'region.test_cave', display_name: 'Test Cave' }, dialogue_id: 'dialogue.test.arrival' } };

  it('accepts only the current player-visible step', () => {
    expect(parseOnboardingEnvelope(valid).dialogue_id).toBe('dialogue.test.arrival');
    expect(parseOnboardingEnvelope({ ok: true, data: { ...valid.data,
      status: 'complete', dialogue_id: null } }).status).toBe('complete');
  });

  it('rejects private authored fields and inconsistent state', () => {
    for (const data of [
      { ...valid.data, reward_definition_id: 'reward.hidden' },
      { ...valid.data, steps: [] },
      { ...valid.data, status: 'complete' },
      { ...valid.data, dialogue_id: null },
      { ...valid.data, region: { ...valid.data.region, run_generation_id: 'run.hidden' } },
    ]) {
      expect(() => parseOnboardingEnvelope({ ok: true, data })).toThrow();
    }
  });
});
