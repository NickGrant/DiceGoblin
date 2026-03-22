import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import { markDebugSceneReady } from "../debug/debugHooks";
import { getPageLayout } from "../layout/pageLayout";
import { apiClient } from "../services/apiClient";
import type {
  DebugCatalogData,
  ProfileData,
} from "../types/ApiResponse";

const ACTION_BUTTON_WIDTH = 280;
const ACTION_BODY_TOP_OFFSET = 74;
const ACTION_BUTTON_GAP = 12;
const CONTENT_BODY_TOP_OFFSET = 74;
const CONTENT_BODY_BOTTOM_PADDING = 20;

export default class DevPanelScene extends Phaser.Scene {
  private statusText?: Phaser.GameObjects.Text;
  private detailText?: Phaser.GameObjects.Text;
  private contentObjects: Phaser.GameObjects.GameObject[] = [];
  private catalog: DebugCatalogData | null = null;
  private profile: ProfileData | null = null;
  private selectedUnitIndex = 0;
  private selectedDiceIndex = 0;
  private selectedRegionItemIndex = 0;

  constructor() {
    super({ key: "DevPanelScene" });
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
      title: "Dev Panel",
      bodyColor: 0x23272a,
    });
    contentFrame.setDepth(-800);

    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Dev Actions",
      bodyColor: 0x35545c,
    });
    actionsFrame.setDepth(-800);

    this.statusText = this.add
      .text(layout.content.x + 16, layout.content.y + layout.content.height - CONTENT_BODY_BOTTOM_PADDING - 12, "Loading dev tools...", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "14px",
        color: "#dcecff",
        wordWrap: { width: Math.max(240, layout.content.width - 32) },
      })
      .setOrigin(0, 1);

    this.renderActionButtons();
    void this.loadData();
  }

  private renderActionButtons(): void {
    const layout = getPageLayout(this);
    const buttonX = layout.buttons.x + Math.max(10, Math.floor((layout.buttons.width - ACTION_BUTTON_WIDTH) / 2));
    let buttonY = layout.buttons.y + ACTION_BODY_TOP_OFFSET;

    const buttons: Array<{ label: string; onClick: () => void }> = [
      { label: "+100 Soft", onClick: () => void this.grantCurrency(100) },
      { label: "+500 Soft", onClick: () => void this.grantCurrency(500) },
      { label: "Grant Unit", onClick: () => void this.grantSelectedUnit() },
      { label: "Grant Die", onClick: () => void this.grantSelectedDie() },
      { label: "Grant Boss Drop", onClick: () => void this.grantSelectedRegionItem() },
      { label: "Reset Account", onClick: () => void this.resetAccount() },
    ];

    for (const button of buttons) {
      new SharedActionButton({
        scene: this,
        x: buttonX,
        y: buttonY,
        label: button.label,
        onClick: button.onClick,
      });
      buttonY += 64 + ACTION_BUTTON_GAP;
    }
  }

  private async loadData(): Promise<void> {
    try {
      const [catalog, profile] = await Promise.all([
        apiClient.getDebugCatalog(),
        apiClient.getProfile({ force: true, allowStaleOnError: true }),
      ]);

      if (!catalog.ok) {
        this.setStatus(catalog.error.message, true);
        markDebugSceneReady(this, { scene: "DevPanelScene", state: "error", reason: catalog.error.message });
        return;
      }
      if (!profile.ok) {
        this.setStatus(profile.error.message, true);
        markDebugSceneReady(this, { scene: "DevPanelScene", state: "error", reason: profile.error.message });
        return;
      }

      this.catalog = catalog.data;
      this.profile = profile.data;
      this.selectedUnitIndex = this.clampIndex(this.selectedUnitIndex, this.catalog.unit_types.length);
      this.selectedDiceIndex = this.clampIndex(this.selectedDiceIndex, this.catalog.dice_definitions.length);
      this.selectedRegionItemIndex = this.clampIndex(this.selectedRegionItemIndex, this.catalog.region_items.length);
      this.renderContent();
      this.setStatus("Dev tools ready.");
      markDebugSceneReady(this, {
        scene: "DevPanelScene",
        unitOptions: this.catalog.unit_types.length,
        diceOptions: this.catalog.dice_definitions.length,
        regionItems: this.catalog.region_items.length,
      });
    } catch (error) {
      const message = error instanceof Error ? error.message : "Failed to load dev tools.";
      this.setStatus(message, true);
      markDebugSceneReady(this, { scene: "DevPanelScene", state: "error", reason: message });
    }
  }

  private renderContent(): void {
    this.clearContent();
    const layout = getPageLayout(this);
    const contentX = layout.content.x + 16;
    const contentY = layout.content.y + CONTENT_BODY_TOP_OFFSET;
    const contentWidth = Math.max(280, layout.content.width - 32);

    const summaryLines = this.buildSummaryLines();
    this.detailText = this.add
      .text(contentX, contentY, summaryLines.join("\n"), {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#f3efe0",
        lineSpacing: 6,
        wordWrap: { width: contentWidth },
      })
      .setOrigin(0, 0);
    this.contentObjects.push(this.detailText);

    let selectorY = contentY + Math.max(220, Math.ceil(this.detailText.height) + 16);
    selectorY = this.renderSelector("Unit Grant", this.currentUnitLabel(), selectorY, () => {
      this.shiftSelection("unit", -1);
    }, () => {
      this.shiftSelection("unit", 1);
    });
    selectorY = this.renderSelector("Dice Grant", this.currentDiceLabel(), selectorY, () => {
      this.shiftSelection("dice", -1);
    }, () => {
      this.shiftSelection("dice", 1);
    });
    this.renderSelector("Boss Drop", this.currentRegionItemLabel(), selectorY, () => {
      this.shiftSelection("item", -1);
    }, () => {
      this.shiftSelection("item", 1);
    });
  }

  private renderSelector(
    label: string,
    value: string,
    y: number,
    onPrev: () => void,
    onNext: () => void,
  ): number {
    const layout = getPageLayout(this);
    const contentX = layout.content.x + 16;
    const contentWidth = Math.max(280, layout.content.width - 32);

    const labelText = this.add
      .text(contentX, y, label, {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "18px",
        color: "#f7d57a",
      })
      .setOrigin(0, 0);

    const valueText = this.add
      .text(contentX, y + 28, value, {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "15px",
        color: "#ecf2ff",
        wordWrap: { width: Math.max(160, contentWidth - 164) },
      })
      .setOrigin(0, 0);

    this.contentObjects.push(labelText, valueText);

    const buttonWidth = 54;
    const buttonHeight = 26;
    const buttonY = y + 4;
    const nextX = contentX + contentWidth - buttonWidth;
    const prevX = nextX - buttonWidth - 8;

    this.contentObjects.push(
      ...this.createMiniButton(prevX, buttonY, buttonWidth, buttonHeight, "Prev", onPrev),
      ...this.createMiniButton(nextX, buttonY, buttonWidth, buttonHeight, "Next", onNext),
    );

    return y + 86;
  }

  private createMiniButton(
    x: number,
    y: number,
    width: number,
    height: number,
    label: string,
    onClick: () => void,
  ): Phaser.GameObjects.GameObject[] {
    const bg = this.add
      .rectangle(x, y, width, height, 0x44535d, 0.95)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xcfe0ff, 0.4)
      .setInteractive({ useHandCursor: true });
    const text = this.add
      .text(x + width / 2, y + height / 2, label, {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#ffffff",
      })
      .setOrigin(0.5, 0.5);

    bg.on("pointerdown", () => onClick());
    return [bg, text];
  }

  private buildSummaryLines(): string[] {
    const lines: string[] = [];
    const profile = this.profile;
    if (!profile) {
      return ["Loading profile..."];
    }

    lines.push(`Currency: ${profile.currency.soft} soft / ${profile.currency.hard} hard`);
    lines.push(`Energy: ${profile.energy.current} / ${profile.energy.max}`);
    lines.push(`Squads: ${profile.squads.length}`);
    lines.push(`Units: ${profile.units.length}`);
    lines.push(`Dice: ${profile.dice.length}`);
    lines.push(`Region items: ${profile.region_items.length}`);
    lines.push(`Active run: ${profile.active_run ? `#${profile.active_run.run_id}` : "none"}`);
    lines.push("");
    lines.push("Use the selectors below to change what Grant Unit / Grant Die / Grant Boss Drop will create.");
    lines.push("Reset Account clears runs, squads, units, dice, drops, currency, and starter grants, then reapplies the fresh-account baseline.");
    return lines;
  }

  private currentUnitLabel(): string {
    const unit = this.catalog?.unit_types[this.selectedUnitIndex];
    return unit ? `${unit.name} (${unit.slug})` : "No unit types loaded";
  }

  private currentDiceLabel(): string {
    const dice = this.catalog?.dice_definitions[this.selectedDiceIndex];
    return dice ? `${dice.rarity} d${dice.sides} (slots ${dice.slot_capacity})` : "No dice loaded";
  }

  private currentRegionItemLabel(): string {
    const item = this.catalog?.region_items[this.selectedRegionItemIndex];
    return item ? `${item.name} (${item.slug}) from ${item.region_name}` : "No region items loaded";
  }

  private shiftSelection(kind: "unit" | "dice" | "item", delta: number): void {
    if (!this.catalog) {
      return;
    }

    if (kind === "unit") {
      this.selectedUnitIndex = this.wrapIndex(this.selectedUnitIndex + delta, this.catalog.unit_types.length);
    } else if (kind === "dice") {
      this.selectedDiceIndex = this.wrapIndex(this.selectedDiceIndex + delta, this.catalog.dice_definitions.length);
    } else {
      this.selectedRegionItemIndex = this.wrapIndex(this.selectedRegionItemIndex + delta, this.catalog.region_items.length);
    }

    this.renderContent();
  }

  private async grantCurrency(soft: number): Promise<void> {
    this.setStatus(`Granting ${soft} soft...`);
    try {
      const res = await apiClient.grantDebugCurrency(soft, 0);
      if (!res.ok) {
        this.setStatus(res.error.message, true);
        return;
      }
      this.setStatus(`Soft currency updated to ${res.data.currency.soft}.`);
      await this.loadData();
    } catch (error) {
      this.setStatus(error instanceof Error ? error.message : "Failed to grant currency.", true);
    }
  }

  private async grantSelectedUnit(): Promise<void> {
    const unit = this.catalog?.unit_types[this.selectedUnitIndex];
    if (!unit) {
      this.setStatus("No unit type selected.", true);
      return;
    }

    this.setStatus(`Granting ${unit.name}...`);
    try {
      const res = await apiClient.grantDebugUnit(unit.slug, 1);
      if (!res.ok) {
        this.setStatus(res.error.message, true);
        return;
      }
      this.setStatus(`Granted unit ${res.data.granted_units.map((entry) => entry.id).join(", ")}.`);
      await this.loadData();
    } catch (error) {
      this.setStatus(error instanceof Error ? error.message : "Failed to grant unit.", true);
    }
  }

  private async grantSelectedDie(): Promise<void> {
    const dice = this.catalog?.dice_definitions[this.selectedDiceIndex];
    if (!dice) {
      this.setStatus("No die selected.", true);
      return;
    }

    this.setStatus(`Granting ${dice.rarity} d${dice.sides}...`);
    try {
      const res = await apiClient.grantDebugDie(dice.sides, dice.rarity, 1);
      if (!res.ok) {
        this.setStatus(res.error.message, true);
        return;
      }
      this.setStatus(`Granted die ${res.data.granted_dice.map((entry) => entry.id).join(", ")}.`);
      await this.loadData();
    } catch (error) {
      this.setStatus(error instanceof Error ? error.message : "Failed to grant die.", true);
    }
  }

  private async grantSelectedRegionItem(): Promise<void> {
    const item = this.catalog?.region_items[this.selectedRegionItemIndex];
    if (!item) {
      this.setStatus("No region item selected.", true);
      return;
    }

    this.setStatus(`Granting ${item.name}...`);
    try {
      const res = await apiClient.grantDebugRegionItem(item.slug, 1);
      if (!res.ok) {
        this.setStatus(res.error.message, true);
        return;
      }
      this.setStatus(`Region item ${item.slug} now at ${res.data.region_item.quantity}.`);
      await this.loadData();
    } catch (error) {
      this.setStatus(error instanceof Error ? error.message : "Failed to grant region item.", true);
    }
  }

  private async resetAccount(): Promise<void> {
    this.setStatus("Resetting account to fresh baseline...");
    try {
      const res = await apiClient.resetDebugAccount();
      if (!res.ok) {
        this.setStatus(res.error.message, true);
        return;
      }
      this.setStatus(`Account reset complete. ${res.data.reset.units} starter units and ${res.data.reset.dice} starter dice are now present.`);
      await this.loadData();
    } catch (error) {
      this.setStatus(error instanceof Error ? error.message : "Failed to reset account.", true);
    }
  }

  private setStatus(message: string, isError = false): void {
    this.statusText?.setColor(isError ? "#ffb3b3" : "#dcecff");
    this.statusText?.setText(message);
  }

  private clearContent(): void {
    for (const object of this.contentObjects) {
      object.destroy();
    }
    this.contentObjects = [];
  }

  private clampIndex(index: number, length: number): number {
    if (length <= 0) {
      return 0;
    }

    return Math.max(0, Math.min(index, length - 1));
  }

  private wrapIndex(index: number, length: number): number {
    if (length <= 0) {
      return 0;
    }

    const normalized = index % length;
    return normalized < 0 ? normalized + length : normalized;
  }
}
