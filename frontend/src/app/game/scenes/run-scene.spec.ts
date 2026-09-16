import { GameBootstrapData } from '../runtime/game-store';
import { ClientContentLoader, ClientContentRegistry } from '../runtime/client-content-registry';
import { RuntimeApiClient, RuntimeApiError } from '../runtime/runtime-api-client';
import { RuntimeStartup } from '../runtime/runtime-startup';
import { RuntimeViewport, calculateRuntimeViewport } from '../runtime/runtime-viewport';
import { GAME_SCENE_KEY, RUN_SCENE_KEY, RunScene, RuntimeLifecycleState, nextSceneForStartup, runShellLayout } from './runtime-scenes';

describe('RunScene lifecycle shell', () => {
  it('routes startup by the compact active-run summary', () => {
    expect(nextSceneForStartup({ status: 'ready' }, false)).toBe(GAME_SCENE_KEY);
    expect(nextSceneForStartup({ status: 'ready' }, true)).toBe(RUN_SCENE_KEY);
  });

  it('keeps the lifecycle panel inside safe bounds at Compact, Standard, and Wide', () => {
    for (const [width, height, coarsePointer] of [[844, 390, true], [1600, 900, false], [2560, 1080, false]] as const) {
      const snapshot = calculateRuntimeViewport({ cssWidth: width, cssHeight: height,
        safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer, noHover: coarsePointer });
      const layout = runShellLayout(snapshot);
      expect(layout.x).toBeGreaterThanOrEqual(snapshot.safeBounds.x);
      expect(layout.y).toBeGreaterThanOrEqual(snapshot.safeBounds.y);
      expect(layout.x + layout.width).toBeLessThanOrEqual(snapshot.safeBounds.right);
      expect(layout.y + layout.height).toBeLessThanOrEqual(snapshot.safeBounds.bottom);
    }
  });

  it('returns to the real GameScene without clearing the active run or runtime store', () => {
    const startup = new RuntimeStartup(); const bootstrap = activeBootstrap(); startup.store.hydrateBootstrap(bootstrap);
    const scene = new RunScene(new RuntimeLifecycleState(), startup, new RuntimeViewport());
    const start = jasmine.createSpy('start');
    (scene as unknown as { scene: { start: jasmine.Spy } }).scene = { start };
    const store = startup.store;
    scene.returnToCamp();
    expect(start).toHaveBeenCalledOnceWith(GAME_SCENE_KEY);
    expect(startup.store).toBe(store); expect(store.bootstrap?.active_run?.id).toBe('41');
  });

  it('keeps node selection presentation-only and preserves it across reflow', async () => {
    const { scene, startup, api, sceneStart } = await readyHarness();
    const current = startup.store.currentRun.data;
    spyOn<any>(scene, 'render').and.stub();
    scene.selectNode('10');
    expect(scene.selectedMapNodeId).toBe('10');
    scene.reflow();
    expect(scene.selectedMapNodeId).toBe('10');
    expect(startup.store.currentRun.data).toBe(current);
    expect(api.abandonRun).not.toHaveBeenCalled();
    expect(sceneStart).not.toHaveBeenCalled();
  });

  it('opens and cancels explicit abandon confirmation without mutating authority', async () => {
    const { scene, startup, api, sceneStart } = await readyHarness();
    const bootstrap = startup.store.bootstrap;
    const current = startup.store.currentRun.data;
    spyOn<any>(scene, 'render').and.stub();
    scene.openAbandonConfirmation();
    expect(scene.abandonActionState).toBe('confirming');
    scene.reflow();
    expect(scene.abandonActionState).toBe('confirming');
    scene.returnToCamp();
    expect(sceneStart).not.toHaveBeenCalled();
    expect(startup.store.bootstrap).toBe(bootstrap);
    expect(startup.store.currentRun.data).toBe(current);
    scene.cancelAbandon();
    expect(scene.abandonActionState).toBe('idle');
    expect(api.abandonRun).not.toHaveBeenCalled();
  });

  it('prevents duplicate abandon submissions and enters Camp only after reconciliation', async () => {
    const { scene, startup, api, sceneStart } = await readyHarness();
    const energy = startup.store.bootstrap!.player.energy;
    spyOn<any>(scene, 'render').and.stub();
    let resolve!: (value: abandonResult) => void;
    api.abandonRun.and.returnValue(new Promise((done) => { resolve = done; }));
    scene.openAbandonConfirmation();
    const first = scene.confirmAbandon(); const duplicate = scene.confirmAbandon();
    expect(api.abandonRun).toHaveBeenCalledTimes(1);
    expect(startup.store.bootstrap?.active_run?.id).toBe('41');
    resolve(abandonSuccess()); await Promise.all([first, duplicate]);
    expect(startup.store.bootstrap?.active_run).toBeNull();
    expect(startup.store.currentRun.data).toBeNull();
    expect(startup.store.bootstrap?.player.energy).toBe(energy);
    expect(sceneStart).toHaveBeenCalledOnceWith(GAME_SCENE_KEY);
  });

  it('preserves the run through ambiguous abandon and retries the exact same run ID', async () => {
    const { scene, startup, api, sceneStart } = await readyHarness();
    spyOn<any>(scene, 'render').and.stub();
    api.abandonRun.and.returnValues(Promise.reject(new RuntimeApiError('network')),
      Promise.reject(new RuntimeApiError('malformed-response', 200)),
      Promise.reject(new RuntimeApiError('http', 503)), Promise.resolve(abandonSuccess()));
    scene.openAbandonConfirmation();
    await scene.confirmAbandon(); await scene.confirmAbandon(); await scene.confirmAbandon();
    expect(scene.abandonActionState).toBe('retryable');
    expect(startup.store.bootstrap?.active_run?.id).toBe('41');
    expect(startup.store.currentRun.data?.id).toBe('41');
    await scene.confirmAbandon();
    expect(api.abandonRun.calls.allArgs().map((args) => args[0])).toEqual(['41', '41', '41', '41']);
    expect(sceneStart).toHaveBeenCalledOnceWith(GAME_SCENE_KEY);
  });

  it('preserves the active run after a definitive abandon rejection', async () => {
    const { scene, startup, api, sceneStart } = await readyHarness();
    spyOn<any>(scene, 'render').and.stub();
    api.abandonRun.and.rejectWith(new RuntimeApiError('http', 409, 'run_lifecycle_conflict'));
    scene.openAbandonConfirmation(); await scene.confirmAbandon();
    expect(scene.abandonActionState).toBe('rejected');
    expect(startup.store.bootstrap?.active_run?.id).toBe('41');
    expect(startup.store.currentRun.data?.id).toBe('41');
    expect(sceneStart).not.toHaveBeenCalled();
  });

  it('requires reload and blocks further mutation when valid success cannot reconcile', async () => {
    const { scene, startup, api, sceneStart } = await readyHarness();
    spyOn<any>(scene, 'render').and.stub();
    api.abandonRun.and.resolveTo({ ...abandonSuccess(), run: { ...abandonSuccess().run, id: '42' } });
    scene.openAbandonConfirmation(); await scene.confirmAbandon();
    expect(scene.abandonActionState).toBe('recovery-required');
    expect(startup.store.bootstrap?.active_run?.id).toBe('41');
    await scene.confirmAbandon();
    expect(api.abandonRun).toHaveBeenCalledTimes(1);
    expect(sceneStart).not.toHaveBeenCalled();
  });
});

