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
import { UnitConfigurationScreen } from '../screens/unit-configuration-screen';
import { RuntimeViewportSnapshot } from '../runtime/runtime-viewport';

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

    const next = nextSceneForStartup(state, this.runtimeStartup.store.bootstrap?.active_run !== null);
    if (next) {
      this.scene.start(next);
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
      enterRun: () => void,
      startup: RuntimeStartup,
    ) => GameSceneScreen = (scene, store, viewport, openWarband, enterRun, startup) => new CampScreen(
      scene, store, viewport, openWarband, enterRun, startup.apiClient, startup.contentRegistry,
    ),
    private readonly createWarbandScreen: (
      scene: Phaser.Scene,
      startup: RuntimeStartup,
      viewport: RuntimeViewport,
      returnToCamp: () => void,
      openSquadEditor: (squad: WarbandSquadSummary | null, action?: SquadEditorInitialAction) => void,
      initialTab: WarbandTab,
      openUnitConfiguration: (unitId: string) => void,
    ) => GameSceneScreen = (scene, startup, viewport, returnToCamp, openSquadEditor, initialTab, openUnitConfiguration) => new WarbandScreen(
      scene, startup.store, startup.apiClient, startup.contentRegistry!, viewport, returnToCamp, openSquadEditor, initialTab, openUnitConfiguration,
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
    private readonly createUnitConfigurationScreen: (
      scene: Phaser.Scene,
      startup: RuntimeStartup,
      viewport: RuntimeViewport,
      unitId: string,
      returnToWarband: () => void,
    ) => GameSceneScreen = (scene, startup, viewport, unitId, returnToWarband) => new UnitConfigurationScreen(
      scene, startup.store, startup.apiClient, startup.contentRegistry!, viewport, unitId, returnToWarband,
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
    const wantsSquadEditor = debugScene.toLowerCase() === 'squad-editor';
    const wantsUnitConfiguration = debugScene.toLowerCase() === 'unit-configuration';
    const initialScreen: GameScreenKey = debugScene.toLowerCase() === 'warband' || wantsSquadEditor || wantsUnitConfiguration ? 'warband' : 'camp';
    this.navigator.start(initialScreen);
    this.activateScreen(initialScreen, debugTab === 'units' || debugTab === 'dice' ? debugTab : 'squads');
    if ((wantsSquadEditor || wantsUnitConfiguration) && this.runtimeStartup.contentRegistry) {
      void this.runtimeStartup.store.loadWarbandDomains(this.runtimeStartup.apiClient, this.runtimeStartup.contentRegistry)
        .then(() => {
          if (!this.scene.isActive(GAME_SCENE_KEY)) return;
          if (wantsSquadEditor) {
            const squad = this.runtimeStartup.store.warband.squads.data?.[0];
            if (squad) this.showSquadEditor(squad);
          } else {
            const unit = this.runtimeStartup.store.warband.units.data?.[0];
            if (unit) this.showUnitConfiguration(unit.id);
          }
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

  showUnitConfiguration(unitId: string): void {
    this.navigator.navigate('unit-configuration');
    this.activateUnitConfiguration(unitId);
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
        this, this.runtimeStartup, this.runtimeViewport, () => this.goBack(),
        (squad, action) => this.showSquadEditor(squad, action), initialTab,
        (unitId) => this.showUnitConfiguration(unitId),
      );
    } else {
      this.activeScreen = this.createCampScreen(
        this, this.runtimeStartup.store, this.runtimeViewport, () => this.showWarband(),
        () => this.scene.start(RUN_SCENE_KEY), this.runtimeStartup,
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

  private activateUnitConfiguration(unitId: string): void {
    if (!this.runtimeStartup.contentRegistry) {
      this.scene.start(BOOT_SCENE_KEY);
      return;
    }
    this.destroyActiveScreen();
    this.activeScreen = this.createUnitConfigurationScreen(
      this, this.runtimeStartup, this.runtimeViewport, unitId,
      () => this.activateScreen(this.navigator.back('warband'), 'units'),
    );
    (this.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game?.canvas.parentElement
      ?.setAttribute('data-game-screen', 'unit-configuration');
    this.activeScreen.create();
  }

  private handleBackInput(): void {
    this.goBack();
  }
}

export class RunScene extends RuntimeScene {
  private root: Phaser.GameObjects.Container | null = null;
  private unsubscribeViewport: (() => void) | null = null;
  private unsubscribeRun: (() => void) | null = null;
  constructor(
    runtimeState: RuntimeLifecycleState,
    runtimeStartup: RuntimeStartup,
    runtimeViewport: RuntimeViewport,
  ) {
    super(RUN_SCENE_KEY, runtimeState, runtimeStartup, runtimeViewport);
  }

  create(): void {
    if (this.runtimeStartup.state.status !== 'ready' || !this.runtimeStartup.contentRegistry) {
      this.scene.start(BOOT_SCENE_KEY); return;
    }
    if (!this.runtimeStartup.store.bootstrap?.active_run) {
      this.scene.start(GAME_SCENE_KEY); return;
    }
    (this.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game?.canvas.parentElement
      ?.setAttribute('data-game-screen', 'run');
    this.unsubscribeViewport = this.runtimeViewport.subscribe(() => this.render());
    this.unsubscribeRun = this.runtimeStartup.store.subscribeCurrentRun(() => this.handleRunState());
    this.events.once(Phaser.Scenes.Events.SHUTDOWN, () => {
      this.unsubscribeViewport?.(); this.unsubscribeViewport = null;
      this.unsubscribeRun?.(); this.unsubscribeRun = null;
      this.root?.destroy(true); this.root = null;
    });
    this.render();
    void this.runtimeStartup.store.loadCurrentRun(this.runtimeStartup.apiClient, this.runtimeStartup.contentRegistry);
  }

  retry(): void {
    const content = this.runtimeStartup.contentRegistry;
    if (content) void this.runtimeStartup.store.retryCurrentRun(this.runtimeStartup.apiClient, content);
  }

  returnToCamp(): void { this.scene.start(GAME_SCENE_KEY); }

  private handleRunState(): void {
    const state = this.runtimeStartup.store.currentRun;
    if (state.status === 'fresh' && state.data === null && !this.runtimeStartup.store.bootstrap?.active_run) {
      this.scene.start(GAME_SCENE_KEY); return;
    }
    this.render();
  }

  private render(): void {
    const content = this.runtimeStartup.contentRegistry;
    if (!content) return;
    this.root?.destroy(true);
    const snapshot = this.runtimeViewport.snapshot;
    const state = this.runtimeStartup.store.currentRun;
    const root = this.add.container(0, 0).setScale(snapshot.gameScale);
    this.root = root;
    const background = this.add.graphics();
    background.fillGradientStyle(0x0b211b, 0x173d31, 0x071414, 0x102827, 1);
    background.fillRect(0, 0, snapshot.logicalWidth, snapshot.logicalHeight);
    root.add(background);
    const layout = runShellLayout(snapshot);
    const panel = this.add.graphics();
    panel.fillStyle(0x342418, 0.97); panel.fillRoundedRect(layout.x, layout.y, layout.width, layout.height, 24);
    panel.lineStyle(5, 0xc9972b, 1); panel.strokeRoundedRect(layout.x, layout.y, layout.width, layout.height, 24);
    root.add(panel);
    const run = state.data;
    const region = run ? content.getRegion(run.regionId) : null;
    const title = this.add.text(layout.centerX, layout.y + 70, region?.display_name ?? 'THE FARM', {
      color: '#f5e8c8', fontFamily: 'Georgia, serif', fontSize: snapshot.layoutClass === 'compact' ? '48px' : '44px', fontStyle: 'bold',
    }).setOrigin(0.5);
    let detail = 'Loading your persisted run…';
    if (state.status === 'error') detail = state.error === 'network' ? 'The run could not be reached. Your progress is safe.' : 'The run state could not be verified safely.';
    else if (run) detail = `Run #${run.id} · Active · Squad #${run.squadId}`;
    const status = this.add.text(layout.centerX, layout.y + 155, detail, {
      color: '#d9c9a5', fontFamily: 'system-ui, sans-serif', fontSize: snapshot.layoutClass === 'compact' ? '32px' : '20px', align: 'center',
      wordWrap: { width: layout.width - 100 },
    }).setOrigin(0.5);
    const note = this.add.text(layout.centerX, layout.y + 225,
      run ? 'The route is persisted on the server. Farm navigation arrives next.' : '', {
        color: '#98b79b', fontFamily: 'system-ui, sans-serif', fontSize: snapshot.layoutClass === 'compact' ? '26px' : '17px', align: 'center',
      }).setOrigin(0.5);
    root.add([title, status, note]);
    if (state.status === 'error') this.addShellButton(root, layout.centerX, layout.y + layout.height - 155, 'RETRY', () => this.retry());
    this.addShellButton(root, layout.centerX, layout.y + layout.height - 70, 'RETURN TO CAMP', () => this.returnToCamp());
  }

  private addShellButton(root: Phaser.GameObjects.Container, centerX: number, centerY: number, label: string, action: () => void): void {
    const width = 310, height = 58, x = centerX - width / 2, y = centerY - height / 2;
    const button = this.add.graphics(); button.fillStyle(0x244b3d, 1); button.fillRoundedRect(x, y, width, height, 12);
    button.lineStyle(3, 0xc9972b, 1); button.strokeRoundedRect(x, y, width, height, 12);
    button.setInteractive(new Phaser.Geom.Rectangle(x, y, width, height), Phaser.Geom.Rectangle.Contains).on('pointerup', action);
    const text = this.add.text(centerX, centerY, label, { color: '#fff4d3', fontFamily: 'system-ui, sans-serif', fontSize: this.runtimeViewport.snapshot.layoutClass === 'compact' ? '28px' : '19px', fontStyle: 'bold' }).setOrigin(0.5);
    root.add([button, text]);
  }
}

export function runShellLayout(snapshot: RuntimeViewportSnapshot): { x: number; y: number; width: number; height: number; centerX: number } {
  const margin = snapshot.layoutClass === 'compact' ? 28 : 56;
  const width = Math.min(snapshot.layoutClass === 'wide' ? 1120 : 980, snapshot.safeBounds.width - margin * 2);
  const height = Math.min(snapshot.layoutClass === 'compact' ? 590 : 560, snapshot.safeBounds.height - margin * 2);
  return { x: snapshot.safeBounds.x + (snapshot.safeBounds.width - width) / 2,
    y: snapshot.safeBounds.y + (snapshot.safeBounds.height - height) / 2, width, height,
    centerX: snapshot.safeBounds.x + snapshot.safeBounds.width / 2 };
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

export function nextSceneForStartup(
  state: RuntimeStartupSnapshot,
  hasActiveRun = false,
): typeof GAME_SCENE_KEY | typeof RUN_SCENE_KEY | null {
  return state.status === 'ready' ? (hasActiveRun ? RUN_SCENE_KEY : GAME_SCENE_KEY) : null;
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
