import Phaser from "phaser";
import type { TeamRecord } from "../types/ApiResponse";
import MetalActionButtonList from "./clickable-panel/MetalActionButtonList";

type SquadListPanelConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  title?: string;
  squads: TeamRecord[];
  onSquadClick: (squad: TeamRecord) => void;
  maxVisibleSquads?: number;
};

export default class SquadListPanel extends Phaser.GameObjects.Container {
  private readonly cfg: SquadListPanelConfig;
  private readonly hasTitle: boolean;
  private readonly pageSize: number;
  private pageIndex = 0;
  private buttonList?: MetalActionButtonList;
  private prevPageText?: Phaser.GameObjects.Text;
  private nextPageText?: Phaser.GameObjects.Text;
  private pageLabelText?: Phaser.GameObjects.Text;

  constructor(cfg: SquadListPanelConfig) {
    super(cfg.scene, cfg.x, cfg.y);
    this.cfg = cfg;
    this.hasTitle = (cfg.title ?? "").trim().length > 0;
    this.pageSize = Math.max(1, cfg.maxVisibleSquads ?? 5);

    if (this.hasTitle) {
      const title = cfg.scene
        .add.text(0, 0, cfg.title ?? "", {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "16px",
          color: "#ffffff",
        })
        .setOrigin(0, 0);
      this.add(title);
    }

    this.prevPageText = cfg.scene
      .add.text(0, 410, "< Prev", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#d6d6d6",
      })
      .setOrigin(0, 0)
      .setInteractive({ useHandCursor: true });

    this.pageLabelText = cfg.scene
      .add.text(150, 410, "Page 1/1", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#cfcfcf",
      })
      .setOrigin(0.5, 0);

    this.nextPageText = cfg.scene
      .add.text(300, 410, "Next >", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#d6d6d6",
      })
      .setOrigin(1, 0)
      .setInteractive({ useHandCursor: true });

    this.prevPageText.on("pointerdown", () => this.setPage(this.pageIndex - 1));
    this.nextPageText.on("pointerdown", () => this.setPage(this.pageIndex + 1));

    this.add([this.prevPageText, this.pageLabelText, this.nextPageText]);
    this.renderPage();

    cfg.scene.add.existing(this);
  }

  public setSquads(squads: TeamRecord[]): this {
    this.cfg.squads = squads;
    this.pageIndex = 0;
    this.renderPage();
    return this;
  }

  private setPage(pageIndex: number): void {
    const totalPages = Math.max(1, Math.ceil(this.cfg.squads.length / this.pageSize));
    this.pageIndex = Phaser.Math.Clamp(pageIndex, 0, totalPages - 1);
    this.renderPage();
  }

  private renderPage(): void {
    this.buttonList?.destroy();

    const totalPages = Math.max(1, Math.ceil(this.cfg.squads.length / this.pageSize));
    const start = this.pageIndex * this.pageSize;
    const visibleSquads = this.cfg.squads.slice(start, start + this.pageSize);

    this.buttonList = new MetalActionButtonList({
      scene: this.cfg.scene,
      x: this.cfg.x,
      y: this.cfg.y + (this.hasTitle ? 24 : 0),
      gapY: 5,
      buttons: visibleSquads.map((squad) => ({
        label: squad.is_active ? `${squad.name} [ACTIVE]` : squad.name,
        onClick: () => this.cfg.onSquadClick(squad),
      })),
    });

    const canPage = this.cfg.squads.length > this.pageSize;
    const prevEnabled = canPage && this.pageIndex > 0;
    const nextEnabled = canPage && this.pageIndex < totalPages - 1;

    this.prevPageText?.setAlpha(prevEnabled ? 1 : 0.35);
    this.nextPageText?.setAlpha(nextEnabled ? 1 : 0.35);
    this.pageLabelText?.setAlpha(canPage ? 1 : 0.55);
    this.pageLabelText?.setText(`Page ${this.pageIndex + 1}/${totalPages}`);
  }
}

