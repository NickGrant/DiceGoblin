import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import UnifiedButtonList from "../components/clickable-panel/UnifiedButtonList";
import DiceCardGrid from "../components/DiceCardGrid";
import UnitCardGrid, { type UnitCardState } from "../components/UnitCardGrid";
import { getDebugSceneConfig } from "../debug/debugScene";
import { getDebugProfileFixture } from "../debug/debugFixtures";
import { markDebugSceneReady } from "../debug/debugHooks";
import { adaptDiceDetails, adaptUnitRecords } from "../adapters/profileViewModels";
import { apiClient } from "../services/apiClient";
import type { DiceDetailsViewModel } from "../adapters/profileViewModels";
import type { UnitRecord } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import { DICE_ATLAS_KEY, getDiceFrameName } from "../assets/diceAtlas";

const FRAME_TITLE_HEIGHT = 56;
const FRAME_MARGIN = 12;
const SECTION_GAP = 12;
const ACTION_BUTTON_GAP = 10;
const DICE_SLOT_SIZE = 72;
const FUSION_SLOT_SIZE = 72;
const REQUIRED_FUSION_UNITS = 2;
const SLOT_ICON_SIZE = 46;
const CONTENT_INSET = 10;
const SLOT_TOP_PADDING = 8;

const RARITY_TO_MATERIAL: Record<string, "cardboard" | "wood" | "bone" | "metal" | "gemstone"> = {
  common: "cardboard",
  uncommon: "wood",
  rare: "bone",
  epic: "metal",
  legendary: "gemstone",
};

export default class UnitDetailsScene extends Phaser.Scene {
  private unitId = "";
  private loadingText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;
  private statusText?: Phaser.GameObjects.Text;

