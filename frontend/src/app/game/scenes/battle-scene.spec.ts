import { BattlePlaybackResult } from '../runtime/battle-playback-contracts';
import { ClientContentLoader, ClientContentRegistry } from '../runtime/client-content-registry';
import { GameBootstrapData } from '../runtime/game-store';
import { CurrentRun } from '../runtime/run-contracts';
import { RuntimeApiClient, RuntimeApiError } from '../runtime/runtime-api-client';
import { RuntimeStartup } from '../runtime/runtime-startup';
import { calculateRuntimeViewport, RuntimeViewport } from '../runtime/runtime-viewport';
import { BATTLE_SCENE_KEY, BattleScene, RuntimeLifecycleState, nextSceneForStartup } from './runtime-scenes';

describe('BattleScene retained playback lifecycle', () => {
  afterEach(() => sessionStorage.removeItem('dice-goblins:battle-presentation:v1'));

  it('routes a ready runtime with a retained presentation before active-run routing', () => {
    expect(nextSceneForStartup({ status: 'ready' }, true, true)).toBe(BATTLE_SCENE_KEY);
    expect(nextSceneForStartup({ status: 'ready' }, false, true)).toBe(BATTLE_SCENE_KEY);
  });

  it('retains the marker and makes Retry issue another playback GET only', async () => {
    const { scene, startup, api } = await harness();
    api.getBattlePlayback.and.returnValues(Promise.reject(new RuntimeApiError('network')), Promise.resolve(playback()));
    await (scene as any).loadPlayback();
    expect(scene.playbackState).toBe('retryable'); expect(startup.battlePresentation.marker).not.toBeNull();
    scene.retryPlayback(); await Promise.resolve(); await Promise.resolve();
    expect(api.getBattlePlayback.calls.allArgs()).toEqual([['81'], ['81']]);
    expect(api.resolveRunNode).not.toHaveBeenCalled();
    expect(scene.playbackController?.snapshot.participants.map((unit) => unit.displayName))
      .toEqual(['Historical Ashback', 'Historical Mudwrestler']);
    expect(scene.playbackController?.snapshot.participants.map((unit) => unit.artKey))
      .toEqual(['goblin_bruiser', 'enemy_mudwrestler']);
  });

  it('clears an identity-mismatched marker and provides an actionable return to the active run', async () => {
    const { scene, startup, api, sceneStart } = await harness(true);
    const mismatched = playback();
    api.getBattlePlayback.and.resolveTo({ ...mismatched, battle: { ...mismatched.battle, runNodeId: '99' } });
    await (scene as any).loadPlayback();
    expect(scene.playbackState).toBe('invalid-marker'); expect(startup.battlePresentation.marker).toBeNull();
    expect(api.resolveRunNode).not.toHaveBeenCalled(); scene.recoverFromInvalidMarker();
    expect(sceneStart).toHaveBeenCalledOnceWith('RunScene');
  });

  it('returns to Camp after a terminal-run identity mismatch instead of stranding BattleScene', async () => {
    const { scene, startup, api, sceneStart } = await harness(false);
    const mismatched = playback();
    api.getBattlePlayback.and.resolveTo({ ...mismatched, battle: { ...mismatched.battle, id: '82' } });
    await (scene as any).loadPlayback(); scene.recoverFromInvalidMarker();
    expect(startup.battlePresentation.marker).toBeNull(); expect(api.resolveRunNode).not.toHaveBeenCalled();
    expect(sceneStart).toHaveBeenCalledOnceWith('GameScene');
  });

  it('enters an explicit persisted result without navigating or inventing reward facts', async () => {
    const { scene, startup, sceneStart } = await harness(true); await completePlayback(scene);
    const state = scene.playbackController!.snapshot;
    expect(scene.playbackState).toBe('complete'); expect(state.outcome).toBe('victory');
    expect(state.participants.filter((unit) => unit.side === 'player').map((unit) =>
      ({ name: unit.displayName, hp: unit.currentHp, defeated: unit.defeated })))
      .toEqual([{ name: 'Historical Ashback', hp: 7, defeated: false }]);
    expect(Object.keys(state)).not.toContain('rewards'); expect(Object.keys(state)).not.toContain('xp');
    expect(startup.battlePresentation.marker).not.toBeNull(); expect(sceneStart).not.toHaveBeenCalled();
  });

  it('presents only retained authoritative Boss rewards after playback completes', async () => {
    const { scene, startup } = await harness(true);
    startup.battlePresentation.retainResolution(bossResolutionForPresentation());

    await completePlayback(scene);

    expect(scene.bossRewardSummary).toBe('Unit 11: +16 XP (Level 2) · unlock.region.mountains unlocked');
    expect(Object.keys(scene.playbackController!.result)).not.toContain('rewards');
    expect(Object.keys(scene.playbackController!.result)).not.toContain('xp');
  });

  it('presents an authoritative generic already-owned unlock outcome without deriving a substitute', async () => {
    const { scene, startup } = await harness(true);
    const result = bossResolutionForPresentation();
    startup.battlePresentation.retainResolution({ ...result,
      rewards: { ...result.rewards!, unlocks: [{ unlockId: 'unlock.region.mountains', outcome: 'already_owned' }] } });
    await completePlayback(scene);
    expect(scene.bossRewardSummary).toContain('unlock.region.mountains already owned');
  });

  it('presents an authored XP-only Boss result with its actual amount', async () => {
    const { scene, startup } = await harness(true);
    const result = bossResolutionForPresentation();
    startup.battlePresentation.retainResolution({ ...result,
      rewards: { unitXp: [{ unitId: '11', amount: 37, levelBefore: 2, xpBefore: 80, levelAfter: 2, xpAfter: 117 }], unlocks: [] } });
    await completePlayback(scene);
    expect(scene.bossRewardSummary).toBe('Unit 11: +37 XP');
  });

  it('forces current-run authority before adopting victory HP, graph, battle ID, revision, and destination', async () => {
    const { scene, startup, api, sceneStart } = await harness(true);
    api.getCurrentRun.and.resolveTo({ run: preCombatRun(), playerRevision: 7 });
    await startup.store.loadCurrentRun(api, startup.contentRegistry!); startup.store.markCurrentRunStale();
    api.getCurrentRun.calls.reset(); await completePlayback(scene); startup.battlePresentation.retainResolution({} as any);
    const before = startup.store.currentRun.data;
    let finish!: (value: { run: CurrentRun; playerRevision: number }) => void;
    api.getCurrentRun.and.returnValue(new Promise((resolve) => { finish = resolve; }));
    const continuing = scene.continueAfterBattle();
    expect(scene.battleContinueState).toBe('submitting'); expect(api.getCurrentRun).toHaveBeenCalledTimes(1);
    expect(startup.store.currentRun.data).toBe(before);
    expect(startup.store.currentRun.data?.nodes[0]).toEqual(jasmine.objectContaining({ status: 'available', battleId: null }));
    expect(startup.battlePresentation.marker).not.toBeNull(); expect(sceneStart).not.toHaveBeenCalled();
    finish({ run: victoryRun(), playerRevision: 8 }); await continuing;
    expect(startup.store.currentRun.status).toBe('fresh');
    expect(startup.store.currentRun.data?.nodes.map((node) => [node.status, node.battleId])).toEqual([
      ['completed', '81'], ['available', null], ['locked', null], ['locked', null], ['locked', null],
    ]);
    expect(startup.store.currentRun.data?.units).toEqual([{ unitId: '11', currentHp: 7 }]);
    expect(startup.store.playerRevision).toBe(8); expect(startup.battlePresentation.marker).toBeNull();
    expect(startup.battlePresentation.resolution).toBeNull(); expect(sceneStart).toHaveBeenCalledOnceWith('RunScene');
    expect(api.getBattlePlayback).toHaveBeenCalledTimes(1); expect(api.resolveRunNode).not.toHaveBeenCalled();
    expect(api.getBootstrap).not.toHaveBeenCalled(); expect(api.getUnits).not.toHaveBeenCalled();
    expect(api.getDice).not.toHaveBeenCalled(); expect(api.getSquads).not.toHaveBeenCalled();
  });

  it('forces the current-run GET after Replay even when the cached aggregate is already fresh', async () => {
    const { scene, startup, api, sceneStart } = await harness(true);
    api.getCurrentRun.and.resolveTo({ run: victoryRun(), playerRevision: 7 });
    await startup.store.loadCurrentRun(api, startup.contentRegistry!); api.getCurrentRun.calls.reset();
    expect(startup.store.currentRun.status).toBe('fresh'); await completePlayback(scene);
    api.getCurrentRun.and.resolveTo({ run: victoryRun(), playerRevision: 8 });

    await scene.continueAfterBattle();

    expect(api.getCurrentRun).toHaveBeenCalledTimes(1); expect(api.resolveRunNode).not.toHaveBeenCalled();
    expect(startup.battlePresentation.marker).toBeNull(); expect(sceneStart).toHaveBeenCalledOnceWith('RunScene');
  });

  it('returns from a persisted Boss battle through current-run authority without another mutation or bootstrap read', async () => {
    const { scene, startup, api, sceneStart } = await harness(true);
    startup.battlePresentation.establish('1', '81', '41', '13');
    const bossPlayback = playback();
    api.getBattlePlayback.and.resolveTo({ ...bossPlayback,
      battle: { ...bossPlayback.battle, runNodeId: '13' } });
    api.getCurrentRun.and.resolveTo({ run: bossVictoryRun(), playerRevision: 9 });

    await completePlayback(scene);
    await scene.continueAfterBattle();

    expect(startup.store.currentRun.data?.nodes[3]).toEqual(jasmine.objectContaining({
      nodeTypeId: 'run_node_type.boss', status: 'completed', battleId: '81',
    }));
    expect(startup.store.currentRun.data?.nodes[4]).toEqual(jasmine.objectContaining({
      nodeTypeId: 'run_node_type.exit', status: 'available', battleId: null,
    }));
    expect(startup.store.currentRun.data?.units).toEqual([{ unitId: '11', currentHp: 7 }]);
    expect(startup.battlePresentation.marker).toBeNull();
    expect(sceneStart).toHaveBeenCalledOnceWith('RunScene');
    expect(api.getCurrentRun).toHaveBeenCalledTimes(1);
    expect(api.resolveRunNode).not.toHaveBeenCalled();
    expect(api.getBootstrap).not.toHaveBeenCalled();
    expect(api.getUnits).not.toHaveBeenCalled();
    expect(api.getDice).not.toHaveBeenCalled();
    expect(api.getSquads).not.toHaveBeenCalled();
  });

  it('allows Continue again when the same BattleScene instance is reused for Replay', async () => {
    const { scene, startup, api, sceneStart } = await harness(true);
    api.getCurrentRun.and.resolveTo({ run: victoryRun(), playerRevision: 8 });
    await completePlayback(scene); await scene.continueAfterBattle();
    expect(scene.battleContinueState).toBe('idle');

    startup.battlePresentation.establish('1', '81', '41', '10');
    (scene as any).resetForActivation();
    await completePlayback(scene); await scene.continueAfterBattle();

    expect(api.getCurrentRun).toHaveBeenCalledTimes(2);
    expect(api.getBattlePlayback).toHaveBeenCalledTimes(2);
    expect(api.resolveRunNode).not.toHaveBeenCalled();
    expect(startup.battlePresentation.marker).toBeNull();
    expect(sceneStart.calls.allArgs()).toEqual([['RunScene'], ['RunScene']]);
  });

  it('reconciles an immediate defeat from stale active-run bootstrap truth and removes its Warband lock', async () => {
    const { scene, startup, api, sceneStart } = await harness(true, 'defeat');
    const energy = startup.store.bootstrap!.player.energy;
    expect(startup.store.activeRunLock).not.toBeNull(); await completePlayback(scene);
    api.getCurrentRun.and.resolveTo({ run: null, playerRevision: 8 }); await scene.continueAfterBattle();
    expect(startup.store.currentRun).toEqual({ status: 'fresh', data: null, error: null });
    expect(startup.store.bootstrap?.active_run).toBeNull(); expect(startup.store.activeRunLock).toBeNull();
    expect(startup.store.playerRevision).toBe(8); expect(startup.store.bootstrap?.player.energy).toBe(energy);
    expect(startup.battlePresentation.marker).toBeNull(); expect(sceneStart).toHaveBeenCalledOnceWith('GameScene');
  });

  it('reconciles a reload-style stalemate when bootstrap already reports no active run', async () => {
    const { scene, startup, api, sceneStart } = await harness(false, 'stalemate');
    await completePlayback(scene); api.getCurrentRun.and.resolveTo({ run: null, playerRevision: 8 });
    await scene.continueAfterBattle();
    expect(startup.store.bootstrap?.active_run).toBeNull(); expect(startup.store.playerRevision).toBe(8);
    expect(startup.battlePresentation.marker).toBeNull(); expect(sceneStart).toHaveBeenCalledOnceWith('GameScene');
  });

  it('retains the completed result and marker across request failures, then retries only current-run GET', async () => {
    const { scene, startup, api, sceneStart } = await harness(true); await completePlayback(scene);
    api.getCurrentRun.and.returnValues(Promise.reject(new RuntimeApiError('network')),
      Promise.reject(new RuntimeApiError('http', 503)), Promise.reject(new RuntimeApiError('malformed-response', 200)),
      Promise.resolve({ run: victoryRun(), playerRevision: 8 }));
    for (let attempt = 1; attempt <= 3; attempt += 1) {
      await scene.continueAfterBattle();
      expect(scene.battleContinueState).toBe('error'); expect(scene.playbackState).toBe('complete');
      expect(startup.battlePresentation.marker).not.toBeNull(); expect(api.getCurrentRun).toHaveBeenCalledTimes(attempt);
      expect(sceneStart).not.toHaveBeenCalled();
    }
    await scene.continueAfterBattle();
    expect(api.getCurrentRun).toHaveBeenCalledTimes(4); expect(api.getBattlePlayback).toHaveBeenCalledTimes(1);
    expect(api.resolveRunNode).not.toHaveBeenCalled(); expect(api.getBootstrap).not.toHaveBeenCalled();
    expect(api.getUnits).not.toHaveBeenCalled(); expect(api.getDice).not.toHaveBeenCalled(); expect(api.getSquads).not.toHaveBeenCalled();
    expect(startup.battlePresentation.marker).toBeNull(); expect(sceneStart).toHaveBeenCalledOnceWith('RunScene');
  });

  it('prevents simultaneous Continue reads and navigation', async () => {
    const { scene, api, sceneStart } = await harness(true); await completePlayback(scene);
    let finish!: (value: { run: CurrentRun; playerRevision: number }) => void;
    api.getCurrentRun.and.returnValue(new Promise((resolve) => { finish = resolve; }));
    const first = scene.continueAfterBattle(); const second = scene.continueAfterBattle();
    expect(api.getCurrentRun).toHaveBeenCalledTimes(1); finish({ run: victoryRun(), playerRevision: 8 });
    await Promise.all([first, second]); expect(sceneStart).toHaveBeenCalledTimes(1);
  });

  it('retains the marker and result when current-run authority contradicts the retained battle', async () => {
    const { scene, startup, api, sceneStart } = await harness(true); await completePlayback(scene);
    const base = victoryRun(); const contradictory: CurrentRun = { ...base,
      nodes: base.nodes.map((node, index) => index === 0 ? { ...node, battleId: '82' } : node) };
    api.getCurrentRun.and.resolveTo({ run: contradictory, playerRevision: 8 }); await scene.continueAfterBattle();
    expect(scene.battleContinueState).toBe('error'); expect(startup.store.currentRun.error).toBe('integrity');
    expect(startup.battlePresentation.marker).not.toBeNull(); expect(sceneStart).not.toHaveBeenCalled();
  });

  it('accepts a newer different active run from another tab without reviving the historical run', async () => {
    const { scene, startup, api, sceneStart } = await harness(true); await completePlayback(scene);
    const newer = { ...preCombatRun(), id: '42' }; api.getCurrentRun.and.resolveTo({ run: newer, playerRevision: 9 });
    await scene.continueAfterBattle();
    expect(startup.store.currentRun.data?.id).toBe('42'); expect(startup.store.bootstrap?.active_run?.id).toBe('42');
    expect(startup.store.playerRevision).toBe(9); expect(startup.battlePresentation.marker).toBeNull();
    expect(sceneStart).toHaveBeenCalledOnceWith('RunScene');
  });

  it('blocks Continue while portrait-gated and preserves the completed result for landscape recovery', async () => {
    const { scene, startup, api, viewport, sceneStart } = await harness(true); await completePlayback(scene);
    (viewport as any).currentSnapshot = calculateRuntimeViewport({ cssWidth: 390, cssHeight: 844,
      safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: true, noHover: true });
    await scene.continueAfterBattle();
    expect(api.getCurrentRun).not.toHaveBeenCalled(); expect(startup.battlePresentation.marker).not.toBeNull();
    expect(scene.playbackState).toBe('complete'); expect(sceneStart).not.toHaveBeenCalled();
    (viewport as any).currentSnapshot = calculateRuntimeViewport({ cssWidth: 844, cssHeight: 390,
      safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: true, noHover: true });
    api.getCurrentRun.and.resolveTo({ run: victoryRun(), playerRevision: 8 }); await scene.continueAfterBattle();
    expect(api.getCurrentRun).toHaveBeenCalledTimes(1); expect(sceneStart).toHaveBeenCalledOnceWith('RunScene');
  });
});

