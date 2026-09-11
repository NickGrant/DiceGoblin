import Phaser from 'phaser';
import { RuntimeLifecycleState, createRuntimeScenes } from '../scenes/runtime-scenes';
import { PortraitGate } from './portrait-gate';
import { RuntimeStartup } from './runtime-startup';
import { RuntimeViewport, RuntimeViewportSnapshot } from './runtime-viewport';

export interface PhaserGameHandle {
  readonly input?: { enabled: boolean };
  readonly scale?: { resize(width: number, height: number): void };
  destroy(removeCanvas?: boolean, noReturn?: boolean): void;
}

export type PhaserGameFactory = (config: Phaser.Types.Core.GameConfig) => PhaserGameHandle;

const defaultPhaserGameFactory: PhaserGameFactory = (config) => new Phaser.Game(config);

export class GameRuntime {
  readonly lifecycleState = new RuntimeLifecycleState();

  private game: PhaserGameHandle | null = null;
  private parent: HTMLElement | null = null;
  private portraitGate: PortraitGate | null = null;
  private unsubscribeViewport: (() => void) | null = null;
  private destroyed = false;

  constructor(
    private readonly createGame: PhaserGameFactory = defaultPhaserGameFactory,
    readonly startup: RuntimeStartup = new RuntimeStartup(),
    readonly viewport: RuntimeViewport = new RuntimeViewport(),
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

    this.parent = parent;
    const viewport = this.viewport.mount(parent);
    this.portraitGate = new PortraitGate(parent);
    this.game = this.createGame({
      type: Phaser.AUTO,
      parent,
      width: viewport.cssWidth,
      height: viewport.cssHeight,
      backgroundColor: '#171b20',
      scene: createRuntimeScenes(this.lifecycleState, this.startup, this.viewport),
      scale: {
        mode: Phaser.Scale.RESIZE,
        autoCenter: Phaser.Scale.CENTER_BOTH,
      },
    });
    const canvas = parent.querySelector('canvas');
    if (canvas) {
      canvas.style.display = 'block';
      canvas.style.position = 'absolute';
      canvas.style.inset = '0';
    }
    this.unsubscribeViewport = this.viewport.subscribe((snapshot) =>
      this.applyViewport(snapshot),
    );
    this.applyViewport(viewport, false);
  }

  destroy(): void {
    if (this.destroyed) {
      return;
    }

    this.destroyed = true;
    this.unsubscribeViewport?.();
    this.unsubscribeViewport = null;
    this.viewport.destroy();
    this.startup.dispose();
    this.game?.destroy(true);
    this.game = null;
    this.portraitGate?.destroy();
    this.portraitGate = null;
    if (this.parent) {
      delete this.parent.dataset['gameLayout'];
      delete this.parent.dataset['gameOrientationGate'];
      delete this.parent.dataset['gameScreen'];
    }
    this.parent = null;
  }

  private applyViewport(snapshot: RuntimeViewportSnapshot, resizeGame = true): void {
    if (resizeGame) {
      this.game?.scale?.resize(snapshot.cssWidth, snapshot.cssHeight);
    }
    if (this.game?.input) {
      this.game.input.enabled = !snapshot.portraitGateActive;
    }
    const canvas = this.parent?.querySelector('canvas');
    if (canvas) canvas.inert = snapshot.portraitGateActive;
    this.portraitGate?.update(snapshot);
    if (this.parent) {
      this.parent.dataset['gameLayout'] = snapshot.layoutClass;
      this.parent.dataset['gameOrientationGate'] = snapshot.portraitGateActive
        ? 'active'
        : 'inactive';
    }
  }
}
