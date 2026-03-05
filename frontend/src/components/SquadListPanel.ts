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
};

export default class SquadListPanel extends Phaser.GameObjects.Container {
  private buttonList?: MetalActionButtonList;

  constructor(cfg: SquadListPanelConfig) {
    super(cfg.scene, cfg.x, cfg.y);

    const title = cfg.scene.add.text(0, 0, cfg.title ?? "CURRENT SQUADS", {
      fontFamily: "Arial",
      fontSize: "14px",
      color: "#ffffff",
    }).setOrigin(0, 0);
    this.add(title);

    this.buttonList = new MetalActionButtonList({
      scene: cfg.scene,
      x: cfg.x,
      y: cfg.y + 24,
      gapY: 5,
      buttons: cfg.squads.map((squad) => ({
        label: squad.is_active ? `${squad.name} [ACTIVE]` : squad.name,
        onClick: () => cfg.onSquadClick(squad),
      })),
    });

    cfg.scene.add.existing(this);
  }
}
