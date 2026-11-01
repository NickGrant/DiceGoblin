import { DialogueRead } from './dialogue-contracts';
import { GameBootstrapData, GameStore } from './game-store';
import { OnboardingStatus } from './onboarding-contracts';
import { OnboardingFlow } from './onboarding-flow';
import { RuntimeApiClient, RuntimeApiError } from './runtime-api-client';

const cave = { id: 'region.mystic_cave', display_name: 'Mystic Cave' };
const required: OnboardingStatus = { status: 'required', region: cave, dialogue_id: 'dialogue.mystic_cave.arrival' };
const progress: OnboardingStatus = { status: 'in_progress', region: cave, dialogue_id: 'dialogue.mystic_cave.machine' };
const ready: OnboardingStatus = { status: 'ready_to_provision', region: cave, dialogue_id: null };
const complete: OnboardingStatus = { status: 'complete', region: cave, dialogue_id: null };
const arrival: DialogueRead = { id: required.dialogue_id!, title: 'Whim Arrives', body: 'Whim welcomes you.',
  choices: [{ id: 'remember', label: 'Remember' }] };
const machine: DialogueRead = { id: progress.dialogue_id!, title: 'The Wrong Machine',
  body: 'Whim shows an image and tells you its name and purpose.', choices: [{ id: 'remember', label: 'Remember' }] };

