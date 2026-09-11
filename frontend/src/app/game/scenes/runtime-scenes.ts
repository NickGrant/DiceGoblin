import Phaser from 'phaser';

export const BOOT_SCENE_KEY = 'BootScene';
export const GAME_SCENE_KEY = 'GameScene';
export const RUN_SCENE_KEY = 'RunScene';
export const BATTLE_SCENE_KEY = 'BattleScene';

export type GameplaySceneKey =
  | typeof GAME_SCENE_KEY
  | typeof RUN_SCENE_KEY
  | typeof BATTLE_SCENE_KEY;

/**
 * A deliberately small runtime-owned marker used to verify that scene changes
 * do not replace application-lifetime state.
 */
export class RuntimeLifecycleState {
  private readonly entries: string[] = [];

  get sceneEntries(): readonly string[] {
    return this.entries;
  }

  recordSceneEntry(sceneKey: string): void {
    this.entries.push(sceneKey);
  }
}

abstract class RuntimeScene extends Phaser.Scene {
  protected constructor(
    private readonly runtimeSceneKey: GameplaySceneKey,
    readonly runtimeState: RuntimeLifecycleState,
  ) {
    super({ key: runtimeSceneKey });
  }

  init(): void {
    this.runtimeState.recordSceneEntry(this.runtimeSceneKey);
  }

  protected renderPlaceholder(title: string, detail: string): void {
    const centerX = this.scale.width / 2;
    const centerY = this.scale.height / 2;

    this.add
      .text(centerX, centerY - 22, title, {
        color: '#f7e7bd',
        fontFamily: 'system-ui, sans-serif',
        fontSize: '32px',
        fontStyle: 'bold',
      })
      .setOrigin(0.5);
    this.add
      .text(centerX, centerY + 24, detail, {
        align: 'center',
        color: '#c8b98f',
        fontFamily: 'system-ui, sans-serif',
        fontSize: '16px',
      })
      .setOrigin(0.5);
  }
}

export class BootScene extends Phaser.Scene {
  constructor(readonly runtimeState: RuntimeLifecycleState) {
    super({ key: BOOT_SCENE_KEY });
  }

  create(): void {
    this.runtimeState.recordSceneEntry(BOOT_SCENE_KEY);
    this.scene.start(GAME_SCENE_KEY);
  }
}

export class GameScene extends RuntimeScene {
  constructor(runtimeState: RuntimeLifecycleState) {
    super(GAME_SCENE_KEY, runtimeState);
  }

  create(): void {
    this.renderPlaceholder('Dice Goblins', 'Persistent Phaser runtime mounted');
  }
}

export class RunScene extends RuntimeScene {
  constructor(runtimeState: RuntimeLifecycleState) {
    super(RUN_SCENE_KEY, runtimeState);
  }

  create(): void {
    this.renderPlaceholder('Run', 'RunScene lifecycle placeholder');
  }
}

export class BattleScene extends RuntimeScene {
  constructor(runtimeState: RuntimeLifecycleState) {
    super(BATTLE_SCENE_KEY, runtimeState);
  }

  create(): void {
    this.renderPlaceholder('Battle', 'BattleScene lifecycle placeholder');
  }
}

export function createRuntimeScenes(runtimeState: RuntimeLifecycleState): Phaser.Scene[] {
  return [
    new BootScene(runtimeState),
    new GameScene(runtimeState),
    new RunScene(runtimeState),
    new BattleScene(runtimeState),
  ];
}