type abandonResult = Awaited<ReturnType<RuntimeApiClient['abandonRun']>>;

function abandonSuccess(): abandonResult {
  return { run: { id: '41', regionId: 'region.the_farm', squadId: '31', status: 'abandoned',
    endedAt: '2026-09-13T12:04:00Z' }, activeRun: null, playerRevision: 8 };
}

async function readyHarness() {
  const api = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getCurrentRun', 'abandonRun']);
  const startup = new RuntimeStartup(api, {} as ClientContentLoader);
  const registry = content();
  startup.store.hydrateBootstrap(activeBootstrap());
  (startup as unknown as { activeContentRegistry: ClientContentRegistry }).activeContentRegistry = registry;
  (startup as unknown as { currentState: { status: 'ready' } }).currentState = { status: 'ready' };
  api.getCurrentRun.and.resolveTo({ run: currentRun(), playerRevision: 7 });
  await startup.store.loadCurrentRun(api, registry);
  const viewport = new RuntimeViewport();
  const scene = new RunScene(new RuntimeLifecycleState(), startup, viewport);
  const sceneStart = jasmine.createSpy('start');
  (scene as unknown as { scene: { start: jasmine.Spy } }).scene = { start: sceneStart };
  return { scene, startup, api, viewport, sceneStart };
}

function content(): ClientContentRegistry {
  return new ClientContentRegistry({ revision: 'a'.repeat(64), content: { gameplay: { run_energy_cost: 10 },
    regions: { 'region.the_farm': { id: 'region.the_farm', display_name: 'The Farm', art_key: 'farm' } },
    kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {},
    run_node_types: { 'run_node_type.combat': { id: 'run_node_type.combat', display_name: 'Combat', description: 'Fight.', icon_key: 'combat' } },
  } });
}

function currentRun() {
  return { id: '41', regionId: 'region.the_farm', squadId: '31', status: 'active' as const,
    createdAt: '2026-09-13T12:00:00Z', nodes: [{ id: '10', nodeIndex: 0, nodeTypeId: 'run_node_type.combat',
      status: 'available' as const, completedAt: null, battleId: null, position: { column: 0, row: 1 } }], edges: [],
    units: [{ unitId: '11', currentHp: null }] };
}

function activeBootstrap(): GameBootstrapData {
  return { account: { id: '1', display_name: 'Goblin', role: 'user' },
    player: { teeth: 0, raw_chaos: 0, player_revision: 7, energy: { current: 40, normal_max: 50,
      regeneration_per_hour: 12, regeneration_interval_seconds: 300, last_regeneration_at: '2026-09-13T12:00:00Z',
      next_regeneration_at: null, fully_regenerated_at: null } }, session: { authenticated: true, csrf_token: 'csrf' },
    server_time: '2026-09-13T12:00:00Z', content_revision: 'a'.repeat(64), progression: { unlock_ids: [] },
    active_squad: { id: '31', name: 'Raiders', is_active: true, formation: ['11', null, null, null, null, null, null, null, null],
      units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' }] },
    active_run: { id: '41', region_id: 'region.the_farm', squad_id: '31', status: 'active' } };
}
