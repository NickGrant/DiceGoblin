import Phaser from 'phaser';
import { RuntimeLifecycleState, createRuntimeScenes } from '../scenes/runtime-scenes';
import { RuntimeStartup } from './runtime-startup';

export interface PhaserGameHandle {
  destroy(removeCanvas?: boolean, noReturn?: boolean): void;
}

export type PhaserGameFactory = (config: Phaser.Types.Core.GameConfig) => PhaserGameHandle;

const defaultPhaserGameFactory: PhaserGameFactory = (config) => new Phaser.Game(config);

export class GameRuntime {
  readonly lifecycleState = new RuntimeLifecycleState();

  private game: PhaserGameHandle | null = null;
  private destroyed = false;

  constructor(
    private readonly createGame: PhaserGameFactory = defaultPhaserGameFactory,
    readonly startup: RuntimeStartup = new RuntimeStartup(),
  ) {}

  get isMounted(): boolean {
    return this.game !== null;
  }

  mount(parent: HTMLElement): void {
    if (this.destroyed) {
      throw new Error('A destroyed GameRuntime cannot be mounted again.');
    }

    if (this.game) {
      return;
    }

    this.game = this.createGame({
      type: Phaser.AUTO,
      parent,
      width: parent.clientWidth || 960,
      height: parent.clientHeight || 540,
      backgroundColor: '#171b20',
      scene: createRuntimeScenes(this.lifecycleState, this.startup),
      scale: {
        mode: Phaser.Scale.RESIZE,
        autoCenter: Phaser.Scale.CENTER_BOTH,
      },
    });
  }

  destroy(): void {
    if (this.destroyed) {
      return;
    }

    this.destroyed = true;
    this.startup.dispose();
    this.game?.destroy(true);
    this.game = null;
  }
}
