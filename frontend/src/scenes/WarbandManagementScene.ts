import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import UnitListPanel from "../components/UnitListPanel";
import SquadListPanel from "../components/SquadListPanel";
import ActionButtonList from "../components/clickable-panel/ActionButtonList";
import { apiClient } from "../services/apiClient";
import { adaptUnitRecords } from "../adapters/profileViewModels";
import type { TeamRecord, UnitRecord } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";

export default class WarbandManagementScene extends Phaser.Scene {
  private loadingText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;

  private units: UnitRecord[] = [];
  private squads: TeamRecord[] = [];

  private unitPanel?: UnitListPanel;
  private squadPanel?: SquadListPanel;

  constructor() {
    super({ key: "WarbandManagementScene" });
  }

  create(): void {
    new BackgroundImage(this, "background_workbench");
    new HudPanel(this);
    const layout = getPageLayout(this);
    new HomeButton(this, {
      x: layout.homeIcon.x,
      y: layout.homeIcon.y,
    });

    this.loadingText = this.add
      .text(layout.content.x + 16, layout.content.y + 120, "Loading warband hub...", {
        fontFamily: "Arial",
        fontSize: "18px",
        color: "#ffffff",
      })
      .setOrigin(0, 0);

    void this.loadData();
  }

  private async loadData(): Promise<void> {
    try {
      const profile = await apiClient.getProfile({ force: true });
      if (!profile.ok) throw new Error(profile.error.message);

      this.units = adaptUnitRecords(profile.data.units ?? []);
      this.squads = (profile.data.squads ?? []) as TeamRecord[];

      this.loadingText?.destroy();
      this.loadingText = undefined;

      this.buildUi();
    } catch (e) {
      this.loadingText?.setText(`Failed to load.\n${(e as Error).message}`);
    }
  }

  private buildUi(): void {
    const layout = getPageLayout(this);
    const splitGap = 24;
    const colW = Math.floor((layout.content.width - splitGap) / 2);
    const leftX = layout.content.x;
    const rightX = leftX + colW + splitGap;

    this.add.text(leftX, layout.content.y - 34, "UNITS", {
      fontFamily: "Arial",
      fontSize: "18px",
      color: "#ffffff",
    });
    this.add.text(rightX, layout.content.y - 34, "SQUADS", {
      fontFamily: "Arial",
      fontSize: "18px",
      color: "#ffffff",
    });

    this.unitPanel?.destroy();
    this.unitPanel = new UnitListPanel({
      scene: this,
      x: leftX,
      y: layout.content.y,
      width: colW,
      height: 460,
      title: "ALL UNITS",
      units: this.units,
      onUnitClick: (u) => this.scene.start("UnitDetailsScene", { unitId: u.id }),
      maxVisibleRows: 12,
    });

    this.squadPanel?.destroy();
    this.squadPanel = new SquadListPanel({
      scene: this,
      x: rightX,
      y: layout.content.y,
      title: "CURRENT SQUADS",
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

    this.add.text(leftX, layout.content.y + 470, "Select a unit to open Unit Details.", {
      fontFamily: "Arial",
      fontSize: "12px",
      color: "#dddddd",
    });
    this.add.text(leftX, layout.content.y + 490, "Unit Details handles stats/xp, promotion, and dice flow.", {
      fontFamily: "Arial",
      fontSize: "11px",
      color: "#bbbbbb",
    });
  }

  private async createSquad(): Promise<void> {
    const name = window.prompt("New squad name:", "New Squad")?.trim();
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
        fontFamily: "Arial",
        fontSize: "12px",
        color,
      })
      .setOrigin(0, 0);
    this.time.delayedCall(2500, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }
}