function bootstrap(revision: number, active = false): GameBootstrapData {
  return {
    account: { id: '1', display_name: 'Goblin', role: 'user' },
    player: { teeth: 0, raw_chaos: 0, player_revision: revision, energy: { current: 50, normal_max: 50,
      regeneration_per_hour: 12, regeneration_interval_seconds: 300, last_regeneration_at: '2026-01-01T00:00:00Z',
      next_regeneration_at: null, fully_regenerated_at: null } },
    session: { authenticated: true, csrf_token: 'csrf' }, server_time: '2026-01-01T00:00:00Z',
    content_revision: 'a'.repeat(64), progression: { unlock_ids: [], available_region_ids: ['region.the_farm'] },
    active_squad: active ? { id: '31', name: 'First Warband', is_active: true,
      formation: ['11', null, null, null, null, null, null, null, null], units: [{ id: '11', display_name: 'Grub',
        unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' }] } : null,
    active_run: null,
  };
}

function harness() {
  const store = new GameStore();
  store.hydrateBootstrap(bootstrap(1));
  const api = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient',
    ['getOnboarding', 'getBootstrap', 'getDialogue', 'completeDialogue', 'provisionOnboarding']);
  const flow = new OnboardingFlow(api, store);
  return { store, api, flow };
}

describe('OnboardingFlow', () => {
  it('uses each server-selected conversation and provisions only after a separate action', async () => {
    const { store, api, flow } = harness();
    api.getOnboarding.and.returnValues(Promise.resolve(required), Promise.resolve(progress),
      Promise.resolve(ready), Promise.resolve(complete));
    api.getBootstrap.and.returnValues(Promise.resolve({ ok: true, data: bootstrap(1) }),
      Promise.resolve({ ok: true, data: bootstrap(2) }), Promise.resolve({ ok: true, data: bootstrap(3) }),
      Promise.resolve({ ok: true, data: bootstrap(4, true) }));
    api.getDialogue.and.returnValues(Promise.resolve(arrival), Promise.resolve(machine));
    api.completeDialogue.and.returnValues(Promise.resolve({ dialogueId: arrival.id, choiceId: 'remember',
      response: 'Remembered.', knowledge: { codexId: 'codex.mystic_cave.whim_arrival', outcome: 'granted' },
      playerRevision: 2 }), Promise.resolve({ dialogueId: machine.id, choiceId: 'remember', response: 'Remembered.',
      knowledge: { codexId: 'codex.mystic_cave.wrong_machine', outcome: 'granted' }, playerRevision: 3 }));
    api.provisionOnboarding.and.resolveTo({ unit_ids: ['11', '12', '13', '14'], die_ids: ['21', '22', '23', '24'], active_squad_id: '31',
      player_revision: 4, onboarding_status: 'complete' });

    expect((await flow.refresh()).kind).toBe('dialogue');
    await flow.choose('remember');
    expect(flow.view.kind).toBe('dialogue');
    if (flow.view.kind === 'dialogue') expect(flow.view.dialogue.id).toBe(machine.id);
    expect(api.getDialogue.calls.allArgs()).toEqual([[arrival.id], [machine.id]]);
    expect(api.provisionOnboarding).not.toHaveBeenCalled();
    await flow.choose('remember');
    expect(flow.view.kind).toBe('provision');
    expect(api.provisionOnboarding).not.toHaveBeenCalled();
    await flow.provision();
    expect(flow.view.kind).toBe('complete');
    expect(store.bootstrap?.active_squad?.id).toBe('31');
    expect(store.playerRevision).toBe(4);
    expect(store.bootstrap?.progression.unlock_ids).not.toContain('unlock.capability.wrong_machine_access');
  });

  it('resumes required, in-progress, ready, and completed states from fresh reads', async () => {
    for (const [status, dialogue, active] of [
      [required, arrival, false], [progress, machine, false], [ready, null, false], [complete, null, true],
    ] as const) {
      const { api, flow } = harness();
      api.getOnboarding.and.resolveTo(status);
      api.getBootstrap.and.resolveTo({ ok: true, data: bootstrap(1, active) });
      if (dialogue) api.getDialogue.and.resolveTo(dialogue);
      const view = await flow.refresh();
      expect(view.kind).toBe(status.status === 'complete' ? 'complete'
        : status.status === 'ready_to_provision' ? 'provision' : 'dialogue');
      if (dialogue && view.kind === 'dialogue') expect(view.dialogue.id).toBe(status.dialogue_id!);
      expect(api.completeDialogue).not.toHaveBeenCalled();
      expect(api.provisionOnboarding).not.toHaveBeenCalled();
    }
  });

  it('keeps a failed onboarding read retryable without choosing a local step', async () => {
    const { api, flow } = harness();
    api.getOnboarding.and.returnValues(Promise.reject(new RuntimeApiError('network')),
      Promise.resolve(required));
    api.getBootstrap.and.resolveTo({ ok: true, data: bootstrap(1) });
    api.getDialogue.and.resolveTo(arrival);
    expect((await flow.refresh()).kind).toBe('error');
    expect((await flow.refresh()).kind).toBe('dialogue');
    expect(api.getDialogue).toHaveBeenCalledOnceWith(arrival.id);
    expect(api.completeDialogue).not.toHaveBeenCalled();
  });

  it('retains an ambiguous dialogue key for retry without advancing locally', async () => {
    const { api, flow } = harness();
    api.getOnboarding.and.returnValues(Promise.resolve(required), Promise.resolve(required), Promise.resolve(progress));
    api.getBootstrap.and.returnValues(Promise.resolve({ ok: true, data: bootstrap(1) }),
      Promise.resolve({ ok: true, data: bootstrap(1) }), Promise.resolve({ ok: true, data: bootstrap(2) }));
    api.getDialogue.and.returnValues(Promise.resolve(arrival), Promise.resolve(arrival), Promise.resolve(machine));
    api.completeDialogue.and.returnValues(Promise.reject(new RuntimeApiError('network')),
      Promise.resolve({ dialogueId: arrival.id, choiceId: 'remember', response: 'Remembered.',
        knowledge: { codexId: 'codex.mystic_cave.whim_arrival', outcome: 'granted' }, playerRevision: 2 }));
    await flow.refresh();
    await flow.choose('remember');
    expect(flow.view.kind).toBe('dialogue');
    if (flow.view.kind === 'dialogue') expect(flow.view.message).toContain('retry');
    await flow.choose('remember');
    expect(api.completeDialogue.calls.count()).toBe(2);
    expect(api.completeDialogue.calls.argsFor(0)[3]).toBe(api.completeDialogue.calls.argsFor(1)[3]);
    expect(flow.view.kind).toBe('dialogue');
    if (flow.view.kind === 'dialogue') expect(flow.view.dialogue.id).toBe(machine.id);
  });

  it('keeps provisioning explicit and retries an ambiguous result with its original key', async () => {
    const { api, flow, store } = harness();
    api.getOnboarding.and.returnValues(Promise.resolve(ready), Promise.resolve(ready), Promise.resolve(complete));
    api.getBootstrap.and.returnValues(Promise.resolve({ ok: true, data: bootstrap(1) }),
      Promise.resolve({ ok: true, data: bootstrap(1) }), Promise.resolve({ ok: true, data: bootstrap(2, true) }));
    api.provisionOnboarding.and.returnValues(Promise.reject(new RuntimeApiError('network')),
      Promise.resolve({ unit_ids: ['11', '12', '13', '14'], die_ids: ['21', '22', '23', '24'], active_squad_id: '31', player_revision: 2,
        onboarding_status: 'complete' }));
    expect((await flow.refresh()).kind).toBe('provision');
    expect(api.provisionOnboarding).not.toHaveBeenCalled();
    await flow.provision();
    expect(flow.view.kind).toBe('provision');
    if (flow.view.kind === 'provision') expect(flow.view.message).toContain('retry');
    expect(store.bootstrap?.active_squad).toBeNull();
    await flow.provision();
    expect(api.provisionOnboarding.calls.argsFor(0)[1]).toBe(api.provisionOnboarding.calls.argsFor(1)[1]);
    expect(flow.view.kind).toBe('complete');
    expect(store.bootstrap?.active_squad?.id).toBe('31');
  });

  it('fails closed when an older current dialogue read arrives after a successful completion', async () => {
    const { api, flow } = harness();
    api.getOnboarding.and.resolveTo(required);
    api.getBootstrap.and.returnValues(Promise.resolve({ ok: true, data: bootstrap(1) }),
      Promise.resolve({ ok: true, data: bootstrap(2) }));
    api.getDialogue.and.resolveTo(arrival);
    api.completeDialogue.and.resolveTo({ dialogueId: arrival.id, choiceId: 'remember', response: 'Remembered.',
      knowledge: { codexId: 'codex.mystic_cave.whim_arrival', outcome: 'granted' }, playerRevision: 2 });
    await flow.refresh();
    await flow.choose('remember');
    expect(flow.view.kind).toBe('error');
    expect(api.getDialogue).toHaveBeenCalledTimes(1);
  });

  it('reconciles a revision jump through a fresh bootstrap instead of leaving the scene submitting', async () => {
    const { api, flow, store } = harness();
    api.getOnboarding.and.returnValues(Promise.resolve(required), Promise.resolve(progress));
    api.getBootstrap.and.returnValues(Promise.resolve({ ok: true, data: bootstrap(1) }),
      Promise.resolve({ ok: true, data: bootstrap(3) }));
    api.getDialogue.and.returnValues(Promise.resolve(arrival), Promise.resolve(machine));
    api.completeDialogue.and.resolveTo({ dialogueId: arrival.id, choiceId: 'remember', response: 'Remembered.',
      knowledge: { codexId: 'codex.mystic_cave.whim_arrival', outcome: 'granted' }, playerRevision: 3 });
    await flow.refresh();
    await flow.choose('remember');
    expect(flow.view.kind).toBe('dialogue');
    expect(store.playerRevision).toBe(3);
  });

  it('ignores an older read and refuses a regressed bootstrap after provisioning', async () => {
    const { api, flow } = harness();
    let resolveOld!: (status: OnboardingStatus) => void;
    api.getOnboarding.and.returnValues(new Promise<OnboardingStatus>((resolve) => { resolveOld = resolve; }),
      Promise.resolve(complete));
    api.getBootstrap.and.resolveTo({ ok: true, data: bootstrap(2, true) });
    const old = flow.refresh();
    expect((await flow.refresh()).kind).toBe('complete');
    resolveOld(required);
    await old;
    expect(flow.view.kind).toBe('complete');

    const second = harness();
    second.api.getOnboarding.and.resolveTo(complete);
    second.api.getBootstrap.and.resolveTo({ ok: true, data: bootstrap(1, true) });
    expect((await second.flow.refresh(2)).kind).toBe('error');
  });
});
