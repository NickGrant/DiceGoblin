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
  const valid = { ok: true, data: { unit_id: '12', die_id: '13', active_squad_id: '14',
    player_revision: 2, onboarding_status: 'complete' as const } };

  it('accepts only the authoritative completion receipt', () => {
    expect(parseOnboardingProvisionEnvelope(valid)).toEqual(valid.data);
    for (const data of [
      { ...valid.data, machine_capability_id: 'capability.secret' },
      { ...valid.data, starting_warband: {} },
      { ...valid.data, unit_id: '0' },
      { ...valid.data, player_revision: 0 },
      { ...valid.data, onboarding_status: 'ready_to_provision' },
    ]) expect(() => parseOnboardingProvisionEnvelope({ ok: true, data })).toThrow();
  });
});