async function harness(activeRun = false, outcome: 'victory' | 'defeat' | 'stalemate' = 'victory') {
  const api = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', [
    'getBattlePlayback', 'getCurrentRun', 'resolveRunNode', 'getBootstrap', 'getUnits', 'getDice', 'getSquads',
  ]);
  const startup = new RuntimeStartup(api, {} as ClientContentLoader); const registry = content();
  startup.store.hydrateBootstrap(bootstrap(activeRun));
  (startup as any).activeContentRegistry = registry; (startup as any).currentState = { status: 'ready' };
  startup.battlePresentation.establish('1', '81', '41', '10'); api.getBattlePlayback.and.resolveTo(playback(outcome));
  const viewport = new RuntimeViewport(); const scene = new BattleScene(new RuntimeLifecycleState(), startup, viewport);
  const sceneStart = jasmine.createSpy('start'); (scene as any).scene = { start: sceneStart };
  spyOn<any>(scene, 'render').and.stub(); spyOn<any>(scene, 'scheduleNext').and.stub();
  return { scene, startup, api, viewport, sceneStart };
}

async function completePlayback(scene: BattleScene): Promise<void> {
  await (scene as any).loadPlayback();
  while (scene.playbackController?.snapshot.state === 'playing') scene.playbackController.advance();
  expect(scene.playbackController?.snapshot.state).toBe('complete');
}

