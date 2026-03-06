import type Phaser from "phaser";
import ClickablePanel, { type ClickablePanelConfig } from "./clickable-panel/ClickablePanel";

const CORNER_WIDTH = 300;
const CORNER_HEIGHT = 218;
const ICON_SIZE = 72;
const ICON_X = 74;
const ICON_Y = 72;

export default class HomeButton extends ClickablePanel {
  constructor(scene: Phaser.Scene, cfg: ClickablePanelConfig) {
    super(scene, {
      ...cfg,
      targetSceneKey: "HomeScene",
      textureKey: "ux_corner_left",
      width: CORNER_WIDTH,
      height: CORNER_HEIGHT,
      deferOverlay: true,
    });
    this.addOverlay();
  }

  override addOverlay(): void {
    const icon = this.scene.add
      .image(ICON_X, ICON_Y, "icon_home")
      .setDisplaySize(ICON_SIZE, ICON_SIZE)
      .setOrigin(0.5, 0.5);
    this.add(icon);
  }
}
