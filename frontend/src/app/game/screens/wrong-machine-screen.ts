import Phaser from 'phaser';
import { readDebugCaptureRequest } from '../../core/debug/debug-capture';
import { GameStore, WrongMachineState } from '../runtime/game-store';
import { ReconstructionPayload, ReconstructionResult, WrongMachineRecipe, reconstructionPayload } from '../runtime/wrong-machine-contracts';
import { ClientContentRegistry } from '../runtime/client-content-registry';
import { RetainedMutationAttempt, RetainedMutationState } from '../runtime/retained-mutation-attempt';
import { RuntimeApiClient, RuntimeApiError } from '../runtime/runtime-api-client';
import { RuntimeViewport, RuntimeViewportSnapshot, Bounds } from '../runtime/runtime-viewport';
import { createEconomyLayout, EconomyLayout } from './economy-layout';
import { GameSceneScreen } from './game-screen-navigation';
import { progressionBackground, progressionButton, progressionText } from './progression-surface';

const box = (x: number, y: number, width: number, height: number): Bounds =>
  ({ x, y, width, height, right: x + width, bottom: y + height });

export function wrongMachineStatus(recipe: WrongMachineRecipe, rawChaos: number): string {
  if (recipe.reconstructable) return 'READY';
  if (!recipe.prerequisitesMet) return 'PREREQUISITE REQUIRED';
  if (!recipe.eligibleUnitTypes.length) return 'NO ELIGIBLE UNIT TYPES';
  if (rawChaos < recipe.price.amount) return 'INSUFFICIENT RAW CHAOS';
  if (recipe.ingredients.some((item) => item.owned < item.quantity)) return 'MATERIALS REQUIRED';
  return 'UNAVAILABLE';
}

export class WrongMachineScreen implements GameSceneScreen {
  readonly key = 'wrong-machine' as const;
  private root: Phaser.GameObjects.Container | null = null;
  private unsubscribeRead: (() => void) | null = null;
  private unsubscribePlayer: (() => void) | null = null;
  private readonly attempt: RetainedMutationAttempt<ReconstructionPayload, ReconstructionResult>;
  private recipeIndex = 0;
  private typePage = 0;
  private selectedUnitTypeId: string | null = null;
  private confirming = false;
  private message = '';
  private activeLayout: EconomyLayout | null = null;

  constructor(private readonly scene: Phaser.Scene, private readonly store: GameStore, private readonly api: RuntimeApiClient,
    private readonly content: ClientContentRegistry, private readonly viewport: RuntimeViewport, private readonly back: () => void,
    createKey: () => string = () => crypto.randomUUID()) {
    this.attempt = new RetainedMutationAttempt(createKey);
  }

  get layout(): EconomyLayout | null { return this.activeLayout; }
  get actionState(): RetainedMutationState { return this.attempt.state; }
  get attemptIdentity(): Readonly<{ identity: string; request: ReconstructionPayload; key: string }> | null { return this.attempt.identity; }
  get selectedTypeId(): string | null { return this.selectedUnitTypeId; }
  get selectedRecipeIndex(): number { return this.recipeIndex; }

  create(): void {
    const debug = readDebugCaptureRequest();
    const page = debug?.scene.toLowerCase() === 'wrong-machine' ? debug.sceneData['page']
      : window.__DG_DEBUG__?.requestedScene.toLowerCase() === 'wrong-machine' ? window.__DG_DEBUG__.sceneData['page'] : null;
    if (typeof page === 'number' && Number.isSafeInteger(page) && page >= 0) this.recipeIndex = page;
    this.unsubscribeRead = this.store.subscribeWrongMachine(() => this.reflow(this.viewport.snapshot));
    this.unsubscribePlayer = this.store.subscribePlayer(() => this.reflow(this.viewport.snapshot));
    this.reflow(this.viewport.snapshot);
    void this.store.retryWrongMachine(this.api, this.content);
  }

  destroy(): void {
    this.unsubscribeRead?.(); this.unsubscribeRead = null;
    this.unsubscribePlayer?.(); this.unsubscribePlayer = null;
    this.root?.destroy(true); this.root = null; this.publishReady(false);
  }

  requestBack(): void {
    if (this.blocked) { this.message = 'Resolve the uncertain reconstruction before leaving.'; this.reflow(this.viewport.snapshot); return; }
    this.back();
  }