function playback(outcome: 'victory' | 'defeat' | 'stalemate' = 'victory'): BattlePlaybackResult {
  const playerDefeated = outcome !== 'victory'; const enemyDefeated = outcome === 'victory';
  return { battle: { id: '81', runId: '41', runNodeId: '10', engineVersion: 1, playbackVersion: 1,
    outcome, endingRound: 1, endingTick: 1, participants: [
      { combatantKey: 'ashback', side: 'player', unitId: '11', unitTypeId: 'unit_type.bruiser', enemyUnitTypeId: null,
        displayName: 'Historical Ashback', artKey: 'goblin_bruiser', position: { x: 1, y: 1 }, initialHp: 20,
        maxHp: 20, terminalHp: playerDefeated ? 0 : 7, isDefeated: playerDefeated, terminalStatuses: [] },
      { combatantKey: 'mudwrestler', side: 'enemy', unitId: null, unitTypeId: null,
        enemyUnitTypeId: 'enemy_unit_type.mudwrestler', displayName: 'Historical Mudwrestler', artKey: 'enemy_mudwrestler',
        position: { x: 2, y: 1 }, initialHp: 10, maxHp: 10, terminalHp: enemyDefeated ? 0 : 4,
        isDefeated: enemyDefeated, terminalStatuses: [] },
    ], events: [
      { sequence: 0, type: 'battle_started', round: 0, tick: 0, facts: { combatant_keys: ['ashback', 'mudwrestler'] } },
      { sequence: 1, type: 'battle_ended', round: 1, tick: 1, facts: { outcome } },
    ] }, playerRevision: 8 };
}

