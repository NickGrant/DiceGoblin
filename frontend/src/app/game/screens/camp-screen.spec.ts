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
  RuntimeViewport,
  RuntimeViewportEnvironment,
  ViewportMeasurement,
  calculateRuntimeViewport,
} from '../runtime/runtime-viewport';
import {
  CampScreen,
  CampStateUnavailableError,
  GameSceneScreen,
  createCampLayout,
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

  function viewportSnapshot(width: number, height: number) {
    return calculateRuntimeViewport({
      cssWidth: width,
      cssHeight: height,
      safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 },
      coarsePointer: false,
      noHover: false,
    });
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
    const screen = new CampScreen(
      {} as Phaser.Scene,
      storeWith(bootstrap()),
      new RuntimeViewport(),
    );

    expect(screen.key).toBe('camp');
    expect(screen instanceof Phaser.Scene).toBeFalse();
  });

  it('keeps the panel and every resource region inside usable bounds in all modes', () => {
    const snapshots = [
      viewportSnapshot(844, 390),
      viewportSnapshot(1600, 900),
      viewportSnapshot(2560, 1080),
      calculateRuntimeViewport({
        cssWidth: 844,
        cssHeight: 390,
        safeInsetsCss: { top: 0, right: 21, bottom: 18, left: 47 },
        coarsePointer: true,
        noHover: true,
      }),
    ];

    for (const snapshot of snapshots) {
      const layout = createCampLayout(snapshot);
      for (const region of [layout.panel, layout.warbandButton, ...layout.resourcePlaques]) {
        expect(region.x).toBeGreaterThanOrEqual(snapshot.safeBounds.x);
        expect(region.y).toBeGreaterThanOrEqual(snapshot.safeBounds.y);
        expect(region.right).toBeLessThanOrEqual(snapshot.safeBounds.right);
        expect(region.bottom).toBeLessThanOrEqual(snapshot.safeBounds.bottom);
      }
    }
  });

  it('uses a distinct Compact composition while retaining the same Camp regions', () => {
    const compact = createCampLayout(viewportSnapshot(844, 390));
    const standard = createCampLayout(viewportSnapshot(1600, 900));
    const wide = createCampLayout(viewportSnapshot(2560, 1080));

    expect(compact.mode).toBe('compact');
    expect(compact.panel.height).toBeGreaterThan(standard.panel.height);
    expect(compact.resourceValueFontSize).toBeGreaterThan(standard.resourceValueFontSize);
    expect(standard.mode).toBe('standard');
    expect(wide.mode).toBe('wide');
    expect(wide.panel.width).toBeGreaterThan(standard.panel.width);
    expect(compact.resourcePlaques.length).toBe(3);
  });

  it('does not transform authoritative values when layout mode changes', () => {
    const store = storeWith(bootstrap('Grizzlewick', 80, 9, 57, 50));
    const before = createCampViewModel(store);

    createCampLayout(viewportSnapshot(844, 390));
    createCampLayout(viewportSnapshot(1600, 900));
    createCampLayout(viewportSnapshot(2560, 1080));

    const after = createCampViewModel(store);
    expect(after).toEqual(before);
    expect(after.energyText).toBe('57 / 50');
    expect(after.isEnergyOvercap).toBeTrue();
  });

  it('does not create Camp before startup is ready', () => {
    const apiClient = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getBootstrap']);
    const contentLoader = jasmine.createSpyObj<ClientContentLoader>('ClientContentLoader', [
      'loadProjection',
    ]);
    const startup = new RuntimeStartup(apiClient, contentLoader);
    const screenFactory = jasmine.createSpy('screenFactory');
    const gameScene = new GameScene(
      new RuntimeLifecycleState(),
      startup,
      new RuntimeViewport(),
      screenFactory,
    );
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
        kin: {},
        unit_types: {},
        abilities: {},
        dice_materials: {},
        dice_aspects: {},
        dice_profiles: {},
      },
    });
    const startup = new RuntimeStartup(apiClient, contentLoader);
    const createdScreens: jasmine.SpyObj<GameSceneScreen>[] = [];
    const screenFactory = jasmine
      .createSpy('screenFactory')
      .and.callFake((_scene: Phaser.Scene, _store: GameStore) => {
        const screen = jasmine.createSpyObj<GameSceneScreen>(
          'CampScreen',
          ['create', 'reflow', 'destroy'],
          { key: 'camp' },
        );
        createdScreens.push(screen);
        return screen;
      });
    const gameScene = new GameScene(
      new RuntimeLifecycleState(),
      startup,
      new RuntimeViewport(),
      screenFactory,
    );

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

  it('reflows the same active Camp screen on resize without refetching or replacing authority', async () => {
    const revision = 'a'.repeat(64);
    const apiClient = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getBootstrap']);
    apiClient.getBootstrap.and.resolveTo({ ok: true, data: bootstrap('Grizzlewick', 80, 9, 57, 50) });
    const contentLoader = jasmine.createSpyObj<ClientContentLoader>('ClientContentLoader', ['loadProjection']);
    contentLoader.loadProjection.and.resolveTo({
      revision,
      content: {
        regions: { 'region.the_farm': { id: 'region.the_farm', display_name: 'The Farm', art_key: 'farm' } },
        kin: {},
        unit_types: {},
        abilities: {},
        dice_materials: {},
        dice_aspects: {},
        dice_profiles: {},
      },
    });
    let currentMeasurement: ViewportMeasurement = {
      cssWidth: 1600, cssHeight: 900,
      safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 },
      coarsePointer: true, noHover: true,
    };
    let resize: (() => void) | null = null;
    const environment: RuntimeViewportEnvironment = {
      measure: () => currentMeasurement,
      listen: (_parent, listener) => {
        resize = listener;
        return () => undefined;
      },
    };
    const viewport = new RuntimeViewport(environment);
    viewport.mount(document.createElement('div'));
    const startup = new RuntimeStartup(apiClient, contentLoader);
    const screen = jasmine.createSpyObj<GameSceneScreen>('CampScreen', ['create', 'reflow', 'destroy'], { key: 'camp' });
    const factory = jasmine.createSpy('factory').and.returnValue(screen);
    const scene = new GameScene(new RuntimeLifecycleState(), startup, viewport, factory);
    const shutdown = jasmine.createSpy('shutdown');
    (scene as unknown as { cameras: unknown; events: unknown }).cameras = {
      main: { setOrigin: () => ({ setZoom: () => ({ setScroll: () => undefined }) }) },
    };
    (scene as unknown as { events: unknown }).events = { once: shutdown };
    await startup.start();
    const store = startup.store;

    scene.create();
    currentMeasurement = { ...currentMeasurement, cssWidth: 844, cssHeight: 390 };
    (resize as unknown as () => void)();
    currentMeasurement = { ...currentMeasurement, cssWidth: 768, cssHeight: 1024 };
    (resize as unknown as () => void)();
    currentMeasurement = { ...currentMeasurement, cssWidth: 1024, cssHeight: 768 };
    (resize as unknown as () => void)();

    expect(factory).toHaveBeenCalledTimes(1);
    expect(screen.reflow.calls.count()).toBe(3);
    expect(screen.reflow.calls.argsFor(0)[0]).toEqual(jasmine.objectContaining({
      layoutClass: 'compact',
      portraitGateActive: false,
    }));
    expect(screen.reflow.calls.argsFor(1)[0]).toEqual(jasmine.objectContaining({
      portraitGateActive: true,
    }));
    expect(screen.reflow.calls.argsFor(2)[0]).toEqual(jasmine.objectContaining({
      portraitGateActive: false,
    }));
    expect(startup.store).toBe(store);
    expect(startup.store.bootstrap?.player.energy.current).toBe(57);
    expect(startup.store.bootstrap?.player.energy.normal_max).toBe(50);
    expect(apiClient.getBootstrap).toHaveBeenCalledTimes(1);
    expect(contentLoader.loadProjection).toHaveBeenCalledTimes(1);
  });

  it('navigates Camp to Warband and back inside one persistent GameScene without startup refetches', async () => {
    const revision = 'a'.repeat(64);
    const apiClient = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getBootstrap']);
    apiClient.getBootstrap.and.resolveTo({ ok: true, data: bootstrap() });
    const contentLoader = jasmine.createSpyObj<ClientContentLoader>('ClientContentLoader', ['loadProjection']);
    contentLoader.loadProjection.and.resolveTo({ revision, content: {
      regions: {}, kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {},
    } });
    const startup = new RuntimeStartup(apiClient, contentLoader);
    await startup.start();
    const viewport = new RuntimeViewport();
    const campScreens: jasmine.SpyObj<GameSceneScreen>[] = [];
    const campFactory = jasmine.createSpy('campFactory').and.callFake(() => {
      const screen = jasmine.createSpyObj<GameSceneScreen>('camp', ['create', 'reflow', 'destroy'], { key: 'camp' });
      campScreens.push(screen);
      return screen;
    });
    const warbandScreen = jasmine.createSpyObj<GameSceneScreen>('warband', ['create', 'reflow', 'destroy'], { key: 'warband' });
    const warbandFactory = jasmine.createSpy('warbandFactory').and.returnValue(warbandScreen);
    const scene = new GameScene(new RuntimeLifecycleState(), startup, viewport, campFactory, warbandFactory);
    (scene as unknown as { events: unknown }).events = { once: jasmine.createSpy('once') };

    scene.create();
    expect(scene.activeScreenKey).toBe('camp');
    scene.showWarband();
    expect(scene.activeScreenKey).toBe('warband');
    (scene as unknown as { handleBackInput(): void }).handleBackInput();

    expect(scene.activeScreenKey).toBe('camp');
    expect(campFactory).toHaveBeenCalledTimes(2);
    expect(warbandFactory).toHaveBeenCalledTimes(1);
    expect(warbandScreen.create).toHaveBeenCalledTimes(1);
    expect(warbandScreen.destroy).toHaveBeenCalledTimes(1);
    expect(apiClient.getBootstrap).toHaveBeenCalledTimes(1);
    expect(contentLoader.loadProjection).toHaveBeenCalledTimes(1);
  });

  it('navigates Warband to squad editor and back inside the same GameScene/runtime', async () => {
    const revision = 'a'.repeat(64);
    const apiClient = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getBootstrap']);
    apiClient.getBootstrap.and.resolveTo({ ok: true, data: bootstrap() });
    const contentLoader = jasmine.createSpyObj<ClientContentLoader>('ClientContentLoader', ['loadProjection']);
    contentLoader.loadProjection.and.resolveTo({ revision, content: {
      regions: {}, kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {},
    } });
    const startup = new RuntimeStartup(apiClient, contentLoader); await startup.start();
    const viewport = new RuntimeViewport();
    const camp = jasmine.createSpyObj<GameSceneScreen>('camp', ['create', 'reflow', 'destroy'], { key: 'camp' });
    const warband = jasmine.createSpyObj<GameSceneScreen>('warband', ['create', 'reflow', 'destroy'], { key: 'warband' });
    const editor = jasmine.createSpyObj<GameSceneScreen>('editor', ['create', 'reflow', 'destroy'], { key: 'squad-editor' });
    let returnToWarband!: () => void;
    const squadFactory = jasmine.createSpy('squadFactory').and.callFake((_scene, _startup, _viewport, _draft, returnAction) => {
      returnToWarband = returnAction; return editor;
    });
    const scene = new GameScene(new RuntimeLifecycleState(), startup, viewport, () => camp, () => warband, squadFactory);
    (scene as unknown as { events: unknown }).events = { once: jasmine.createSpy('once') };

    scene.create(); scene.showWarband();
    scene.showSquadEditor({ id: '31', name: 'Raiders', isActive: false, formation: Array(9).fill(null) });
    expect(scene.activeScreenKey).toBe('squad-editor');
    returnToWarband();
    expect(scene.activeScreenKey).toBe('warband');
    expect(apiClient.getBootstrap).toHaveBeenCalledTimes(1);
    expect(contentLoader.loadProjection).toHaveBeenCalledTimes(1);
  });

  it('preserves the active Warband screen across resize and portrait-gate snapshot changes', async () => {
    const revision = 'a'.repeat(64);
    const apiClient = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getBootstrap']);
    apiClient.getBootstrap.and.resolveTo({ ok: true, data: bootstrap() });
    const contentLoader = jasmine.createSpyObj<ClientContentLoader>('ClientContentLoader', ['loadProjection']);
    contentLoader.loadProjection.and.resolveTo({ revision, content: {
      regions: {}, kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {},
    } });
    let measurement: ViewportMeasurement = { cssWidth: 1600, cssHeight: 900, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: true, noHover: true };
    let resize: (() => void) | null = null;
    const viewport = new RuntimeViewport({
      measure: () => measurement,
      listen: (_parent, listener) => { resize = listener; return () => undefined; },
    });
    viewport.mount(document.createElement('div'));
    const startup = new RuntimeStartup(apiClient, contentLoader);
    await startup.start();
    const camp = jasmine.createSpyObj<GameSceneScreen>('camp', ['create', 'reflow', 'destroy'], { key: 'camp' });
    const warband = jasmine.createSpyObj<GameSceneScreen>('warband', ['create', 'reflow', 'destroy'], { key: 'warband' });
    const scene = new GameScene(new RuntimeLifecycleState(), startup, viewport, () => camp, () => warband);
    (scene as unknown as { events: unknown }).events = { once: jasmine.createSpy('once') };
    scene.create();
    scene.showWarband();

    measurement = { ...measurement, cssWidth: 768, cssHeight: 1024 };
    (resize as unknown as () => void)();
    measurement = { ...measurement, cssWidth: 1024, cssHeight: 768 };
    (resize as unknown as () => void)();

    expect(scene.activeScreenKey).toBe('warband');
    expect(warband.reflow).toHaveBeenCalledTimes(2);
    expect(warband.reflow.calls.argsFor(0)[0].portraitGateActive).toBeTrue();
    expect(warband.reflow.calls.argsFor(1)[0].portraitGateActive).toBeFalse();
    expect(apiClient.getBootstrap).toHaveBeenCalledTimes(1);
    expect(contentLoader.loadProjection).toHaveBeenCalledTimes(1);
  });
});
