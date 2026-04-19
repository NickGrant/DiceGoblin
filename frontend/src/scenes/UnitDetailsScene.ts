import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import DiceCardGrid from "../components/DiceCardGrid";
import UnitCardGrid, { type UnitCardState } from "../components/UnitCardGrid";
import InputModal from "../components/feedback/InputModal";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
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

const FRAME_TITLE_HEIGHT = 56;
const FRAME_MARGIN = 12;
const CONTENT_INSET = 10;
const SECTION_GAP = 12;
const ACTION_BUTTON_GAP = 10;
const SLOT_SIZE = 72;
const SLOT_ICON_SIZE = 46;
const REQUIRED_FUSION_UNITS = 2;
const ABILITY_ROW_HEIGHT = 38;
const ABILITY_LIST_HEIGHT = 164;
const SIDEBAR_BUTTON_WIDTH = 280;

const RARITY_TO_MATERIAL: Record<string, "cardboard" | "wood" | "bone" | "metal" | "gemstone"> = {
  common: "cardboard",
  uncommon: "wood",
  rare: "bone",
  epic: "metal",
  legendary: "gemstone",
};

function normalizeUnitName(value: string): string | null {
  const trimmed = value.trim();
  if (trimmed.length === 0 || trimmed.length > 32) return null;
  return SQUAD_NAME_ALLOWED_CHARACTER_PATTERN.test(trimmed) ? trimmed : null;
}

export default class UnitDetailsScene extends Phaser.Scene {
  private unitId = "";
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
  private dicePanel?: DiceCardGrid;
  private secondaryPanel?: UnitCardGrid;
  private moveUpButton?: SharedActionButton;
  private moveDownButton?: SharedActionButton;
  private renameButton?: SharedActionButton;
  private equipDiceButton?: SharedActionButton;
  private unequipSlotButton?: SharedActionButton;
  private clearFusionButton?: SharedActionButton;
  private promoteButton?: SharedActionButton;
  private loadoutRowBorders: Phaser.GameObjects.Rectangle[] = [];
  private loadoutRowTexts: Phaser.GameObjects.Text[] = [];
  private abilitySlotBorders: Phaser.GameObjects.Rectangle[] = [];
  private abilitySlotLabels: Phaser.GameObjects.Text[] = [];
  private abilitySlotIcons: Phaser.GameObjects.Image[] = [];
  private fusionSlotBorders: Phaser.GameObjects.Rectangle[] = [];
  private fusionSlotLabels: Phaser.GameObjects.Text[] = [];
  private unitSummaryText?: Phaser.GameObjects.Text;
  private helperText?: Phaser.GameObjects.Text;
  private promotionOptionButtonBg?: Phaser.GameObjects.Rectangle;
  private promotionOptionButtonText?: Phaser.GameObjects.Text;
  private promotionOptionButtonZone?: Phaser.GameObjects.Zone;

  constructor() {
    super({ key: "UnitDetailsScene" });
  }