function bossResolutionForPresentation() {
  return { resolutionType: 'boss' as const,
    battle: { id: '81', outcome: 'victory' as const, engineVersion: 1 as const, playbackVersion: 1 as const,
      endingRound: 1, endingTick: 1 },
    node: { id: '10', status: 'completed' as const, completedAt: '2026-09-16T12:00:00Z' },
    newlyAvailableNodeIds: ['11'], terminalPlayerHp: { '11': 7 },
    run: { id: '41', status: 'active' as const, endedAt: null },
    rewards: { unitXp: [{ unitId: '11', amount: 16, levelBefore: 1, xpBefore: 90, levelAfter: 2, xpAfter: 6 }],
      unlocks: [{ unlockId: 'unlock.region.mountains', outcome: 'granted' as const }] }, playerRevision: 8 };
}

function preCombatRun(): CurrentRun {
  return { ...victoryRun(), nodes: victoryRun().nodes.map((node, index) => index === 0
    ? { ...node, status: 'available' as const, completedAt: null, battleId: null }
    : { ...node, status: 'locked' as const }) };
}

function victoryRun(): CurrentRun {
  return { id: '41', regionId: 'region.the_farm', squadId: '31', status: 'active', createdAt: '2026-09-13T12:00:00Z',
    nodes: [
      { id: '10', nodeIndex: 0, nodeTypeId: 'run_node_type.combat', status: 'completed', completedAt: '2026-09-16T12:00:00Z', battleId: '81', position: { column: 0, row: 1 } },
      { id: '11', nodeIndex: 1, nodeTypeId: 'run_node_type.loot', status: 'available', completedAt: null, battleId: null, position: { column: 1, row: 1 } },
      { id: '12', nodeIndex: 2, nodeTypeId: 'run_node_type.rest', status: 'locked', completedAt: null, battleId: null, position: { column: 2, row: 1 } },
      { id: '13', nodeIndex: 3, nodeTypeId: 'run_node_type.boss', status: 'locked', completedAt: null, battleId: null, position: { column: 3, row: 1 } },
      { id: '14', nodeIndex: 4, nodeTypeId: 'run_node_type.exit', status: 'locked', completedAt: null, battleId: null, position: { column: 4, row: 1 } },
    ], edges: [
      { fromNodeId: '10', toNodeId: '11' }, { fromNodeId: '11', toNodeId: '12' },
      { fromNodeId: '12', toNodeId: '13' }, { fromNodeId: '13', toNodeId: '14' },
    ], units: [{ unitId: '11', currentHp: 7 }] };
}

