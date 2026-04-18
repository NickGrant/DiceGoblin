import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import UnitCardGrid from "../components/UnitCardGrid";
import SquadListPanel from "../components/SquadListPanel";
import { getDebugSceneConfig } from "../debug/debugScene";
import { getDebugProfileFixture } from "../debug/debugFixtures";
import { apiClient } from "../services/apiClient";
import type { DiceRecord, TeamRecord, UnitRecord } from "../types/ApiResponse";
import { markDebugSceneReady } from "../debug/debugHooks";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import { resolveContentFrameBodyRect } from "../components/layout/contentAreaMath";
import Tooltip from "../components/feedback/Tooltip";
import InputModal from "../components/feedback/InputModal";
import {
  deriveWarbandHubState,
  SQUAD_NAME_ALLOWED_CHARACTER_PATTERN,
  normalizeNewSquadName,
} from "./warbandManagementState";

const FRAME_TITLE_HEIGHT = 56;
const FRAME_MARGIN = 12;
const INNER_PANEL_PADDING = 10;
const PANEL_COLUMN_GAP = 10;
const SQUAD_COLUMN_MIN_WIDTH = 300;
const SQUAD_COLUMN_RATIO = 0.38;
const SUMMARY_PANEL_PADDING = 10;

export default class WarbandManagementScene extends Phaser.Scene {
  private loadingText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;
  private summaryUiObjects: Phaser.GameObjects.GameObject[] = [];

  private units: UnitRecord[] = [];
  private squads: TeamRecord[] = [];
  private diceVisualsById = new Map<string, Pick<DiceRecord, "rarity" | "sides">>();

  private unitPanel?: UnitCardGrid;
  private squadPanel?: SquadListPanel;
  private createSquadDialog?: InputModal;

