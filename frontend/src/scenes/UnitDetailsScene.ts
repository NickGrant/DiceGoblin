import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import DiceCardGrid from "../components/DiceCardGrid";
import UnitCardGrid, { type UnitCardState } from "../components/UnitCardGrid";
import InputModal from "../components/feedback/InputModal";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import SceneTabStrip from "../components/navigation/SceneTabStrip";
import { DICE_ATLAS_KEY, getDiceFrameName } from "../assets/diceAtlas";
import {
  adaptDiceDetails,
  adaptUnitDetails,
  adaptUnitRecords,
  type DiceDetailsViewModel,
  type UnitDetailsViewModel,
  type UnitEquippedAbilityViewModel,
} from "../adapters/profileViewModels";
import { getDebugSceneConfig } from "../debug/debugScene";
import { getDebugAbilityCatalogFixture, getDebugProfileFixture } from "../debug/debugFixtures";
import { markDebugSceneReady } from "../debug/debugHooks";
import { getPageLayout } from "../layout/pageLayout";
import { apiClient } from "../services/apiClient";
import type { PromotionOptionRecord, UnitRecord } from "../types/ApiResponse";
import { SQUAD_NAME_ALLOWED_CHARACTER_PATTERN } from "./warbandManagementState";
import {
  assignFusionSecondarySelection,
  buildUnitSummaryText,
  clearFusionSelections,
  getFusionCandidates,
  getLoadoutAbilityCandidates,
  getSelectableDice,
  getSelectedLoadoutEntry,
  getSelectedPromotionOption,
  isPromotionCompatible,
  REQUIRED_FUSION_UNITS,
  resolveUnitDetailsSelections,
} from "./unitDetailsState";

const FRAME_TITLE_HEIGHT = 56;
const FRAME_MARGIN = 12;
const CONTENT_INSET = 10;
const SECTION_GAP = 12;
const SLOT_SIZE = 72;
const SLOT_ICON_SIZE = 46;
const ABILITY_ROW_HEIGHT = 38;
const SIDEBAR_BUTTON_WIDTH = 280;
const TAB_HEIGHT = 38;
const SUMMARY_COLUMN_WIDTH = 238;
const BUTTON_STEP = 66;
const LOADOUT_LIST_HEIGHT = 176;
const AVAILABLE_ABILITY_ROW_HEIGHT = 34;
const DESTINATION_PANEL_HEIGHT = 86;
const FUSION_SECTION_HEIGHT = 120;

const RARITY_TO_MATERIAL: Record<string, "cardboard" | "wood" | "bone" | "metal" | "gemstone"> = {
  common: "cardboard",
  uncommon: "wood",
  rare: "bone",
  epic: "metal",
  legendary: "gemstone",
};

type UnitDetailsTabId = "loadout" | "promotion";

function normalizeUnitName(value: string): string | null {
  const trimmed = value.trim();
  if (trimmed.length === 0 || trimmed.length > 32) return null;
  return SQUAD_NAME_ALLOWED_CHARACTER_PATTERN.test(trimmed) ? trimmed : null;
}

function readDebugInitialTab(): UnitDetailsTabId | null {
  if (typeof window === "undefined") {
    return null;
  }

  const value = new URLSearchParams(window.location.search).get("debugInitialTab");
  return value === "promotion" ? "promotion" : value === "loadout" ? "loadout" : null;
}

export default class UnitDetailsScene extends Phaser.Scene {
  private unitId = "";
  private activeTab: UnitDetailsTabId = "loadout";
  private loadingText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;
  private renameDialog?: InputModal;

  private rawUnits: UnitRecord[] = [];
  private unitDetails: UnitDetailsViewModel[] = [];
  private unit: UnitDetailsViewModel | null = null;
  private rawUnit: UnitRecord | null = null;
  private dice: DiceDetailsViewModel[] = [];
  private selectedDiceId: string | null = null;
  private selectedLoadoutIndex = 0;
  private selectedAbilitySlotIndex = 0;
  private activeRun = false;
  private fusionSecondaryIds: Array<string | null> = Array(REQUIRED_FUSION_UNITS).fill(null);
  private selectedFusionSlotIndex = 0;
  private promotionOptions: PromotionOptionRecord[] = [];
  private selectedPromotionOptionIndex = 0;

  private layoutUiObjects: Phaser.GameObjects.GameObject[] = [];
  private tabStrip?: SceneTabStrip<UnitDetailsTabId>;
  private dicePanel?: DiceCardGrid;
  private secondaryPanel?: UnitCardGrid;
  private renameButton?: SharedActionButton;
  private removeAbilityButton?: SharedActionButton;
  private equipDiceButton?: SharedActionButton;
  private unequipSlotButton?: SharedActionButton;
  private clearFusionButton?: SharedActionButton;
  private promoteButton?: SharedActionButton;
  private backButton?: SharedActionButton;
  private loadoutRowBorders: Phaser.GameObjects.Rectangle[] = [];
  private loadoutRowTexts: Phaser.GameObjects.Text[] = [];
  private abilitySlotBorders: Phaser.GameObjects.Rectangle[] = [];
  private abilitySlotLabels: Phaser.GameObjects.Text[] = [];
  private abilitySlotIcons: Phaser.GameObjects.Image[] = [];
  private fusionSlotBorders: Phaser.GameObjects.Rectangle[] = [];
  private fusionSlotLabels: Phaser.GameObjects.Text[] = [];
  private unitSummaryText?: Phaser.GameObjects.Text;
  private helperText?: Phaser.GameObjects.Text;
  private actionSummaryText?: Phaser.GameObjects.Text;
  private promotionOptionButtonBg?: Phaser.GameObjects.Rectangle;
  private promotionOptionButtonText?: Phaser.GameObjects.Text;
  private promotionOptionButtonZone?: Phaser.GameObjects.Zone;
  private loadoutRowBaseY = 0;

  constructor() {
    super({ key: "UnitDetailsScene" });
  }

  init(data: { unitId?: string; tab?: UnitDetailsTabId }): void {
    this.unitId = String(data?.unitId ?? "");
    this.activeTab = data?.tab === "promotion"
      ? "promotion"
      : readDebugInitialTab() ?? "loadout";
  }

