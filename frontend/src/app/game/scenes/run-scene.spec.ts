import { GameBootstrapData } from '../runtime/game-store';
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
});

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