  constructor() {
    super({ key: "WarbandManagementScene" });
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
      title: "Manage Warband",
      bodyColor: 0x4f5a65,
    });
    contentFrame.setDepth(-800);
    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Warband Summary",
      bodyColor: 0x006f7a,
    });
    actionsFrame.setDepth(-800);
    this.loadingText = this.add
      .text(layout.content.x + 16, layout.content.y + 120, "Loading warband hub...", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "20px",
        color: "#ffffff",
      })
      .setOrigin(0, 0);

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
      const state = deriveWarbandHubState(profile);
      this.units = state.units;
      this.squads = state.squads;
      this.diceVisualsById = this.buildDiceVisualMap(profile.ok ? profile.data.dice ?? [] : []);

      this.loadingText?.destroy();
      this.loadingText = undefined;

      this.buildUi();
      markDebugSceneReady(this, {
        units: this.units.length,
        squads: this.squads.length,
      });
    } catch (e) {
      this.loadingText?.setText(`Failed to load.\n${(e as Error).message}`);
      markDebugSceneReady(this, { state: "error" });
    }
  }

  private buildUi(): void {
    const layout = getPageLayout(this);
    const contentBody = resolveContentFrameBodyRect({
      width: layout.content.width,
      height: layout.content.height,
      titleHeight: FRAME_TITLE_HEIGHT,
      marginPx: FRAME_MARGIN,
    });
    const actionsBody = resolveContentFrameBodyRect({
      width: layout.buttons.width,
      height: layout.buttons.height,
      titleHeight: FRAME_TITLE_HEIGHT,
      marginPx: FRAME_MARGIN,
    });

    const contentBodyX = layout.content.x + contentBody.x;
    const contentBodyY = layout.content.y + contentBody.y;
    const actionsBodyX = layout.buttons.x + actionsBody.x;
    const actionsBodyY = layout.buttons.y + actionsBody.y;

    const innerContentX = contentBodyX + INNER_PANEL_PADDING;
    const innerContentY = contentBodyY + INNER_PANEL_PADDING;
    const innerContentWidth = Math.max(280, contentBody.width - INNER_PANEL_PADDING * 2);
    const innerContentHeight = Math.max(240, contentBody.height - INNER_PANEL_PADDING * 2);

    const squadColumnWidth = Math.max(
      SQUAD_COLUMN_MIN_WIDTH,
      Math.min(Math.floor(innerContentWidth * SQUAD_COLUMN_RATIO), innerContentWidth - 220),
    );
    const unitColumnWidth = Math.max(220, innerContentWidth - PANEL_COLUMN_GAP - squadColumnWidth);
    const leftX = innerContentX;
    const rightX = leftX + unitColumnWidth + PANEL_COLUMN_GAP;

    this.clearSummaryUi();

    this.unitPanel?.destroy();
    this.unitPanel = new UnitCardGrid({
      scene: this,
      x: leftX,
      y: innerContentY,
      width: unitColumnWidth,
      height: innerContentHeight,
      title: "ALL UNITS",
      units: this.units,
      diceVisualsById: this.diceVisualsById,
      getCardState: (unit) => this.getUnitCardState(unit),
      onUnitClick: (u) => this.scene.start("UnitDetailsScene", { unitId: u.id }),
    });

    this.squadPanel?.destroy();
    this.squadPanel = new SquadListPanel({
      scene: this,
      x: rightX,
      y: innerContentY,
      width: squadColumnWidth,
      height: innerContentHeight,
      title: "SQUADS",
      squads: this.squads,
      onSquadClick: (squad) => this.scene.start("SquadDetailsScene", { squadId: squad.id }),
    });

    this.buildNewSquadButton(rightX + squadColumnWidth - 20, innerContentY + 18);

    const activeSquad = this.squads.find((squad) => squad.is_active);
    const summaryLines = [
      `Units: ${this.units.length}`,
      `Squads: ${this.squads.length}`,
      `Active: ${activeSquad?.name ?? "None"}`,
    ];

    const summaryCardX = actionsBodyX + SUMMARY_PANEL_PADDING;
    const summaryCardY = actionsBodyY + SUMMARY_PANEL_PADDING;
    const summaryCardWidth = Math.max(120, actionsBody.width - SUMMARY_PANEL_PADDING * 2);
    const summaryCardHeight = 150;
    const summaryCard = this.add
      .rectangle(summaryCardX, summaryCardY, summaryCardWidth, summaryCardHeight, 0x0f2024, 0.56)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x8db8bc, 0.45);

    const summaryText = this.add
      .text(summaryCardX + 12, summaryCardY + 10, summaryLines.join("\n"), {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "19px",
        color: "#e7f4f5",
        lineSpacing: 10,
        wordWrap: { width: Math.max(120, summaryCardWidth - 24) },
      })
      .setOrigin(0, 0);
    this.summaryUiObjects.push(summaryCard, summaryText);
  }

  private buildNewSquadButton(centerX: number, centerY: number): void {
    const buttonSize = 28;
    const hitArea = this.add
      .rectangle(centerX, centerY, buttonSize, buttonSize, 0x11181c, 0.95)
      .setStrokeStyle(1, 0xd7c16f, 0.7)
      .setInteractive({ useHandCursor: true });
    const label = this.add
      .text(centerX, centerY - 1, "+", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "24px",
        color: "#f2e5b4",
        stroke: "#1a1a1a",
        strokeThickness: 2,
      })
      .setOrigin(0.5, 0.5);
    const tooltip = new Tooltip({
      scene: this,
      text: "Create new squad",
      x: centerX,
      y: centerY - buttonSize / 2 - 6,
      placement: "top",
      visible: false,
    }).setDepth(20);

    hitArea.on("pointerover", () => {
      hitArea.setFillStyle(0x1d262b, 0.98);
      tooltip.show();
    });
    hitArea.on("pointerout", () => {
      hitArea.setFillStyle(0x11181c, 0.95);
      tooltip.hide();
    });
    hitArea.on("pointerdown", () => {
      void this.createSquad();
    });

    this.summaryUiObjects.push(hitArea, label, tooltip);
  }

  private async createSquad(): Promise<void> {
    if (this.createSquadDialog) return;
    let enteredName = "New Squad";

    this.createSquadDialog = new InputModal({
      scene: this,
      title: "CREATE NEW SQUAD?",
      message: "Enter a squad name and confirm.",
      acceptLabel: "Create",
      rejectLabel: "Cancel",
      input: {
        initialValue: "New Squad",
        placeholder: "New Squad",
        maxLength: 24,
        allowedCharacterPattern: SQUAD_NAME_ALLOWED_CHARACTER_PATTERN,
      },
      onAcceptInput: (value) => {
        enteredName = value;
      },
      onReject: () => {
        this.createSquadDialog = undefined;
      },
      onAccept: async () => {
        this.createSquadDialog?.close();
        this.createSquadDialog = undefined;
        const name = normalizeNewSquadName(enteredName);
        if (!name) {
          this.showToast("Name must use letters, numbers, spaces, or [].-");
          return;
        }
        await this.executeCreateSquad(name);
      },
      width: 620,
      height: 320,
    });
  }

  private buildDiceVisualMap(dice: DiceRecord[]): Map<string, Pick<DiceRecord, "rarity" | "sides">> {
    const map = new Map<string, Pick<DiceRecord, "rarity" | "sides">>();
    for (const die of dice) {
      if (!die?.id) {
        continue;
      }
      map.set(String(die.id), {
        rarity: die.rarity,
        sides: die.sides,
      });
    }
    return map;
  }

  private getUnitCardState(unit: UnitRecord): { cornerColor: number; cornerAlpha: number } {
    const activeSquad = this.squads.find((squad) => squad.is_active);
    const inActiveSquad = Boolean(activeSquad?.unit_ids?.includes(unit.id));
    return {
      cornerColor: inActiveSquad ? 0xd7b54a : 0x111111,
      cornerAlpha: inActiveSquad ? 0.98 : 0.95,
    };
  }

  private async executeCreateSquad(name: string): Promise<void> {
    const res = await apiClient.createTeam(name, false);
    if (!res.ok) {
      this.showToast(`Create failed: ${res.error.message}`);
      return;
    }
    this.showToast("Squad created.", "#ccffcc");
    this.scene.start("SquadDetailsScene", { squadId: res.data.team_id });
  }

  private showToast(message: string, color = "#ffcccc"): void {
    this.toastText?.destroy();
    const layout = getPageLayout(this);
    this.toastText = this.add
      .text(layout.content.x + 16, layout.content.y + layout.content.height - 24, message, {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color,
      })
      .setOrigin(0, 0);
    this.time.delayedCall(2500, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }

  private clearSummaryUi(): void {
    for (const uiObject of this.summaryUiObjects) {
      uiObject.destroy();
    }
    this.summaryUiObjects = [];
  }
}
