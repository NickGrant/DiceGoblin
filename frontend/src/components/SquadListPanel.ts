import Phaser from "phaser";
import type { TeamRecord } from "../types/ApiResponse";
import UnifiedButtonList from "./clickable-panel/UnifiedButtonList";

type SquadListPanelConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  width: number;
  height: number;
  title?: string;
  squads: TeamRecord[];
  onSquadClick: (squad: TeamRecord) => void;
  maxVisibleSquads?: number;
  buttonGapY?: number;
};

export default class SquadListPanel extends Phaser.GameObjects.Container {
  private static readonly BUTTON_WIDTH = 280;
  private readonly cfg: SquadListPanelConfig;
  private readonly hasTitle: boolean;
  private readonly panelWidth: number;
  private readonly panelHeight: number;
  private readonly buttonGapY: number;
  private readonly pageSize: number;
  private pageIndex = 0;
  private buttonList?: UnifiedButtonList;
  private prevPageText?: Phaser.GameObjects.Text;
  private nextPageText?: Phaser.GameObjects.Text;
  private pageLabelText?: Phaser.GameObjects.Text;

  constructor(cfg: SquadListPanelConfig) {
    super(cfg.scene, cfg.x, cfg.y);
    this.cfg = cfg;
    this.hasTitle = (cfg.title ?? "").trim().length > 0;
    this.panelWidth = cfg.width;
    this.panelHeight = cfg.height;
    this.buttonGapY = Math.max(0, cfg.buttonGapY ?? 8);

    if (cfg.maxVisibleSquads && cfg.maxVisibleSquads > 0) {
      this.pageSize = Math.max(1, cfg.maxVisibleSquads);
    } else {
      const titleOffset = this.hasTitle ? 24 : 0;
      const pagerTopOffset = 30;
      const availableHeight = Math.max(64, this.panelHeight - titleOffset - pagerTopOffset);
      const rowHeight = 64 + this.buttonGapY;
      this.pageSize = Math.max(1, Math.floor((availableHeight + this.buttonGapY) / rowHeight));
    }

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

    const pagerY = this.panelHeight - 22;
    this.prevPageText = cfg.scene
      .add.text(0, pagerY, "< Prev", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#d6d6d6",
      })
      .setOrigin(0, 0)
      .setInteractive({ useHandCursor: true });

    this.pageLabelText = cfg.scene
      .add.text(this.panelWidth / 2, pagerY, "Page 1/1", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#cfcfcf",
      })
      .setOrigin(0.5, 0);

    this.nextPageText = cfg.scene
      .add.text(this.panelWidth, pagerY, "Next >", {
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

    const titleOffset = this.hasTitle ? 24 : 0;
    const buttonListY = this.cfg.y + titleOffset;
    const buttonListX = this.cfg.x + Math.max(0, Math.floor((this.panelWidth - SquadListPanel.BUTTON_WIDTH) / 2));

    this.buttonList = new UnifiedButtonList({
      scene: this.cfg.scene,
      x: buttonListX,
      y: buttonListY,
      gapY: this.buttonGapY,
      defaultVariant: "metal",
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

