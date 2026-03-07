import BackgroundImage from "../components/BackgroundImage";
import HudPanel from "../components/HudPanel";
import ActionButton from "../components/clickable-panel/ActionButton";
import ActionButtonList from "../components/clickable-panel/ActionButtonList";
import UnitCardGrid, { type UnitCardState } from "../components/UnitCardGrid";
import { adaptDiceDetails, adaptUnitRecords } from "../adapters/profileViewModels";
import { apiClient } from "../services/apiClient";
import type { UnitRecord } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";
import HomeCornerButton from "../components/navigation/HomeCornerButton";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";

export default class UnitDetailsScene extends Phaser.Scene {
  private unitId = "";
  private loadingText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;
  private detailsText?: Phaser.GameObjects.Text;
  private statusText?: Phaser.GameObjects.Text;

  private units: UnitRecord[] = [];
  private activeRun = false;
  private unit: UnitRecord | null = null;
  private selectedSecondaryIds: string[] = [];
  private secondaryPanel?: UnitCardGrid;
  private promoteButton?: ActionButton;

  constructor() {
    super({ key: "UnitDetailsScene" });
  }

  init(data: { unitId?: string }): void {
    this.unitId = String(data?.unitId ?? "");
  }

  create(): void {
    new BackgroundImage(this);
    new HudPanel(this);
    const layout = getPageLayout(this);
    const contentFrame = new ContentAreaFrame({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
      title: "Unit Details",
      bodyColor: 0x00f6ff,
    });
    contentFrame.setDepth(-800);
    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Unit Actions",
      bodyColor: 0x00ff72,
    });
    actionsFrame.setDepth(-800);
    new HomeCornerButton({ scene: this, x: layout.homeIcon.x, y: layout.homeIcon.y });

    this.loadingText = this.add.text(layout.content.x + 16, layout.content.y + 120, "Loading unit details...", {
      fontFamily: "Arial",
      fontSize: "20px",
      color: "#ffffff",
    });

    void this.loadData();
  }

  private async loadData(): Promise<void> {
    try {
      const profile = await apiClient.getProfile({ force: true });
      if (!profile.ok) throw new Error(profile.error.message);

      this.units = adaptUnitRecords(profile.data.units ?? []);
      this.activeRun = profile.data.active_run !== null;
      this.unit = this.units.find((u) => u.id === this.unitId) ?? this.units[0] ?? null;
      if (!this.unit) throw new Error("No units found.");
      this.unitId = this.unit.id;

      this.loadingText?.destroy();
      this.loadingText = undefined;
      this.buildUi(profile.data.dice ?? [], profile.data.units ?? []);
    } catch (e) {
      this.loadingText?.setText(`Failed to load.\n${(e as Error).message}`);
    }
  }

  private buildUi(rawDice: unknown[], rawUnits: unknown[]): void {
    const layout = getPageLayout(this);
    const buttonX = layout.buttons.x + 10;
    if (!this.unit) return;

    const diceVm = adaptDiceDetails(rawDice as any, rawUnits as any);
    const equipped = diceVm.filter((d) => d.equipped?.unitId === this.unit?.id);
    const xp = typeof this.unit.xp === "number" ? this.unit.xp : 0;
    const max = typeof this.unit.max_level === "number" ? this.unit.max_level : "?";
    const tier = typeof this.unit.tier === "number" ? this.unit.tier : 1;

    this.detailsText?.destroy();
    this.detailsText = this.add.text(layout.content.x + 16, layout.content.y, [
      `UNIT: ${this.unit.name}`,
      `Level: ${this.unit.level} / ${max}`,
      `XP: ${xp}`,
      `Tier: ${tier}`,
      "",
      `Equipped Dice (${equipped.length}):`,
      ...(equipped.length > 0 ? equipped.map((d) => `- ${d.displayName}`) : ["- None"]),
    ].join("\n"), {
      fontFamily: "monospace",
      fontSize: "15px",
      color: "#f5f5f5",
      wordWrap: { width: 420 },
    });

    this.secondaryPanel?.destroy();
    this.secondaryPanel = new UnitCardGrid({
      scene: this,
      x: layout.content.x + 440,
      y: layout.content.y,
      width: 460,
      height: 420,
      title: "PROMOTION SECONDARIES",
      units: this.units.filter((u) => u.id !== this.unitId),
      maxVisibleCards: 6,
      onUnitClick: (u) => this.toggleSecondary(u.id),
      getCardState: (u) => this.secondaryRowState(u),
    });

    new ActionButtonList({
      scene: this,
      x: buttonX,
      y: layout.buttons.y + 24,
      gapY: 5,
      buttons: [
        { label: "Back", onClick: () => this.scene.start("WarbandManagementScene") },
        {
          label: "Manage Dice",
          onClick: () => this.scene.start("DiceInventoryScene", {
            returnScene: "UnitDetailsScene",
            unitId: this.unitId,
          }),
        },
        {
          label: "Clear 2ndaries",
          onClick: () => {
            this.selectedSecondaryIds = [];
            this.secondaryPanel?.refreshCardStates();
            this.refreshStatus();
          },
        },
      ],
    });

    this.promoteButton?.destroy();
    this.promoteButton = new ActionButton({
      scene: this,
      x: buttonX,
      y: layout.buttons.y + 284,
      label: "Promote Unit",
      enabled: !this.activeRun && this.selectedSecondaryIds.length === 2,
      onClick: () => void this.promoteUnit(),
    });

    this.statusText?.destroy();
    this.statusText = this.add.text(layout.content.x + 440, layout.content.y + 432, "", {
      fontFamily: "Arial",
      fontSize: "13px",
      color: "#dddddd",
      wordWrap: { width: 460 },
    });
    this.refreshStatus();
  }

  private secondaryRowState(unit: UnitRecord): UnitCardState {
    const selected = this.selectedSecondaryIds.includes(unit.id);
    const compatible = this.isPromotionCompatible(this.unitId, unit.id);
    return {
      highlighted: selected,
      disabled: !compatible || this.activeRun,
      badgeText: selected ? "SELECTED" : compatible ? null : "INCOMPATIBLE",
    };
  }

  private toggleSecondary(unitId: string): void {
    if (this.activeRun) return;
    if (!this.isPromotionCompatible(this.unitId, unitId)) return;
    if (this.selectedSecondaryIds.includes(unitId)) {
      this.selectedSecondaryIds = this.selectedSecondaryIds.filter((id) => id !== unitId);
    } else {
      if (this.selectedSecondaryIds.length === 2) this.selectedSecondaryIds.shift();
      this.selectedSecondaryIds.push(unitId);
    }
    this.secondaryPanel?.refreshCardStates();
    this.refreshStatus();
  }

  private async promoteUnit(): Promise<void> {
    if (this.activeRun) {
      this.showToast("Promotion unavailable while a run is active.");
      return;
    }
    if (this.selectedSecondaryIds.length !== 2) {
      this.showToast("Select two compatible secondaries.");
      return;
    }
    const [a, b] = this.selectedSecondaryIds as [string, string];
    const res = await apiClient.promoteUnit(this.unitId, [a, b]);
    if (!res.ok) {
      this.showToast(`Promote failed: ${res.error.message}`);
      return;
    }
    this.showToast("Promotion applied.", "#ccffcc");
    this.selectedSecondaryIds = [];
    await this.loadData();
  }

  private isPromotionCompatible(primaryId: string, secondaryId: string): boolean {
    const primary = this.units.find((u) => u.id === primaryId);
    const secondary = this.units.find((u) => u.id === secondaryId);
    if (!primary || !secondary) return false;
    if ((primary.unit_type_id ?? null) !== (secondary.unit_type_id ?? null)) return false;
    if ((primary.tier ?? 1) !== (secondary.tier ?? 1)) return false;
    const maxPrimary = typeof primary.max_level === "number" ? primary.max_level : null;
    const maxSecondary = typeof secondary.max_level === "number" ? secondary.max_level : null;
    if (maxPrimary !== null && primary.level < maxPrimary) return false;
    if (maxSecondary !== null && secondary.level < maxSecondary) return false;
    return true;
  }

  private refreshStatus(): void {
    const secondaries = this.selectedSecondaryIds.length > 0
      ? this.selectedSecondaryIds.map((id) => this.units.find((u) => u.id === id)?.name ?? id).join(", ")
      : "(none)";
    const gate = this.activeRun
      ? "Promotion disabled while an active run exists."
      : "Select two compatible max-level units as secondaries.";
    this.statusText?.setText(`Primary: ${this.unit?.name ?? this.unitId}\nSecondaries: ${secondaries}\n${gate}`);
    this.promoteButton?.setEnabled(!this.activeRun && this.selectedSecondaryIds.length === 2);
  }

  private showToast(message: string, color = "#ffcccc"): void {
    this.toastText?.destroy();
    const layout = getPageLayout(this);
    this.toastText = this.add.text(layout.content.x + 16, layout.content.y + layout.content.height - 24, message, {
      fontFamily: "Arial",
      fontSize: "13px",
      color,
    });
    this.time.delayedCall(2500, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }
}
