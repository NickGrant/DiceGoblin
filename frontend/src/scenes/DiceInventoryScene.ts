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

export default class DiceInventoryScene extends Phaser.Scene {
  private runId = "";
  private nodeId = "";
  private returnScene = "HomeScene";

  private dice: DiceDetailsViewModel[] = [];
  private selectedDiceId: string | null = null;
  private diceGrid?: DiceCardGrid;
  private actionButtonList?: UnifiedButtonList;
  private viewEquippedUnitButton?: SharedActionButton;
  private actionSummaryUiObjects: Phaser.GameObjects.GameObject[] = [];
  private actionSummaryText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;

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
      this.selectedDiceId = this.dice[0]?.id ?? null;
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
    if (this.selectedDiceId === null && this.dice.length > 0) {
      this.selectedDiceId = this.dice[0]?.id ?? null;
    }
    const selectedIndex = this.dice.findIndex((d) => d.id === this.selectedDiceId);
    const safeSelected = selectedIndex >= 0 ? selectedIndex : 0;
    if (this.dice.length > 0) {
      const selectedDie = this.dice[safeSelected];
      this.selectedDiceId = selectedDie ? selectedDie.id : null;
    }
    this.diceGrid?.destroy();
    this.diceGrid = new DiceCardGrid({
      scene: this,
      x: dicePanelX,
      y: contentBodyY,
      width: dicePanelWidth,
      height: contentBodyHeight,
      title: "DICE",
      dice: this.dice,
      selectedDiceId: this.selectedDiceId,
      onDiceClick: (die) => {
        this.selectedDiceId = die.id;
        this.diceGrid?.setSelectedDiceId(die.id);
        this.refreshActionSummary();
      },
    });
  }

  private openEquippedUnit(): void {
    const selectedDie = this.selectedDiceId
      ? this.dice.find((die) => die.id === this.selectedDiceId)
      : null;
    const equippedUnitId = selectedDie?.equipped?.unitId;
    if (!equippedUnitId) {
      this.showToast("Selected die is not equipped.");
      return;
    }

    this.scene.start("UnitDetailsScene", { unitId: equippedUnitId });
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
    const summaryCardHeight = Math.min(230, Math.max(140, Math.floor(actionsBodyHeight * 0.48)));
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

    const actionButtonX = actionsBodyX + Math.max(0, Math.floor((actionsBodyWidth - ACTION_BUTTON_WIDTH) / 2));
    const actionButtonY = summaryCardY + summaryCardHeight + ACTION_CONTENT_GAP;

    this.actionButtonList?.destroy();
    this.viewEquippedUnitButton?.destroy();
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
  }

  private refreshActionSummary(): void {
    if (!this.actionSummaryText) {
      return;
    }

    const selectedDie = this.selectedDiceId
      ? this.dice.find((die) => die.id === this.selectedDiceId)
      : null;
    const equippedUnitName = selectedDie?.equipped?.unitName ?? "None";
    this.actionSummaryText.setText([
      "INVENTORY SUMMARY",
      `Dice: ${this.dice.length}`,
      `Selected Die: ${selectedDie?.displayName ?? "None"}`,
      `Equipped To: ${equippedUnitName}`,
    ].join("\n"));

    this.viewEquippedUnitButton?.setEnabled(Boolean(selectedDie?.equipped?.unitId));
  }

  private clearActionSummaryUi(): void {
    for (const uiObject of this.actionSummaryUiObjects) {
      uiObject.destroy();
    }
    this.actionSummaryUiObjects = [];
    this.actionSummaryText = undefined;
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









