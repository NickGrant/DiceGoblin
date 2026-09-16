import Phaser from 'phaser';
import { actionCursor } from '../screens/action-cursor';
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
import { RuntimeApiError } from '../runtime/runtime-api-client';
import { createRunMapLayout, createRunMapPresentation, runNodeColors } from '../runtime/run-map-model';
import { Bounds } from '../runtime/runtime-viewport';
import { CombatResolutionAttempt, CombatResolutionAttemptState } from '../runtime/combat-resolution-attempt';
import { BattlePlaybackController } from '../runtime/battle-playback-controller';
import { BattlePlaybackResult } from '../runtime/battle-playback-contracts';
import { battleArtTextureKey, battleColumnX, supportedBattleArtAssets } from '../runtime/battle-presentation-layout';

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

    const next = nextSceneForStartup(state, this.runtimeStartup.store.bootstrap?.active_run !== null,
      this.runtimeStartup.battlePresentation.marker !== null);
    if (next) {
      const preview = (readDebugCaptureRequest()?.scene ?? window.__DG_DEBUG__?.requestedScene ?? '').toLowerCase();
      // Capture-only navigation can inspect Warband lock presentation while real startup still routes to RunScene.
      this.scene.start(next === RUN_SCENE_KEY && ['warband', 'squad-editor', 'unit-configuration'].includes(preview ?? '')
        ? GAME_SCENE_KEY : next);
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
  private selectedNodeId: string | null = null;
  private abandonState: 'idle' | 'confirming' | 'submitting' | 'retryable' | 'rejected' | 'recovery-required' = 'idle';
  private abandonRunId: string | null = null;
  private abandonMessage = '';
  private combatMessage = '';
  constructor(
    runtimeState: RuntimeLifecycleState,
    runtimeStartup: RuntimeStartup,
    runtimeViewport: RuntimeViewport,
    private readonly combatAttempt: CombatResolutionAttempt = new CombatResolutionAttempt(),
  ) {
    super(RUN_SCENE_KEY, runtimeState, runtimeStartup, runtimeViewport);
  }

  preload(): void {
    for (const nodeType of this.runtimeStartup.contentRegistry?.listRunNodeTypes() ?? []) {
      const textureKey = `run-node-icon:${nodeType.icon_key}`;
      if (!this.textures.exists(textureKey)) this.load.image(textureKey, `assets/ui/icons/${nodeType.icon_key}.png`);
    }
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
    this.unsubscribeViewport = this.runtimeViewport.subscribe(() => this.reflow());
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

  get selectedMapNodeId(): string | null { return this.selectedNodeId; }

  get abandonActionState(): string { return this.abandonState; }
  get combatActionState(): CombatResolutionAttemptState { return this.combatAttempt.state; }

  reflow(): void { this.render(); }

  selectNode(nodeId: string): void {
    if (this.abandonState !== 'idle' || !this.runtimeStartup.store.currentRun.data?.nodes.some((node) => node.id === nodeId)) return;
    this.selectedNodeId = nodeId;
    this.render();
  }

  async activateSelectedCombat(): Promise<void> {
    const run = this.runtimeStartup.store.currentRun.data;
    const node = run?.nodes.find((candidate) => candidate.id === this.selectedNodeId);
    const bootstrap = this.runtimeStartup.store.bootstrap;
    if (!run || !node || !bootstrap || node.nodeTypeId !== 'run_node_type.combat') return;
    if (node.status === 'completed' && node.battleId) {
      this.presentBattle(bootstrap.account.id, node.battleId, run.id, node.id); return;
    }
    if (node.status !== 'available' || node.battleId !== null || this.combatAttempt.state === 'submitting') return;
    this.combatAttempt.begin(run.id, node.id); this.combatMessage = 'Resolving combat authoritatively…'; this.render();
    const outcome = await this.combatAttempt.submit(this.runtimeStartup.apiClient, bootstrap.session.csrf_token);
    if (outcome.kind === 'success') {
      const result = outcome.result;
      if (result.run.id !== run.id || result.node.id !== node.id) {
        this.combatMessage = 'The response did not match this combat. Reload to recover.'; this.render(); return;
      }
      this.runtimeStartup.battlePresentation.retainResolution(result);
      this.runtimeStartup.store.markCurrentRunStale();
      this.presentBattle(bootstrap.account.id, result.battle.id, result.run.id, result.node.id);
      return;
    }
    if (outcome.kind === 'already-resolved') {
      const content = this.runtimeStartup.contentRegistry;
      if (content) await this.runtimeStartup.store.retryCurrentRun(this.runtimeStartup.apiClient, content);
      const recovered = this.runtimeStartup.store.currentRun.data?.nodes.find((candidate) => candidate.id === node.id);
      if (recovered?.battleId) this.presentBattle(bootstrap.account.id, recovered.battleId, run.id, node.id);
      else { this.combatMessage = 'Combat was already resolved. Reload to recover its battle.'; this.render(); }
      return;
    }
    this.combatMessage = outcome.kind === 'ambiguous'
      ? 'The result is uncertain. Retry with the same combat attempt.'
      : 'Combat could not be entered. The run cache was not changed.';
    this.render();
  }

  private presentBattle(accountId: string, battleId: string, runId: string, nodeId: string): void {
    this.runtimeStartup.battlePresentation.establish(accountId, battleId, runId, nodeId);
    this.scene.start(BATTLE_SCENE_KEY);
  }

  returnToCamp(): void {
    if (this.abandonState === 'idle') this.scene.start(GAME_SCENE_KEY);
  }

  openAbandonConfirmation(): void {
    const run = this.runtimeStartup.store.currentRun.data;
    if (this.abandonState !== 'idle' || !run) return;
    this.abandonRunId = run.id;
    this.abandonState = 'confirming';
    this.abandonMessage = 'This run will end. The Energy spent to enter will not be refunded.';
    this.render();
  }

  cancelAbandon(): void {
    if (this.abandonState !== 'confirming' && this.abandonState !== 'rejected') return;
    this.abandonState = 'idle';
    this.abandonRunId = null;
    this.abandonMessage = '';
    this.render();
  }

  async confirmAbandon(): Promise<void> {
    if ((this.abandonState !== 'confirming' && this.abandonState !== 'retryable') || !this.abandonRunId) return;
    const content = this.runtimeStartup.contentRegistry;
    const bootstrap = this.runtimeStartup.store.bootstrap;
    if (!content || !bootstrap) return;
    const runId = this.abandonRunId;
    this.abandonState = 'submitting';
    this.abandonMessage = 'Ending the run safely…';
    this.render();
    let result: Awaited<ReturnType<RuntimeStartup['apiClient']['abandonRun']>>;
    try {
      result = await this.runtimeStartup.apiClient.abandonRun(runId, bootstrap.session.csrf_token, content);
    } catch (error) {
      if (this.isDefinitiveAbandonRejection(error)) {
        this.abandonState = 'rejected';
        this.abandonMessage = error instanceof RuntimeApiError && error.kind === 'unauthorized'
          ? 'Your session expired. Reload and sign in again.'
          : 'The run could not be abandoned. Its cached state has been preserved.';
      } else {
        this.abandonState = 'retryable';
        this.abandonMessage = 'The result is uncertain. Retry abandonment for this same run.';
      }
      this.render();
      return;
    }
    try {
      this.runtimeStartup.store.reconcileRunAbandon(result);
    } catch {
      this.abandonState = 'recovery-required';
      this.abandonMessage = 'The server ended the run, but local state disagrees. Reload to recover.';
      this.render();
      return;
    }
    this.abandonState = 'idle';
    this.abandonRunId = null;
    this.scene.start(GAME_SCENE_KEY);
  }

  private handleRunState(): void {
    const state = this.runtimeStartup.store.currentRun;
    if (state.status === 'fresh' && state.data === null && !this.runtimeStartup.store.bootstrap?.active_run) {
      this.scene.start(GAME_SCENE_KEY); return;
    }
    const debugScene = (readDebugCaptureRequest()?.scene ?? window.__DG_DEBUG__?.requestedScene ?? '').toLowerCase();
    if (debugScene === 'run-abandon' && state.status === 'fresh' && state.data && this.abandonState === 'idle') {
      this.openAbandonConfirmation();
      return;
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
    const run = state.data;
    this.host()?.setAttribute('data-run-map-ready', run && state.status === 'fresh' ? 'true' : 'false');
    this.host()?.setAttribute('data-run-abandon-confirmation', this.abandonState === 'idle' ? 'false' : 'true');
    if (!run) {
      const shell = runShellLayout(snapshot);
      const panel = this.add.graphics();
      panel.fillStyle(0x342418, 0.97); panel.fillRoundedRect(shell.x, shell.y, shell.width, shell.height, 24);
      panel.lineStyle(5, 0xc9972b, 1); panel.strokeRoundedRect(shell.x, shell.y, shell.width, shell.height, 24);
      root.add(panel);
      const title = this.add.text(shell.centerX, shell.y + 95, 'THE FARM', { color: '#f5e8c8', fontFamily: 'Georgia, serif',
        fontSize: snapshot.layoutClass === 'compact' ? '48px' : '44px', fontStyle: 'bold' }).setOrigin(0.5);
      let detail = 'Loading your persisted run…';
      if (state.status === 'error') detail = state.error === 'network'
        ? 'The run could not be reached. Your progress is safe.' : 'The run state could not be verified safely.';
      const status = this.add.text(shell.centerX, shell.y + 190, detail, { color: '#d9c9a5', fontFamily: 'system-ui, sans-serif',
        fontSize: snapshot.layoutClass === 'compact' ? '32px' : '20px', align: 'center', wordWrap: { width: shell.width - 100 } }).setOrigin(0.5);
      root.add([title, status]);
      if (state.status === 'error') this.addButton(root, { x: shell.centerX - 155, y: shell.y + shell.height - 190,
        width: 310, height: 58, right: shell.centerX + 155, bottom: shell.y + shell.height - 132 }, 'RETRY', () => this.retry());
      this.addButton(root, { x: shell.centerX - 155, y: shell.y + shell.height - 100,
        width: 310, height: 58, right: shell.centerX + 155, bottom: shell.y + shell.height - 42 }, 'RETURN TO CAMP', () => this.returnToCamp());
      return;
    }

    const presentation = createRunMapPresentation(run, content);
    const layout = createRunMapLayout(snapshot, presentation);
    this.renderMap(root, layout, presentation.regionName);
    if (this.abandonState !== 'idle') this.renderAbandonConfirmation(root, layout.confirmation);
  }

  private renderMap(root: Phaser.GameObjects.Container, layout: ReturnType<typeof createRunMapLayout>, regionName: string): void {
    const compact = this.runtimeViewport.snapshot.layoutClass === 'compact';
    const mapPanel = this.add.graphics();
    mapPanel.fillStyle(0x342418, 1); mapPanel.fillRoundedRect(layout.panel.x, layout.panel.y, layout.panel.width, layout.panel.height, 24);
    mapPanel.lineStyle(5, 0xc9972b, 1); mapPanel.strokeRoundedRect(layout.panel.x, layout.panel.y, layout.panel.width, layout.panel.height, 24);
    const title = this.add.text(layout.panel.x + layout.panel.width / 2, layout.titleY, regionName, {
      color: '#f5e8c8', fontFamily: 'Georgia, serif', fontSize: compact ? '46px' : '40px', fontStyle: 'bold',
    }).setOrigin(0.5);
    const identity = this.add.text(layout.panel.x + layout.panel.width / 2, layout.identityY,
      `Active run · ${layout.nodes.length} locations`, { color: '#d9c9a5', fontFamily: 'system-ui, sans-serif',
        fontSize: compact ? '25px' : '17px' }).setOrigin(0.5);
    root.add([mapPanel, title, identity]);
    const edgeGraphics = this.add.graphics();
    for (const edge of layout.edges) {
      edgeGraphics.lineStyle(compact ? 9 : 7, 0xb18a42, 0.85);
      edgeGraphics.lineBetween(edge.startX, edge.startY, edge.endX, edge.endY);
      const angle = Math.atan2(edge.endY - edge.startY, edge.endX - edge.startX);
      const arrow = compact ? 18 : 14;
      edgeGraphics.fillStyle(0xd5b45f, 1);
      edgeGraphics.fillTriangle(edge.endX, edge.endY,
        edge.endX - Math.cos(angle - 0.55) * arrow, edge.endY - Math.sin(angle - 0.55) * arrow,
        edge.endX - Math.cos(angle + 0.55) * arrow, edge.endY - Math.sin(angle + 0.55) * arrow);
    }
    root.add(edgeGraphics);
    for (const node of layout.nodes) {
      const colors = runNodeColors[node.status];
      const graphic = this.add.graphics();
      graphic.fillStyle(colors.fill, 1); graphic.fillCircle(node.centerX, node.centerY, node.radius);
      graphic.lineStyle(this.selectedNodeId === node.id ? 7 : 5,
        this.selectedNodeId === node.id ? 0xffffff : colors.border, 1);
      graphic.strokeCircle(node.centerX, node.centerY, node.radius);
      if (this.abandonState === 'idle') {
        graphic.setInteractive(new Phaser.Geom.Circle(node.centerX, node.centerY, node.radius), Phaser.Geom.Circle.Contains)
          .on('pointerup', () => this.selectNode(node.id));
        actionCursor(graphic);
      }
      const textureKey = `run-node-icon:${node.iconKey}`;
      const icon = this.textures.exists(textureKey)
        ? this.add.image(node.centerX, node.centerY - 7, textureKey).setDisplaySize(node.radius * 1.05, node.radius * 1.05)
        : this.add.text(node.centerX, node.centerY - 7, node.name.slice(0, 1).toUpperCase(), {
          color: colors.text, fontFamily: 'Georgia, serif', fontSize: compact ? '42px' : '36px', fontStyle: 'bold',
        }).setOrigin(0.5);
      const name = this.add.text(node.centerX, node.centerY + node.radius + 15, node.name, { color: '#f5e8c8',
        fontFamily: 'system-ui, sans-serif', fontSize: compact ? '26px' : '18px', fontStyle: 'bold' }).setOrigin(0.5);
      const status = this.add.text(node.centerX, node.centerY + node.radius + (compact ? 43 : 38), node.statusLabel.toUpperCase(), {
        color: colors.text, fontFamily: 'system-ui, sans-serif', fontSize: compact ? '22px' : '13px', fontStyle: 'bold',
      }).setOrigin(0.5);
      root.add([graphic, icon, name, status]);
    }
    const selected = layout.nodes.find((node) => node.id === this.selectedNodeId);
    const detail = this.add.text(layout.panel.x + layout.panel.width / 2, layout.detailY,
      selected ? `${selected.name} · ${selected.statusLabel} — ${selected.description}`
        : 'Select a location for details. Ready locations are not entered yet.', {
        color: '#b9d3bb', fontFamily: 'system-ui, sans-serif', fontSize: compact ? '26px' : '16px',
        align: 'center', wordWrap: { width: layout.panel.width - 160 },
      }).setOrigin(0.5);
    root.add(detail);
    if (selected?.nodeTypeId === 'run_node_type.combat') {
      const canFight = selected.status === 'available' && selected.battleId === null;
      const canWatch = selected.status === 'completed' && selected.battleId !== null;
      const submitting = this.combatAttempt.state === 'submitting';
      if (canFight || canWatch) this.addButton(root, layout.combatButton,
        canWatch ? 'WATCH / REPLAY BATTLE' : submitting ? 'RESOLVING…' : this.combatAttempt.state === 'retryable' ? 'RETRY FIGHT' : 'ENTER COMBAT',
        () => void this.activateSelectedCombat(), canWatch ? 0x315d68 : 0x8a5424, !submitting);
    }
    if (this.combatMessage) {
      const message = this.add.text(layout.panel.x + layout.panel.width / 2, layout.combatButton.y - 18, this.combatMessage,
        { color: '#f0c982', fontFamily: 'system-ui, sans-serif', fontSize: compact ? '22px' : '14px' }).setOrigin(0.5, 1);
      root.add(message);
    }
    this.addButton(root, layout.returnButton, 'RETURN TO CAMP', () => this.returnToCamp(), 0x244b3d, this.abandonState === 'idle');
    this.addButton(root, layout.abandonButton, 'ABANDON RUN', () => this.openAbandonConfirmation(), 0x7a302b, this.abandonState === 'idle');
  }

  private renderAbandonConfirmation(root: Phaser.GameObjects.Container, region: Bounds): void {
    const compact = this.runtimeViewport.snapshot.layoutClass === 'compact';
    const shade = this.add.graphics(); shade.fillStyle(0x06100e, 0.78);
    const snapshot = this.runtimeViewport.snapshot; shade.fillRect(0, 0, snapshot.logicalWidth, snapshot.logicalHeight);
    const panel = this.add.graphics(); panel.fillStyle(0x392219, 1); panel.fillRoundedRect(region.x, region.y, region.width, region.height, 22);
    panel.lineStyle(5, 0xd15a47, 1); panel.strokeRoundedRect(region.x, region.y, region.width, region.height, 22);
    const titleText = this.abandonState === 'recovery-required' ? 'RELOAD REQUIRED'
      : this.abandonState === 'retryable' ? 'RESULT UNCERTAIN'
      : this.abandonState === 'rejected' ? 'RUN PRESERVED'
      : this.abandonState === 'submitting' ? 'ENDING RUN…' : 'ABANDON THIS RUN?';
    const title = this.add.text(region.x + region.width / 2, region.y + 70, titleText, { color: '#fff0d0',
      fontFamily: 'Georgia, serif', fontSize: compact ? '38px' : '32px', fontStyle: 'bold' }).setOrigin(0.5);
    const message = this.add.text(region.x + region.width / 2, region.y + 155, this.abandonMessage, { color: '#efd6b0',
      fontFamily: 'system-ui, sans-serif', fontSize: compact ? '25px' : '19px', align: 'center',
      wordWrap: { width: region.width - 100 } }).setOrigin(0.5);
    root.add([shade, panel, title, message]);
    const buttonWidth = 260, buttonHeight = compact ? 92 : 56, buttonY = region.bottom - buttonHeight - 30;
    if (this.abandonState === 'confirming') {
      this.addButton(root, this.box(region.x + 55, buttonY, buttonWidth, buttonHeight), 'KEEP RUN', () => this.cancelAbandon());
      this.addButton(root, this.box(region.right - buttonWidth - 55, buttonY, buttonWidth, buttonHeight), 'CONFIRM ABANDON', () => void this.confirmAbandon(), 0x8a342c);
    } else if (this.abandonState === 'retryable') {
      this.addButton(root, this.box(region.x + (region.width - buttonWidth) / 2, buttonY, buttonWidth, buttonHeight), 'RETRY ABANDON', () => void this.confirmAbandon(), 0x8a342c);
    } else if (this.abandonState === 'rejected') {
      this.addButton(root, this.box(region.x + 55, buttonY, buttonWidth, buttonHeight), 'RETURN TO MAP', () => this.cancelAbandon());
      this.addButton(root, this.box(region.right - buttonWidth - 55, buttonY, buttonWidth, buttonHeight), 'RELOAD', () => window.location.reload());
    } else if (this.abandonState === 'recovery-required') {
      this.addButton(root, this.box(region.x + (region.width - buttonWidth) / 2, buttonY, buttonWidth, buttonHeight), 'RELOAD', () => window.location.reload());
    }
  }

  private addButton(root: Phaser.GameObjects.Container, region: Bounds, label: string, action: () => void, fill = 0x244b3d, enabled = true): void {
    const button = this.add.graphics(); button.fillStyle(enabled ? fill : 0x6c6658, 1); button.fillRoundedRect(region.x, region.y, region.width, region.height, 12);
    button.lineStyle(3, 0xc9972b, 1); button.strokeRoundedRect(region.x, region.y, region.width, region.height, 12);
    if (enabled) {
      button.setInteractive(new Phaser.Geom.Rectangle(region.x, region.y, region.width, region.height), Phaser.Geom.Rectangle.Contains).on('pointerup', action);
      actionCursor(button);
    }
    const text = this.add.text(region.x + region.width / 2, region.y + region.height / 2, label, { color: enabled ? '#fff4d3' : '#d4c8ae',
      fontFamily: 'system-ui, sans-serif', fontSize: this.runtimeViewport.snapshot.layoutClass === 'compact' ? '30px' : '17px', fontStyle: 'bold' }).setOrigin(0.5);
    root.add([button, text]);
  }

  private isDefinitiveAbandonRejection(error: unknown): boolean {
    return error instanceof RuntimeApiError && (error.kind === 'unauthorized'
      || (error.kind === 'http' && error.status !== null && error.status >= 400 && error.status < 500));
  }

  private host(): HTMLElement | null {
    return (this.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game?.canvas.parentElement ?? null;
  }

  private box(x: number, y: number, width: number, height: number): Bounds {
    return { x, y, width, height, right: x + width, bottom: y + height };
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
  private root: Phaser.GameObjects.Container | null = null;
  private controller: BattlePlaybackController | null = null;
  private loadState: 'loading' | 'retryable' | 'integrity-error' | 'invalid-marker' | 'playing' | 'complete' = 'loading';
  private message = 'Loading retained playback…';
  private timer: Phaser.Time.TimerEvent | null = null;
  private unsubscribeViewport: (() => void) | null = null;
  constructor(
    runtimeState: RuntimeLifecycleState,
    runtimeStartup: RuntimeStartup,
    runtimeViewport: RuntimeViewport,
  ) {
    super(BATTLE_SCENE_KEY, runtimeState, runtimeStartup, runtimeViewport);
  }

  preload(): void {
    for (const asset of supportedBattleArtAssets()) {
      const textureKey = battleArtTextureKey(asset.artKey);
      if (!this.textures.exists(textureKey)) this.load.image(textureKey, asset.path);
    }
  }

  create(): void {
    const bootstrap = this.runtimeStartup.store.bootstrap;
    const marker = this.runtimeStartup.battlePresentation.marker;
    if (this.runtimeStartup.state.status !== 'ready' || !bootstrap || !marker || marker.accountId !== bootstrap.account.id) {
      this.runtimeStartup.battlePresentation.clear();
      this.scene.start(bootstrap?.active_run ? RUN_SCENE_KEY : GAME_SCENE_KEY); return;
    }
    this.host()?.setAttribute('data-game-screen', 'battle');
    this.unsubscribeViewport = this.runtimeViewport.subscribe((snapshot) => {
      if (snapshot.portraitGateActive) { this.controller?.pause(); if (this.timer) this.timer.paused = true; }
      else {
        this.controller?.resume();
        if (this.timer) this.timer.paused = false;
        else if (this.controller?.snapshot.state === 'playing' && this.loadState === 'playing') this.scheduleNext();
      }
      this.render();
    });
    this.events.once(Phaser.Scenes.Events.SHUTDOWN, () => {
      this.unsubscribeViewport?.(); this.unsubscribeViewport = null; this.timer?.destroy(); this.timer = null;
      this.root?.destroy(true); this.root = null;
    });
    this.render(); void this.loadPlayback();
  }

  get playbackState(): string { return this.controller?.snapshot.state ?? this.loadState; }
  get playbackController(): BattlePlaybackController | null { return this.controller; }

  retryPlayback(): void { if (this.loadState === 'retryable' || this.loadState === 'integrity-error') void this.loadPlayback(); }

  recoverFromInvalidMarker(): void {
    if (this.loadState !== 'invalid-marker') return;
    this.scene.start(this.runtimeStartup.store.bootstrap?.active_run ? RUN_SCENE_KEY : GAME_SCENE_KEY);
  }

  private async loadPlayback(): Promise<void> {
    const marker = this.runtimeStartup.battlePresentation.marker;
    if (!marker) return;
    this.loadState = 'loading'; this.message = 'Loading retained playback…'; this.render();
    let playback: BattlePlaybackResult;
    try { playback = await this.runtimeStartup.apiClient.getBattlePlayback(marker.battleId); }
    catch (error) {
      const markerInvalid = error instanceof RuntimeApiError && error.kind === 'http' && error.status === 404;
      if (markerInvalid) this.runtimeStartup.battlePresentation.clear();
      if (markerInvalid) {
        this.loadState = 'invalid-marker'; this.message = 'This retained battle is unavailable. Return safely to continue.';
        this.render(); return;
      }
      this.loadState = error instanceof RuntimeApiError && (error.kind === 'network' || (error.kind === 'http' && (error.status ?? 0) >= 500))
        ? 'retryable' : 'integrity-error';
      this.message = this.loadState === 'retryable' ? 'Playback could not be reached. Retry the retained battle.'
        : 'Playback could not be verified safely. Combat will not be rerun.';
      this.render(); return;
    }
    if (playback.battle.id !== marker.battleId || playback.battle.runId !== marker.runId || playback.battle.runNodeId !== marker.runNodeId) {
      this.runtimeStartup.battlePresentation.clear(); this.loadState = 'invalid-marker';
      this.message = 'Retained playback identity did not match. Return safely to continue.'; this.render(); return;
    }
    this.controller = new BattlePlaybackController(playback); this.loadState = 'playing'; this.render(); this.scheduleNext();
  }

  private scheduleNext(): void {
    if (!this.controller || this.controller.snapshot.state !== 'playing') return;
    if (this.runtimeViewport.snapshot.portraitGateActive) { this.controller.pause(); return; }
    const event = this.controller.advance(); this.render();
    if (!event) return;
    const state: string = this.controller.snapshot.state;
    if (state === 'complete') { this.loadState = 'complete'; this.host()?.setAttribute('data-battle-playback', 'complete'); return; }
    this.timer = this.time.delayedCall(this.controller.durationFor(event), () => { this.timer = null; this.scheduleNext(); });
    if (this.runtimeViewport.snapshot.portraitGateActive) { this.controller.pause(); this.timer.paused = true; }
  }

  private render(): void {
    this.root?.destroy(true); const snapshot = this.runtimeViewport.snapshot; const root = this.add.container(0, 0).setScale(snapshot.gameScale); this.root = root;
    const background = this.add.graphics(); background.fillGradientStyle(0x071219, 0x183041, 0x120d18, 0x301824, 1);
    background.fillRect(0, 0, snapshot.logicalWidth, snapshot.logicalHeight); root.add(background);
    const safe = snapshot.safeBounds; const compact = snapshot.layoutClass === 'compact';
    const title = this.add.text(safe.x + safe.width / 2, safe.y + (compact ? 48 : 55), 'BATTLE PLAYBACK',
      { color: '#f5e8c8', fontFamily: 'Georgia, serif', fontSize: compact ? '42px' : '38px', fontStyle: 'bold' }).setOrigin(0.5); root.add(title);
    if (!this.controller) {
      const detail = this.add.text(safe.x + safe.width / 2, safe.y + safe.height / 2, this.message,
        { color: '#e6d4ad', fontFamily: 'system-ui, sans-serif', fontSize: compact ? '28px' : '21px', align: 'center', wordWrap: { width: safe.width - 140 } }).setOrigin(0.5); root.add(detail);
      if (this.loadState === 'retryable' || this.loadState === 'integrity-error') this.addBattleButton(root, safe.x + safe.width / 2, safe.bottom - 80, 'RETRY PLAYBACK', () => this.retryPlayback());
      if (this.loadState === 'invalid-marker') this.addBattleButton(root, safe.x + safe.width / 2, safe.bottom - 80,
        this.runtimeStartup.store.bootstrap?.active_run ? 'RETURN TO RUN' : 'RETURN TO CAMP', () => this.recoverFromInvalidMarker());
      return;
    }
    const state = this.controller.snapshot; const laneTop = safe.y + (compact ? 125 : 135); const laneHeight = safe.height - (compact ? 300 : 315);
    for (const participant of state.participants) {
      const sideCenter = participant.side === 'player' ? safe.x + safe.width * 0.27 : safe.x + safe.width * 0.73;
      const x = battleColumnX(participant.side, participant.position.x, sideCenter, compact ? 145 : 175);
      const y = laneTop + (participant.position.y + 0.5) * laneHeight / 3;
      const width = compact ? 205 : 225, height = compact ? 112 : 126;
      const card = this.add.graphics(); card.fillStyle(participant.defeated ? 0x3b3b3b : participant.side === 'player' ? 0x244f55 : 0x642f37, 0.96);
      card.fillRoundedRect(x - width / 2, y - height / 2, width, height, 14);
      card.lineStyle(state.actorKey === participant.combatantKey || state.targetKey === participant.combatantKey ? 6 : 3,
        state.actorKey === participant.combatantKey ? 0xf4c542 : state.targetKey === participant.combatantKey ? 0xf06a5e : 0xbda96e, 1);
      card.strokeRoundedRect(x - width / 2, y - height / 2, width, height, 14);
      const artTexture = battleArtTextureKey(participant.artKey);
      const art = this.textures.exists(artTexture)
        ? this.add.image(x, y - (compact ? 102 : 112), artTexture).setDisplaySize(compact ? 92 : 108, compact ? 76 : 88)
        : null;
      const name = this.add.text(x, y - 31, participant.displayName, { color: '#fff2cf', fontFamily: 'system-ui, sans-serif',
        fontSize: compact ? '22px' : '20px', fontStyle: 'bold' }).setOrigin(0.5);
      const hp = this.add.text(x, y + 2, `HP ${participant.currentHp} / ${participant.maxHp}`, { color: '#d8f0d8', fontFamily: 'system-ui, sans-serif', fontSize: compact ? '20px' : '17px' }).setOrigin(0.5);
      const hpBar = this.add.graphics(); const barWidth = width - 42, barY = y + 20;
      hpBar.fillStyle(0x111820, 1); hpBar.fillRoundedRect(x - barWidth / 2, barY, barWidth, 7, 3);
      if (participant.currentHp > 0) { hpBar.fillStyle(participant.currentHp / participant.maxHp > 0.3 ? 0x79c979 : 0xe56a5f, 1);
        hpBar.fillRoundedRect(x - barWidth / 2, barY, barWidth * participant.currentHp / participant.maxHp, 7, 3); }
      const status = this.add.text(x, y + 39, participant.defeated ? 'DEFEATED' : [...participant.statuses].map((id) => id.replaceAll('_', ' ')).join(' · '),
        { color: participant.defeated ? '#ff9c91' : '#c8bdf3', fontFamily: 'system-ui, sans-serif', fontSize: compact ? '17px' : '14px' }).setOrigin(0.5);
      root.add(art ? [card, art, name, hp, hpBar, status] : [card, name, hp, hpBar, status]);
    }
    const captionY = safe.bottom - (compact ? 120 : 125);
    const caption = this.add.text(safe.x + safe.width / 2, captionY, state.caption,
      { color: '#fff1bd', fontFamily: 'Georgia, serif', fontSize: compact ? '29px' : '25px', fontStyle: 'bold', align: 'center' }).setOrigin(0.5); root.add(caption);
    const facts = [state.dice, state.hit, state.state === 'complete' ? 'PLAYBACK COMPLETE' : `EVENT ${state.nextSequence}`].filter(Boolean).join('   ·   ');
    const factText = this.add.text(safe.x + safe.width / 2, captionY + (compact ? 42 : 38), facts,
      { color: '#d1c7ac', fontFamily: 'system-ui, sans-serif', fontSize: compact ? '20px' : '16px' }).setOrigin(0.5); root.add(factText);
    this.host()?.setAttribute('data-battle-playback', state.state);
  }

  private addBattleButton(root: Phaser.GameObjects.Container, x: number, y: number, label: string, action: () => void): void {
    const button = this.add.rectangle(x, y, 300, 58, 0x315d68).setStrokeStyle(3, 0xc9972b).setInteractive().on('pointerup', action); actionCursor(button);
    const text = this.add.text(x, y, label, { color: '#fff2cf', fontFamily: 'system-ui, sans-serif', fontSize: '18px', fontStyle: 'bold' }).setOrigin(0.5); root.add([button, text]);
  }
  private host(): HTMLElement | null { return (this.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game?.canvas.parentElement ?? null; }
}

export function nextSceneForStartup(
  state: RuntimeStartupSnapshot,
  hasActiveRun = false,
  hasBattlePresentation = false,
): typeof GAME_SCENE_KEY | typeof RUN_SCENE_KEY | typeof BATTLE_SCENE_KEY | null {
  return state.status === 'ready' ? (hasBattlePresentation ? BATTLE_SCENE_KEY : hasActiveRun ? RUN_SCENE_KEY : GAME_SCENE_KEY) : null;
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