  selectRecipe(index: number): void {
    if (this.blocked || this.store.wrongMachine.status !== 'fresh'
      || index < 0 || index >= (this.store.wrongMachine.data?.recipes.length ?? 0)) return;
    this.recipeIndex = index; this.typePage = 0; this.selectedUnitTypeId = null; this.confirming = false;
    this.message = ''; this.reflow(this.viewport.snapshot);
  }

  selectUnitType(id: string): void {
    const recipe = this.selectedRecipe();
    if (this.blocked || recipe?.mode !== 'repeat_reconstruction'
      || !recipe.eligibleUnitTypes.some((type) => type.id === id)) return;
    this.selectedUnitTypeId = id; this.confirming = false; this.message = ''; this.reflow(this.viewport.snapshot);
  }

  changeTypePage(delta: number): void {
    const recipe = this.selectedRecipe();
    if (this.blocked || !recipe) return;
    const pages = Math.max(1, Math.ceil(recipe.eligibleUnitTypes.length / this.typePageSize));
    this.typePage = Math.max(0, Math.min(pages - 1, this.typePage + delta));
    this.reflow(this.viewport.snapshot);
  }

  confirmSelected(): void {
    if (this.blocked || !this.actionableSelected()) return;
    this.confirming = true; this.reflow(this.viewport.snapshot);
  }

  cancelConfirmation(): void { if (!this.blocked) { this.confirming = false; this.reflow(this.viewport.snapshot); } }
  retryRead(): void { if (!this.blocked) void this.store.retryWrongMachine(this.api, this.content); }

  async submitReconstruction(): Promise<void> {
    const bootstrap = this.store.bootstrap;
    if (!bootstrap || this.attempt.state === 'submitting') return;
    if (this.attempt.state !== 'retryable') {
      const recipe = this.actionableSelected();
      if (!this.confirming || !recipe) return;
      const payload = reconstructionPayload(recipe, this.selectedUnitTypeId);
      this.attempt.begin(JSON.stringify(payload), payload);
    }
    this.message = 'Reconstructing…'; this.reflow(this.viewport.snapshot);
    const outcome = await this.attempt.submit((payload, key) =>
      this.api.reconstructKin(payload, bootstrap.session.csrf_token, key, this.content));
    if (outcome.kind === 'success') {
      this.confirming = false; this.selectedUnitTypeId = null;
      try {
        this.store.reconcileReconstruction(outcome.result, this.content);
        this.message = `${outcome.result.unit.displayName} restored as ${this.content.getKin(outcome.result.unit.kinId)?.display_name ?? 'Kin'}.`;
        await this.store.retryWrongMachine(this.api, this.content);
        if (this.store.wrongMachine.status === 'error') this.message = 'Reconstruction committed. Retry the Wrong Machine read.';
      } catch { this.message = 'Reconstruction committed. Reload to recover progression safely.'; }
    } else if (outcome.kind === 'ambiguous') {
      this.message = 'Outcome uncertain. RETRY uses the same reconstruction attempt.';
    } else if (outcome.kind === 'rejected') {
      this.confirming = false;
      const error = outcome.error;
      if (error instanceof RuntimeApiError && error.code === 'reconstruction_changed') {
        this.message = 'Reconstruction changed. Review the refreshed recipe before confirming again.';
      } else if (error instanceof RuntimeApiError && error.code === 'insufficient_raw_chaos') {
        this.message = 'Not enough Raw Chaos. Review your balance.';
      } else if (error instanceof RuntimeApiError && error.code === 'insufficient_ingredients') {
        this.message = 'Required materials are missing. Review the refreshed recipe.';
      } else if (error instanceof RuntimeApiError && error.kind === 'unauthorized') {
        this.message = 'Session expired. Reload and sign in again.';
      } else this.message = 'Reconstruction was rejected. Review the refreshed recipe.';
      await this.store.retryWrongMachine(this.api, this.content);
      this.selectedUnitTypeId = null;
    }
    this.reflow(this.viewport.snapshot);
  }

  reflow(snapshot: RuntimeViewportSnapshot): void {
    this.root?.destroy(true);
    this.activeLayout = createEconomyLayout(snapshot);
    this.render(snapshot, this.activeLayout, this.store.wrongMachine);
    this.publishReady(this.store.wrongMachine.status === 'fresh');
  }

