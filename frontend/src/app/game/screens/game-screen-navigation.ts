import { RuntimeViewportSnapshot } from '../runtime/runtime-viewport';

export type GameScreenKey = 'camp' | 'warband';

export interface GameSceneScreen {
  readonly key: GameScreenKey;
  create(): void;
  reflow(snapshot: RuntimeViewportSnapshot): void;
  destroy(): void;
}

/** Small history model for destinations rendered inside the persistent GameScene. */
export class GameScreenNavigator {
  private currentScreen: GameScreenKey | null = null;
  private readonly history: GameScreenKey[] = [];

  get current(): GameScreenKey | null {
    return this.currentScreen;
  }

  get canGoBack(): boolean {
    return this.history.length > 0;
  }

  start(screen: GameScreenKey): GameScreenKey {
    this.history.length = 0;
    this.currentScreen = screen;
    return screen;
  }

  navigate(screen: GameScreenKey): GameScreenKey {
    if (this.currentScreen === screen) return screen;
    if (this.currentScreen) this.history.push(this.currentScreen);
    this.currentScreen = screen;
    return screen;
  }

  back(fallback: GameScreenKey = 'camp'): GameScreenKey {
    this.currentScreen = this.history.pop() ?? fallback;
    return this.currentScreen;
  }
}
