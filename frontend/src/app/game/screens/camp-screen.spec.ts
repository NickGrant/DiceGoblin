import Phaser from 'phaser';
import {
  BOOT_SCENE_KEY,
  GAME_SCENE_KEY,
  GameScene,
  RuntimeLifecycleState,
  nextSceneForStartup,
} from '../scenes/runtime-scenes';
import { ClientContentLoader } from '../runtime/client-content-registry';
import { GameBootstrapData, GameStore } from '../runtime/game-store';
import { RuntimeApiClient } from '../runtime/runtime-api-client';
import { RuntimeStartup } from '../runtime/runtime-startup';
import {
  CampScreen,
  CampStateUnavailableError,
  GameSceneScreen,
  createCampViewModel,
} from './camp-screen';

describe('CampScreen', () => {
  function bootstrap(
    displayName = 'Grizzlewick',
    teeth = 1234,
    rawChaos = 17,
    energyCurrent = 41,
    energyNormalMaximum = 50,
  ): GameBootstrapData {
    return {
      account: { id: '42', display_name: displayName, role: 'user' },
      player: {
        teeth,
        raw_chaos: rawChaos,
        energy: {
          current: energyCurrent,
          normal_max: energyNormalMaximum,
          regeneration_per_hour: 12,
          regeneration_interval_seconds: 300,
          last_regeneration_at: '2026-09-11T00:00:00Z',
          next_regeneration_at: null,
          fully_regenerated_at: null,
        },
        player_revision: 3,
      },
      session: { authenticated: true, csrf_token: 'not-for-camp' },
      server_time: '2026-09-11T00:00:00Z',
      content_revision: 'a'.repeat(64),
      progression: { unlock_ids: [] },
      active_squad: null,
      active_run: null,
    };
  }

  function storeWith(data: GameBootstrapData): GameStore {
    const store = new GameStore();
    store.hydrateBootstrap(data);
    return store;
  }

  it('derives identity, Teeth, Raw Chaos, and Energy only from authoritative bootstrap state', () => {
    const view = createCampViewModel(storeWith(bootstrap()));

    expect(view.displayName).toBe('Grizzlewick');
    expect(view.teeth).toBe(1234);
    expect(view.teethText).toBe('1,234');
    expect(view.rawChaos).toBe(17);
    expect(view.rawChaosText).toBe('17');
    expect(view.energyCurrent).toBe(41);
    expect(view.energyNormalMaximum).toBe(50);
    expect(view.energyText).toBe('41 / 50');
    expect(view.isEnergyOvercap).toBeFalse();
  });

  it('preserves overcap Energy in Camp presentation state', () => {
    const view = createCampViewModel(storeWith(bootstrap('Grizzlewick', 80, 9, 57, 50)));

    expect(view.energyCurrent).toBe(57);
    expect(view.energyNormalMaximum).toBe(50);
    expect(view.energyText).toBe('57 / 50');
    expect(view.isEnergyOvercap).toBeTrue();
  });

  it('changes Camp output when authoritative fixture values change', () => {
    const first = createCampViewModel(storeWith(bootstrap('Ashback', 4, 2, 12, 50)));
    const second = createCampViewModel(storeWith(bootstrap('Bogwort', 999, 31, 68, 60)));

    expect(first).not.toEqual(second);
    expect(second).toEqual(
      jasmine.objectContaining({
        displayName: 'Bogwort',
        teeth: 999,
        rawChaos: 31,
        energyText: '68 / 60',
      }),
    );
  });

  it('fails visibly instead of fabricating Camp state when bootstrap is absent', () => {
    expect(() => createCampViewModel(new GameStore())).toThrowError(CampStateUnavailableError);
  });

  it('is a GameScene-owned screen boundary rather than another Phaser Scene', () => {
    const screen = new CampScreen({} as Phaser.Scene, storeWith(bootstrap()));

    expect(screen.key).toBe('camp');
    expect(screen instanceof Phaser.Scene).toBeFalse();
  });

  it('does not create Camp before startup is ready', () => {
    const apiClient = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getBootstrap']);
    const contentLoader = jasmine.createSpyObj<ClientContentLoader>('ClientContentLoader', [
      'loadProjection',
    ]);
    const startup = new RuntimeStartup(apiClient, contentLoader);
    const screenFactory = jasmine.createSpy('screenFactory');
    const gameScene = new GameScene(new RuntimeLifecycleState(), startup, screenFactory);
    const start = jasmine.createSpy('start');
    (gameScene as unknown as { scene: { start: jasmine.Spy } }).scene = { start };

    gameScene.create();

    expect(start).toHaveBeenCalledOnceWith(BOOT_SCENE_KEY);
    expect(screenFactory).not.toHaveBeenCalled();
    expect(gameScene.activeScreenKey).toBeNull();
  });

  it('creates Camp from the ready runtime store without refetching startup resources', async () => {
    const revision = 'a'.repeat(64);
    const apiClient = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getBootstrap']);
    apiClient.getBootstrap.and.resolveTo({ ok: true, data: bootstrap() });
    const contentLoader = jasmine.createSpyObj<ClientContentLoader>('ClientContentLoader', [
      'loadProjection',
    ]);
    contentLoader.loadProjection.and.resolveTo({
      revision,
      content: {
        regions: {
          'region.the_farm': { id: 'region.the_farm', display_name: 'The Farm', art_key: 'farm' },
        },
      },
    });
    const startup = new RuntimeStartup(apiClient, contentLoader);
    const createdScreens: jasmine.SpyObj<GameSceneScreen>[] = [];
    const screenFactory = jasmine
      .createSpy('screenFactory')
      .and.callFake((_scene: Phaser.Scene, _store: GameStore) => {
        const screen = jasmine.createSpyObj<GameSceneScreen>('CampScreen', ['create', 'destroy'], {
          key: 'camp',
        });
        createdScreens.push(screen);
        return screen;
      });
    const gameScene = new GameScene(new RuntimeLifecycleState(), startup, screenFactory);

    const state = await startup.start();
    expect(nextSceneForStartup(state)).toBe(GAME_SCENE_KEY);
    gameScene.showCamp();
    gameScene.showCamp();

    expect(screenFactory).toHaveBeenCalledTimes(2);
    expect(screenFactory.calls.allArgs().every(([, store]) => store === startup.store)).toBeTrue();
    expect(createdScreens[0].create).toHaveBeenCalledTimes(1);
    expect(createdScreens[0].destroy).toHaveBeenCalledTimes(1);
    expect(createdScreens[1].create).toHaveBeenCalledTimes(1);
    expect(gameScene.activeScreenKey).toBe('camp');
    expect(apiClient.getBootstrap).toHaveBeenCalledTimes(1);
    expect(contentLoader.loadProjection).toHaveBeenCalledTimes(1);
  });
});