  private publishReady(ready: boolean): void {
    const parent = (this.scene.sys as (Phaser.Scenes.Systems & { game?: Phaser.Game }) | undefined)?.game?.canvas.parentElement;
    if (parent) parent.dataset['wrongMachineReady'] = ready ? 'true' : 'false';
  }

  private get blocked(): boolean { return this.attempt.state === 'submitting' || this.attempt.state === 'retryable'; }
  private get typePageSize(): number { return this.viewport.snapshot.layoutClass === 'compact' ? 3 : 4; }
  private selectedRecipe(): WrongMachineRecipe | null {
    return this.store.wrongMachine.status === 'fresh'
      ? this.store.wrongMachine.data?.recipes[this.recipeIndex] ?? null : null;
  }
  private actionableSelected(): WrongMachineRecipe | null {
    const recipe = this.selectedRecipe();
    return recipe?.reconstructable && (recipe.mode === 'first_restoration'
      || recipe.eligibleUnitTypes.some((type) => type.id === this.selectedUnitTypeId)) ? recipe : null;
  }

  private render(snapshot: RuntimeViewportSnapshot, layout: EconomyLayout, state: WrongMachineState): void {
    const root = this.scene.add.container(0, 0).setScale(snapshot.gameScale); this.root = root;
    progressionBackground(this.scene, root, snapshot, layout.content);
    progressionButton(this.scene, root, layout.back, 'RETURN', () => this.requestBack(), !this.blocked);
    progressionText(this.scene, root, layout.back.right + 20, layout.header.y + 6, 'WRONG MACHINE',
      layout.mode === 'compact' ? 34 : 38, '#f5e8c8');
    progressionText(this.scene, root, layout.wallet.x, layout.wallet.y + 15,
      `${this.store.bootstrap?.player.raw_chaos ?? '—'} RAW CHAOS`, layout.mode === 'compact' ? 26 : 20, '#d0a5ef');
    if (state.status === 'not-loaded' || state.status === 'loading') {
      progressionText(this.scene, root, layout.content.x + 25, layout.content.y + 30, 'Loading Wrong Machine…', 24); return;
    }
    if (state.status === 'error' || state.status === 'stale') {
      progressionText(this.scene, root, layout.content.x + 25, layout.content.y + 30,
        state.error === 'integrity' ? 'Wrong Machine data could not be verified. Reload if retry fails.'
          : 'Wrong Machine needs a fresh read.', 22);
      progressionButton(this.scene, root, layout.action, 'RETRY READ', () => this.retryRead(), !this.blocked); return;
    }
    const recipes = state.data?.recipes ?? [];
    if (!recipes.length) {
      progressionText(this.scene, root, layout.content.x + 25, layout.content.y + 30, 'No reconstructions are available.', 24); return;
    }
    this.recipeIndex = Math.min(this.recipeIndex, recipes.length - 1);
    const recipe = recipes[this.recipeIndex];
    const x = layout.content.x + 24; const y = layout.content.y + 20;
    const width = layout.content.width - 48;
    const compact = layout.mode === 'compact';
    const headerSize = compact ? 34 : 28; const bodySize = compact ? 22 : 18;
    progressionText(this.scene, root, x, y, recipe.displayName, headerSize, '#3a2a1a');
    progressionText(this.scene, root, x, y + (compact ? 46 : 38), recipe.description, bodySize, '#5b351f', width * .58);
    progressionText(this.scene, root, x + width * .62, y + 4,
      recipe.kinRestored ? `${recipe.kin.display_name} RESTORED` : `${recipe.kin.display_name} UNRESTORED`,
      bodySize, '#315947', width * .36);
    progressionText(this.scene, root, x + width * .62, y + (compact ? 43 : 37),
      recipe.mode === 'first_restoration' ? 'FIRST RESTORATION' : 'REPEAT RECONSTRUCTION', bodySize, '#5b351f');
    const costY = y + (compact ? 125 : 100);
    progressionText(this.scene, root, x, costY, `${recipe.price.amount} RAW CHAOS / ${state.data?.rawChaos} OWNED`, bodySize, '#5b351f');
    recipe.ingredients.forEach((ingredient, index) => progressionText(this.scene, root, x, costY + 34 + index * (compact ? 33 : 27),
      `${ingredient.item.display_name}: ${ingredient.quantity} REQUIRED / ${ingredient.owned} OWNED`, bodySize, '#5b351f'));
    progressionText(this.scene, root, x + width * .62, costY,
      wrongMachineStatus(recipe, state.data?.rawChaos ?? 0), bodySize,
      recipe.reconstructable ? '#315947' : '#9a4434', width * .36);
    const choiceY = costY + 35 + recipe.ingredients.length * (compact ? 33 : 27);
    if (recipe.mode === 'first_restoration') {
      progressionText(this.scene, root, x, choiceY,
        `The server selects one unlocked unit type (${recipe.eligibleUnitTypes.length} eligible).`, bodySize, '#315947', width);
    } else {
      progressionText(this.scene, root, x, choiceY, 'CHOOSE AN UNLOCKED UNIT TYPE', bodySize, '#315947');
      const pages = Math.max(1, Math.ceil(recipe.eligibleUnitTypes.length / this.typePageSize));
      this.typePage = Math.min(this.typePage, pages - 1);
      const visible = recipe.eligibleUnitTypes.slice(this.typePage * this.typePageSize,
        (this.typePage + 1) * this.typePageSize);
      const gap = 10; const itemWidth = Math.min(compact ? 245 : 210, (width - gap * (visible.length - 1)) / Math.max(1, visible.length));
      visible.forEach((type, index) => {
        const bounds = box(x + index * (itemWidth + gap), choiceY + 37, itemWidth, compact ? 48 : 42);
        progressionButton(this.scene, root, bounds, type.display_name.toUpperCase(), () => this.selectUnitType(type.id), !this.blocked);
        if (this.selectedUnitTypeId === type.id) progressionText(this.scene, root, bounds.x + 5, bounds.bottom + 2,
          'SELECTED', compact ? 18 : 14, '#315947');
      });
      if (pages > 1) {
        const pageY = choiceY + (compact ? 90 : 83);
        progressionButton(this.scene, root, box(x, pageY, 95, 38), '‹ TYPES', () => this.changeTypePage(-1),
          this.typePage > 0 && !this.blocked);
        progressionText(this.scene, root, x + 105, pageY + 9, `${this.typePage + 1}/${pages}`, 17);
        progressionButton(this.scene, root, box(x + 155, pageY, 95, 38), 'TYPES ›', () => this.changeTypePage(1),
          this.typePage + 1 < pages && !this.blocked);
      }
    }
    const footerY = layout.content.bottom - (compact ? 104 : 75);
    progressionButton(this.scene, root, box(x, footerY, compact ? 125 : 100, compact ? 54 : 42), 'PREV',
      () => this.selectRecipe(this.recipeIndex - 1), this.recipeIndex > 0 && !this.blocked);
    progressionText(this.scene, root, x + (compact ? 140 : 115), footerY + 12,
      `${this.recipeIndex + 1} / ${recipes.length}`, compact ? 21 : 16);
    progressionButton(this.scene, root, box(x + (compact ? 210 : 170), footerY, compact ? 125 : 100, compact ? 54 : 42),
      'NEXT', () => this.selectRecipe(this.recipeIndex + 1), this.recipeIndex + 1 < recipes.length && !this.blocked);
    progressionButton(this.scene, root, box(layout.content.right - (compact ? 315 : 260), footerY,
      compact ? 290 : 235, compact ? 54 : 42),
      this.attempt.state === 'retryable' ? 'RETRY' : this.confirming ? 'CONFIRM' : 'RECONSTRUCT',
      () => this.attempt.state === 'retryable' || this.confirming
        ? void this.submitReconstruction() : this.confirmSelected(),
      this.attempt.state === 'retryable' || (!!this.actionableSelected() && !this.blocked));
    if (this.confirming && this.attempt.state !== 'retryable') progressionText(this.scene, root, x,
      footerY - 31, `Confirm ${recipe.displayName} for ${recipe.price.amount} Raw Chaos?`, compact ? 20 : 16);
    else if (this.message) progressionText(this.scene, root, x, footerY - 31, this.message, compact ? 20 : 16, '#5b351f', width);
  }
}
