import Phaser from 'phaser';
import { readDebugCaptureRequest } from '../../core/debug/debug-capture';
import { GameStore } from '../runtime/game-store';
import { RuntimeStartup, RuntimeStartupSnapshot } from '../runtime/runtime-startup';
import { RuntimeViewport } from '../runtime/runtime-viewport';
import { CampScreen } from '../screens/camp-screen';
import { GameSceneScreen, GameScreenKey, GameScreenNavigator } from '../screens/game-screen-navigation';
import { WarbandScreen, WarbandTab } from '../screens/warband-screen';
import { SquadEditorDraft } from '../screens/squad-editor-model';
import { SquadEditorInitialAction, SquadEditorScreen } from '../screens/squad-editor-screen';
import { WarbandSquadSummary } from '../runtime/warband-contracts';

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
    readonly runtimeViewport: RuntimeViewport,
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
    readonly runtimeViewport: RuntimeViewport,
  ) {
    super({ key: BOOT_SCENE_KEY });
  }

  create(): void {
    this.runtimeState.recordSceneEntry(BOOT_SCENE_KEY);
    (this.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game?.canvas.parentElement
      ?.setAttribute('data-game-screen', 'boot');
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
  private activeScreen: GameSceneScreen | null = null;
  private unsubscribeViewport: (() => void) | null = null;
  private readonly navigator = new GameScreenNavigator();
  private escapeKey: Phaser.Input.Keyboard.Key | null = null;

  constructor(
    runtimeState: RuntimeLifecycleState,
    runtimeStartup: RuntimeStartup,
    runtimeViewport: RuntimeViewport,
    private readonly createCampScreen: (
      scene: Phaser.Scene,
      store: GameStore,
      viewport: RuntimeViewport,
      openWarband: () => void,
    ) => GameSceneScreen = (scene, store, viewport, openWarband) => new CampScreen(scene, store, viewport, openWarband),
    private readonly createWarbandScreen: (
      scene: Phaser.Scene,
      startup: RuntimeStartup,
      viewport: RuntimeViewport,
      returnToCamp: () => void,
      openSquadEditor: (squad: WarbandSquadSummary | null, action?: SquadEditorInitialAction) => void,
      initialTab: WarbandTab,
    ) => GameSceneScreen = (scene, startup, viewport, returnToCamp, openSquadEditor, initialTab) => new WarbandScreen(
      scene, startup.store, startup.apiClient, startup.contentRegistry!, viewport, returnToCamp, openSquadEditor, initialTab,
    ),
    private readonly createSquadEditorScreen: (
      scene: Phaser.Scene,
      startup: RuntimeStartup,
      viewport: RuntimeViewport,
      draft: SquadEditorDraft,
      returnToWarband: () => void,
      initialAction: SquadEditorInitialAction,
    ) => GameSceneScreen = (scene, startup, viewport, draft, returnToWarband, initialAction) => new SquadEditorScreen(
      scene, startup.store, startup.apiClient, viewport, draft, returnToWarband, initialAction,
    ),
  ) {
    super(GAME_SCENE_KEY, runtimeState, runtimeStartup, runtimeViewport);
  }

  preload(): void {
    CampScreen.preload(this);
  }

  create(): void {
    if (nextSceneForStartup(this.runtimeStartup.state) !== GAME_SCENE_KEY) {
      this.scene.start(BOOT_SCENE_KEY);
      return;
    }

    const debug = readDebugCaptureRequest();
    const debugScene = debug?.scene ?? window.__DG_DEBUG__?.requestedScene ?? '';
    const debugTab = debug?.initialTab ?? window.__DG_DEBUG__?.initialTab ?? '';
    const wantsEditor = debugScene.toLowerCase() === 'squad-editor';
    const initialScreen: GameScreenKey = debugScene.toLowerCase() === 'warband' || wantsEditor ? 'warband' : 'camp';
    this.navigator.start(initialScreen);
    this.activateScreen(initialScreen, debugTab === 'units' || debugTab === 'dice' ? debugTab : 'squads');
    if (wantsEditor && this.runtimeStartup.contentRegistry) {
      void this.runtimeStartup.store.loadWarbandDomains(this.runtimeStartup.apiClient, this.runtimeStartup.contentRegistry)
        .then(() => {
          const squad = this.runtimeStartup.store.warband.squads.data?.[0];
          if (squad && this.scene.isActive(GAME_SCENE_KEY)) this.showSquadEditor(squad);
        });
    }
    this.unsubscribeViewport = this.runtimeViewport.subscribe((snapshot) => {
      this.activeScreen?.reflow(snapshot);
    });
    this.escapeKey = (this.input as Phaser.Input.InputPlugin | undefined)?.keyboard
      ?.addKey(Phaser.Input.Keyboard.KeyCodes.ESC) ?? null;
    this.escapeKey?.on('down', this.handleBackInput, this);
    this.events.once(Phaser.Scenes.Events.SHUTDOWN, () => {
      this.unsubscribeViewport?.();
      this.unsubscribeViewport = null;
      this.escapeKey?.off('down', this.handleBackInput, this);
      this.escapeKey = null;
      this.destroyActiveScreen();
    });
  }

  showCamp(): void {
    this.navigator.navigate('camp');
    this.activateScreen('camp');
  }

  showWarband(): void {
    this.navigator.navigate('warband');
    this.activateScreen('warband');
  }

  showSquadEditor(squad: WarbandSquadSummary | null, initialAction: SquadEditorInitialAction = 'none'): void {
    this.navigator.navigate('squad-editor');
    this.activateSquadEditor(squad ? SquadEditorDraft.edit(squad) : SquadEditorDraft.create(), initialAction);
  }

  goBack(): void {
    if (this.activeScreen?.requestBack) {
      this.activeScreen.requestBack();
      return;
    }
    if (this.activeScreen?.key !== 'warband') return;
    this.activateScreen(this.navigator.back('camp'));
  }

  get activeScreenKey(): GameSceneScreen['key'] | null {
    return this.activeScreen?.key ?? null;
  }

  private destroyActiveScreen(): void {
    this.activeScreen?.destroy();
    this.activeScreen = null;
  }

  private activateScreen(screen: GameScreenKey, initialTab: WarbandTab = 'squads'): void {
    this.destroyActiveScreen();
    if (screen === 'warband') {
      if (!this.runtimeStartup.contentRegistry) {
        this.scene.start(BOOT_SCENE_KEY);
        return;
      }
      this.activeScreen = this.createWarbandScreen(
        this, this.runtimeStartup, this.runtimeViewport, () => this.goBack(), (squad) => this.showSquadEditor(squad), initialTab,
      );
    } else {
      this.activeScreen = this.createCampScreen(
        this, this.runtimeStartup.store, this.runtimeViewport, () => this.showWarband(),
      );
    }
    (this.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game?.canvas.parentElement
      ?.setAttribute('data-game-screen', screen);
    this.activeScreen.create();
  }

  private activateSquadEditor(draft: SquadEditorDraft, initialAction: SquadEditorInitialAction = 'none'): void {
    this.destroyActiveScreen();
    this.activeScreen = this.createSquadEditorScreen(
      this, this.runtimeStartup, this.runtimeViewport, draft,
      () => this.activateScreen(this.navigator.back('warband')),
      initialAction,
    );
    (this.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game?.canvas.parentElement
      ?.setAttribute('data-game-screen', 'squad-editor');
    this.activeScreen.create();
  }

  private handleBackInput(): void {
    this.goBack();
  }
}

export class RunScene extends RuntimeScene {
  constructor(
    runtimeState: RuntimeLifecycleState,
    runtimeStartup: RuntimeStartup,
    runtimeViewport: RuntimeViewport,
  ) {
    super(RUN_SCENE_KEY, runtimeState, runtimeStartup, runtimeViewport);
  }

  create(): void {
    this.renderPlaceholder('Run', 'RunScene lifecycle placeholder');
  }
}

export class BattleScene extends RuntimeScene {
  constructor(
    runtimeState: RuntimeLifecycleState,
    runtimeStartup: RuntimeStartup,
    runtimeViewport: RuntimeViewport,
  ) {
    super(BATTLE_SCENE_KEY, runtimeState, runtimeStartup, runtimeViewport);
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
  runtimeViewport: RuntimeViewport,
): Phaser.Scene[] {
  return [
    new BootScene(runtimeState, runtimeStartup, runtimeViewport),
    new GameScene(runtimeState, runtimeStartup, runtimeViewport),
    new RunScene(runtimeState, runtimeStartup, runtimeViewport),
    new BattleScene(runtimeState, runtimeStartup, runtimeViewport),
  ];
}
