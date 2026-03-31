import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import UnifiedButtonList from "../components/clickable-panel/UnifiedButtonList";
import DiceCardGrid from "../components/DiceCardGrid";
import { getDebugSceneConfig } from "../debug/debugScene";
import { getDebugProfileFixture } from "../debug/debugFixtures";
import { markDebugSceneReady } from "../debug/debugHooks";
import { adaptDiceDetails } from "../adapters/profileViewModels";
import { apiClient } from "../services/apiClient";
import type { DiceDetailsViewModel } from "../adapters/profileViewModels";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";

const FRAME_TITLE_HEIGHT = 56;
const FRAME_MARGIN = 12;
const ACTION_PANEL_PADDING = 14;
const ACTION_CONTENT_GAP = 14;
const ACTION_BUTTON_WIDTH = 280;
const ACTION_BUTTON_GAP = 18;

type DiceSortMode = "rarity" | "size" | "equipped";
type DiceSizeFilter = "all" | "d4" | "d6" | "d8" | "d10" | "d12" | "d20";
type DiceRarityFilter = "all" | "common" | "uncommon" | "rare" | "epic" | "legendary";
type DiceEquippedFilter = "all" | "equipped" | "unequipped";

const SORT_LABELS: Record<DiceSortMode, string> = {
  rarity: "Rarity",
  size: "Size",
  equipped: "Equipped",
};

const SIZE_FILTER_ORDER: DiceSizeFilter[] = ["all", "d4", "d6", "d8", "d10", "d12", "d20"];
const RARITY_FILTER_ORDER: DiceRarityFilter[] = ["all", "common", "uncommon", "rare", "epic", "legendary"];
const EQUIPPED_FILTER_ORDER: DiceEquippedFilter[] = ["all", "equipped", "unequipped"];
const SORT_ORDER: DiceSortMode[] = ["rarity", "size", "equipped"];
const RARITY_SORT_VALUE: Record<string, number> = {
  common: 0,
  uncommon: 1,
  rare: 2,
  epic: 3,
  legendary: 4,
};

export default class DiceInventoryScene extends Phaser.Scene {
  private runId = "";
  private nodeId = "";
  private returnScene = "HomeScene";

  private dice: DiceDetailsViewModel[] = [];
  private selectedDiceId: string | null = null;
  private hoveredDiceId: string | null = null;
  private sortMode: DiceSortMode = "rarity";
  private sizeFilter: DiceSizeFilter = "all";
  private rarityFilter: DiceRarityFilter = "all";
  private equippedFilter: DiceEquippedFilter = "all";
  private diceGrid?: DiceCardGrid;
  private actionButtonList?: UnifiedButtonList;
  private viewEquippedUnitButton?: SharedActionButton;
  private sellDiceButton?: SharedActionButton;
  private compactControlObjects: Phaser.GameObjects.GameObject[] = [];
  private sortButtonText?: Phaser.GameObjects.Text;
  private sizeFilterButtonText?: Phaser.GameObjects.Text;
  private rarityFilterButtonText?: Phaser.GameObjects.Text;
  private equippedFilterButtonText?: Phaser.GameObjects.Text;
  private actionSummaryUiObjects: Phaser.GameObjects.GameObject[] = [];
  private actionSummaryText?: Phaser.GameObjects.Text;
  private hoverDetailsText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;
  private sellInFlight = false;

  constructor() {
    super({ key: "DiceInventoryScene" });
  }

  init(data: { runId?: string; nodeId?: string; returnScene?: string; unitId?: string }): void {
    this.runId = String(data?.runId ?? "");
    this.nodeId = String(data?.nodeId ?? "");
    this.returnScene = String(data?.returnScene ?? "HomeScene");
  }

