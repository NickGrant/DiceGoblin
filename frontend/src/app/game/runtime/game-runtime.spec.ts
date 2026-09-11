import Phaser from 'phaser';
import {
  BATTLE_SCENE_KEY,
  GAME_SCENE_KEY,
  GameScene,
  RUN_SCENE_KEY,
  RunScene,
  BattleScene,
} from '../scenes/runtime-scenes';
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

  it('does not require backend or bootstrap requests to mount', () => {
    const fetchSpy = spyOn(window, 'fetch');
    const runtime = new GameRuntime(createGame);

    runtime.mount(parent);

    expect(fetchSpy).not.toHaveBeenCalled();
  });
});