  init(data: { unitId?: string }): void {
    this.unitId = String(data?.unitId ?? "");
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
      if (!profile.ok) throw new Error(profile.error.message);

      const abilityCatalog = await apiClient.getAbilityCatalog().catch(() => {
        if (!debugConfig.enabled) throw new Error("Failed to fetch ability catalog");
        return getDebugAbilityCatalogFixture();
      });
      if (!abilityCatalog.ok) throw new Error(abilityCatalog.error.message);

      this.rawUnits = adaptUnitRecords(profile.data.units ?? []);
      this.unitDetails = adaptUnitDetails(profile.data.units ?? [], abilityCatalog.data.abilities ?? []);
      this.activeRun = profile.data.active_run !== null;
      this.unit = this.unitDetails.find((unit) => unit.id === this.unitId) ?? this.unitDetails[0] ?? null;
      this.rawUnit = this.rawUnits.find((unit) => unit.id === this.unitId) ?? this.rawUnits[0] ?? null;
      if (!this.unit || !this.rawUnit) throw new Error("No units found.");

      const currentUnit = this.unit;
      this.unitId = currentUnit.id;
      const promotionOptions = await apiClient.getPromotionOptions(this.unitId).catch(() => {
        if (!debugConfig.enabled) throw new Error("Failed to fetch promotion options");
        return { ok: true as const, data: { unit_id: this.unitId, current_tier: currentUnit.tier, options: this.buildDebugPromotionOptions() } };
      });
      if (!promotionOptions.ok) throw new Error(promotionOptions.error.message);

      this.dice = adaptDiceDetails(profile.data.dice ?? [], profile.data.units ?? []);
      this.promotionOptions = promotionOptions.data.options ?? [];
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

    const leftColumnWidth = 210;
    const rightColumnX = contentX + leftColumnWidth + SECTION_GAP;
    const rightColumnWidth = Math.max(280, contentWidth - leftColumnWidth - SECTION_GAP);
    const promotionHeight = 178;
    const topSectionHeight = Math.max(230, contentHeight - promotionHeight - SECTION_GAP);
    const dicePanelHeight = Math.max(112, topSectionHeight - ABILITY_LIST_HEIGHT - SLOT_SIZE - SECTION_GAP * 2 - 38);

    const summaryBg = this.add.rectangle(contentX, contentY, leftColumnWidth, topSectionHeight, 0x20323a, 0.72)
      .setOrigin(0, 0).setStrokeStyle(1, 0x8db8bc, 0.45);
    const portrait = this.add.image(contentX + leftColumnWidth / 2, contentY + 56, "icon_warband")
      .setDisplaySize(96, 96).setAlpha(0.9);
    this.unitSummaryText = this.add.text(contentX + 12, contentY + 116, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "17px",
      color: "#eef4f5",
      lineSpacing: 6,
      wordWrap: { width: leftColumnWidth - 24 },
    }).setOrigin(0, 0);
    this.helperText = this.add.text(contentX + 12, contentY + topSectionHeight - 56, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "14px",
      color: "#d6e7e8",
      wordWrap: { width: leftColumnWidth - 24 },
    }).setOrigin(0, 0);
    this.layoutUiObjects.push(summaryBg, portrait, this.unitSummaryText, this.helperText);

    const loadoutLabel = this.add.text(rightColumnX + 2, contentY, "EQUIPPED LOADOUT", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: "#f0f0f0",
    }).setOrigin(0, 0);
    this.layoutUiObjects.push(loadoutLabel);
    this.buildLoadoutRows(rightColumnX, contentY + 24, rightColumnWidth);

    const slotsLabel = this.add.text(rightColumnX + 2, contentY + ABILITY_LIST_HEIGHT + SECTION_GAP, "ABILITY SLOTS", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: "#f0f0f0",
    }).setOrigin(0, 0);
    this.layoutUiObjects.push(slotsLabel);
    this.buildAbilitySlots(rightColumnX, contentY + ABILITY_LIST_HEIGHT + SECTION_GAP + 24, rightColumnWidth);

    this.dicePanel = new DiceCardGrid({
      scene: this,
      x: rightColumnX,
      y: contentY + ABILITY_LIST_HEIGHT + SLOT_SIZE + SECTION_GAP * 2 + 32,
      width: rightColumnWidth,
      height: dicePanelHeight,
      title: "AVAILABLE DICE",
      dice: this.getSelectableDice(),
      selectedDiceId: this.selectedDiceId,
      maxVisibleCards: 3,
      onDiceClick: (die) => {
        this.selectedDiceId = die.id;
        this.dicePanel?.setSelectedDiceId(die.id);
        this.refreshUi();
      },
    });
    this.layoutUiObjects.push(this.dicePanel);

    const promotionY = contentY + topSectionHeight + SECTION_GAP;
    const promotionLabel = this.add.text(contentX + 2, promotionY, "PROMOTION MATERIAL", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: "#f0f0f0",
    }).setOrigin(0, 0);
    this.layoutUiObjects.push(promotionLabel);
    this.buildFusionSlots(contentX, promotionY + 24);

    this.secondaryPanel = new UnitCardGrid({
      scene: this,
      x: contentX + 180,
      y: promotionY + 24,
      width: contentWidth - 180,
      height: promotionHeight - 24,
      title: "PROMOTION CANDIDATES",
      units: this.getFusionCandidates(),
      onUnitClick: (unit) => this.assignFusionSecondary(unit.id),
      getCardState: (unit) => this.candidateRowState(unit),
    });
    this.layoutUiObjects.push(this.secondaryPanel);

    this.buildSidebar(sidebarX, sidebarY, sidebarWidth);
    this.refreshUi();
  }

  private buildLoadoutRows(x: number, y: number, width: number): void {
    this.loadoutRowBorders = [];
    this.loadoutRowTexts = [];
    const visibleRows = Math.min(4, Math.max(1, this.unit?.equippedLoadout.length ?? 1));
    for (let index = 0; index < visibleRows; index += 1) {
      const top = y + index * (ABILITY_ROW_HEIGHT + 4);
      const border = this.add.rectangle(x, top, width, ABILITY_ROW_HEIGHT, 0x142127, 0.88)
        .setOrigin(0, 0).setStrokeStyle(1, 0x8db8bc, 0.32).setInteractive({ useHandCursor: true });
      border.on("pointerdown", () => {
        this.selectedLoadoutIndex = index;
        this.selectedAbilitySlotIndex = 0;
        this.refreshUi();
      });
      const text = this.add.text(x + 12, top + 8, "", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "18px",
        color: "#eef4f5",
        wordWrap: { width: width - 24 },
      }).setOrigin(0, 0);
      this.loadoutRowBorders.push(border);
      this.loadoutRowTexts.push(text);
      this.layoutUiObjects.push(border, text);
    }
  }

  private buildAbilitySlots(x: number, y: number, width: number): void {
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

    const totalWidth = slotCount * SLOT_SIZE + Math.max(0, slotCount - 1) * 10;
    const startX = x + Math.max(0, Math.floor((width - totalWidth) / 2));
    for (let slotIndex = 0; slotIndex < slotCount; slotIndex += 1) {
      const slotX = startX + slotIndex * (SLOT_SIZE + 10);
      const border = this.add.rectangle(slotX, y, SLOT_SIZE, SLOT_SIZE, 0x1c2b31, 0.82)
        .setOrigin(0, 0).setStrokeStyle(1, 0x8db8bc, 0.48).setInteractive({ useHandCursor: true });
      border.on("pointerdown", () => {
        this.selectedAbilitySlotIndex = slotIndex;
        this.refreshUi();
      });
      const icon = this.add.image(slotX + SLOT_SIZE / 2, y + 24, DICE_ATLAS_KEY, "cardboard_d6")
        .setDisplaySize(SLOT_ICON_SIZE, SLOT_ICON_SIZE).setVisible(false);
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

  private buildFusionSlots(x: number, y: number): void {
    this.fusionSlotBorders = [];
    this.fusionSlotLabels = [];
    for (let index = 0; index < REQUIRED_FUSION_UNITS; index += 1) {
      const slotX = x + index * (SLOT_SIZE + 10);
      const border = this.add.rectangle(slotX, y, SLOT_SIZE, SLOT_SIZE, 0x3b331e, 0.76)
        .setOrigin(0, 0).setStrokeStyle(1, 0xe0b85a, 0.62).setInteractive({ useHandCursor: true });
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
    const summaryCard = this.add.rectangle(x + 12, y + 14, width - 24, 156, 0x0f2024, 0.58)
      .setOrigin(0, 0).setStrokeStyle(1, 0x8db8bc, 0.32);
    this.layoutUiObjects.push(summaryCard);

    this.promotionOptionButtonBg = this.add.rectangle(x + 24, y + 114, width - 48, 42, 0xf2ead8, 0.92)
      .setOrigin(0, 0).setStrokeStyle(2, 0x7a5f39, 0.85);
    this.promotionOptionButtonText = this.add.text(x + width / 2, y + 135, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "14px",
      color: "#3e2b16",
      align: "center",
      wordWrap: { width: width - 64 },
    }).setOrigin(0.5, 0.5);
    this.promotionOptionButtonZone = this.add.zone(x + width / 2, y + 135, width - 48, 42)
      .setOrigin(0.5, 0.5)
      .setInteractive({ useHandCursor: true });
    this.promotionOptionButtonZone.on("pointerdown", () => {
      if (this.promotionOptions.length <= 1) {
        return;
      }
      this.selectedPromotionOptionIndex = (this.selectedPromotionOptionIndex + 1) % this.promotionOptions.length;
      this.refreshUi();
    });
    this.promotionOptionButtonZone.on("pointerover", () => this.promotionOptionButtonBg?.setFillStyle(0xfff2cf, 1));
    this.promotionOptionButtonZone.on("pointerout", () => this.promotionOptionButtonBg?.setFillStyle(0xf2ead8, 0.92));
    this.layoutUiObjects.push(this.promotionOptionButtonBg, this.promotionOptionButtonText, this.promotionOptionButtonZone);

    const buttonX = x + Math.max(0, Math.floor((width - SIDEBAR_BUTTON_WIDTH) / 2));
    const firstButtonY = y + 188;
    this.renameButton = new SharedActionButton({ scene: this, x: buttonX, y: firstButtonY, label: "Rename Unit", enabled: true, onClick: () => void this.openRenameDialog() });
    this.moveUpButton = new SharedActionButton({ scene: this, x: buttonX, y: firstButtonY + 66, label: "Move Ability Up", enabled: false, onClick: () => void this.reorderSelectedAbility(-1) });
    this.moveDownButton = new SharedActionButton({ scene: this, x: buttonX, y: firstButtonY + 132, label: "Move Ability Down", enabled: false, onClick: () => void this.reorderSelectedAbility(1) });
    this.equipDiceButton = new SharedActionButton({ scene: this, x: buttonX, y: firstButtonY + 198, label: "Equip Selected Die", enabled: false, onClick: () => void this.assignSelectedDie() });
    this.unequipSlotButton = new SharedActionButton({ scene: this, x: buttonX, y: firstButtonY + 264, label: "Clear Slot Die", enabled: false, onClick: () => void this.clearSelectedSlotDie() });
    this.clearFusionButton = new SharedActionButton({ scene: this, x: buttonX, y: firstButtonY + 330, label: "Clear Promotion", enabled: false, onClick: () => this.clearFusionSelections() });
    this.promoteButton = new SharedActionButton({ scene: this, x: buttonX, y: firstButtonY + 396, label: "Promote Unit", enabled: false, onClick: () => void this.promoteUnit() });
    const backButton = new SharedActionButton({ scene: this, x: buttonX, y: firstButtonY + 462, label: "Back", onClick: () => this.scene.start("WarbandManagementScene") });

    this.layoutUiObjects.push(
      this.renameButton,
      this.moveUpButton,
      this.moveDownButton,
      this.equipDiceButton,
      this.unequipSlotButton,
      this.clearFusionButton,
      this.promoteButton,
      backButton,
    );
  }

  private refreshUi(): void {
    this.syncSelections();
    const expectedSlotCount = this.getSelectedLoadoutEntry()?.diceCost ?? 0;
    if (expectedSlotCount !== this.abilitySlotBorders.length) {
      this.buildUi();
      return;
    }
    this.refreshLoadoutRows();
    this.refreshAbilitySlots();
    this.refreshFusionSlots();
    this.refreshSidebarSummary();
    this.refreshDicePanel();
    this.refreshActionButtons();
    this.secondaryPanel?.refreshCardStates();
  }

  private refreshLoadoutRows(): void {
    const loadout = this.unit?.equippedLoadout ?? [];
    this.loadoutRowBorders.forEach((border, index) => {
      const entry = loadout[index];
      const text = this.loadoutRowTexts[index];
      if (!entry || !text) {
        border.setVisible(false);
        if (text) {
          text.setVisible(false);
        }
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

  private refreshSidebarSummary(): void {
    if (!this.unitSummaryText || !this.helperText || !this.unit) return;

    this.unitSummaryText.setText(this.buildSummaryText());
    const selectedAbility = this.getSelectedLoadoutEntry();
    const selectedSlotDie = this.getSelectedSlotDie();
    const selectedDie = this.getSelectedDie();
    const selectedFusions = this.fusionSecondaryIds.filter((id): id is string => Boolean(id));
    const selectedPromotion = this.getSelectedPromotionOption();

    this.helperText.setText([
      selectedAbility
        ? `Loadout: ${selectedAbility.label} (${selectedAbility.speedCost} pts)`
        : "Loadout: none selected",
      selectedAbility && selectedAbility.diceCost > 0
        ? `Slot ${this.selectedAbilitySlotIndex + 1}: ${selectedSlotDie?.displayName ?? "Empty"}`
        : "This ability does not consume dice.",
      selectedDie
        ? `Picked die: ${selectedDie.displayName}${selectedDie.equipped ? ` on ${selectedDie.equipped.unitName}` : ""}`
        : "Pick a die from the pool to assign it.",
      selectedPromotion
        ? `Promotion path: ${selectedPromotion.mode === "chain" ? "Chain" : "Sideways"} to ${selectedPromotion.target_unit_type_name}`
        : "Promotion path: unavailable",
      selectedFusions.length > 0
        ? `Promotion fodder: ${selectedFusions.length}/${REQUIRED_FUSION_UNITS}`
        : "Select promotion fodder below when ready.",
    ].join("\n"));

    if (this.promotionOptionButtonBg && this.promotionOptionButtonText) {
      const hasOptions = this.promotionOptions.length > 0;
      this.promotionOptionButtonBg.setAlpha(hasOptions ? 1 : 0.45);
      this.promotionOptionButtonText.setText(
        hasOptions
          ? `DESTINATION: ${selectedPromotion?.target_unit_type_name.toUpperCase() ?? "UNKNOWN"}${this.promotionOptions.length > 1 ? "  (CLICK TO CYCLE)" : ""}`
          : "NO PROMOTION DESTINATIONS"
      );
      this.promotionOptionButtonText.setColor(hasOptions ? "#3e2b16" : "#7c7c7c");
    }
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

    this.renameButton?.setEnabled(true);
    this.moveUpButton?.setEnabled(Boolean(selectedAbility && this.selectedLoadoutIndex > 0));
    this.moveDownButton?.setEnabled(Boolean(
      selectedAbility
      && this.unit
      && this.selectedLoadoutIndex < this.unit.equippedLoadout.length - 1,
    ));
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
    if (!this.unit) return null;
    return this.unit.equippedLoadout[this.selectedLoadoutIndex] ?? null;
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
    return this.dice.filter((die) => !die.equipped || die.equipped.unitId === this.unitId);
  }

  private getSelectedPromotionOption(): PromotionOptionRecord | null {
    return this.promotionOptions[this.selectedPromotionOptionIndex] ?? null;
  }

  private buildSummaryText(): string {
    if (!this.unit || !this.rawUnit) return "";

    const activeAbilities = this.unit.unlockedAbilities.filter((ability) => ability.type === "active").length;
    const passiveAbilities = this.unit.unlockedAbilities.filter((ability) => ability.type === "passive").length;
    const totalAttack = typeof this.rawUnit.total_attack === "number" ? this.rawUnit.total_attack : "?";
    const totalDefense = typeof this.rawUnit.total_defense === "number" ? this.rawUnit.total_defense : "?";
    const hpCurrent = typeof this.rawUnit.current_hp === "number" ? this.rawUnit.current_hp : "?";
    const hpMax = typeof this.rawUnit.max_hp === "number" ? this.rawUnit.max_hp : "?";
    const xpToNext = typeof this.rawUnit.xp_to_next_level === "number" ? this.rawUnit.xp_to_next_level : null;

    return [
      this.unit.name,
      `${this.unit.roleLabel}  T${this.unit.tier}  LV ${this.unit.level}${this.unit.maxLevel ? `/${this.unit.maxLevel}` : ""}`,
      `HP ${hpCurrent}/${hpMax}  ATK ${totalAttack}  DEF ${totalDefense}`,
      `Loadout ${this.unit.loadoutBudget.used}/${this.unit.loadoutBudget.max} pts`,
      `Abilities ${activeAbilities} active / ${passiveAbilities} passive`,
      this.unit.isMaxLevel ? "XP MAX" : `XP ${this.unit.xp}${xpToNext !== null ? ` (${xpToNext} to next)` : ""}`,
    ].join("\n");
  }

  private syncSelections(): void {
    if (!this.unit) return;

    const loadoutLength = this.unit.equippedLoadout.length;
    if (loadoutLength === 0) {
      this.selectedLoadoutIndex = 0;
      this.selectedAbilitySlotIndex = 0;
    } else {
      this.selectedLoadoutIndex = Math.max(0, Math.min(this.selectedLoadoutIndex, loadoutLength - 1));
      const selectedAbility = this.unit.equippedLoadout[this.selectedLoadoutIndex];
      const slotCount = selectedAbility?.diceCost ?? 0;
      this.selectedAbilitySlotIndex = slotCount > 0
        ? Math.max(0, Math.min(this.selectedAbilitySlotIndex, slotCount - 1))
        : 0;
    }

    const selectableDice = this.getSelectableDice();
    if (!this.selectedDiceId || !selectableDice.some((die) => die.id === this.selectedDiceId)) {
      this.selectedDiceId = selectableDice[0]?.id ?? null;
    }

    const compatibleIds = new Set(this.getFusionCandidates().map((unit) => unit.id));
    this.fusionSecondaryIds = this.fusionSecondaryIds.map((unitId) => (unitId && compatibleIds.has(unitId) ? unitId : null));
    this.selectedFusionSlotIndex = Math.max(0, Math.min(this.selectedFusionSlotIndex, REQUIRED_FUSION_UNITS - 1));
    if (this.promotionOptions.length === 0) {
      this.selectedPromotionOptionIndex = 0;
    } else {
      this.selectedPromotionOptionIndex = Math.max(0, Math.min(this.selectedPromotionOptionIndex, this.promotionOptions.length - 1));
    }
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

    const abilityIds = this.unit.equippedLoadout.map((entry) => entry.abilityId);
    const [moved] = abilityIds.splice(this.selectedLoadoutIndex, 1);
    if (!moved) {
      return;
    }
    abilityIds.splice(targetIndex, 0, moved);

    const response = await apiClient.replaceEquippedAbilities(this.unitId, abilityIds);
    if (!response.ok) {
      this.showToast(`Loadout update failed: ${response.error.message}`);
      return;
    }

    this.selectedLoadoutIndex = targetIndex;
    this.showToast("Loadout order updated.", "#ccffcc");
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

    const existingIndex = this.fusionSecondaryIds.findIndex((id) => id === unitId);
    if (existingIndex >= 0) {
      this.fusionSecondaryIds[existingIndex] = null;
      this.selectedFusionSlotIndex = existingIndex;
      this.refreshUi();
      return;
    }

    const targetIndex = this.fusionSecondaryIds.findIndex((id) => id === null);
    const safeIndex = targetIndex >= 0 ? targetIndex : this.selectedFusionSlotIndex;
    this.fusionSecondaryIds[safeIndex] = unitId;
    this.selectedFusionSlotIndex = Math.min(REQUIRED_FUSION_UNITS - 1, safeIndex + 1);
    this.refreshUi();
  }

  private clearFusionSelections(): void {
    this.fusionSecondaryIds = Array(REQUIRED_FUSION_UNITS).fill(null);
    this.selectedFusionSlotIndex = 0;
    this.refreshUi();
  }

  private getFusionCandidates(): UnitRecord[] {
    return this.rawUnits.filter((unit) => unit.id !== this.unitId);
  }

  private isPromotionCompatible(primaryId: string, secondaryId: string): boolean {
    const primary = this.rawUnits.find((unit) => unit.id === primaryId);
    const secondary = this.rawUnits.find((unit) => unit.id === secondaryId);
    if (!primary || !secondary) return false;
    if (primary.id === secondary.id) return false;
    if ((primary.unit_type_id ?? "") !== (secondary.unit_type_id ?? "")) return false;
    if ((primary.tier ?? 1) !== (secondary.tier ?? 1)) return false;

    const primaryMaxLevel = typeof primary.max_level === "number" ? primary.max_level : null;
    const secondaryMaxLevel = typeof secondary.max_level === "number" ? secondary.max_level : null;
    if (primaryMaxLevel !== null && primary.level < primaryMaxLevel) return false;
    if (secondaryMaxLevel !== null && secondary.level < secondaryMaxLevel) return false;
    return true;
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
    this.moveUpButton = undefined;
    this.moveDownButton = undefined;
    this.equipDiceButton = undefined;
    this.unequipSlotButton = undefined;
    this.clearFusionButton = undefined;
    this.promoteButton = undefined;
    this.unitSummaryText = undefined;
    this.helperText = undefined;
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
