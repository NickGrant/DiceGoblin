import { BattlePlaybackResult } from '../runtime/battle-playback-contracts';
import { GameBootstrapData } from '../runtime/game-store';
import { RuntimeApiClient, RuntimeApiError } from '../runtime/runtime-api-client';
import { RuntimeStartup } from '../runtime/runtime-startup';
import { RuntimeViewport } from '../runtime/runtime-viewport';
import { BATTLE_SCENE_KEY, BattleScene, RuntimeLifecycleState, nextSceneForStartup } from './runtime-scenes';

describe('BattleScene retained playback lifecycle', () => {
  afterEach(() => sessionStorage.removeItem('dice-goblins:battle-presentation:v1'));
  it('routes a ready runtime with a retained presentation before active-run routing', () => {
    expect(nextSceneForStartup({ status: 'ready' }, true, true)).toBe(BATTLE_SCENE_KEY);
    expect(nextSceneForStartup({ status: 'ready' }, false, true)).toBe(BATTLE_SCENE_KEY);
  });

  it('retains the marker and makes Retry issue another playback GET only', async () => {
    const { scene, startup, api } = harness();
    api.getBattlePlayback.and.returnValues(Promise.reject(new RuntimeApiError('network')), Promise.resolve(playback()));

    await (scene as any).loadPlayback();
    expect(scene.playbackState).toBe('retryable');
    expect(startup.battlePresentation.marker).not.toBeNull();
    scene.retryPlayback(); await Promise.resolve(); await Promise.resolve();

    expect(api.getBattlePlayback.calls.allArgs()).toEqual([['81'], ['81']]);
    expect(api.resolveRunNode).not.toHaveBeenCalled();
    expect(scene.playbackController?.snapshot.participants.map((unit) => unit.displayName))
      .toEqual(['Historical Ashback', 'Historical Mudwrestler']);
    expect(scene.playbackController?.snapshot.participants.map((unit) => unit.artKey))
      .toEqual(['goblin_bruiser', 'enemy_mudwrestler']);
  });

  it('clears an identity-mismatched marker and provides an actionable return to the active run', async () => {
    const { scene, startup, api, sceneStart } = harness(true);
    const mismatched = playback();
    api.getBattlePlayback.and.resolveTo({ ...mismatched, battle: { ...mismatched.battle, runNodeId: '99' } });

    await (scene as any).loadPlayback();

    expect(scene.playbackState).toBe('invalid-marker');
    expect(startup.battlePresentation.marker).toBeNull();
    expect(api.resolveRunNode).not.toHaveBeenCalled();
    scene.recoverFromInvalidMarker();
    expect(sceneStart).toHaveBeenCalledOnceWith('RunScene');
  });

  it('returns to Camp after a terminal-run identity mismatch instead of stranding BattleScene', async () => {
    const { scene, startup, api, sceneStart } = harness(false);
    const mismatched = playback();
    api.getBattlePlayback.and.resolveTo({ ...mismatched, battle: { ...mismatched.battle, id: '82' } });

    await (scene as any).loadPlayback(); scene.recoverFromInvalidMarker();

    expect(startup.battlePresentation.marker).toBeNull();
    expect(api.resolveRunNode).not.toHaveBeenCalled();
    expect(sceneStart).toHaveBeenCalledOnceWith('GameScene');
  });
});

function harness(activeRun = false) {
  const api = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getBattlePlayback', 'resolveRunNode']);
  const startup = new RuntimeStartup(api);
  startup.store.hydrateBootstrap(bootstrap(activeRun));
  (startup as any).currentState = { status: 'ready' };
  startup.battlePresentation.establish('1', '81', '41', '10');
  const scene = new BattleScene(new RuntimeLifecycleState(), startup, new RuntimeViewport());
  const sceneStart = jasmine.createSpy('start'); (scene as any).scene = { start: sceneStart };
  spyOn<any>(scene, 'render').and.stub(); spyOn<any>(scene, 'scheduleNext').and.stub();
  return { scene, startup, api, sceneStart };
}

function playback(): BattlePlaybackResult {
  return { battle: { id: '81', runId: '41', runNodeId: '10', engineVersion: 1, playbackVersion: 1,
    outcome: 'victory', endingRound: 1, endingTick: 1, participants: [
      { combatantKey: 'ashback', side: 'player', unitId: '11', unitTypeId: 'unit_type.bruiser', enemyUnitTypeId: null,
        displayName: 'Historical Ashback', artKey: 'goblin_bruiser', position: { x: 1, y: 1 }, initialHp: 20,
        maxHp: 20, terminalHp: 20, isDefeated: false, terminalStatuses: [] },
      { combatantKey: 'mudwrestler', side: 'enemy', unitId: null, unitTypeId: null,
        enemyUnitTypeId: 'enemy_unit_type.mudwrestler', displayName: 'Historical Mudwrestler', artKey: 'enemy_mudwrestler',
        position: { x: 2, y: 1 }, initialHp: 10, maxHp: 10, terminalHp: 0, isDefeated: true, terminalStatuses: [] },
    ], events: [
      { sequence: 0, type: 'battle_started', round: 0, tick: 0, facts: { combatant_keys: ['ashback', 'mudwrestler'] } },
      { sequence: 1, type: 'battle_ended', round: 1, tick: 1, facts: { outcome: 'victory' } },
    ] }, playerRevision: 8 };
}

function bootstrap(activeRun: boolean): GameBootstrapData {
  return { account: { id: '1', display_name: 'Now Renamed', role: 'user' },
    player: { teeth: 0, raw_chaos: 0, player_revision: 7, energy: { current: 40, normal_max: 50,
      regeneration_per_hour: 12, regeneration_interval_seconds: 300, last_regeneration_at: '2026-09-16T12:00:00Z',
      next_regeneration_at: null, fully_regenerated_at: null } }, session: { authenticated: true, csrf_token: 'csrf' },
    server_time: '2026-09-16T12:00:00Z', content_revision: 'a'.repeat(64), progression: { unlock_ids: [] },
    active_squad: null, active_run: activeRun ? { id: '41', region_id: 'region.the_farm', squad_id: '31', status: 'active' } : null };
}
