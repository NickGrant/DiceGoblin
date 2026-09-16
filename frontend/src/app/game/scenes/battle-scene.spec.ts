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

  it('retries only the playback GET and presents historical participants from the persisted projection', async () => {
    const { scene, api } = harness();
    api.getBattlePlayback.and.returnValues(Promise.reject(new RuntimeApiError('network')), Promise.resolve(playback()));

    await (scene as any).loadPlayback();
    expect(scene.playbackState).toBe('retryable');
    await (scene as any).loadPlayback();

    expect(api.getBattlePlayback.calls.allArgs()).toEqual([['81'], ['81']]);
    expect(api.resolveRunNode).not.toHaveBeenCalled();
    expect(scene.playbackController?.snapshot.participants.map((unit) => unit.displayName))
      .toEqual(['Historical Ashback', 'Historical Mudwrestler']);
  });

  it('rejects a playback whose immutable identity does not match the session marker', async () => {
    const { scene, startup, api } = harness();
    const mismatched = playback();
    api.getBattlePlayback.and.resolveTo({ ...mismatched, battle: { ...mismatched.battle, runNodeId: '99' } });

    await (scene as any).loadPlayback();

    expect(scene.playbackState).toBe('integrity-error');
    expect(startup.battlePresentation.marker).toBeNull();
    expect(api.resolveRunNode).not.toHaveBeenCalled();
  });
});

function harness() {
  const api = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getBattlePlayback', 'resolveRunNode']);
  const startup = new RuntimeStartup(api);
  startup.store.hydrateBootstrap(bootstrap());
  (startup as any).currentState = { status: 'ready' };
  startup.battlePresentation.establish('1', '81', '41', '10');
  const scene = new BattleScene(new RuntimeLifecycleState(), startup, new RuntimeViewport());
  spyOn<any>(scene, 'render').and.stub(); spyOn<any>(scene, 'scheduleNext').and.stub();
  return { scene, startup, api };
}

function playback(): BattlePlaybackResult {
  return { battle: { id: '81', runId: '41', runNodeId: '10', engineVersion: 1, playbackVersion: 1,
    outcome: 'victory', endingRound: 1, endingTick: 1, participants: [
      { combatantKey: 'ashback', side: 'player', unitId: '11', unitTypeId: 'unit_type.bruiser', enemyUnitTypeId: null,
        displayName: 'Historical Ashback', artKey: 'old.ashback', position: { x: 1, y: 1 }, initialHp: 20,
        maxHp: 20, terminalHp: 20, isDefeated: false, terminalStatuses: [] },
      { combatantKey: 'mudwrestler', side: 'enemy', unitId: null, unitTypeId: null,
        enemyUnitTypeId: 'enemy_unit_type.mudwrestler', displayName: 'Historical Mudwrestler', artKey: 'old.mud',
        position: { x: 2, y: 1 }, initialHp: 10, maxHp: 10, terminalHp: 0, isDefeated: true, terminalStatuses: [] },
    ], events: [
      { sequence: 0, type: 'battle_started', round: 0, tick: 0, facts: { combatant_keys: ['ashback', 'mudwrestler'] } },
      { sequence: 1, type: 'battle_ended', round: 1, tick: 1, facts: { outcome: 'victory' } },
    ] }, playerRevision: 8 };
}

function bootstrap(): GameBootstrapData {
  return { account: { id: '1', display_name: 'Now Renamed', role: 'user' },
    player: { teeth: 0, raw_chaos: 0, player_revision: 7, energy: { current: 40, normal_max: 50,
      regeneration_per_hour: 12, regeneration_interval_seconds: 300, last_regeneration_at: '2026-09-16T12:00:00Z',
      next_regeneration_at: null, fully_regenerated_at: null } }, session: { authenticated: true, csrf_token: 'csrf' },
    server_time: '2026-09-16T12:00:00Z', content_revision: 'a'.repeat(64), progression: { unlock_ids: [] },
    active_squad: null, active_run: null };
}
