import { GameBootstrapData, GameStore } from './game-store';
import { PostFarmWhimFlow } from './post-farm-whim-flow';
import { RuntimeApiClient, RuntimeApiError } from './runtime-api-client';

const dialogueId = 'dialogue.camp.whim_machine_search';
const dialogue = { id: dialogueId, title: "The Whim's Missing Machine",
  body: 'The Wrong Machine is missing. Follow the kobolds into the Mountains.',
  choices: [{ id: 'follow_lead', label: 'Follow the lead' }] };

function bootstrap(revision: number, pending: boolean): GameBootstrapData {
  return {
    account: { id: '7', display_name: 'Warchief', role: 'user' },
    player: { teeth: 0, raw_chaos: 0, player_revision: revision, energy: {
      current: 50, normal_max: 50, regeneration_per_hour: 12, regeneration_interval_seconds: 300,
      last_regeneration_at: '2026-01-01T00:00:00Z', next_regeneration_at: null, fully_regenerated_at: null } },
    session: { authenticated: true, csrf_token: 'csrf' }, server_time: '2026-01-01T00:00:00Z',
    content_revision: 'a'.repeat(64), progression: {
      unlock_ids: pending ? ['unlock.region.the_farm', 'unlock.capability.shop_access']
        : ['unlock.region.the_farm', 'unlock.capability.shop_access', 'unlock.region.mountains'],
      available_region_ids: pending ? ['region.the_farm'] : ['region.the_farm', 'region.mountains'],
      camp_whim_dialogue_id: pending ? dialogueId : null,
    }, active_squad: null, active_run: null,
  };
}

function harness(pending = true) {
  const store = new GameStore();
  store.hydrateBootstrap(bootstrap(3, pending));
  const api = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getBootstrap', 'getDialogue', 'completeDialogue']);
  const flow = new PostFarmWhimFlow(api, store);
  return { store, api, flow };
}

describe('PostFarmWhimFlow', () => {
  it('restores a pending server-owned conversation after a fresh bootstrap read', async () => {
    const { api, flow } = harness();
    api.getBootstrap.and.resolveTo({ ok: true, data: bootstrap(3, true) });
    api.getDialogue.and.resolveTo(dialogue);
    await flow.refresh();
    expect(flow.view.kind).toBe('dialogue');
    expect(api.getDialogue).toHaveBeenCalledOnceWith(dialogueId);
    expect(api.completeDialogue).not.toHaveBeenCalled();
  });

  it('completes one choice and uses refreshed bootstrap for Mountains availability', async () => {
    const { api, flow, store } = harness();
    api.getBootstrap.and.returnValues(Promise.resolve({ ok: true, data: bootstrap(3, true) }),
      Promise.resolve({ ok: true, data: bootstrap(4, false) }));
    api.getDialogue.and.resolveTo(dialogue);
    api.completeDialogue.and.resolveTo({ dialogueId, choiceId: 'follow_lead', response: 'Follow the trail.',
      knowledge: { codexId: 'codex.camp.wrong_machine_search', outcome: 'granted' },
      unlock: { unlockId: 'unlock.region.mountains', outcome: 'granted' }, playerRevision: 4 });
    await flow.refresh();
    await flow.choose('follow_lead');
    expect(flow.view.kind).toBe('complete');
    expect(store.bootstrap?.progression.available_region_ids).toContain('region.mountains');
    expect(store.bootstrap?.progression.camp_whim_dialogue_id).toBeNull();
    expect(store.bootstrap?.progression.unlock_ids).not.toContain('unlock.capability.wrong_machine_access');
  });

  it('retains the same key after an uncertain completion until the server confirms it', async () => {
    const { api, flow } = harness();
    api.getBootstrap.and.returnValues(Promise.resolve({ ok: true, data: bootstrap(3, true) }),
      Promise.resolve({ ok: true, data: bootstrap(3, true) }),
      Promise.resolve({ ok: true, data: bootstrap(4, false) }));
    api.getDialogue.and.resolveTo(dialogue);
    api.completeDialogue.and.returnValues(Promise.reject(new RuntimeApiError('network')),
      Promise.resolve({ dialogueId, choiceId: 'follow_lead', response: 'Follow the trail.',
        knowledge: { codexId: 'codex.camp.wrong_machine_search', outcome: 'granted' },
        unlock: { unlockId: 'unlock.region.mountains', outcome: 'granted' }, playerRevision: 4 }));
    await flow.refresh();
    await flow.choose('follow_lead');
    expect(flow.view.kind).toBe('retryable');
    await flow.choose('follow_lead');
    expect(api.completeDialogue.calls.count()).toBe(2);
    expect(api.completeDialogue.calls.argsFor(0)[3]).toBe(api.completeDialogue.calls.argsFor(1)[3]);
    expect(flow.view.kind).toBe('complete');
  });

  it('does not read the Whim dialogue when server state has no pending interaction', async () => {
    const { api, flow } = harness(false);
    api.getBootstrap.and.resolveTo({ ok: true, data: bootstrap(3, false) });
    await flow.refresh();
    expect(flow.view.kind).toBe('complete');
    expect(api.getDialogue).not.toHaveBeenCalled();
  });
});