  private units: UnitRecord[] = [];
  private dice: DiceDetailsViewModel[] = [];
  private selectedDiceId: string | null = null;
  private selectedEquipSlotIndex: number | null = 0;
  private selectedFusionSlotIndex: number | null = 0;
  private activeRun = false;
  private unit: UnitRecord | null = null;
  private fusionSecondaryIds: Array<string | null> = Array(REQUIRED_FUSION_UNITS).fill(null);
  private secondaryPanel?: UnitCardGrid;
  private dicePanel?: DiceCardGrid;
  private clearFusionButton?: SharedActionButton;
  private promoteButton?: SharedActionButton;
  private equipDiceButton?: SharedActionButton;
  private unequipSlotButton?: SharedActionButton;
  private layoutUiObjects: Phaser.GameObjects.GameObject[] = [];
  private equipSlotBorders: Phaser.GameObjects.Rectangle[] = [];
  private equipSlotLabels: Phaser.GameObjects.Text[] = [];
  private equipSlotIcons: Phaser.GameObjects.Image[] = [];
  private fusionSlotBorders: Phaser.GameObjects.Rectangle[] = [];
  private fusionSlotLabels: Phaser.GameObjects.Text[] = [];

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
    const contentFrame = new ContentAreaFrame({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
      title: "Unit Details",
      bodyColor: 0x4f5a65,
    });
    contentFrame.setDepth(-800);
    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Unit Actions",
      bodyColor: 0x006f7a,
    });
    actionsFrame.setDepth(-800);
    this.loadingText = this.add.text(layout.content.x + 16, layout.content.y + 120, "Loading unit details...", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "20px",
      color: "#ffffff",
    });

    void this.loadData();
  }

  private async loadData(): Promise<void> {
    try {
      const profile = await apiClient.getProfile({ force: true }).catch(() => {
        const debugConfig = getDebugSceneConfig();
        if (!debugConfig.enabled) {
          throw new Error("Failed to fetch");
        }
        return getDebugProfileFixture();
      });
      if (!profile.ok) throw new Error(profile.error.message);

      this.units = adaptUnitRecords(profile.data.units ?? []);
      this.activeRun = profile.data.active_run !== null;
      this.unit = this.units.find((u) => u.id === this.unitId) ?? this.units[0] ?? null;
      if (!this.unit) throw new Error("No units found.");
      this.unitId = this.unit.id;

      this.loadingText?.destroy();
      this.loadingText = undefined;
      const diceVm = adaptDiceDetails(profile.data.dice ?? [], profile.data.units ?? []);
      this.syncLocalSelections(diceVm);
      this.buildUi(profile.data.dice ?? [], profile.data.units ?? []);
      markDebugSceneReady(this, {
        unitId: this.unitId,
        activeRun: this.activeRun,
      });
    } catch (e) {
      this.loadingText?.setText(`Failed to load.\n${(e as Error).message}`);
      markDebugSceneReady(this, { state: "error" });
    }
  }

  private buildUi(rawDice: unknown[], rawUnits: unknown[]): void {
    const layout = getPageLayout(this);
    const contentBodyX = layout.content.x + FRAME_MARGIN + CONTENT_INSET;
    const contentBodyY = layout.content.y + FRAME_TITLE_HEIGHT + FRAME_MARGIN + CONTENT_INSET + 78;
    const contentBodyWidth = Math.max(320, layout.content.width - (FRAME_MARGIN + CONTENT_INSET) * 2);
    const contentBodyHeight = Math.max(280, layout.content.height - FRAME_TITLE_HEIGHT - (FRAME_MARGIN + CONTENT_INSET) * 2 - 78);

    const actionsBodyX = layout.buttons.x + FRAME_MARGIN;
    const actionsBodyY = layout.buttons.y + FRAME_TITLE_HEIGHT + FRAME_MARGIN;
    const actionsBodyWidth = Math.max(280, layout.buttons.width - FRAME_MARGIN * 2);

    const leftColumnW = Math.max(130, Math.floor(contentBodyWidth * 0.22));
    const rightAreaX = contentBodyX + leftColumnW + SECTION_GAP;
    const rightAreaW = Math.max(280, contentBodyWidth - leftColumnW - SECTION_GAP);

    const sectionHeaderH = 20;
    const sharedSectionH = Math.max(140, Math.floor((contentBodyHeight - SECTION_GAP) / 2));
    const topRowY = contentBodyY;
    const topRowH = sharedSectionH;
    const topContentY = topRowY + sectionHeaderH;
    const topContentH = Math.max(80, topRowH - sectionHeaderH);
    const bottomRowY = contentBodyY + sharedSectionH + SECTION_GAP;
    const bottomRowH = sharedSectionH;
    const bottomContentY = bottomRowY + sectionHeaderH;
    const bottomContentH = Math.max(80, bottomRowH - sectionHeaderH);

    const slotColumnW = Math.max(190, Math.min(260, Math.floor(rightAreaW * 0.38)));
    const dicePanelX = rightAreaX + slotColumnW + SECTION_GAP;
    const dicePanelW = Math.max(220, rightAreaW - slotColumnW - SECTION_GAP);

    const fusionSlotAreaW = slotColumnW;
    const fusionCandidatesX = dicePanelX;
    const fusionCandidatesW = dicePanelW;

    if (!this.unit) return;

    const overviewLabel = this.add
      .text(layout.content.x + 24, layout.content.y + 88, "UNIT LOADOUT", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "20px",
        color: "#f0d38a",
      })
      .setOrigin(0, 0);
    const overviewBody = this.add
      .text(layout.content.x + 24, layout.content.y + 118, "Review current stats, equip better dice, and queue promotion fodder from the lower candidate list.", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "22px",
        color: "#eef4f5",
        lineSpacing: 8,
        wordWrap: { width: layout.content.width - 48 },
      })
      .setOrigin(0, 0);
    this.layoutUiObjects.push(overviewLabel, overviewBody);

    const diceVm = adaptDiceDetails(rawDice as any, rawUnits as any);
    this.dice = diceVm;
    this.clearDynamicUi();
    this.syncLocalSelections(diceVm);

    const max = typeof this.unit.max_level === "number" ? this.unit.max_level : "?";
    const xp = typeof this.unit.xp === "number" ? this.unit.xp : 0;
    const tier = typeof this.unit.tier === "number" ? this.unit.tier : 1;
    const maxTier = typeof this.unit.max_tier === "number" ? this.unit.max_tier : 3;
    const totalAttack = typeof this.unit.total_attack === "number" ? this.unit.total_attack : "?";
    const totalDefense = typeof this.unit.total_defense === "number" ? this.unit.total_defense : "?";
    const currentHp = typeof this.unit.current_hp === "number" ? this.unit.current_hp : "?";
    const maxHp = typeof this.unit.max_hp === "number" ? this.unit.max_hp : "?";
    const xpToNext = typeof this.unit.xp_to_next_level === "number" ? this.unit.xp_to_next_level : null;

    const portraitHeight = Math.max(96, Math.floor(topContentH * 0.45));
    const statsHeight = Math.max(96, contentBodyHeight - portraitHeight - SECTION_GAP);

    const portraitBg = this.add
      .rectangle(contentBodyX, contentBodyY, leftColumnW, portraitHeight, 0x1f2b32, 0.72)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x8db8bc, 0.45);
    const portraitIcon = this.add
      .image(contentBodyX + leftColumnW / 2, contentBodyY + portraitHeight / 2, "icon_warband")
      .setDisplaySize(
        Math.min(100, leftColumnW - 26, portraitHeight - 26),
        Math.min(100, leftColumnW - 26, portraitHeight - 26)
      )
      .setAlpha(0.88);
    this.layoutUiObjects.push(portraitBg, portraitIcon);

    const statsX = contentBodyX;
    const statsY = contentBodyY + portraitHeight + SECTION_GAP;
    const statsW = leftColumnW;
    const statsBg = this.add
      .rectangle(statsX, statsY, statsW, statsHeight, 0x20323a, 0.72)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x8db8bc, 0.45);
    const statsText = this.add
      .text(statsX + 10, statsY + 10, [
        `Unit: ${this.unit.name}`,
        `Level: ${this.unit.level} / ${max}`,
        `XP: ${xp}${xpToNext !== null ? ` (${xpToNext} to next)` : ""}`,
        `Tier: ${tier} / ${maxTier}`,
        `HP: ${currentHp} / ${maxHp}`,
        `ATK: ${totalAttack}`,
        `DEF: ${totalDefense}`,
      ].join("\n"), {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "17px",
        color: "#f0f3f4",
        lineSpacing: 5,
        wordWrap: { width: Math.max(100, statsW - 20) },
      })
      .setOrigin(0, 0);
    this.layoutUiObjects.push(statsBg, statsText);

    const equippedLabel = this.add
      .text(rightAreaX + 2, topRowY, "EQUIPPED DICE", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#f0f0f0",
      })
      .setOrigin(0, 0);
    this.layoutUiObjects.push(equippedLabel);

    this.buildEquipSlots(rightAreaX, topContentY + SLOT_TOP_PADDING, slotColumnW);

    this.secondaryPanel?.destroy();
    this.secondaryPanel = undefined;

    this.dicePanel?.destroy();
    this.dicePanel = undefined;

    this.dicePanel = new DiceCardGrid({
      scene: this,
      x: dicePanelX,
      y: topContentY + 2,
      width: dicePanelW,
      height: topContentH - 2,
      title: "",
      dice: this.getSelectableDice(),
      selectedDiceId: this.selectedDiceId,
      maxVisibleCards: 3,
      onDiceClick: (die) => {
        this.selectedDiceId = die.id;
        this.dicePanel?.setSelectedDiceId(die.id);
        this.refreshStatus();
        this.refreshActionButtons();
      },
    });
    this.layoutUiObjects.push(this.dicePanel);

    const promotionLabel = this.add
      .text(rightAreaX + 2, bottomRowY, "UNIT PROMOTION", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#f0f0f0",
      })
      .setOrigin(0, 0);
    this.layoutUiObjects.push(promotionLabel);

    this.secondaryPanel = new UnitCardGrid({
      scene: this,
      x: fusionCandidatesX,
      y: bottomContentY,
      width: fusionCandidatesW,
      height: bottomContentH,
      title: "",
      units: this.getFusionCandidates(),
      onUnitClick: (u) => this.assignFusionSecondary(u.id),
      getCardState: (u) => this.candidateRowState(u),
    });
    this.layoutUiObjects.push(this.secondaryPanel);

    this.buildFusionSlots(rightAreaX, bottomContentY + SLOT_TOP_PADDING, fusionSlotAreaW);

    const buttonX = actionsBodyX + Math.max(0, Math.floor((actionsBodyWidth - 280) / 2));
    const topActionY = actionsBodyY + 14;
    const actionSummaryCard = this.add
      .rectangle(actionsBodyX + 12, topActionY, actionsBodyWidth - 24, 116, 0x0f2024, 0.58)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x8db8bc, 0.32);
    const actionSummaryText = this.add
      .text(actionsBodyX + 24, topActionY + 12, [
        `${this.unit.name}`,
        `Level ${this.unit.level} | Tier ${tier}/${maxTier}`,
        `ATK ${totalAttack} | DEF ${totalDefense} | HP ${currentHp}/${maxHp}`,
        this.activeRun ? "Active run: loadout changes affect the current push." : "No active run: safe time to rebuild this unit."
      ].join("\n"), {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "17px",
        color: "#e7f4f5",
        lineSpacing: 6,
        wordWrap: { width: actionsBodyWidth - 48 },
      })
      .setOrigin(0, 0);
    this.layoutUiObjects.push(actionSummaryCard, actionSummaryText);

    new UnifiedButtonList({
      scene: this,
      x: buttonX,
      y: topActionY + 132,
      gapY: ACTION_BUTTON_GAP,
      buttons: [
        { label: "Back", onClick: () => this.scene.start("WarbandManagementScene") },
      ],
    });

    this.clearFusionButton?.destroy();
    this.clearFusionButton = new SharedActionButton({
      scene: this,
      x: buttonX,
      y: topActionY + 348,
      label: "Clear Fusion",
      enabled: false,
      onClick: () => {
        this.clearFusionSelections();
      },
    });

    this.promoteButton?.destroy();
    this.promoteButton = new SharedActionButton({
      scene: this,
      x: buttonX,
      y: topActionY + 414,
      label: "Promote Unit",
      enabled: false,
      onClick: () => void this.promoteUnit(),
    });

    this.equipDiceButton?.destroy();
    this.unequipSlotButton?.destroy();
    this.equipDiceButton = new SharedActionButton({
      scene: this,
      x: buttonX,
      y: topActionY + 216,
      label: "Equip Selected Die",
      enabled: false,
      onClick: () => void this.equipSelectedDie(),
    });
    this.unequipSlotButton = new SharedActionButton({
      scene: this,
      x: buttonX,
      y: topActionY + 282,
      label: "Unequip Slot Die",
      enabled: false,
      onClick: () => void this.unequipSelectedSlotDie(),
    });

    this.statusText?.destroy();
    this.statusText = undefined;

    this.refreshStatus();
    this.refreshEquipSlotUi();
    this.refreshFusionSlotUi();
    this.refreshActionButtons();
  }

  private buildEquipSlots(x: number, y: number, width: number): void {
    const slotCount = this.getDiceSlotCount();
    this.equipSlotBorders = [];
    this.equipSlotLabels = [];
    this.equipSlotIcons = [];

    const slotsY = y;
    const gap = 10;
    const startX = x;

    for (let i = 0; i < slotCount; i += 1) {
      const slotX = startX + i * (DICE_SLOT_SIZE + gap);
      const border = this.add
        .rectangle(slotX, slotsY, DICE_SLOT_SIZE, DICE_SLOT_SIZE, 0x1c2b31, 0.8)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0x8db8bc, 0.48)
        .setInteractive({ useHandCursor: true });
      border.on("pointerdown", () => {
        this.selectedEquipSlotIndex = i;
        this.refreshEquipSlotUi();
        this.refreshActionButtons();
        this.refreshStatus();
      });

      const label = this.add
        .text(slotX + DICE_SLOT_SIZE / 2, slotsY + DICE_SLOT_SIZE - 14, "Empty", {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "12px",
          color: "#d6d6d6",
          align: "center",
          wordWrap: { width: DICE_SLOT_SIZE - 12 },
        })
        .setOrigin(0.5, 0.5);

      const icon = this.add
        .image(slotX + DICE_SLOT_SIZE / 2, slotsY + 22, DICE_ATLAS_KEY, "cardboard_d6")
        .setDisplaySize(SLOT_ICON_SIZE, SLOT_ICON_SIZE)
        .setVisible(false)
        .setAlpha(0.96);

      this.equipSlotBorders.push(border);
      this.equipSlotLabels.push(label);
      this.equipSlotIcons.push(icon);
      this.layoutUiObjects.push(border, icon, label);
    }
  }

  private buildFusionSlots(x: number, y: number, width: number): void {
    this.fusionSlotBorders = [];
    this.fusionSlotLabels = [];

    const slotY = y;
    const gap = 10;
    const bandWidth = REQUIRED_FUSION_UNITS * FUSION_SLOT_SIZE + (REQUIRED_FUSION_UNITS - 1) * gap;
    const startX = x;

    for (let i = 0; i < REQUIRED_FUSION_UNITS; i += 1) {
      const slotX = startX + i * (FUSION_SLOT_SIZE + gap);
      const border = this.add
        .rectangle(slotX, slotY, FUSION_SLOT_SIZE, FUSION_SLOT_SIZE, 0x3b331e, 0.76)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0xe0b85a, 0.62)
        .setInteractive({ useHandCursor: true });
      border.on("pointerdown", () => {
        this.selectedFusionSlotIndex = i;
        this.refreshFusionSlotUi();
      });

      const label = this.add
        .text(slotX + FUSION_SLOT_SIZE / 2, slotY + FUSION_SLOT_SIZE / 2, "Empty", {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "13px",
          color: "#f3dca6",
          align: "center",
          wordWrap: { width: FUSION_SLOT_SIZE - 10 },
        })
        .setOrigin(0.5, 0.5);

      this.fusionSlotBorders.push(border);
      this.fusionSlotLabels.push(label);
      this.layoutUiObjects.push(border, label);
    }
  }

  private refreshEquipSlotUi(): void {
    const bySlot = this.getUnitEquippedDiceBySlot();
    for (let i = 0; i < this.equipSlotBorders.length; i += 1) {
      const border = this.equipSlotBorders[i];
      const label = this.equipSlotLabels[i];
      const icon = this.equipSlotIcons[i];
      if (!border || !label || !icon) {
        continue;
      }
      const die = bySlot.get(i) ?? null;
      const selected = this.selectedEquipSlotIndex === i;

      border.setStrokeStyle(2, selected ? 0xd7eef0 : 0x8db8bc, selected ? 0.95 : 0.48);
      label.setText(die ? die.sizeLabel.toUpperCase() : "Empty");
      label.setColor(die ? "#ecf6ff" : "#d6d6d6");
      if (die) {
        icon.setFrame(this.pickDieFrame(die));
        icon.setVisible(true);
      } else {
        icon.setVisible(false);
      }
    }

    this.refreshDiceSelectionGrid();
  }

  private refreshFusionSlotUi(): void {
    for (let i = 0; i < this.fusionSlotBorders.length; i += 1) {
      const border = this.fusionSlotBorders[i];
      const label = this.fusionSlotLabels[i];
      if (!border || !label) {
        continue;
      }
      const unitId = this.fusionSecondaryIds[i] ?? null;
      const unitName = unitId ? this.units.find((u) => u.id === unitId)?.name ?? `Unit ${unitId}` : "Empty";
      const selected = this.selectedFusionSlotIndex === i;

      border.setStrokeStyle(2, selected ? 0xf2e3b5 : 0xe0b85a, selected ? 0.95 : 0.62);
      label.setText(unitName);
      label.setColor(unitId ? "#fff2d2" : "#f3dca6");
    }
  }

  private candidateRowState(unit: UnitRecord): UnitCardState {
    const selected = this.fusionSecondaryIds.includes(unit.id);
    return {
      highlighted: selected,
      disabled: this.activeRun,
      badgeText: selected ? "SELECTED" : null,
    };
  }

  private assignFusionSecondary(unitId: string): void {
    if (this.activeRun) return;
    if (!this.isPromotionCompatible(this.unitId, unitId)) {
      return;
    }

    const existingIndex = this.fusionSecondaryIds.findIndex((id) => id === unitId);
    if (existingIndex >= 0) {
      this.fusionSecondaryIds[existingIndex] = null;
      this.selectedFusionSlotIndex = existingIndex;
      this.secondaryPanel?.refreshCardStates();
      this.refreshFusionSlotUi();
      this.refreshStatus();
      this.refreshActionButtons();
      return;
    }

    const targetIndex = this.selectedFusionSlotIndex !== null
      ? this.selectedFusionSlotIndex
      : this.fusionSecondaryIds.findIndex((id) => id === null);
    const safeIndex = targetIndex >= 0 ? targetIndex : 0;
    this.fusionSecondaryIds[safeIndex] = unitId;
    this.selectedFusionSlotIndex = (safeIndex + 1) % REQUIRED_FUSION_UNITS;

    this.secondaryPanel?.refreshCardStates();
    this.refreshFusionSlotUi();
    this.refreshStatus();
    this.refreshActionButtons();
  }

  private async promoteUnit(): Promise<void> {
    if (this.activeRun) {
      this.showToast("Promotion unavailable while a run is active.");
      return;
    }
    const selectedSecondaries = this.fusionSecondaryIds.filter((id): id is string => Boolean(id));
    if (selectedSecondaries.length !== REQUIRED_FUSION_UNITS) {
      this.showToast("Select two compatible secondaries.");
      return;
    }

    const [a, b] = selectedSecondaries as [string, string];
    const res = await apiClient.promoteUnit(this.unitId, [a, b]);
    if (!res.ok) {
      this.showToast(`Promote failed: ${res.error.message}`);
      return;
    }
    this.showToast("Promotion applied.", "#ccffcc");
    this.fusionSecondaryIds = Array(REQUIRED_FUSION_UNITS).fill(null);
    this.selectedFusionSlotIndex = 0;
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
    this.refreshActionButtons();
  }

  private getSelectedDie(): DiceDetailsViewModel | null {
    if (!this.selectedDiceId) {
      return null;
    }
    return this.dice.find((die) => die.id === this.selectedDiceId) ?? null;
  }

  private refreshActionButtons(): void {
    const selectedDie = this.getSelectedDie();
    const canMutateDice = !this.activeRun;
    const selectedSlotDie = this.getSelectedSlotDie();

    const dieEquippedElsewhere = Boolean(selectedDie?.equipped && selectedDie.equipped.unitId !== this.unitId);
    const canEquip = Boolean(canMutateDice && this.selectedEquipSlotIndex !== null && selectedDie && !dieEquippedElsewhere);
    const canUnequip = Boolean(canMutateDice && selectedSlotDie);
    const selectedSecondaries = this.fusionSecondaryIds.filter((id): id is string => Boolean(id));
    const canClearFusion = selectedSecondaries.length > 0;
    const canPromote = !this.activeRun && selectedSecondaries.length === REQUIRED_FUSION_UNITS;

    this.equipDiceButton?.setEnabled(canEquip);
    this.unequipSlotButton?.setEnabled(canUnequip);
    this.clearFusionButton?.setEnabled(canClearFusion);
    this.promoteButton?.setEnabled(canPromote);
  }

  private clearFusionSelections(): void {
    this.fusionSecondaryIds = Array(REQUIRED_FUSION_UNITS).fill(null);
    this.selectedFusionSlotIndex = 0;
    this.secondaryPanel?.refreshCardStates();
    this.refreshFusionSlotUi();
    this.refreshStatus();
    this.refreshActionButtons();
  }

  private async equipSelectedDie(): Promise<void> {
    if (this.activeRun) {
      this.showToast("Dice changes unavailable while an active run exists.");
      return;
    }

    if (this.selectedEquipSlotIndex === null) {
      this.showToast("Select a target dice slot first.");
      return;
    }

    const selectedDie = this.getSelectedDie();
    if (!selectedDie) {
      this.showToast("Select a die first.");
      return;
    }

    if (selectedDie.equipped && selectedDie.equipped.unitId !== this.unitId) {
      this.showToast(`Die already equipped on ${selectedDie.equipped.unitName}.`);
      return;
    }

    const slotDie = this.getSelectedSlotDie();
    if (slotDie && slotDie.id !== selectedDie.id) {
      const clearSlot = await apiClient.unequipDice(this.unitId, slotDie.id);
      if (!clearSlot.ok) {
        this.showToast(`Unequip existing slot die failed: ${clearSlot.error.message}`);
        return;
      }
    }

    if (selectedDie.equipped && selectedDie.equipped.unitId === this.unitId && slotDie?.id !== selectedDie.id) {
      const clearOld = await apiClient.unequipDice(this.unitId, selectedDie.id);
      if (!clearOld.ok) {
        this.showToast(`Move failed: ${clearOld.error.message}`);
        return;
      }
    }

    const res = await apiClient.equipDice(this.unitId, selectedDie.id);
    if (!res.ok) {
      this.showToast(`Equip failed: ${res.error.message}`);
      return;
    }

    this.showToast("Die equipped.", "#ccffcc");
    await this.loadData();
  }

  private async unequipSelectedSlotDie(): Promise<void> {
    if (this.activeRun) {
      this.showToast("Dice changes unavailable while an active run exists.");
      return;
    }

    if (this.selectedEquipSlotIndex === null) {
      this.showToast("Select a slot first.");
      return;
    }

    const slotDie = this.getSelectedSlotDie();
    if (!slotDie) {
      this.showToast("Selected slot is already empty.");
      return;
    }

    const res = await apiClient.unequipDice(this.unitId, slotDie.id);
    if (!res.ok) {
      this.showToast(`Unequip failed: ${res.error.message}`);
      return;
    }

    this.showToast("Die unequipped.", "#ccffcc");
    await this.loadData();
  }

  private getSelectedSlotDie(): DiceDetailsViewModel | null {
    if (this.selectedEquipSlotIndex === null) {
      return null;
    }
    return this.getUnitEquippedDiceBySlot().get(this.selectedEquipSlotIndex) ?? null;
  }

  private getDiceSlotCount(): number {
    if (!this.unit) {
      return 2;
    }

    const explicit = Number((this.unit as Record<string, unknown>).max_equipped_dice ?? 0);
    if (Number.isFinite(explicit) && explicit > 0) {
      return Math.max(1, Math.floor(explicit));
    }

    const equipped = Array.isArray(this.unit.equipped_dice) ? this.unit.equipped_dice : [];
    const maxSlotIndex = equipped.reduce((max, item) => {
      const slotIndex = Number(item?.slot_index ?? -1);
      return Number.isFinite(slotIndex) ? Math.max(max, slotIndex) : max;
    }, -1);

    return Math.max(2, maxSlotIndex + 1);
  }

  private getUnitEquippedDiceBySlot(): Map<number, DiceDetailsViewModel> {
    const map = new Map<number, DiceDetailsViewModel>();
    if (!this.unit || !Array.isArray(this.unit.equipped_dice)) {
      return map;
    }

    for (const equipped of this.unit.equipped_dice) {
      const slotIndex = Number(equipped.slot_index);
      if (!Number.isFinite(slotIndex) || slotIndex < 0) {
        continue;
      }
      const die = this.dice.find((candidate) => candidate.id === String(equipped.dice_instance_id));
      if (die) {
        map.set(slotIndex, die);
      }
    }

    return map;
  }

  private getFusionCandidates(): UnitRecord[] {
    return this.units.filter((u) => u.id !== this.unitId && this.isPromotionCompatible(this.unitId, u.id));
  }

  private getSelectableDice(): DiceDetailsViewModel[] {
    return this.dice.filter((die) => !die.equipped);
  }

  private refreshDiceSelectionGrid(): void {
    if (!this.dicePanel) {
      return;
    }

    const selectable = this.getSelectableDice();
    if (this.selectedDiceId && !selectable.some((die) => die.id === this.selectedDiceId)) {
      this.selectedDiceId = selectable[0]?.id ?? null;
    }
    this.dicePanel.setDice(selectable, this.selectedDiceId);
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

  private syncLocalSelections(diceVm: DiceDetailsViewModel[]): void {
    this.dice = diceVm;

    const selectable = this.getSelectableDice();
    const selectedStillExists = this.selectedDiceId
      ? selectable.some((die) => die.id === this.selectedDiceId)
      : false;
    if (!selectedStillExists) {
      this.selectedDiceId = selectable[0]?.id ?? null;
    }

    const slotCount = this.getDiceSlotCount();
    if (this.selectedEquipSlotIndex === null || this.selectedEquipSlotIndex >= slotCount) {
      this.selectedEquipSlotIndex = 0;
    }

    const compatibleIds = new Set(this.getFusionCandidates().map((u) => u.id));
    this.fusionSecondaryIds = this.fusionSecondaryIds.map((id) => (id && compatibleIds.has(id) ? id : null));

    if (this.selectedFusionSlotIndex !== null && this.selectedFusionSlotIndex >= REQUIRED_FUSION_UNITS) {
      this.selectedFusionSlotIndex = 0;
    }
  }

  private clearDynamicUi(): void {
    for (const obj of this.layoutUiObjects) {
      obj.destroy();
    }
    this.layoutUiObjects = [];
    this.equipSlotBorders = [];
    this.equipSlotLabels = [];
    this.equipSlotIcons = [];
    this.fusionSlotBorders = [];
    this.fusionSlotLabels = [];
    this.clearFusionButton?.destroy();
    this.clearFusionButton = undefined;
  }

  private showToast(message: string, color = "#ffcccc"): void {
    this.toastText?.destroy();
    const layout = getPageLayout(this);
    this.toastText = this.add.text(layout.content.x + 16, layout.content.y + layout.content.height - 24, message, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color,
    });
    this.time.delayedCall(2500, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }
}






