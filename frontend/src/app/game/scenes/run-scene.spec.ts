import { GameBootstrapData } from '../runtime/game-store';
import { ClientContentLoader, ClientContentRegistry } from '../runtime/client-content-registry';
import { RuntimeApiClient, RuntimeApiError } from '../runtime/runtime-api-client';
import { RuntimeStartup } from '../runtime/runtime-startup';
import { RuntimeViewport, calculateRuntimeViewport } from '../runtime/runtime-viewport';
import { RunNodeResolutionAttempt } from '../runtime/run-node-resolution-attempt';
import { CurrentRun } from '../runtime/run-contracts';
import { BATTLE_SCENE_KEY, GAME_SCENE_KEY, RUN_SCENE_KEY, RunScene, RuntimeLifecycleState, nextSceneForStartup, runShellLayout } from './runtime-scenes';

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

  it('records the retained battle before navigation and only marks the current-run cache stale', async () => {
    const { scene, startup, api, sceneStart } = await readyHarness();
    spyOn<any>(scene, 'render').and.stub();
    const priorRun = startup.store.currentRun.data;
    api.resolveRunNode.and.resolveTo(resolutionSuccess());
    scene.selectNode('10');

    await scene.activateSelectedCombat();

    expect(api.resolveRunNode).toHaveBeenCalledOnceWith('41', '10', 'csrf', 'run-node:fixed');
    expect(startup.battlePresentation.marker).toEqual(jasmine.objectContaining({ battleId: '81', runId: '41', runNodeId: '10' }));
    expect(startup.store.currentRun).toEqual(jasmine.objectContaining({ status: 'stale', data: priorRun }));
    expect(startup.store.currentRun.data?.nodes[0].status).toBe('available');
    expect(startup.store.currentRun.data?.nodes[0].battleId).toBeNull();
    expect(sceneStart).toHaveBeenCalledOnceWith(BATTLE_SCENE_KEY);
  });

  it('prevents simultaneous Fight submissions without changing cached run facts', async () => {
    const { scene, startup, api } = await readyHarness();
    spyOn<any>(scene, 'render').and.stub(); scene.selectNode('10');
    const before = startup.store.currentRun.data;
    let complete!: (result: ReturnType<typeof resolutionSuccess>) => void;
    api.resolveRunNode.and.returnValue(new Promise((resolve) => { complete = resolve; }));
    const first = scene.activateSelectedCombat(); const duplicate = scene.activateSelectedCombat();
    expect(api.resolveRunNode).toHaveBeenCalledTimes(1);
    expect(startup.store.currentRun.data).toBe(before);
    complete(resolutionSuccess()); await Promise.all([first, duplicate]);
  });

  it('retries a Combat semantic mismatch with the exact original attempt identity', async () => {
    const createKey = jasmine.createSpy('createKey').and.returnValues('run-node:first', 'run-node:second');
    const { scene, startup, api, sceneStart } = await readyHarness(currentRun(), createKey);
    spyOn<any>(scene, 'render').and.stub(); scene.selectNode('10');
    api.resolveRunNode.and.returnValues(Promise.resolve(lootResolutionSuccess()), Promise.resolve(resolutionSuccess()));

    await scene.activateSelectedCombat();
    expect(scene.combatActionState).toBe('retryable');
    expect(startup.battlePresentation.marker).toBeNull();
    expect(sceneStart).not.toHaveBeenCalled();
    await scene.activateSelectedCombat();

    expect(createKey).toHaveBeenCalledTimes(1);
    expect(api.resolveRunNode.calls.allArgs().map((args) => args[3])).toEqual(['run-node:first', 'run-node:first']);
    expect(sceneStart).toHaveBeenCalledOnceWith(BATTLE_SCENE_KEY);
  });

  it('replays an already-completed combat directly without a resolution POST', async () => {
    const completed: any = currentRun();
    completed.nodes[0] = { ...completed.nodes[0], status: 'completed', completedAt: '2026-09-16T12:00:00Z', battleId: '81' };
    const { scene, startup, api, sceneStart } = await readyHarness(completed);
    spyOn<any>(scene, 'render').and.stub(); scene.selectNode('10');

    await scene.activateSelectedCombat();

    expect(api.resolveRunNode).not.toHaveBeenCalled();
    expect(startup.battlePresentation.marker?.battleId).toBe('81');
    expect(sceneStart).toHaveBeenCalledOnceWith(BATTLE_SCENE_KEY);
  });

  it('adopts exact Loot wallet facts and reconciles Rest availability through current-run GET', async () => {
    const { scene, startup, api } = await readyHarness(lootAvailableRun());
    spyOn<any>(scene, 'render').and.stub();
    api.resolveRunNode.and.resolveTo(lootResolutionSuccess());
    api.getCurrentRun.and.resolveTo({ run: restAvailableRun(), playerRevision: 8 });
    scene.selectNode('11');

    await scene.activateSelectedNonCombat();

    expect(api.resolveRunNode).toHaveBeenCalledOnceWith('41', '11', 'csrf', 'run-node:fixed');
    expect(api.getCurrentRun).toHaveBeenCalledTimes(2);
    expect(startup.store.bootstrap?.player).toEqual(jasmine.objectContaining({ teeth: 8, player_revision: 8 }));
    expect(startup.store.currentRun.data?.nodes.find((node) => node.id === '12')?.status).toBe('available');
    expect(scene.resolvedNodeSyncState).toBe('succeeded');
  });

  it('retries Loot run, node, and discriminator mismatches with the exact original attempt identity', async () => {
    const createKey = jasmine.createSpy('createKey').and.returnValues('run-node:first', 'run-node:second');
    const { scene, startup, api } = await readyHarness(lootAvailableRun(), createKey);
    spyOn<any>(scene, 'render').and.stub(); scene.selectNode('11');
    const loot = lootResolutionSuccess();
    const wrongRun = { ...loot, run: { ...loot.run, id: '42' } };
    const wrongNode = { ...loot, node: { ...loot.node, id: '99' } };
    const rest = restResolutionSuccess();
    const wrongType = { ...rest, node: { ...rest.node, id: '11' } };
    api.resolveRunNode.and.returnValues(Promise.resolve(wrongRun), Promise.resolve(wrongNode),
      Promise.resolve(wrongType), Promise.resolve(loot));
    api.getCurrentRun.and.resolveTo({ run: restAvailableRun(), playerRevision: 8 });

    for (let index = 0; index < 3; index++) {
      await scene.activateSelectedNonCombat();
      expect(scene.nodeActionState).toBe('retryable');
      expect(startup.store.bootstrap?.player.teeth).toBe(0);
      expect(scene.resolvedNodeSyncState).toBe('idle');
    }
    await scene.activateSelectedNonCombat();

    expect(createKey).toHaveBeenCalledTimes(1);
    expect(api.resolveRunNode.calls.allArgs().map((args) => args[3])).toEqual([
      'run-node:first', 'run-node:first', 'run-node:first', 'run-node:first',
    ]);
    expect(startup.store.bootstrap?.player.teeth).toBe(8);
    expect(scene.resolvedNodeSyncState).toBe('succeeded');
  });

  it('presents Rest facts and reconciles authoritative HP and Boss availability', async () => {
    const { scene, startup, api } = await readyHarness(restAvailableRun());
    spyOn<any>(scene, 'render').and.stub();
    api.resolveRunNode.and.resolveTo(restResolutionSuccess());
    api.getCurrentRun.and.resolveTo({ run: bossAvailableRun(), playerRevision: 8 });
    scene.selectNode('12');

    await scene.activateSelectedNonCombat();

    expect(api.resolveRunNode).toHaveBeenCalledOnceWith('41', '12', 'csrf', 'run-node:fixed');
    expect(startup.store.currentRun.data?.units).toEqual([{ unitId: '11', currentHp: 26 }]);
    expect(startup.store.currentRun.data?.nodes.find((node) => node.id === '13')?.status).toBe('available');
    expect(startup.store.bootstrap?.player.teeth).toBe(0);
  });

  it('retries only current-run synchronization after a committed Loot mutation', async () => {
    const { scene, api } = await readyHarness(lootAvailableRun());
    spyOn<any>(scene, 'render').and.stub();
    api.resolveRunNode.and.resolveTo(lootResolutionSuccess());
    api.getCurrentRun.and.returnValues(Promise.reject(new RuntimeApiError('network')),
      Promise.resolve({ run: restAvailableRun(), playerRevision: 8 }));
    scene.selectNode('11');

    await scene.activateSelectedNonCombat();
    expect(scene.resolvedNodeSyncState).toBe('sync-error');
    await scene.retryNodeSync();

    expect(scene.resolvedNodeSyncState).toBe('succeeded');
    expect(api.resolveRunNode).toHaveBeenCalledTimes(1);
    expect(api.getCurrentRun).toHaveBeenCalledTimes(3);
  });

  it('resolves an available Boss once into retained BattleScene presentation', async () => {
    const { scene, startup, api, sceneStart } = await readyHarness(bossAvailableRun());
    spyOn<any>(scene, 'render').and.stub();
    api.resolveRunNode.and.resolveTo(bossResolutionSuccess());
    scene.selectNode('13'); await scene.activateSelectedCombat();
    expect(api.resolveRunNode).toHaveBeenCalledTimes(1);
    expect(startup.battlePresentation.resolution?.resolutionType).toBe('boss');
    expect(startup.battlePresentation.marker).toEqual(jasmine.objectContaining({ battleId: '82', runNodeId: '13' }));
    expect(sceneStart).toHaveBeenCalledWith(BATTLE_SCENE_KEY);
  });

  it('resolves Exit once, reconciles authoritative bootstrap progression, and enters Camp', async () => {
    const { scene, startup, api, sceneStart } = await readyHarness(bossCompletedRun());
    spyOn<any>(scene, 'render').and.stub(); scene.selectNode('14');
    api.resolveRunNode.and.resolveTo(exitResolutionSuccess());
    api.getBootstrap.and.resolveTo({ ok: true, data: terminalBootstrap() });

    await scene.activateSelectedNonCombat();

    expect(api.resolveRunNode).toHaveBeenCalledOnceWith('41', '14', 'csrf', 'run-node:fixed');
    expect(api.getBootstrap).toHaveBeenCalledTimes(1);
    expect(startup.store.bootstrap?.active_run).toBeNull();
    expect(startup.store.bootstrap?.active_squad?.units[0]).toEqual(jasmine.objectContaining({ level: 2, xp: 6 }));
    expect(startup.store.bootstrap?.progression.unlock_ids).toEqual(['unlock.region.mountains']);
    expect(sceneStart).toHaveBeenCalledOnceWith(GAME_SCENE_KEY);
  });

  it('locks every ordinary run interaction while committed Exit bootstrap synchronization is pending', async () => {
    const { scene, startup, api, sceneStart } = await readyHarness(bossCompletedRun());
    spyOn<any>(scene, 'render').and.stub(); scene.selectNode('14');
    api.resolveRunNode.and.resolveTo(exitResolutionSuccess());
    let finishBootstrap!: (value: unknown) => void;
    api.getBootstrap.and.returnValue(new Promise((resolve) => { finishBootstrap = resolve; }));

    const resolving = scene.activateSelectedNonCombat();
    await Promise.resolve(); await Promise.resolve();
    expect(scene.exitReconciliationLocked).toBeTrue();
    expect(scene.resolvedNodeSyncState).toBe('syncing');

    scene.returnToCamp(); scene.openAbandonConfirmation(); scene.selectNode('13');
    expect(scene.selectedMapNodeId).toBe('14');
    expect(scene.abandonActionState).toBe('idle');
    (scene as unknown as { selectedNodeId: string }).selectedNodeId = '13';
    await scene.activateSelectedCombat();
    (scene as unknown as { selectedNodeId: string }).selectedNodeId = '14';
    await scene.activateSelectedNonCombat();

    expect(api.resolveRunNode).toHaveBeenCalledTimes(1);
    expect(api.abandonRun).not.toHaveBeenCalled();
    expect(startup.battlePresentation.marker).toBeNull();
    expect(sceneStart).not.toHaveBeenCalled();
    finishBootstrap({ ok: true, data: terminalBootstrap() }); await resolving;
    expect(sceneStart).toHaveBeenCalledOnceWith(GAME_SCENE_KEY);
  });

  it('retries only bootstrap synchronization after a committed Exit result', async () => {
    const { scene, startup, api, sceneStart } = await readyHarness(bossCompletedRun());
    spyOn<any>(scene, 'render').and.stub(); scene.selectNode('14');
    api.resolveRunNode.and.resolveTo(exitResolutionSuccess());
    api.getBootstrap.and.returnValues(Promise.reject(new RuntimeApiError('network')),
      Promise.resolve({ ok: true, data: terminalBootstrap() }));

    await scene.activateSelectedNonCombat();
    expect(scene.resolvedNodeSyncState).toBe('sync-error'); expect(sceneStart).not.toHaveBeenCalled();
    expect(scene.exitReconciliationLocked).toBeTrue();
    scene.returnToCamp(); scene.openAbandonConfirmation(); scene.selectNode('13');
    expect(scene.selectedMapNodeId).toBe('14'); expect(scene.abandonActionState).toBe('idle');
    (scene as unknown as { selectedNodeId: string }).selectedNodeId = '13';
    await scene.activateSelectedCombat();
    (scene as unknown as { selectedNodeId: string }).selectedNodeId = '14';
    expect(api.resolveRunNode).toHaveBeenCalledTimes(1);
    expect(api.abandonRun).not.toHaveBeenCalled();
    expect(startup.battlePresentation.marker).toBeNull(); expect(sceneStart).not.toHaveBeenCalled();
    await scene.retryNodeSync();

    expect(api.resolveRunNode).toHaveBeenCalledTimes(1);
    expect(api.getBootstrap).toHaveBeenCalledTimes(2);
    expect(sceneStart).toHaveBeenCalledOnceWith(GAME_SCENE_KEY);
  });

  it('retries Exit discriminator and identity mismatches with one attempt key and suppresses duplicates', async () => {
    const createKey = jasmine.createSpy('createKey').and.returnValues('exit:first', 'exit:second');
    const { scene, api, sceneStart } = await readyHarness(bossCompletedRun(), createKey);
    spyOn<any>(scene, 'render').and.stub(); scene.selectNode('14');
    const exit = exitResolutionSuccess();
    api.resolveRunNode.and.returnValues(Promise.resolve({ ...exit, run: { ...exit.run, id: '42' } }),
      Promise.resolve({ ...exit, node: { ...exit.node, id: '99' } }), Promise.resolve(restResolutionSuccess()),
      Promise.resolve(exit));
    api.getBootstrap.and.resolveTo({ ok: true, data: terminalBootstrap() });

    for (let index = 0; index < 3; index++) await scene.activateSelectedNonCombat();
    const success = scene.activateSelectedNonCombat(); const duplicate = scene.activateSelectedNonCombat();
    await Promise.all([success, duplicate]);

    expect(createKey).toHaveBeenCalledTimes(1);
    expect(api.resolveRunNode.calls.allArgs().map((args) => args[3])).toEqual([
      'exit:first', 'exit:first', 'exit:first', 'exit:first',
    ]);
    expect(api.getBootstrap).toHaveBeenCalledTimes(1);
    expect(sceneStart).toHaveBeenCalledOnceWith(GAME_SCENE_KEY);
  });

  it('retries a Boss semantic mismatch with the original key and suppresses duplicate Fight clicks', async () => {
    const createKey = jasmine.createSpy('createKey').and.returnValues('boss:first', 'boss:second');
    const { scene, startup, api, sceneStart } = await readyHarness(bossAvailableRun(), createKey);
    spyOn<any>(scene, 'render').and.stub(); scene.selectNode('13');
    let finish!: (value: ReturnType<typeof resolutionSuccess>) => void;
    api.resolveRunNode.and.returnValues(Promise.resolve(resolutionSuccess()),
      new Promise((resolve) => { finish = resolve; }) as any);

    await scene.activateSelectedCombat();
    expect(scene.combatActionState).toBe('retryable');
    const retry = scene.activateSelectedCombat(); const duplicate = scene.activateSelectedCombat();
    expect(api.resolveRunNode).toHaveBeenCalledTimes(2);
    finish(bossResolutionSuccess() as any); await Promise.all([retry, duplicate]);

    expect(createKey).toHaveBeenCalledTimes(1);
    expect(api.resolveRunNode.calls.allArgs().map((args) => args[3])).toEqual(['boss:first', 'boss:first']);
    expect(startup.battlePresentation.marker).toEqual(jasmine.objectContaining({ battleId: '82', runNodeId: '13' }));
    expect(sceneStart).toHaveBeenCalledOnceWith(BATTLE_SCENE_KEY);
  });

  it('replays a completed Boss from persisted battle identity without resolving it again', async () => {
    const { scene, startup, api, sceneStart } = await readyHarness(bossCompletedRun());
    spyOn<any>(scene, 'render').and.stub(); scene.selectNode('13');

    await scene.activateSelectedCombat();

    expect(api.resolveRunNode).not.toHaveBeenCalled();
    expect(startup.battlePresentation.marker).toEqual(jasmine.objectContaining({
      accountId: '1', battleId: '82', runId: '41', runNodeId: '13',
    }));
    expect(sceneStart).toHaveBeenCalledOnceWith(BATTLE_SCENE_KEY);
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

async function readyHarness(run: CurrentRun = currentRun(), createKey: () => string = () => 'run-node:fixed') {
  const api = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getBootstrap', 'getCurrentRun', 'abandonRun', 'resolveRunNode']);
  const startup = new RuntimeStartup(api, {} as ClientContentLoader);
  const registry = content();
  startup.store.hydrateBootstrap(activeBootstrap());
  (startup as unknown as { activeContentRegistry: ClientContentRegistry }).activeContentRegistry = registry;
  (startup as unknown as { currentState: { status: 'ready' } }).currentState = { status: 'ready' };
  api.getCurrentRun.and.resolveTo({ run, playerRevision: 7 });
  await startup.store.loadCurrentRun(api, registry);
  const viewport = new RuntimeViewport();
  const scene = new RunScene(new RuntimeLifecycleState(), startup, viewport, new RunNodeResolutionAttempt(createKey));
  const sceneStart = jasmine.createSpy('start');
  (scene as unknown as { scene: { start: jasmine.Spy } }).scene = { start: sceneStart };
  return { scene, startup, api, viewport, sceneStart };
}

function resolutionSuccess() {
  return { resolutionType: 'combat' as const, battle: { id: '81', outcome: 'victory' as const, engineVersion: 1 as const, playbackVersion: 1 as const,
    endingRound: 3, endingTick: 41 }, node: { id: '10', status: 'completed' as const,
    completedAt: '2026-09-16T12:00:00Z' }, newlyAvailableNodeIds: ['11'], terminalPlayerHp: { '11': 7 },
    run: { id: '41', status: 'active' as const, endedAt: null }, playerRevision: 8 };
}

function bossResolutionSuccess() {
  return { resolutionType: 'boss' as const, battle: { id: '82', outcome: 'victory' as const, engineVersion: 1 as const,
      playbackVersion: 1 as const, endingRound: 4, endingTick: 62 },
    node: { id: '13', status: 'completed' as const, completedAt: '2026-09-16T12:03:00Z' },
    newlyAvailableNodeIds: ['14'], terminalPlayerHp: { '11': 9 }, run: { id: '41', status: 'active' as const, endedAt: null },
    rewards: { unitXp: [{ unitId: '11', amount: 16, levelBefore: 1, xpBefore: 90, levelAfter: 2, xpAfter: 6 }],
      unlocks: [{ unlockId: 'unlock.region.mountains', outcome: 'granted' as const }] }, playerRevision: 9 };
}

function lootResolutionSuccess() {
  return { resolutionType: 'loot' as const, wallet: { teeth: 8 },
    grantedRewards: [{ rewardType: 'currency' as const, currencyId: 'teeth' as const, amount: 8 }] as const,
    node: { id: '11', status: 'completed' as const, completedAt: '2026-09-16T12:01:00Z' },
    newlyAvailableNodeIds: ['12'], run: { id: '41', status: 'active' as const, endedAt: null }, playerRevision: 8 };
}

function restResolutionSuccess() {
  return { resolutionType: 'rest' as const, healing: [{ unitId: '11', hpBefore: 0, hpAfter: 26, maxHp: 26 }],
    node: { id: '12', status: 'completed' as const, completedAt: '2026-09-16T12:02:00Z' },
    newlyAvailableNodeIds: ['13'], run: { id: '41', status: 'active' as const, endedAt: null }, playerRevision: 8 };
}

function exitResolutionSuccess() {
  return { resolutionType: 'exit' as const,
    node: { id: '14', status: 'completed' as const, completedAt: '2026-09-19T12:00:00Z' },
    newlyAvailableNodeIds: [] as const, run: { id: '41', status: 'completed' as const, endedAt: '2026-09-19T12:00:00Z' },
    playerRevision: 10 };
}

function content(): ClientContentRegistry {
  return new ClientContentRegistry({ revision: 'a'.repeat(64), content: { gameplay: { run_energy_cost: 10 },
    regions: { 'region.the_farm': { id: 'region.the_farm', display_name: 'The Farm', art_key: 'farm' } },
    kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {},
    run_node_types: {
      'run_node_type.combat': { id: 'run_node_type.combat', display_name: 'Combat', description: 'Fight.', icon_key: 'combat' },
      'run_node_type.loot': { id: 'run_node_type.loot', display_name: 'Loot', description: 'Collect.', icon_key: 'loot' },
      'run_node_type.rest': { id: 'run_node_type.rest', display_name: 'Rest', description: 'Recover.', icon_key: 'rest' },
      'run_node_type.boss': { id: 'run_node_type.boss', display_name: 'Boss', description: 'Boss.', icon_key: 'boss' },
      'run_node_type.exit': { id: 'run_node_type.exit', display_name: 'Exit', description: 'Exit.', icon_key: 'exit' },
    },
  } });
}

function currentRun(): CurrentRun {
  return { id: '41', regionId: 'region.the_farm', squadId: '31', status: 'active' as const,
    createdAt: '2026-09-13T12:00:00Z', nodes: [{ id: '10', nodeIndex: 0, nodeTypeId: 'run_node_type.combat',
      status: 'available' as const, completedAt: null, battleId: null, position: { column: 0, row: 1 } }], edges: [],
    units: [{ unitId: '11', currentHp: null }] };
}

function farmRun(statuses: readonly ['completed', 'completed' | 'available', 'locked' | 'completed' | 'available', 'locked' | 'available' | 'completed', 'locked' | 'available']): CurrentRun {
  const types = ['combat', 'loot', 'rest', 'boss', 'exit'];
  return { id: '41', regionId: 'region.the_farm', squadId: '31', status: 'active' as const,
    createdAt: '2026-09-13T12:00:00Z', nodes: types.map((type, index) => ({ id: String(10 + index), nodeIndex: index,
      nodeTypeId: `run_node_type.${type}`, status: statuses[index], completedAt: statuses[index] === 'completed'
        ? `2026-09-16T12:0${index}:00Z` : null, battleId: type === 'combat' ? '81' : type === 'boss' && statuses[index] === 'completed' ? '82' : null,
      position: { column: index, row: 1 } })),
    edges: types.slice(0, -1).map((_, index) => ({ fromNodeId: String(10 + index), toNodeId: String(11 + index) })),
    units: [{ unitId: '11', currentHp: 0 }] };
}

function lootAvailableRun() { return farmRun(['completed', 'available', 'locked', 'locked', 'locked']); }
function restAvailableRun() { return farmRun(['completed', 'completed', 'available', 'locked', 'locked']); }
function bossAvailableRun() {
  const run = farmRun(['completed', 'completed', 'completed', 'available', 'locked']);
  return { ...run, units: [{ unitId: '11', currentHp: 26 }] };
}
function bossCompletedRun() { return farmRun(['completed', 'completed', 'completed', 'completed', 'available']); }

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

function terminalBootstrap(): GameBootstrapData {
  const value = activeBootstrap();
  return { ...value, player: { ...value.player, player_revision: 10 }, progression: { unlock_ids: ['unlock.region.mountains'] },
    active_squad: { ...value.active_squad!, units: [{ ...value.active_squad!.units[0], level: 2, xp: 6 }] }, active_run: null };
}
