import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import UnitCardGrid from "../components/UnitCardGrid";
import SquadListPanel from "../components/SquadListPanel";
import UnifiedButtonList from "../components/clickable-panel/UnifiedButtonList";
import { getDebugSceneConfig } from "../debug/debugScene";
import { getDebugProfileFixture } from "../debug/debugFixtures";
import { apiClient } from "../services/apiClient";
import type { TeamRecord, UnitRecord } from "../types/ApiResponse";
import { markDebugSceneReady } from "../debug/debugHooks";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import InputModal from "../components/feedback/InputModal";
import {
  computeWarbandColumns,
  deriveWarbandHubState,
  SQUAD_NAME_ALLOWED_CHARACTER_PATTERN,
  normalizeNewSquadName,
} from "./warbandManagementState";

const FRAME_TITLE_HEIGHT = 56;
const FRAME_MARGIN = 12;
const PANEL_COLUMN_GAP = 16;
const ACTION_PANEL_PADDING = 14;
const ACTION_CONTENT_GAP = 14;
const ACTION_BUTTON_WIDTH = 280;

export default class WarbandManagementScene extends Phaser.Scene {
  private loadingText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;
  private summaryUiObjects: Phaser.GameObjects.GameObject[] = [];

  private units: UnitRecord[] = [];
  private squads: TeamRecord[] = [];

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
      title: "Squad Actions",
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
    const contentBodyX = layout.content.x + FRAME_MARGIN;
    const contentBodyY = layout.content.y + FRAME_TITLE_HEIGHT + FRAME_MARGIN + 94;
    const contentBodyWidth = Math.max(280, layout.content.width - FRAME_MARGIN * 2);
    const contentBodyHeight = Math.max(240, layout.content.height - FRAME_TITLE_HEIGHT - FRAME_MARGIN * 2 - 94);

    const actionsBodyX = layout.buttons.x + FRAME_MARGIN;
    const actionsBodyY = layout.buttons.y + FRAME_TITLE_HEIGHT + FRAME_MARGIN;
    const actionsBodyWidth = Math.max(280, layout.buttons.width - FRAME_MARGIN * 2);
    const actionsBodyHeight = Math.max(220, layout.buttons.height - FRAME_TITLE_HEIGHT - FRAME_MARGIN * 2);

    const columns = computeWarbandColumns(contentBodyX, contentBodyWidth, PANEL_COLUMN_GAP);
    const leftX = columns.leftX;
    const rightX = columns.rightX;
    const colW = columns.columnWidth;

    this.clearSummaryUi();

    const headerText = this.add
      .text(layout.content.x + 24, layout.content.y + 88, "ROSTER AND SQUADS", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "20px",
        color: "#f0d38a",
      })
      .setOrigin(0, 0);
    const subheadText = this.add
      .text(layout.content.x + 24, layout.content.y + 118, "Review every unit on the left, keep squads readable on the right, and jump into details when a recruit needs equipment or promotion work.", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "19px",
        color: "#eef4f5",
        lineSpacing: 6,
        wordWrap: { width: layout.content.width - 48 },
      })
      .setOrigin(0, 0);
    this.summaryUiObjects.push(headerText, subheadText);

    this.unitPanel?.destroy();
    this.unitPanel = new UnitCardGrid({
      scene: this,
      x: leftX,
      y: contentBodyY,
      width: colW,
      height: contentBodyHeight,
      title: "ALL UNITS",
      units: this.units,
      onUnitClick: (u) => this.scene.start("UnitDetailsScene", { unitId: u.id }),
    });

    this.squadPanel?.destroy();
    this.squadPanel = new SquadListPanel({
      scene: this,
      x: rightX,
      y: contentBodyY,
      width: colW,
      height: contentBodyHeight,
      title: "SQUADS",
      squads: this.squads,
      onSquadClick: (squad) => this.scene.start("SquadDetailsScene", { squadId: squad.id }),
    });

    const activeSquad = this.squads.find((squad) => squad.is_active);
    const summaryLines = [
      "WARBAND SUMMARY",
      `Units: ${this.units.length}`,
      `Squads: ${this.squads.length}`,
      `Active: ${activeSquad?.name ?? "None"}`,
      "Tip: open a unit for dice and promotion work.",
    ];

    const summaryCardX = actionsBodyX + ACTION_PANEL_PADDING;
    const summaryCardY = actionsBodyY + ACTION_PANEL_PADDING;
    const summaryCardWidth = Math.max(120, actionsBodyWidth - ACTION_PANEL_PADDING * 2);
    const summaryCardHeight = Math.min(186, Math.max(116, Math.floor(actionsBodyHeight * 0.38)));
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

    const helperCardY = summaryCardY + summaryCardHeight + 12;
    const helperCardHeight = 88;
    const helperCard = this.add
      .rectangle(summaryCardX, helperCardY, summaryCardWidth, helperCardHeight, 0x0b191d, 0.66)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x8db8bc, 0.28);
    const helperText = this.add
      .text(summaryCardX + 12, helperCardY + 10, "Recommended flow:\n1. Review units\n2. Open squad\n3. Save changes", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "14px",
        color: "#dff0f2",
        lineSpacing: 4,
        wordWrap: { width: Math.max(120, summaryCardWidth - 24) },
      })
      .setOrigin(0, 0);
    this.summaryUiObjects.push(helperCard, helperText);

    const actionButtonY = helperCardY + helperCardHeight + ACTION_CONTENT_GAP;
    const actionButtonX =
      actionsBodyX + Math.max(0, Math.floor((actionsBodyWidth - ACTION_BUTTON_WIDTH) / 2));

    new UnifiedButtonList({
      scene: this,
      x: actionButtonX,
      y: actionButtonY,
      gapY: 10,
      buttons: [
        {
          label: "Shop",
          onClick: () => this.scene.start("ShopScene"),
        },
        {
          label: "New Squad",
          onClick: () => void this.createSquad(),
        },
      ],
    });
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

