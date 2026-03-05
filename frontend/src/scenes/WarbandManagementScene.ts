import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import UnitListPanel from "../components/UnitListPanel";
import ActionButtonList from "../components/clickable-panel/ActionButtonList";
import { apiClient } from "../services/apiClient";
import { adaptUnitRecords } from "../adapters/profileViewModels";
import type { TeamRecord, UnitRecord } from "../types/ApiResponse";
import { getPageLayout } from "../layout/pageLayout";

type SquadListRow = UnitRecord & { _squadId: string };

export default class WarbandManagementScene extends Phaser.Scene {
  private loadingText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;

  private units: UnitRecord[] = [];
  private squads: TeamRecord[] = [];
  private selectedSquadId: string | null = null;

  private unitPanel?: UnitListPanel;
  private squadPanel?: UnitListPanel;

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
      if (!this.selectedSquadId) {
        this.selectedSquadId = this.squads.find((s) => s.is_active)?.id ?? this.squads[0]?.id ?? null;
      }

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

    const squadRows: SquadListRow[] = this.squads.map((s) => ({
      id: s.id,
      name: s.name,
      level: 0,
      _squadId: s.id,
    }));

    this.squadPanel?.destroy();
    this.squadPanel = new UnitListPanel({
      scene: this,
      x: rightX,
      y: layout.content.y,
      width: colW,
      height: 460,
      title: "CURRENT SQUADS",
      units: squadRows,
      maxVisibleRows: 12,
      onUnitClick: (row) => {
        this.selectedSquadId = (row as SquadListRow)._squadId;
        this.squadPanel?.refreshRowStates();
      },
      getRowState: (row) => {
        const squadId = (row as SquadListRow)._squadId;
        const squad = this.squads.find((s) => s.id === squadId);
        return {
          highlighted: squadId === this.selectedSquadId,
          badgeText: squad?.is_active ? "ACTIVE" : squadId === this.selectedSquadId ? "SELECTED" : null,
        };
      },
    });

    new ActionButtonList({
      scene: this,
      x: rightX,
      y: layout.content.y + 470,
      gapY: 5,
      buttons: [
        {
          label: "Open Squad",
          onClick: () => this.openSelectedSquad(),
        },
        {
          label: "Add Squad",
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
    this.selectedSquadId = res.data.team_id;
    await this.loadData();
  }

  private openSelectedSquad(): void {
    if (!this.selectedSquadId) {
      this.showToast("Select a squad first.");
      return;
    }
    this.scene.start("SquadDetailsScene", { squadId: this.selectedSquadId });
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