  create(): void {
    new BackgroundImage(this);
    mountBottomCommandStrip(this);
    const layout = getPageLayout(this);
    const contentFrame = new ContentAreaFrame({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
      title: "Dice Inventory",
      bodyColor: 0x4f5a65,
    });
    contentFrame.setDepth(-800);
    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Manage Inventory",
      bodyColor: 0x006f7a,
    });
    actionsFrame.setDepth(-800);
    this.buildActionColumn();

    void this.loadData();
  }

  private async loadData(): Promise<void> {
    const profile = await apiClient.getProfile({ force: true }).catch(() => {
      const debugConfig = getDebugSceneConfig();
      if (!debugConfig.enabled) {
        throw new Error("Failed to fetch");
      }
      return getDebugProfileFixture();
    });
    if (!profile.ok) {
      this.showToast(`Profile unavailable: ${profile.error.message}`);
      markDebugSceneReady(this, { state: "error" });
      return;
    }
    this.dice = adaptDiceDetails(profile.data.dice ?? [], profile.data.units ?? []);
    if (this.selectedDiceId === null && this.dice.length > 0) {
      this.selectedDiceId = this.getVisibleDice()[0]?.id ?? this.dice[0]?.id ?? null;
    }
    this.renderDiceGrid();
    this.refreshActionSummary();
    markDebugSceneReady(this, {
      dice: this.dice.length,
      selectedDiceId: this.selectedDiceId,
    });
  }

  private renderDiceGrid(): void {
    const layout = getPageLayout(this);
    const contentBodyX = layout.content.x + FRAME_MARGIN;
    const contentBodyY = layout.content.y + FRAME_TITLE_HEIGHT + FRAME_MARGIN;
    const contentBodyWidth = Math.max(280, layout.content.width - FRAME_MARGIN * 2);
    const contentBodyHeight = Math.max(240, layout.content.height - FRAME_TITLE_HEIGHT - FRAME_MARGIN * 2);

    const dicePanelX = contentBodyX;
    const dicePanelWidth = Math.max(280, contentBodyWidth);
    const visibleDice = this.getVisibleDice();
    if (this.selectedDiceId === null && visibleDice.length > 0) {
      this.selectedDiceId = visibleDice[0]?.id ?? null;
    }
    const selectedIndex = visibleDice.findIndex((die) => die.id === this.selectedDiceId);
    const safeSelected = selectedIndex >= 0 ? selectedIndex : 0;
    if (visibleDice.length > 0) {
      const selectedDie = visibleDice[safeSelected];
      this.selectedDiceId = selectedDie ? selectedDie.id : null;
    } else {
      this.selectedDiceId = null;
    }

    this.diceGrid?.destroy();
    this.diceGrid = new DiceCardGrid({
      scene: this,
      x: dicePanelX,
      y: contentBodyY,
      width: dicePanelWidth,
      height: contentBodyHeight,
      title: "DICE",
      dice: visibleDice,
      selectedDiceId: this.selectedDiceId,
      onDiceClick: (die) => {
        this.selectedDiceId = die.id;
        this.diceGrid?.setSelectedDiceId(die.id);
        this.refreshActionSummary();
      },
      onDiceHover: (die) => {
        this.hoveredDiceId = die.id;
        this.refreshActionSummary();
      },
      onDiceHoverEnd: (die) => {
        if (this.hoveredDiceId === die.id) {
          this.hoveredDiceId = null;
          this.refreshActionSummary();
        }
      },
    });
  }

  private openEquippedUnit(): void {
    const selectedDie = this.getSelectedDie();
    const equippedUnitId = selectedDie?.equipped?.unitId;
    if (!equippedUnitId) {
      this.showToast("Selected die is not equipped.");
      return;
    }

    this.scene.start("UnitDetailsScene", { unitId: equippedUnitId });
  }

  private async sellSelectedDie(): Promise<void> {
    const selectedDie = this.getSelectedDie();
    if (!selectedDie) {
      this.showToast("Select a die to sell.");
      return;
    }
    if (selectedDie.equipped) {
      this.showToast("Unequip this die before selling it.");
      return;
    }
    if (this.sellInFlight) {
      return;
    }

    this.sellInFlight = true;
    this.sellDiceButton?.setEnabled(false);
    this.sellDiceButton?.setText(`Selling (${selectedDie.sellValue})`);

    try {
      const response = await apiClient.sellDice(selectedDie.id);
      if (!response.ok) {
        this.showToast(`Sell failed: ${response.error.message}`);
        return;
      }

      this.dice = this.dice.filter((die) => die.id !== selectedDie.id);
      const visibleDice = this.getVisibleDice();
      this.selectedDiceId = visibleDice[0]?.id ?? this.dice[0]?.id ?? null;
      this.hoveredDiceId = null;
      this.renderDiceGrid();
      this.refreshActionSummary();
      this.showToast(`Sold ${selectedDie.displayName} for ${response.data.sell_value}.`, "#d8ffd6");
    } catch (error) {
      const message = error instanceof Error ? error.message : "Unexpected error.";
      this.showToast(`Sell failed: ${message}`);
    } finally {
      this.sellInFlight = false;
      this.refreshActionSummary();
    }
  }

  private buildActionColumn(): void {
    const layout = getPageLayout(this);
    const actionsBodyX = layout.buttons.x + FRAME_MARGIN;
    const actionsBodyY = layout.buttons.y + FRAME_TITLE_HEIGHT + FRAME_MARGIN;
    const actionsBodyWidth = Math.max(280, layout.buttons.width - FRAME_MARGIN * 2);
    const actionsBodyHeight = Math.max(220, layout.buttons.height - FRAME_TITLE_HEIGHT - FRAME_MARGIN * 2);
    const inRestContext = this.runId !== "" && this.nodeId !== "";

    this.clearActionSummaryUi();
    const summaryCardX = actionsBodyX + ACTION_PANEL_PADDING;
    const summaryCardY = actionsBodyY + ACTION_PANEL_PADDING;
    const summaryCardWidth = Math.max(120, actionsBodyWidth - ACTION_PANEL_PADDING * 2);
    const summaryCardHeight = Math.min(180, Math.max(118, Math.floor(actionsBodyHeight * 0.24)));
    const summaryCard = this.add
      .rectangle(summaryCardX, summaryCardY, summaryCardWidth, summaryCardHeight, 0x0f2024, 0.56)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x8db8bc, 0.45);
    this.actionSummaryUiObjects.push(summaryCard);

    this.actionSummaryText = this.add
      .text(summaryCardX + 12, summaryCardY + 10, "INVENTORY SUMMARY\nLoading...", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "18px",
        color: "#e7f4f5",
        lineSpacing: 9,
        wordWrap: { width: Math.max(120, summaryCardWidth - 24) },
      })
      .setOrigin(0, 0);
    this.actionSummaryUiObjects.push(this.actionSummaryText);

    const hoverCardY = summaryCardY + summaryCardHeight + 12;
    const hoverCardHeight = 150;
    const hoverCard = this.add
      .rectangle(summaryCardX, hoverCardY, summaryCardWidth, hoverCardHeight, 0x10292e, 0.62)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xa9d6da, 0.35);
    this.actionSummaryUiObjects.push(hoverCard);

    this.hoverDetailsText = this.add
      .text(summaryCardX + 12, hoverCardY + 10, "AFFIX DETAILS\nHover a die to inspect its affixes.", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "14px",
        color: "#e2f8fa",
        lineSpacing: 5,
        wordWrap: { width: Math.max(120, summaryCardWidth - 24) },
      })
      .setOrigin(0, 0);
    this.actionSummaryUiObjects.push(this.hoverDetailsText);

    const actionButtonX = actionsBodyX + Math.max(0, Math.floor((actionsBodyWidth - ACTION_BUTTON_WIDTH) / 2));
    const actionButtonY = hoverCardY + hoverCardHeight + ACTION_CONTENT_GAP;

    this.actionButtonList?.destroy();
    this.viewEquippedUnitButton?.destroy();
    this.sellDiceButton?.destroy();
    this.clearCompactControls();

    const buttons = [] as Array<{ label: string; onClick: () => void }>;
    if (inRestContext || this.returnScene !== "HomeScene") {
      buttons.push({
        label: inRestContext ? "Back to Rest" : "Back",
        onClick: () => this.scene.start(this.returnScene, {
          runId: this.runId,
          nodeId: this.nodeId,
        }),
      });
    }

    this.actionButtonList = new UnifiedButtonList({
      scene: this,
      x: actionButtonX,
      y: actionButtonY,
      gapY: ACTION_BUTTON_GAP,
      buttons,
    });

    const listHeight = buttons.length > 0
      ? buttons.length * 64 + (buttons.length - 1) * ACTION_BUTTON_GAP
      : 0;
    const equippedButtonY = actionButtonY + listHeight + (buttons.length > 0 ? ACTION_BUTTON_GAP : 0);
    this.viewEquippedUnitButton = new SharedActionButton({
      scene: this,
      x: actionButtonX,
      y: equippedButtonY,
      label: "View Equipped Unit",
      enabled: false,
      onClick: () => this.openEquippedUnit(),
    });

    const sellButtonY = equippedButtonY + 64 + ACTION_BUTTON_GAP;
    this.sellDiceButton = new SharedActionButton({
      scene: this,
      x: actionButtonX,
      y: sellButtonY,
      label: "Sell Die",
      enabled: false,
      variant: "reject",
      onClick: () => {
        void this.sellSelectedDie();
      },
    });

    const controlsStartY = sellButtonY + 64 + ACTION_BUTTON_GAP;
    const controlWidth = Math.floor((ACTION_BUTTON_WIDTH - 12) / 2);
    const controlHeight = 44;
    const controlGapX = 12;
    const controlGapY = 10;
    this.sortButtonText = this.createCompactControlButton(
      actionButtonX,
      controlsStartY,
      controlWidth,
      controlHeight,
      "",
      () => {
        this.sortMode = this.cycleValue(SORT_ORDER, this.sortMode);
        this.syncDiceFilters();
      }
    );
    this.sizeFilterButtonText = this.createCompactControlButton(
      actionButtonX + controlWidth + controlGapX,
      controlsStartY,
      controlWidth,
      controlHeight,
      "",
      () => {
        this.sizeFilter = this.cycleValue(SIZE_FILTER_ORDER, this.sizeFilter);
        this.syncDiceFilters();
      }
    );
    this.rarityFilterButtonText = this.createCompactControlButton(
      actionButtonX,
      controlsStartY + controlHeight + controlGapY,
      controlWidth,
      controlHeight,
      "",
      () => {
        this.rarityFilter = this.cycleValue(RARITY_FILTER_ORDER, this.rarityFilter);
        this.syncDiceFilters();
      }
    );
    this.equippedFilterButtonText = this.createCompactControlButton(
      actionButtonX + controlWidth + controlGapX,
      controlsStartY + controlHeight + controlGapY,
      controlWidth,
      controlHeight,
      "",
      () => {
        this.equippedFilter = this.cycleValue(EQUIPPED_FILTER_ORDER, this.equippedFilter);
        this.syncDiceFilters();
      }
    );
    this.refreshControlButtonLabels();
  }

  private refreshActionSummary(): void {
    if (!this.actionSummaryText || !this.hoverDetailsText) {
      return;
    }

    const visibleDice = this.getVisibleDice();
    const selectedDie = this.getSelectedDie();
    const hoveredDie = this.hoveredDiceId
      ? visibleDice.find((die) => die.id === this.hoveredDiceId) ?? this.dice.find((die) => die.id === this.hoveredDiceId)
      : null;
    const detailDie = hoveredDie ?? selectedDie ?? null;
    const equippedUnitName = selectedDie?.equipped?.unitName ?? "None";

    this.actionSummaryText.setText([
      "INVENTORY SUMMARY",
      `Dice: ${visibleDice.length} / ${this.dice.length}`,
      `Selected Die: ${selectedDie?.displayName ?? "None"}`,
      `Equipped To: ${equippedUnitName}`,
      `Value: ${selectedDie?.value ?? 0}`,
      `Sell Price: ${selectedDie?.sellValue ?? 0}`,
    ].join("\n"));

    this.hoverDetailsText.setText(this.buildHoverDetails(detailDie, hoveredDie !== null));
    this.viewEquippedUnitButton?.setEnabled(Boolean(selectedDie?.equipped?.unitId));
    this.sellDiceButton?.setEnabled(Boolean(selectedDie) && !selectedDie?.equipped && !this.sellInFlight);
    this.sellDiceButton?.setText(selectedDie ? `Sell (${selectedDie.sellValue})` : "Sell Die");
    this.refreshControlButtonLabels();
  }

  private clearActionSummaryUi(): void {
    for (const uiObject of this.actionSummaryUiObjects) {
      uiObject.destroy();
    }
    this.actionSummaryUiObjects = [];
    this.actionSummaryText = undefined;
    this.hoverDetailsText = undefined;
    this.clearCompactControls();
  }

  private createCompactControlButton(
    x: number,
    y: number,
    width: number,
    height: number,
    label: string,
    onClick: () => void,
  ): Phaser.GameObjects.Text {
    const background = this.add
      .rectangle(x, y, width, height, 0xf2ead8, 0.94)
      .setOrigin(0, 0)
      .setStrokeStyle(2, 0x7a5f39, 0.85);
    const text = this.add
      .text(x + 8, y + (height / 2), label, {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "14px",
        color: "#3e2b16",
        wordWrap: { width: width - 16 },
      })
      .setOrigin(0, 0.5);
    const zone = this.add.zone(x + (width / 2), y + (height / 2), width, height)
      .setOrigin(0.5, 0.5)
      .setInteractive({ useHandCursor: true });

    zone.on("pointerdown", onClick);
    zone.on("pointerover", () => background.setFillStyle(0xfff2cf, 1));
    zone.on("pointerout", () => background.setFillStyle(0xf2ead8, 0.94));

    this.compactControlObjects.push(background, text, zone);
    return text;
  }

  private clearCompactControls(): void {
    for (const object of this.compactControlObjects) {
      object.destroy();
    }
    this.compactControlObjects = [];
    this.sortButtonText = undefined;
    this.sizeFilterButtonText = undefined;
    this.rarityFilterButtonText = undefined;
    this.equippedFilterButtonText = undefined;
  }

  private refreshControlButtonLabels(): void {
    this.sortButtonText?.setText(`Sort\n${SORT_LABELS[this.sortMode]}`);
    this.sizeFilterButtonText?.setText(`Size\n${this.sizeFilter.toUpperCase()}`);
    this.rarityFilterButtonText?.setText(`Rarity\n${this.rarityFilter.toUpperCase()}`);
    this.equippedFilterButtonText?.setText(`Equipped\n${this.equippedFilter.toUpperCase()}`);
  }

  private syncDiceFilters(): void {
    const visibleDice = this.getVisibleDice();
    if (visibleDice.length === 0) {
      this.selectedDiceId = null;
    } else if (!visibleDice.some((die) => die.id === this.selectedDiceId)) {
      this.selectedDiceId = visibleDice[0]?.id ?? null;
    }
    this.hoveredDiceId = null;
    this.renderDiceGrid();
    this.refreshActionSummary();
  }

  private getVisibleDice(): DiceDetailsViewModel[] {
    return [...this.dice]
      .filter((die) => this.matchesFilters(die))
      .sort((a, b) => this.compareDice(a, b));
  }

  private matchesFilters(die: DiceDetailsViewModel): boolean {
    if (this.sizeFilter !== "all" && die.sizeLabel.toLowerCase() !== this.sizeFilter) {
      return false;
    }
    if (this.rarityFilter !== "all" && die.rarity.toLowerCase() !== this.rarityFilter) {
      return false;
    }
    if (this.equippedFilter === "equipped" && !die.equipped) {
      return false;
    }
    if (this.equippedFilter === "unequipped" && die.equipped) {
      return false;
    }
    return true;
  }

  private compareDice(a: DiceDetailsViewModel, b: DiceDetailsViewModel): number {
    if (this.sortMode === "equipped") {
      const equippedDelta = Number(Boolean(b.equipped)) - Number(Boolean(a.equipped));
      if (equippedDelta !== 0) {
        return equippedDelta;
      }
    }
    if (this.sortMode === "size") {
      const sizeDelta = this.sizeValue(b.sizeLabel) - this.sizeValue(a.sizeLabel);
      if (sizeDelta !== 0) {
        return sizeDelta;
      }
    }
    if (this.sortMode === "rarity" || this.sortMode === "equipped") {
      const rarityDelta = (RARITY_SORT_VALUE[b.rarity] ?? -1) - (RARITY_SORT_VALUE[a.rarity] ?? -1);
      if (rarityDelta !== 0) {
        return rarityDelta;
      }
    }
    if (this.sortMode === "rarity") {
      const sizeDelta = this.sizeValue(b.sizeLabel) - this.sizeValue(a.sizeLabel);
      if (sizeDelta !== 0) {
        return sizeDelta;
      }
    }
    return a.displayName.localeCompare(b.displayName);
  }

  private sizeValue(sizeLabel: string): number {
    const raw = Number(sizeLabel.replace(/[^0-9]/g, ""));
    return Number.isFinite(raw) ? raw : 0;
  }

  private cycleValue<T extends string>(values: readonly T[], current: T): T {
    const currentIndex = values.indexOf(current);
    const nextIndex = currentIndex < 0 ? 0 : (currentIndex + 1) % values.length;
    return values[nextIndex] ?? values[0]!;
  }

  private getSelectedDie(): DiceDetailsViewModel | null {
    if (!this.selectedDiceId) {
      return null;
    }

    return this.getVisibleDice().find((die) => die.id === this.selectedDiceId)
      ?? this.dice.find((die) => die.id === this.selectedDiceId)
      ?? null;
  }

  private buildHoverDetails(die: DiceDetailsViewModel | null, hovered: boolean): string {
    if (!die) {
      return "AFFIX DETAILS\nHover a die to inspect its affixes.";
    }

    const affixLines = die.affixes.map((affix) => {
      if (affix.empty) {
        return "Empty Slot";
      }
      return `${affix.label} | ${affix.rarity.toUpperCase()} | ${affix.kindLabel}\n${affix.valueLabel} | ${affix.description}`;
    });

    return [
      hovered ? "AFFIX DETAILS (HOVER)" : "AFFIX DETAILS",
      `${die.displayName} | ${die.sizeLabel.toUpperCase()} | ${die.rarity.toUpperCase()}`,
      ...affixLines,
    ].join("\n");
  }

  private showToast(message: string, color = "#ffcccc"): void {
    this.toastText?.destroy();
    const layout = getPageLayout(this);
    this.toastText = this.add.text(layout.content.x + 16, layout.content.y + layout.content.height - 24, message, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color,
    }).setOrigin(0, 0);
    this.time.delayedCall(2000, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }
}
