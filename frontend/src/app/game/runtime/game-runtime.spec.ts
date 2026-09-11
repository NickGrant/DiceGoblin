import Phaser from 'phaser';
import {
  BATTLE_SCENE_KEY,
  GAME_SCENE_KEY,
  GameScene,
  RUN_SCENE_KEY,
  RunScene,
  BattleScene,
} from '../scenes/runtime-scenes';
import { ClientContentLoader } from './client-content-registry';
import { RuntimeApiClient } from './runtime-api-client';
import { RuntimeStartup } from './runtime-startup';
import { GameRuntime, PhaserGameFactory, PhaserGameHandle } from './game-runtime';

describe('GameRuntime', () => {
  let parent: HTMLElement;
  let configs: Phaser.Types.Core.GameConfig[];
  let games: jasmine.SpyObj<PhaserGameHandle>[];
  let createGame: jasmine.Spy<PhaserGameFactory>;

  beforeEach(() => {
    parent = document.createElement('div');
    document.body.appendChild(parent);
    configs = [];
    games = [];
    createGame = jasmine.createSpy('createGame').and.callFake((config) => {
      configs.push(config);
      const canvas = document.createElement('canvas');
      (config.parent as HTMLElement).appendChild(canvas);
      const game = jasmine.createSpyObj<PhaserGameHandle>('PhaserGame', ['destroy']);
      game.destroy.and.callFake((removeCanvas?: boolean) => {
        if (removeCanvas) {
          canvas.remove();
        }
      });
      games.push(game);
      return game;
    });
  });

  afterEach(() => parent.remove());

  it('mounts one Phaser game and one canvas even when mount is repeated', () => {
    const runtime = new GameRuntime(createGame);

    runtime.mount(parent);
    runtime.mount(parent);

    expect(createGame).toHaveBeenCalledTimes(1);
    expect(configs[0].parent).toBe(parent);
    expect(parent.querySelectorAll('canvas').length).toBe(1);
    expect(runtime.isMounted).toBeTrue();
  });

  it('destroys the Phaser game and removes its canvas exactly once', () => {
    const runtime = new GameRuntime(createGame);
    runtime.mount(parent);

    runtime.destroy();
    runtime.destroy();

    expect(games[0].destroy).toHaveBeenCalledOnceWith(true);
    expect(parent.querySelector('canvas')).toBeNull();
    expect(runtime.isMounted).toBeFalse();
  });

  it('allows a fresh runtime after the previous runtime is destroyed', () => {
    const firstRuntime = new GameRuntime(createGame);
    firstRuntime.mount(parent);
    firstRuntime.destroy();

    const secondRuntime = new GameRuntime(createGame);
    secondRuntime.mount(parent);

    expect(createGame).toHaveBeenCalledTimes(2);
    expect(parent.querySelectorAll('canvas').length).toBe(1);
    expect(secondRuntime.isMounted).toBeTrue();
  });

  it('shares one runtime-owned state marker across accepted gameplay scenes', () => {
    const runtime = new GameRuntime(createGame);
    runtime.mount(parent);
    const scenes = configs[0].scene as Phaser.Scene[];
    const gameScene = scenes.find((scene) => scene instanceof GameScene) as GameScene;
    const runScene = scenes.find((scene) => scene instanceof RunScene) as RunScene;
    const battleScene = scenes.find((scene) => scene instanceof BattleScene) as BattleScene;

    gameScene.init();
    runScene.init();
    battleScene.init();

    expect(gameScene.runtimeState).toBe(runtime.lifecycleState);
    expect(runScene.runtimeState).toBe(runtime.lifecycleState);
    expect(battleScene.runtimeState).toBe(runtime.lifecycleState);
    expect(runtime.lifecycleState.sceneEntries).toEqual([
      GAME_SCENE_KEY,
      RUN_SCENE_KEY,
      BATTLE_SCENE_KEY,
    ]);
    expect(createGame).toHaveBeenCalledTimes(1);
  });

  it('shares startup services and cache across scenes without refetching startup resources', async () => {
    const revision = 'a'.repeat(64);
    const apiClient = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getBootstrap']);
    apiClient.getBootstrap.and.resolveTo({
      ok: true,
      data: {
        account: { id: '1', display_name: 'Goblin', role: 'user' },
        player: {
          teeth: 0,
          raw_chaos: 0,
          energy: {
            current: 50,
            normal_max: 50,
            regeneration_per_hour: 12,
            regeneration_interval_seconds: 300,
            last_regeneration_at: '2026-09-11T00:00:00Z',
            next_regeneration_at: null,
            fully_regenerated_at: null,
          },
          player_revision: 1,
        },
        session: { authenticated: true, csrf_token: 'csrf' },
        server_time: '2026-09-11T00:00:00Z',
        content_revision: revision,
        progression: { unlock_ids: [] },
        active_squad: null,
        active_run: null,
      },
    });
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
    const runtime = new GameRuntime(createGame, startup);
    runtime.mount(parent);
    const scenes = configs[0].scene as Phaser.Scene[];

    await startup.start();
    for (const scene of scenes.slice(1) as Array<GameScene | RunScene | BattleScene>) {
      scene.init();
      expect(scene.runtimeStartup).toBe(startup);
      expect(scene.runtimeStartup.store).toBe(startup.store);
      expect(scene.runtimeStartup.contentRegistry).toBe(startup.contentRegistry);
      await scene.runtimeStartup.start();
    }

    expect(contentLoader.loadProjection).toHaveBeenCalledTimes(1);
    expect(apiClient.getBootstrap).toHaveBeenCalledTimes(1);
    expect(startup.store.playerRevision).toBe(1);
  });

  it('discards startup cache on destroy so a fresh runtime can bootstrap independently', async () => {
    const makeStartup = (playerRevision: number): RuntimeStartup => {
      const revision = 'a'.repeat(64);
      const apiClient = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', [
        'getBootstrap',
      ]);
      apiClient.getBootstrap.and.resolveTo({
        ok: true,
        data: {
          account: { id: String(playerRevision), display_name: 'Goblin', role: 'user' },
          player: {
            teeth: 0,
            raw_chaos: 0,
            energy: {
              current: 50,
              normal_max: 50,
              regeneration_per_hour: 12,
              regeneration_interval_seconds: 300,
              last_regeneration_at: '2026-09-11T00:00:00Z',
              next_regeneration_at: null,
              fully_regenerated_at: null,
            },
            player_revision: playerRevision,
          },
          session: { authenticated: true, csrf_token: 'csrf' },
          server_time: '2026-09-11T00:00:00Z',
          content_revision: revision,
          progression: { unlock_ids: [] },
          active_squad: null,
          active_run: null,
        },
      });
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
      return new RuntimeStartup(apiClient, contentLoader);
    };
    const firstStartup = makeStartup(1);
    const firstRuntime = new GameRuntime(createGame, firstStartup);
    firstRuntime.mount(parent);
    await firstStartup.start();
    firstRuntime.destroy();

    const secondStartup = makeStartup(2);
    const secondRuntime = new GameRuntime(createGame, secondStartup);
    secondRuntime.mount(parent);
    await secondStartup.start();

    expect(firstStartup.store.bootstrap).toBeNull();
    expect(firstStartup.contentRegistry).toBeNull();
    expect(secondStartup.store.playerRevision).toBe(2);
  });

  it('does not fetch before Phaser enters its BootScene lifecycle', () => {
    const fetchSpy = spyOn(window, 'fetch');
    const runtime = new GameRuntime(createGame);

    runtime.mount(parent);

    expect(fetchSpy).not.toHaveBeenCalled();
  });
});
