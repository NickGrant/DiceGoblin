import { parseOnboardingEnvelope, parseOnboardingProvisionEnvelope } from './onboarding-contracts';

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

describe('Onboarding provisioning contract', () => {
  const valid = { ok: true, data: { unit_ids: ['12', '15', '16', '17'], die_ids: ['13', '18', '19', '20'], active_squad_id: '14',
    player_revision: 2, onboarding_status: 'complete' as const } };

  it('accepts only the authoritative completion receipt', () => {
    expect(parseOnboardingProvisionEnvelope(valid)).toEqual(valid.data);
    for (const data of [
      { ...valid.data, machine_capability_id: 'capability.secret' },
      { ...valid.data, starting_warband: {} },
      { ...valid.data, unit_ids: ['0'] },
      { ...valid.data, unit_ids: ['12', '12', '16', '17'] },
      { ...valid.data, die_ids: ['13'] },
      { ...valid.data, player_revision: 0 },
      { ...valid.data, onboarding_status: 'ready_to_provision' },
    ]) expect(() => parseOnboardingProvisionEnvelope({ ok: true, data })).toThrow();
  });
});