function bossVictoryRun(): CurrentRun {
  const run = victoryRun();
  return { ...run, nodes: run.nodes.map((node, index) => {
    if (index === 0) return { ...node, battleId: '80' };
    if (index === 1 || index === 2) return { ...node, status: 'completed' as const,
      completedAt: '2026-09-16T12:00:00Z', battleId: null };
    if (index === 3) return { ...node, status: 'completed' as const,
      completedAt: '2026-09-16T12:00:00Z', battleId: '81' };
    return { ...node, status: 'available' as const, completedAt: null, battleId: null };
  }) };
}

function content(): ClientContentRegistry {
  const node = (id: string) => ({ id, display_name: id, description: id, icon_key: id });
  return new ClientContentRegistry({ revision: 'a'.repeat(64), content: { gameplay: { run_energy_cost: 10 },
    regions: { 'region.the_farm': { id: 'region.the_farm', display_name: 'The Farm', art_key: 'farm' } },
    kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {}, run_node_types: {
      'run_node_type.combat': node('run_node_type.combat'), 'run_node_type.loot': node('run_node_type.loot'),
      'run_node_type.rest': node('run_node_type.rest'), 'run_node_type.boss': node('run_node_type.boss'),
      'run_node_type.exit': node('run_node_type.exit'),
    },
  } });
}

function bootstrap(activeRun: boolean): GameBootstrapData {
  return { account: { id: '1', display_name: 'Now Renamed', role: 'user' },
    player: { teeth: 0, raw_chaos: 0, player_revision: 7, energy: { current: 40, normal_max: 50,
      regeneration_per_hour: 12, regeneration_interval_seconds: 300, last_regeneration_at: '2026-09-16T12:00:00Z',
      next_regeneration_at: null, fully_regenerated_at: null } }, session: { authenticated: true, csrf_token: 'csrf' },
    server_time: '2026-09-16T12:00:00Z', content_revision: 'a'.repeat(64),
    progression: { unlock_ids: [], available_region_ids: ['region.the_farm'] },
    active_squad: activeRun ? { id: '31', name: 'Raiders', is_active: true,
      formation: ['11', null, null, null, null, null, null, null, null],
      units: [{ id: '11', display_name: 'Ashback', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin',
        level: 1, xp: 0, lifecycle_status: 'active' }] } : null,
    active_run: activeRun ? { id: '41', region_id: 'region.the_farm', squad_id: '31', status: 'active' } : null };
}
