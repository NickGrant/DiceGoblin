import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import UnitCardGrid from "../components/UnitCardGrid";
import SquadListPanel from "../components/SquadListPanel";
import ActionButtonList from "../components/clickable-panel/ActionButtonList";
import { apiClient } from "../services/apiClient";
import type { TeamRecord, UnitRecord } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import {
  computeWarbandColumns,
  deriveWarbandHubState,
  normalizeNewSquadName,
} from "./warbandManagementState";

export default class WarbandManagementScene extends Phaser.Scene {
  private loadingText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;

  private units: UnitRecord[] = [];
  private squads: TeamRecord[] = [];

  private unitPanel?: UnitCardGrid;
  private squadPanel?: SquadListPanel;

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
      const profile = await apiClient.getProfile({ force: true });
      const state = deriveWarbandHubState(profile);
      this.units = state.units;
      this.squads = state.squads;

      this.loadingText?.destroy();
      this.loadingText = undefined;

      this.buildUi();
    } catch (e) {
      this.loadingText?.setText(`Failed to load.\n${(e as Error).message}`);
    }
  }

  private buildUi(): void {
    const layout = getPageLayout(this);
    const columns = computeWarbandColumns(layout.content.x, layout.content.width);
    const leftX = columns.leftX;
    const rightX = columns.rightX;
    const colW = columns.columnWidth;

    this.add.text(leftX, layout.content.y - 34, "UNITS", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "20px",
      color: "#ffffff",
    });
    this.add.text(rightX, layout.content.y - 34, "SQUADS", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "20px",
      color: "#ffffff",
    });

    this.unitPanel?.destroy();
    this.unitPanel = new UnitCardGrid({
      scene: this,
      x: leftX,
      y: layout.content.y,
      width: colW,
      height: 460,
      title: "ALL UNITS",
      units: this.units,
      onUnitClick: (u) => this.scene.start("UnitDetailsScene", { unitId: u.id }),
      maxVisibleCards: 6,
    });

    this.squadPanel?.destroy();
    this.squadPanel = new SquadListPanel({
      scene: this,
      x: rightX,
      y: layout.content.y,
      title: "",
      squads: this.squads,
      onSquadClick: (squad) => this.scene.start("SquadDetailsScene", { squadId: squad.id }),
    });

    new ActionButtonList({
      scene: this,
      x: rightX,
      y: layout.content.y + 470,
      gapY: 5,
      buttons: [
        {
          label: "New Squad",
          onClick: () => void this.createSquad(),
        },
      ],
    });

    this.add.text(leftX, layout.content.y + 490, "Unit Details handles stats/xp, promotion, and dice flow.", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color: "#bbbbbb",
    });
  }

  private async createSquad(): Promise<void> {
    const name = normalizeNewSquadName(window.prompt("New squad name:", "New Squad"));
    if (!name) return;
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
}






