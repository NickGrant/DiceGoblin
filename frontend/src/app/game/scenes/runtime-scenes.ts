import Phaser from 'phaser';
import { RuntimeStartup, RuntimeStartupSnapshot } from '../runtime/runtime-startup';

export const BOOT_SCENE_KEY = 'BootScene';
export const GAME_SCENE_KEY = 'GameScene';
export const RUN_SCENE_KEY = 'RunScene';
export const BATTLE_SCENE_KEY = 'BattleScene';

export type GameplaySceneKey =
  typeof GAME_SCENE_KEY | typeof RUN_SCENE_KEY | typeof BATTLE_SCENE_KEY;

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
    readonly runtimeStartup: RuntimeStartup,
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
  private detailText: Phaser.GameObjects.Text | null = null;

  constructor(
    readonly runtimeState: RuntimeLifecycleState,
    readonly runtimeStartup: RuntimeStartup,
  ) {
    super({ key: BOOT_SCENE_KEY });
  }

  create(): void {
    this.runtimeState.recordSceneEntry(BOOT_SCENE_KEY);
    const centerX = this.scale.width / 2;
    const centerY = this.scale.height / 2;
    this.add
      .text(centerX, centerY - 22, 'Dice Goblins', {
        color: '#f7e7bd',
        fontFamily: 'system-ui, sans-serif',
        fontSize: '32px',
        fontStyle: 'bold',
      })
      .setOrigin(0.5);
    this.detailText = this.add
      .text(centerX, centerY + 24, 'Loading game…', {
        align: 'center',
        color: '#c8b98f',
        fontFamily: 'system-ui, sans-serif',
        fontSize: '16px',
      })
      .setOrigin(0.5);

    void this.completeStartup();
  }

  private async completeStartup(): Promise<void> {
    const state = await this.runtimeStartup.start();
    if (!this.scene.isActive(BOOT_SCENE_KEY)) return;

    if (nextSceneForStartup(state) === GAME_SCENE_KEY) {
      this.scene.start(GAME_SCENE_KEY);
      return;
    }

    const message = startupMessage(state);
    this.detailText?.setText(message);
    if (state.status === 'content-mismatch' || state.status === 'failure') {
      this.detailText
        ?.setInteractive({ useHandCursor: true })
        .once('pointerup', () => window.location.reload());
    }
  }
}

export class GameScene extends RuntimeScene {
  constructor(runtimeState: RuntimeLifecycleState, runtimeStartup: RuntimeStartup) {
    super(GAME_SCENE_KEY, runtimeState, runtimeStartup);
  }

  create(): void {
    if (nextSceneForStartup(this.runtimeStartup.state) !== GAME_SCENE_KEY) {
      this.scene.start(BOOT_SCENE_KEY);
      return;
    }
    this.renderPlaceholder('Dice Goblins', 'Persistent Phaser runtime mounted');
  }
}

export class RunScene extends RuntimeScene {
  constructor(runtimeState: RuntimeLifecycleState, runtimeStartup: RuntimeStartup) {
    super(RUN_SCENE_KEY, runtimeState, runtimeStartup);
  }

  create(): void {
    this.renderPlaceholder('Run', 'RunScene lifecycle placeholder');
  }
}

export class BattleScene extends RuntimeScene {
  constructor(runtimeState: RuntimeLifecycleState, runtimeStartup: RuntimeStartup) {
    super(BATTLE_SCENE_KEY, runtimeState, runtimeStartup);
  }

  create(): void {
    this.renderPlaceholder('Battle', 'BattleScene lifecycle placeholder');
  }
}

export function nextSceneForStartup(state: RuntimeStartupSnapshot): typeof GAME_SCENE_KEY | null {
  return state.status === 'ready' ? GAME_SCENE_KEY : null;
}

export function startupMessage(state: RuntimeStartupSnapshot): string {
  if (state.status === 'content-mismatch') {
    return 'An update is required before playing. Click to reload.';
  }

  if (state.status === 'failure') {
    return state.reason === 'unauthorized'
      ? 'Your session has expired. Click to reload or sign in again.'
      : 'The game could not start safely. Click to reload.';
  }

  return state.status === 'ready' ? 'Ready' : 'Loading game…';
}

export function createRuntimeScenes(
  runtimeState: RuntimeLifecycleState,
  runtimeStartup: RuntimeStartup,
): Phaser.Scene[] {
  return [
    new BootScene(runtimeState, runtimeStartup),
    new GameScene(runtimeState, runtimeStartup),
    new RunScene(runtimeState, runtimeStartup),
    new BattleScene(runtimeState, runtimeStartup),
  ];
}