  create(): void {
    new BackgroundImage(this);
    mountBottomCommandStrip(this);
    const layout = getPageLayout(this);

    new ContentAreaFrame({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
      title: "Unit Details",
      bodyColor: 0x4f5a65,
    }).setDepth(-800);

    new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Unit Actions",
      bodyColor: 0x006f7a,
    }).setDepth(-800);

    this.loadingText = this.add.text(layout.content.x + 16, layout.content.y + 120, "Loading unit details...", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "20px",
      color: "#ffffff",
    });

    void this.loadData();
  }

  private async loadData(): Promise<void> {
    try {
      const debugConfig = getDebugSceneConfig();
      const profile = await apiClient.getProfile({ force: true }).catch(() => {
        if (!debugConfig.enabled) throw new Error("Failed to fetch");
        return getDebugProfileFixture();
      });
      const resolvedProfile = profile.ok || !debugConfig.enabled
        ? profile
        : getDebugProfileFixture();
      if (!resolvedProfile.ok) throw new Error(resolvedProfile.error.message);

      const abilityCatalog = await apiClient.getAbilityCatalog().catch(() => {
        if (!debugConfig.enabled) throw new Error("Failed to fetch ability catalog");
        return getDebugAbilityCatalogFixture();
      });
      const resolvedAbilityCatalog = abilityCatalog.ok || !debugConfig.enabled
        ? abilityCatalog
        : getDebugAbilityCatalogFixture();
      if (!resolvedAbilityCatalog.ok) throw new Error(resolvedAbilityCatalog.error.message);

      this.rawUnits = adaptUnitRecords(resolvedProfile.data.units ?? []);
      this.unitDetails = adaptUnitDetails(resolvedProfile.data.units ?? [], resolvedAbilityCatalog.data.abilities ?? []);
      this.activeRun = resolvedProfile.data.active_run !== null;
      this.unit = this.unitDetails.find((unit) => unit.id === this.unitId) ?? this.unitDetails[0] ?? null;
      this.rawUnit = this.rawUnits.find((unit) => unit.id === this.unitId) ?? this.rawUnits[0] ?? null;
      if (!this.unit || !this.rawUnit) throw new Error("No units found.");

      const currentUnit = this.unit;
      this.unitId = currentUnit.id;
      const promotionOptions = await apiClient.getPromotionOptions(this.unitId).catch(() => {
        if (!debugConfig.enabled) throw new Error("Failed to fetch promotion options");
        return { ok: true as const, data: { unit_id: this.unitId, current_tier: currentUnit.tier, options: this.buildDebugPromotionOptions() } };
      });
      const resolvedPromotionOptions = promotionOptions.ok || !debugConfig.enabled
        ? promotionOptions
        : { ok: true as const, data: { unit_id: this.unitId, current_tier: currentUnit.tier, options: this.buildDebugPromotionOptions() } };
      if (!resolvedPromotionOptions.ok) throw new Error(resolvedPromotionOptions.error.message);

      this.dice = adaptDiceDetails(resolvedProfile.data.dice ?? [], resolvedProfile.data.units ?? []);
      this.promotionOptions = resolvedPromotionOptions.data.options ?? [];
      this.syncSelections();

      this.loadingText?.destroy();
      this.loadingText = undefined;
      this.buildUi();

      markDebugSceneReady(this, {
        unitId: this.unitId,
        activeRun: this.activeRun,
        loadoutCount: this.unit.equippedLoadout.length,
      });
    } catch (error) {
      this.loadingText?.setText(`Failed to load.\n${(error as Error).message}`);
      markDebugSceneReady(this, { state: "error" });
    }
  }

  private buildUi(): void {
    const layout = getPageLayout(this);
    this.clearDynamicUi();
    if (!this.unit || !this.rawUnit) return;

    const contentX = layout.content.x + FRAME_MARGIN + CONTENT_INSET;
    const contentY = layout.content.y + FRAME_TITLE_HEIGHT + FRAME_MARGIN + CONTENT_INSET;
    const contentWidth = Math.max(320, layout.content.width - (FRAME_MARGIN + CONTENT_INSET) * 2);
    const contentHeight = Math.max(320, layout.content.height - FRAME_TITLE_HEIGHT - (FRAME_MARGIN + CONTENT_INSET) * 2);
    const sidebarX = layout.buttons.x + FRAME_MARGIN;
    const sidebarY = layout.buttons.y + FRAME_TITLE_HEIGHT + FRAME_MARGIN;
    const sidebarWidth = Math.max(220, layout.buttons.width - FRAME_MARGIN * 2);

    this.tabStrip = new SceneTabStrip<UnitDetailsTabId>({
      scene: this,
      x: contentX,
      y: contentY,
      width: Math.min(contentWidth, 360),
      height: TAB_HEIGHT,
      activeId: this.activeTab,
      tabs: [
        { id: "loadout", label: "Loadout" },
        { id: "promotion", label: "Promotion" },
      ],
      onChange: (tabId) => {
        this.activeTab = tabId;
        this.buildUi();
      },
    });
    this.layoutUiObjects.push(this.tabStrip);

    const bodyY = contentY + TAB_HEIGHT + SECTION_GAP;
    const bodyHeight = Math.max(220, contentHeight - TAB_HEIGHT - SECTION_GAP);
    const summaryWidth = Math.min(SUMMARY_COLUMN_WIDTH, Math.max(210, Math.floor(contentWidth * 0.28)));
    const mainX = contentX + summaryWidth + SECTION_GAP;
    const mainWidth = Math.max(280, contentWidth - summaryWidth - SECTION_GAP);

    this.buildSummaryColumn(contentX, bodyY, summaryWidth, bodyHeight);
    if (this.activeTab === "loadout") {
      this.buildLoadoutPanel(mainX, bodyY, mainWidth, bodyHeight);
    } else {
      this.buildPromotionPanel(mainX, bodyY, mainWidth, bodyHeight);
    }
    this.buildSidebar(sidebarX, sidebarY, sidebarWidth);
    this.refreshUi();
  }

  private buildSummaryColumn(x: number, y: number, width: number, height: number): void {
    const summaryBg = this.add.rectangle(x, y, width, height, 0x20323a, 0.72)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x8db8bc, 0.45);
    const portrait = this.add.image(x + width / 2, y + 60, "icon_warband")
      .setDisplaySize(98, 98)
      .setAlpha(0.9);
    this.unitSummaryText = this.add.text(x + 14, y + 122, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "17px",
      color: "#eef4f5",
      lineSpacing: 6,
      wordWrap: { width: width - 28 },
    }).setOrigin(0, 0);
    this.helperText = this.add.text(x + 14, y + height - 132, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "14px",
      color: "#d6e7e8",
      lineSpacing: 5,
      wordWrap: { width: width - 28 },
    }).setOrigin(0, 0);
    this.layoutUiObjects.push(summaryBg, portrait, this.unitSummaryText, this.helperText);
  }

  private buildLoadoutPanel(x: number, y: number, width: number, height: number): void {
    const loadoutLabel = this.add.text(x + 2, y, "LOADOUT ORDER", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: "#f0f0f0",
    }).setOrigin(0, 0);
    this.layoutUiObjects.push(loadoutLabel);
    this.buildLoadoutRows(x, y + 24, width);

    const slotsY = y + LOADOUT_LIST_HEIGHT;
    const slotsLabel = this.add.text(x + 2, slotsY, "ABILITY SLOTS", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: "#f0f0f0",
    }).setOrigin(0, 0);
    this.layoutUiObjects.push(slotsLabel);
    this.buildAbilitySlots(x, slotsY + 24, width);

    const bottomY = slotsY + 112;
    const bottomHeight = Math.max(164, height - (bottomY - y));
    const availableWidth = Math.max(210, Math.floor(width * 0.42));
    const diceWidth = Math.max(220, width - availableWidth - SECTION_GAP);

    this.buildAvailableAbilitiesPanel(x, bottomY, availableWidth, bottomHeight);
    this.dicePanel = new DiceCardGrid({
      scene: this,
      x: x + availableWidth + SECTION_GAP,
      y: bottomY,
      width: diceWidth,
      height: bottomHeight,
      title: "AVAILABLE DICE",
      dice: this.getSelectableDice(),
      selectedDiceId: this.selectedDiceId,
      onDiceClick: (die) => {
        this.selectedDiceId = die.id;
        this.dicePanel?.setSelectedDiceId(die.id);
        this.refreshUi();
      },
      maxVisibleCards: 4,
    });
    this.layoutUiObjects.push(this.dicePanel);
  }

  private buildPromotionPanel(x: number, y: number, width: number, height: number): void {
    const destinationLabel = this.add.text(x + 2, y, "PROMOTION DESTINATION", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: "#f0f0f0",
    }).setOrigin(0, 0);
    this.layoutUiObjects.push(destinationLabel);

    this.promotionOptionButtonBg = this.add.rectangle(x, y + 26, width, DESTINATION_PANEL_HEIGHT, 0x132328, 0.78)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xe0b85a, 0.5);
    this.promotionOptionButtonText = this.add.text(x + width / 2, y + 26 + DESTINATION_PANEL_HEIGHT / 2, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "18px",
      color: "#f4ebd4",
      align: "center",
      wordWrap: { width: width - 32 },
    }).setOrigin(0.5, 0.5);
    this.promotionOptionButtonZone = this.add.zone(x + width / 2, y + 26 + DESTINATION_PANEL_HEIGHT / 2, width, DESTINATION_PANEL_HEIGHT)
      .setOrigin(0.5, 0.5)
      .setInteractive({ useHandCursor: true });
    this.promotionOptionButtonZone.on("pointerdown", () => {
      if (this.promotionOptions.length <= 1) {
        return;
      }
      this.selectedPromotionOptionIndex = (this.selectedPromotionOptionIndex + 1) % this.promotionOptions.length;
      this.refreshUi();
    });
    this.promotionOptionButtonZone.on("pointerover", () => this.promotionOptionButtonBg?.setFillStyle(0x18343b, 0.9));
    this.promotionOptionButtonZone.on("pointerout", () => this.promotionOptionButtonBg?.setFillStyle(0x132328, 0.78));
    this.layoutUiObjects.push(this.promotionOptionButtonBg, this.promotionOptionButtonText, this.promotionOptionButtonZone);

    const fusionLabelY = y + 26 + DESTINATION_PANEL_HEIGHT + SECTION_GAP;
    const fusionLabel = this.add.text(x + 2, fusionLabelY, "REQUIRED MATERIAL", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: "#f0f0f0",
    }).setOrigin(0, 0);
    this.layoutUiObjects.push(fusionLabel);
    this.buildFusionSlots(x, fusionLabelY + 24);

    const candidatesY = y + 26 + DESTINATION_PANEL_HEIGHT + FUSION_SECTION_HEIGHT;
    const candidatesHeight = Math.max(160, height - (candidatesY - y));
    this.secondaryPanel = new UnitCardGrid({
      scene: this,
      x,
      y: candidatesY,
      width,
      height: candidatesHeight,
      title: "COMPATIBLE UNITS",
      units: this.getFusionCandidates(),
      onUnitClick: (unit) => this.assignFusionSecondary(unit.id),
      getCardState: (unit) => this.candidateRowState(unit),
      maxVisibleCards: 8,
      columns: 4,
      maxCardWidth: 96,
      footerHeight: 34,
      gapX: 8,
      gapY: 8,
    });
    this.layoutUiObjects.push(this.secondaryPanel);
  }

  private buildLoadoutRows(x: number, y: number, width: number): void {
    this.loadoutRowBorders = [];
    this.loadoutRowTexts = [];
    this.loadoutRowBaseY = y;
    const visibleRows = Math.min(4, Math.max(1, this.unit?.equippedLoadout.length ?? 1));
    for (let index = 0; index < visibleRows; index += 1) {
      const top = y + index * (ABILITY_ROW_HEIGHT + 6);
      let didDrag = false;
      const border = this.add.rectangle(x, top, width, ABILITY_ROW_HEIGHT, 0x142127, 0.88)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0x8db8bc, 0.32)
        .setDepth(1)
        .setInteractive({ useHandCursor: true });
      this.input.setDraggable(border);
      border.on("dragstart", () => {
        didDrag = false;
        border.setDepth(30);
      });
      border.on("drag", (_pointer: unknown, _dragX: number, dragY: number) => {
        if (!didDrag && Math.abs(dragY - top) < 10) {
          return;
        }
        didDrag = true;
        border.y = dragY;
        const text = this.loadoutRowTexts[index];
        if (text) {
          text.y = dragY + 8;
          text.setDepth(31);
        }
      });
      border.on("dragend", async () => {
        const targetIndex = this.resolveLoadoutDropIndex(border.y);
        this.restoreLoadoutRowVisual(index);
        if (didDrag && targetIndex !== index) {
          await this.reorderAbilityTo(index, targetIndex);
          return;
        }
        this.selectedLoadoutIndex = index;
        this.selectedAbilitySlotIndex = 0;
        this.refreshUi();
      });
      border.on("pointerup", () => {
        if (didDrag) {
          return;
        }
        this.selectedLoadoutIndex = index;
        this.selectedAbilitySlotIndex = 0;
        this.refreshUi();
      });
      const text = this.add.text(x + 12, top + 8, "", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "18px",
        color: "#eef4f5",
        wordWrap: { width: width - 24 },
      }).setOrigin(0, 0).setDepth(2);
      this.loadoutRowBorders.push(border);
      this.loadoutRowTexts.push(text);
      this.layoutUiObjects.push(border, text);
    }
  }

  private buildAbilitySlots(x: number, y: number, width: number): void {
    this.abilitySlotBorders = [];
    this.abilitySlotLabels = [];
    this.abilitySlotIcons = [];

    const selectedAbility = this.getSelectedLoadoutEntry();
    const slotCount = Math.max(0, selectedAbility?.diceCost ?? 0);
    if (!selectedAbility || slotCount === 0) {
      const label = this.add.text(x + 6, y + 16, "This ability has no die slots.", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#d6e7e8",
      });
      this.abilitySlotLabels.push(label);
      this.layoutUiObjects.push(label);
      return;
    }

    const startX = x + 8;
    const rowWidth = SLOT_SIZE + 10;
    for (let slotIndex = 0; slotIndex < slotCount; slotIndex += 1) {
      const slotX = startX + slotIndex * rowWidth;
      if (slotX + SLOT_SIZE > x + width) {
        break;
      }
      const border = this.add.rectangle(slotX, y, SLOT_SIZE, SLOT_SIZE, 0x1c2b31, 0.82)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0x8db8bc, 0.48)
        .setInteractive({ useHandCursor: true });
      border.on("pointerdown", () => {
        this.selectedAbilitySlotIndex = slotIndex;
        this.refreshUi();
      });
      const icon = this.add.image(slotX + SLOT_SIZE / 2, y + 24, DICE_ATLAS_KEY, "cardboard_d6")
        .setDisplaySize(SLOT_ICON_SIZE, SLOT_ICON_SIZE)
        .setVisible(false);
      const label = this.add.text(slotX + SLOT_SIZE / 2, y + SLOT_SIZE - 14, "", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "12px",
        color: "#eef4f5",
        align: "center",
        wordWrap: { width: SLOT_SIZE - 10 },
      }).setOrigin(0.5, 0.5);
      this.abilitySlotBorders.push(border);
      this.abilitySlotIcons.push(icon);
      this.abilitySlotLabels.push(label);
      this.layoutUiObjects.push(border, icon, label);
    }
  }

  private buildAvailableAbilitiesPanel(x: number, y: number, width: number, height: number): void {
    const panel = this.add.rectangle(x, y, width, height, 0x0f1a1f, 0.54)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x8db8bc, 0.38);
    const title = this.add.text(x + 12, y + 8, "AVAILABLE ABILITIES", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: "#f0f0f0",
    }).setOrigin(0, 0);
    this.layoutUiObjects.push(panel, title);

    const abilities = this.getLoadoutAbilityCandidates();
    if (abilities.length === 0) {
      const emptyLabel = this.add.text(x + 12, y + 42, "No active abilities are available for this unit yet.", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "15px",
        color: "#d6e7e8",
        wordWrap: { width: width - 24 },
      }).setOrigin(0, 0);
      this.layoutUiObjects.push(emptyLabel);
      return;
    }

    abilities.slice(0, Math.max(1, Math.floor((height - 36) / AVAILABLE_ABILITY_ROW_HEIGHT))).forEach((ability, index) => {
      const rowY = y + 32 + index * AVAILABLE_ABILITY_ROW_HEIGHT;
      const canAfford = ability.speedCost <= (this.unit?.loadoutBudget.remaining ?? 0);
      const row = this.add.rectangle(x + 8, rowY, width - 16, AVAILABLE_ABILITY_ROW_HEIGHT - 4, 0x142127, 0.88)
        .setOrigin(0, 0)
        .setStrokeStyle(1, canAfford ? 0x8db8bc : 0x7d5a5a, canAfford ? 0.26 : 0.3);
      const label = this.add.text(x + 18, rowY + 6, `${ability.label.toUpperCase()}  ${canAfford ? "+ ADD" : `NEEDS ${ability.speedCost} PTS`}`, {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: canAfford ? "#eef4f5" : "#cbb5b5",
        wordWrap: { width: width - 36 },
      }).setOrigin(0, 0);
      if (canAfford) {
        row.setInteractive({ useHandCursor: true });
        row.on("pointerdown", () => void this.addAbilityToLoadout(ability.id));
      }
      this.layoutUiObjects.push(row, label);
    });
  }

  private resolveLoadoutDropIndex(currentY: number): number {
    const rowStep = ABILITY_ROW_HEIGHT + 6;
    const relativeY = currentY - this.loadoutRowBaseY;
    return Math.max(0, Math.min(this.loadoutRowBorders.length - 1, Math.round(relativeY / rowStep)));
  }

  private resetLoadoutRowPositions(): void {
    const rowStep = ABILITY_ROW_HEIGHT + 6;
    this.loadoutRowBorders.forEach((border, index) => {
      border.y = this.loadoutRowBaseY + index * rowStep;
      border.setDepth(1);
    });
    this.loadoutRowTexts.forEach((text, index) => {
      text.y = this.loadoutRowBaseY + index * rowStep + 8;
      text.setDepth(2);
    });
  }

  private restoreLoadoutRowVisual(index: number): void {
    this.resetLoadoutRowPositions();
    this.loadoutRowBorders[index]?.setDepth(1);
    this.loadoutRowTexts[index]?.setDepth(2);
  }

  private buildFusionSlots(x: number, y: number): void {
    this.fusionSlotBorders = [];
    this.fusionSlotLabels = [];
    for (let index = 0; index < REQUIRED_FUSION_UNITS; index += 1) {
      const slotX = x + index * (SLOT_SIZE + 12);
      const border = this.add.rectangle(slotX, y, SLOT_SIZE, SLOT_SIZE, 0x3b331e, 0.76)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0xe0b85a, 0.62)
        .setInteractive({ useHandCursor: true });
      border.on("pointerdown", () => {
        this.selectedFusionSlotIndex = index;
        this.refreshUi();
      });
      const label = this.add.text(slotX + SLOT_SIZE / 2, y + SLOT_SIZE / 2, "Empty", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#f3dca6",
        align: "center",
        wordWrap: { width: SLOT_SIZE - 10 },
      }).setOrigin(0.5, 0.5);
      this.fusionSlotBorders.push(border);
      this.fusionSlotLabels.push(label);
      this.layoutUiObjects.push(border, label);
    }
  }

  private buildSidebar(x: number, y: number, width: number): void {
    const buttonX = x + Math.max(0, Math.floor((width - SIDEBAR_BUTTON_WIDTH) / 2));
    const firstButtonY = y + 18;

    if (this.activeTab === "loadout") {
      this.renameButton = new SharedActionButton({
        scene: this,
        x: buttonX,
        y: firstButtonY,
        label: "Rename Unit",
        enabled: true,
        onClick: () => void this.openRenameDialog(),
      });
      this.removeAbilityButton = new SharedActionButton({
        scene: this,
        x: buttonX,
        y: firstButtonY + BUTTON_STEP,
        label: "Remove Ability",
        enabled: false,
        onClick: () => void this.removeSelectedAbility(),
      });
      this.equipDiceButton = new SharedActionButton({
        scene: this,
        x: buttonX,
        y: firstButtonY + BUTTON_STEP * 2,
        label: "Equip Selected Die",
        enabled: false,
        onClick: () => void this.assignSelectedDie(),
      });
      this.unequipSlotButton = new SharedActionButton({
        scene: this,
        x: buttonX,
        y: firstButtonY + BUTTON_STEP * 3,
        label: "Clear Slot Die",
        enabled: false,
        onClick: () => void this.clearSelectedSlotDie(),
      });
      this.layoutUiObjects.push(
        this.renameButton,
        this.removeAbilityButton,
        this.equipDiceButton,
        this.unequipSlotButton,
      );
    } else {
      this.clearFusionButton = new SharedActionButton({
        scene: this,
        x: buttonX,
        y: firstButtonY,
        label: "Clear Promotion",
        enabled: false,
        onClick: () => this.clearFusionSelections(),
      });
      this.promoteButton = new SharedActionButton({
        scene: this,
        x: buttonX,
        y: firstButtonY + BUTTON_STEP,
        label: "Promote Unit",
        enabled: false,
        onClick: () => void this.promoteUnit(),
      });
      this.layoutUiObjects.push(this.clearFusionButton, this.promoteButton);
    }

    this.backButton = new SharedActionButton({
      scene: this,
      x: buttonX,
      y: firstButtonY + (this.activeTab === "loadout" ? BUTTON_STEP * 4 : BUTTON_STEP * 2),
      label: "Back",
      onClick: () => this.scene.start("WarbandManagementScene"),
    });
    this.layoutUiObjects.push(this.backButton);
  }

  private refreshUi(): void {
    this.syncSelections();
    if (this.activeTab === "loadout") {
      const expectedSlotCount = this.getSelectedLoadoutEntry()?.diceCost ?? 0;
      if (expectedSlotCount !== this.abilitySlotBorders.length) {
        this.buildUi();
        return;
      }
    }

    this.refreshLoadoutRows();
    this.refreshAbilitySlots();
    this.refreshFusionSlots();
    this.refreshSummaryText();
    this.refreshSidebarSummary();
    this.refreshDicePanel();
    this.refreshPromotionDestination();
    this.refreshActionButtons();
    this.secondaryPanel?.refreshCardStates();
  }

  private refreshLoadoutRows(): void {
    const loadout = this.unit?.equippedLoadout ?? [];
    this.loadoutRowBorders.forEach((border, index) => {
      const entry = loadout[index];
      const text = this.loadoutRowTexts[index];
      if (!text) {
        border.setVisible(false);
        return;
      }
      if (!entry) {
        border.setVisible(false);
        text.setVisible(false);
        return;
      }
      const selected = index === this.selectedLoadoutIndex;
      border.setVisible(true);
      text.setVisible(true);
      border.setStrokeStyle(2, selected ? 0xffcc00 : 0x8db8bc, selected ? 0.82 : 0.32);
      text.setText(`${index + 1}. ${entry.label.toUpperCase()}   SPD ${entry.speedCost}   ${entry.diceCost > 0 ? `SLOTS ${entry.diceCost}` : "NO DICE"}`);
      text.setColor(selected ? "#fff4c2" : "#eef4f5");
    });
  }

  private refreshAbilitySlots(): void {
    const selectedAbility = this.getSelectedLoadoutEntry();
    if (!selectedAbility || selectedAbility.diceCost === 0) {
      if (this.abilitySlotLabels[0]) {
        this.abilitySlotLabels[0].setText("This ability has no die slots.");
        this.abilitySlotLabels[0].setColor("#d6e7e8");
      }
      return;
    }
    for (let index = 0; index < this.abilitySlotBorders.length; index += 1) {
      const dieId = selectedAbility.slots[index]?.diceInstanceId ?? null;
      const die = dieId ? this.dice.find((candidate) => candidate.id === dieId) ?? null : null;
      const selected = index === this.selectedAbilitySlotIndex;
      this.abilitySlotBorders[index]?.setStrokeStyle(2, selected ? 0xd7eef0 : 0x8db8bc, selected ? 0.95 : 0.48);
      this.abilitySlotLabels[index]?.setText(die ? die.sizeLabel.toUpperCase() : "Empty");
      this.abilitySlotLabels[index]?.setColor(die ? "#ecf6ff" : "#d6d6d6");
      if (die) {
        this.abilitySlotIcons[index]?.setFrame(this.pickDieFrame(die));
        this.abilitySlotIcons[index]?.setVisible(true);
      } else {
        this.abilitySlotIcons[index]?.setVisible(false);
      }
    }
  }

  private refreshFusionSlots(): void {
    for (let index = 0; index < this.fusionSlotBorders.length; index += 1) {
      const border = this.fusionSlotBorders[index];
      const label = this.fusionSlotLabels[index];
      if (!border || !label) continue;

      const unitId = this.fusionSecondaryIds[index] ?? null;
      const unitName = unitId
        ? this.rawUnits.find((candidate) => candidate.id === unitId)?.name ?? `Unit ${unitId}`
        : "Empty";
      const selected = index === this.selectedFusionSlotIndex;

      border.setStrokeStyle(2, selected ? 0xf2e3b5 : 0xe0b85a, selected ? 0.95 : 0.62);
      label.setText(unitName);
      label.setColor(unitId ? "#fff2d2" : "#f3dca6");
    }
  }

  private refreshSummaryText(): void {
    if (!this.unitSummaryText || !this.helperText || !this.unit) return;

    this.unitSummaryText.setText(this.buildSummaryText());
    const selectedAbility = this.getSelectedLoadoutEntry();
    const selectedSlotDie = this.getSelectedSlotDie();
    const selectedDie = this.getSelectedDie();
    const selectedPromotion = this.getSelectedPromotionOption();
    const selectedFusions = this.fusionSecondaryIds.filter((id): id is string => Boolean(id));

    const helperLines = this.activeTab === "loadout"
      ? [
          selectedAbility
            ? `Selected: ${selectedAbility.label} (${selectedAbility.speedCost} speed)`
            : "Select a loadout row to inspect it.",
          "Drag equipped abilities to reorder. Click an available ability to add another copy.",
          selectedAbility && selectedAbility.diceCost > 0
            ? `Slot ${this.selectedAbilitySlotIndex + 1}: ${selectedSlotDie?.displayName ?? "Empty"}`
            : "This ability does not consume dice.",
          selectedDie
            ? `Ready die: ${selectedDie.displayName}${selectedDie.equipped ? ` on ${selectedDie.equipped.unitName}` : ""}`
            : "Choose a die from the pool to equip.",
        ]
      : [
          selectedPromotion
            ? `Path: ${selectedPromotion.mode === "chain" ? "Chain" : "Sideways"} to ${selectedPromotion.target_unit_type_name}`
            : "No promotion destination is available.",
          `Material selected: ${selectedFusions.length}/${REQUIRED_FUSION_UNITS}`,
          this.activeRun
            ? "Promotion is locked during active runs."
            : "Pick two compatible units to promote.",
        ];
    this.helperText.setText(helperLines.join("\n"));
  }

  private refreshSidebarSummary(): void {
    if (!this.actionSummaryText || !this.unit) {
      return;
    }

    const selectedAbility = this.getSelectedLoadoutEntry();
    const selectedPromotion = this.getSelectedPromotionOption();
    const selectedFusions = this.fusionSecondaryIds.filter((id): id is string => Boolean(id));

    this.actionSummaryText.setText(this.activeTab === "loadout"
      ? [
          "LOADOUT EDITING",
          selectedAbility ? selectedAbility.label : "No ability selected",
          selectedAbility ? `${selectedAbility.diceCost} slots • ${selectedAbility.speedCost} speed` : "Select an equipped ability row.",
        ].join("\n")
      : [
          "PROMOTION WORKFLOW",
          selectedPromotion ? selectedPromotion.target_unit_type_name : "No destination",
          `${selectedFusions.length}/${REQUIRED_FUSION_UNITS} material units selected`,
        ].join("\n"));
  }

  private refreshPromotionDestination(): void {
    if (!this.promotionOptionButtonBg || !this.promotionOptionButtonText) {
      return;
    }

    const selectedPromotion = this.getSelectedPromotionOption();
    const hasOptions = this.promotionOptions.length > 0;
    this.promotionOptionButtonBg.setAlpha(hasOptions ? 1 : 0.45);
    this.promotionOptionButtonText.setText(
      hasOptions
        ? `${selectedPromotion?.target_unit_type_name.toUpperCase() ?? "UNKNOWN"}${this.promotionOptions.length > 1 ? "\nCLICK TO CYCLE DESTINATIONS" : "\nREADY WHEN MATERIAL IS FILLED"}`
        : "NO PROMOTION DESTINATIONS"
    );
    this.promotionOptionButtonText.setColor(hasOptions ? "#f4ebd4" : "#9ba6a7");
  }

  private refreshDicePanel(): void {
    const selectable = this.getSelectableDice();
    if (this.selectedDiceId && !selectable.some((die) => die.id === this.selectedDiceId)) {
      this.selectedDiceId = selectable[0]?.id ?? null;
    }
    this.dicePanel?.setDice(selectable, this.selectedDiceId);
  }

  private refreshActionButtons(): void {
    const selectedAbility = this.getSelectedLoadoutEntry();
    const selectedDie = this.getSelectedDie();
    const selectedSlotDie = this.getSelectedSlotDie();
    const selectedFusions = this.fusionSecondaryIds.filter((id): id is string => Boolean(id));
    const selectedPromotion = this.getSelectedPromotionOption();

    this.renameButton?.setEnabled(this.activeTab === "loadout");
    this.removeAbilityButton?.setEnabled(Boolean(this.unit && selectedAbility && this.unit.equippedLoadout.length > 1));
    this.equipDiceButton?.setEnabled(Boolean(
      selectedAbility
      && selectedAbility.diceCost > 0
      && selectedDie
      && (!selectedDie.equipped || selectedDie.equipped.unitId === this.unitId),
    ));
    this.unequipSlotButton?.setEnabled(Boolean(selectedAbility && selectedAbility.diceCost > 0 && selectedSlotDie));
    this.clearFusionButton?.setEnabled(selectedFusions.length > 0);
    this.promoteButton?.setEnabled(!this.activeRun && selectedFusions.length === REQUIRED_FUSION_UNITS && Boolean(selectedPromotion));
  }

  private getSelectedLoadoutEntry(): UnitEquippedAbilityViewModel | null {
    return getSelectedLoadoutEntry(this.unit, this.selectedLoadoutIndex);
  }

  private getSelectedDie(): DiceDetailsViewModel | null {
    if (!this.selectedDiceId) return null;
    return this.dice.find((die) => die.id === this.selectedDiceId) ?? null;
  }

  private getSelectedSlotDie(): DiceDetailsViewModel | null {
    const selectedAbility = this.getSelectedLoadoutEntry();
    if (!selectedAbility) return null;
    const dieId = selectedAbility.slots[this.selectedAbilitySlotIndex]?.diceInstanceId ?? null;
    return dieId ? this.dice.find((die) => die.id === dieId) ?? null : null;
  }

  private getSelectableDice(): DiceDetailsViewModel[] {
    return getSelectableDice(this.dice, this.unitId);
  }

  private getLoadoutAbilityCandidates() {
    return getLoadoutAbilityCandidates(this.unit);
  }

  private getSelectedPromotionOption(): PromotionOptionRecord | null {
    return getSelectedPromotionOption(this.promotionOptions, this.selectedPromotionOptionIndex);
  }

  private buildSummaryText(): string {
    return buildUnitSummaryText(this.unit, this.rawUnit);
  }

  private syncSelections(): void {
    const nextState = resolveUnitDetailsSelections({
      unit: this.unit,
      rawUnits: this.rawUnits,
      dice: this.dice,
      unitId: this.unitId,
      promotionOptions: this.promotionOptions,
      state: {
        selectedLoadoutIndex: this.selectedLoadoutIndex,
        selectedAbilitySlotIndex: this.selectedAbilitySlotIndex,
        selectedDiceId: this.selectedDiceId,
        fusionSecondaryIds: this.fusionSecondaryIds,
        selectedFusionSlotIndex: this.selectedFusionSlotIndex,
        selectedPromotionOptionIndex: this.selectedPromotionOptionIndex,
      },
    });

    this.selectedLoadoutIndex = nextState.selectedLoadoutIndex;
    this.selectedAbilitySlotIndex = nextState.selectedAbilitySlotIndex;
    this.selectedDiceId = nextState.selectedDiceId;
    this.fusionSecondaryIds = nextState.fusionSecondaryIds;
    this.selectedFusionSlotIndex = nextState.selectedFusionSlotIndex;
    this.selectedPromotionOptionIndex = nextState.selectedPromotionOptionIndex;
  }

  private async openRenameDialog(): Promise<void> {
    this.renameDialog?.close();
    if (!this.unit) return;

    this.renameDialog = new InputModal({
      scene: this,
      title: "Rename Unit",
      message: "Give this unit a player-facing label. Names are cosmetic and can be changed later.",
      acceptLabel: "Save",
      rejectLabel: "Cancel",
      onAccept: () => {
        this.renameDialog?.close();
      },
      onReject: () => {
        this.renameDialog?.close();
      },
      onAcceptInput: async (value) => {
        const normalized = normalizeUnitName(value);
        if (!normalized) {
          throw new Error("Use 1-32 valid characters.");
        }
        const response = await apiClient.renameUnit(this.unitId, normalized);
        if (!response.ok) {
          throw new Error(response.error.message);
        }
        this.showToast("Unit renamed.", "#ccffcc");
        await this.loadData();
      },
      input: {
        initialValue: this.unit.name,
        placeholder: "Unit name",
        maxLength: 32,
        allowedCharacterPattern: SQUAD_NAME_ALLOWED_CHARACTER_PATTERN,
      },
    });
  }

  private async reorderSelectedAbility(direction: -1 | 1): Promise<void> {
    if (!this.unit) return;
    const targetIndex = this.selectedLoadoutIndex + direction;
    if (targetIndex < 0 || targetIndex >= this.unit.equippedLoadout.length) return;
    await this.reorderAbilityTo(this.selectedLoadoutIndex, targetIndex);
  }

  private async reorderAbilityTo(fromIndex: number, targetIndex: number): Promise<void> {
    if (!this.unit || fromIndex === targetIndex) return;

    const previousLoadout = [...this.unit.equippedLoadout];
    const abilityIds = this.unit.equippedLoadout.map((entry) => entry.abilityId);
    const [moved] = abilityIds.splice(fromIndex, 1);
    if (!moved) {
      return;
    }
    abilityIds.splice(targetIndex, 0, moved);
    const [movedEntry] = this.unit.equippedLoadout.splice(fromIndex, 1);
    if (!movedEntry) {
      return;
    }
    this.unit.equippedLoadout.splice(targetIndex, 0, movedEntry);
    this.selectedLoadoutIndex = targetIndex;
    this.selectedAbilitySlotIndex = 0;
    this.refreshUi();

    const response = await apiClient.replaceEquippedAbilities(this.unitId, abilityIds);
    if (!response.ok) {
      this.unit.equippedLoadout = previousLoadout;
      this.selectedLoadoutIndex = fromIndex;
      this.selectedAbilitySlotIndex = 0;
      this.refreshUi();
      this.showToast(`Loadout update failed: ${response.error.message}`);
      return;
    }

    this.showToast("Loadout order updated.", "#ccffcc");
    await this.loadData();
  }

  private async addAbilityToLoadout(abilityId: string): Promise<void> {
    if (!this.unit) return;
    const abilityIds = this.unit.equippedLoadout.map((entry) => entry.abilityId);
    abilityIds.push(abilityId);

    const response = await apiClient.replaceEquippedAbilities(this.unitId, abilityIds);
    if (!response.ok) {
      this.showToast(`Add ability failed: ${response.error.message}`);
      return;
    }

    this.selectedLoadoutIndex = abilityIds.length - 1;
    this.selectedAbilitySlotIndex = 0;
    this.showToast("Ability added to loadout.", "#ccffcc");
    await this.loadData();
  }

  private async removeSelectedAbility(): Promise<void> {
    if (!this.unit || this.unit.equippedLoadout.length <= 1) {
      return;
    }

    const abilityIds = this.unit.equippedLoadout.map((entry) => entry.abilityId);
    abilityIds.splice(this.selectedLoadoutIndex, 1);
    const response = await apiClient.replaceEquippedAbilities(this.unitId, abilityIds);
    if (!response.ok) {
      this.showToast(`Remove ability failed: ${response.error.message}`);
      return;
    }

    this.selectedLoadoutIndex = Math.max(0, Math.min(this.selectedLoadoutIndex, abilityIds.length - 1));
    this.selectedAbilitySlotIndex = 0;
    this.showToast("Ability removed from loadout.", "#ccffcc");
    await this.loadData();
  }

  private async assignSelectedDie(): Promise<void> {
    const selectedAbility = this.getSelectedLoadoutEntry();
    const selectedDie = this.getSelectedDie();
    if (!selectedAbility || selectedAbility.diceCost === 0) {
      this.showToast("Selected ability has no die slots.");
      return;
    }
    if (!selectedDie) {
      this.showToast("Select a die first.");
      return;
    }
    if (selectedDie.equipped && selectedDie.equipped.unitId !== this.unitId) {
      this.showToast(`Die already equipped on ${selectedDie.equipped.unitName}.`);
      return;
    }

    const response = await apiClient.assignAbilitySlotDie(
      this.unitId,
      selectedAbility.abilityId,
      this.selectedAbilitySlotIndex,
      selectedDie.id,
    );
    if (!response.ok) {
      this.showToast(`Die equip failed: ${response.error.message}`);
      return;
    }

    this.showToast("Die assigned to ability slot.", "#ccffcc");
    await this.loadData();
  }

  private async clearSelectedSlotDie(): Promise<void> {
    const selectedAbility = this.getSelectedLoadoutEntry();
    const selectedSlotDie = this.getSelectedSlotDie();
    if (!selectedAbility || selectedAbility.diceCost === 0) {
      this.showToast("Selected ability has no die slots.");
      return;
    }
    if (!selectedSlotDie) {
      this.showToast("Selected slot is already empty.");
      return;
    }

    const response = await apiClient.clearAbilitySlotDie(
      this.unitId,
      selectedAbility.abilityId,
      this.selectedAbilitySlotIndex,
    );
    if (!response.ok) {
      this.showToast(`Clear slot failed: ${response.error.message}`);
      return;
    }

    this.showToast("Slot cleared.", "#ccffcc");
    await this.loadData();
  }

  private candidateRowState(unit: UnitRecord): UnitCardState {
    const selected = this.fusionSecondaryIds.includes(unit.id);
    return {
      highlighted: selected,
      outlined: selected || this.selectedFusionSlotIndex >= 0,
      disabled: !this.isPromotionCompatible(this.unitId, unit.id),
      badgeText: selected ? "SELECTED" : null,
    };
  }

  private assignFusionSecondary(unitId: string): void {
    if (!this.isPromotionCompatible(this.unitId, unitId)) {
      this.showToast("This unit is not valid promotion material.");
      return;
    }

    const nextState = assignFusionSecondarySelection(this.fusionSecondaryIds, this.selectedFusionSlotIndex, unitId);
    this.fusionSecondaryIds = nextState.fusionSecondaryIds;
    this.selectedFusionSlotIndex = nextState.selectedFusionSlotIndex;
    this.refreshUi();
  }

  private clearFusionSelections(): void {
    const nextState = clearFusionSelections();
    this.fusionSecondaryIds = nextState.fusionSecondaryIds;
    this.selectedFusionSlotIndex = nextState.selectedFusionSlotIndex;
    this.refreshUi();
  }

  private getFusionCandidates(): UnitRecord[] {
    return getFusionCandidates(this.rawUnits, this.unitId);
  }

  private isPromotionCompatible(primaryId: string, secondaryId: string): boolean {
    return isPromotionCompatible(this.rawUnits, primaryId, secondaryId);
  }

  private async promoteUnit(): Promise<void> {
    if (this.activeRun) {
      this.showToast("Promotion is still disabled during active runs.");
      return;
    }

    const selectedSecondaries = this.fusionSecondaryIds.filter((id): id is string => Boolean(id));
    if (selectedSecondaries.length !== REQUIRED_FUSION_UNITS) {
      this.showToast("Select two compatible promotion units.");
      return;
    }
    const selectedPromotion = this.getSelectedPromotionOption();
    if (!selectedPromotion) {
      this.showToast("No promotion destination is available.");
      return;
    }

    const [secondaryA, secondaryB] = selectedSecondaries;
    if (!secondaryA || !secondaryB) {
      this.showToast("Select two compatible promotion units.");
      return;
    }

    const response = await apiClient.promoteUnit(this.unitId, [secondaryA, secondaryB], {
      destinationUnitTypeId: selectedPromotion.target_unit_type_id,
    });
    if (!response.ok) {
      this.showToast(`Promotion failed: ${response.error.message}`);
      return;
    }

    this.showToast(`Promotion applied: ${selectedPromotion.target_unit_type_name}.`, "#ccffcc");
    this.fusionSecondaryIds = Array(REQUIRED_FUSION_UNITS).fill(null);
    this.selectedFusionSlotIndex = 0;
    await this.loadData();
  }

  private buildDebugPromotionOptions(): PromotionOptionRecord[] {
    const tier = this.rawUnit?.tier ?? 1;
    const role = this.unit?.roleLabel ?? this.rawUnit?.unit_type_name ?? "Unit";
    if (tier >= 3) {
      return [];
    }

    const nextTier = tier + 1;
    return [
      {
        branch_unit_type_id: this.rawUnit?.unit_type_id ?? "debug_chain",
        branch_unit_type_slug: this.rawUnit?.unit_type_id ?? "debug_chain",
        branch_unit_type_name: role,
        target_unit_type_id: `${this.rawUnit?.unit_type_id ?? "debug_chain"}_t${nextTier}`,
        target_unit_type_slug: `${this.rawUnit?.unit_type_id ?? "debug_chain"}_t${nextTier}`,
        target_unit_type_name: `${role} T${nextTier}`,
        target_tier: nextTier,
        mode: "chain",
      },
    ];
  }

  private pickDieFrame(die: DiceDetailsViewModel): string {
    const material = RARITY_TO_MATERIAL[(die.rarity || "common").toLowerCase()] ?? "cardboard";
    const size = (die.sizeLabel || "d6").toLowerCase() as "d4" | "d6" | "d8" | "d10" | "d12" | "d20";
    const validSize = size === "d4" || size === "d6" || size === "d8" || size === "d10" || size === "d12" || size === "d20"
      ? size
      : "d6";
    const frame = getDiceFrameName(material, validSize);
    const atlas = this.textures.get(DICE_ATLAS_KEY);
    return atlas.has(frame) ? frame : "cardboard_d6";
  }

  private clearDynamicUi(): void {
    for (const obj of this.layoutUiObjects) {
      obj.destroy();
    }
    this.layoutUiObjects = [];
    this.tabStrip = undefined;
    this.loadoutRowBorders = [];
    this.loadoutRowTexts = [];
    this.abilitySlotBorders = [];
    this.abilitySlotLabels = [];
    this.abilitySlotIcons = [];
    this.fusionSlotBorders = [];
    this.fusionSlotLabels = [];
    this.dicePanel?.destroy();
    this.dicePanel = undefined;
    this.secondaryPanel?.destroy();
    this.secondaryPanel = undefined;
    this.renameButton = undefined;
    this.removeAbilityButton = undefined;
    this.equipDiceButton = undefined;
    this.unequipSlotButton = undefined;
    this.clearFusionButton = undefined;
    this.promoteButton = undefined;
    this.backButton = undefined;
    this.unitSummaryText = undefined;
    this.helperText = undefined;
    this.actionSummaryText = undefined;
    this.promotionOptionButtonBg = undefined;
    this.promotionOptionButtonText = undefined;
    this.promotionOptionButtonZone = undefined;
  }

  private showToast(message: string, color = "#ffcccc"): void {
    this.toastText?.destroy();
    const layout = getPageLayout(this);
    this.toastText = this.add.text(layout.content.x + 18, layout.content.y + layout.content.height - 22, message, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color,
    }).setOrigin(0, 1);
    this.time.delayedCall(2500, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }
}
