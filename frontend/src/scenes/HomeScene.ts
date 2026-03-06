import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import { getPageLayout, type LayoutRect } from "../layout/pageLayout";

const AREA_MARGIN = 12;
const AREA_GAP = 12;
const TITLE_HEIGHT = 56;

export default class HomeScene extends Phaser.Scene {
  constructor() {
    super({ key: "HomeScene" });
  }

  create(): void {
    new BackgroundImage(this);
    const layout = getPageLayout(this);

    new HomeButton(this, {
      x: layout.homeIcon.x,
      y: layout.homeIcon.y,
    });

    new HudPanel(this);

    const leftArea: LayoutRect = {
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
    };

    const rightTopHeight = Math.floor((layout.buttons.height - AREA_GAP) / 2);
    const rightTopArea: LayoutRect = {
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: rightTopHeight,
    };

    const rightBottomArea: LayoutRect = {
      x: layout.buttons.x,
      y: layout.buttons.y + rightTopHeight + AREA_GAP,
      width: layout.buttons.width,
      height: Math.max(0, layout.buttons.height - rightTopHeight - AREA_GAP),
    };

    this.renderNavArea(leftArea, "Start a Run", 0x0600ff, "RegionSelectScene");
    this.renderNavArea(rightTopArea, "Manage Warband", 0x00f6ff, "WarbandManagementScene");
    this.renderNavArea(rightBottomArea, "Manage Inventory", 0x00ff72, "DiceInventoryScene");
  }

  private renderNavArea(area: LayoutRect, title: string, bodyColor: number, targetSceneKey: string): void {
    const zone = this.add.zone(area.x + area.width / 2, area.y + area.height / 2, area.width, area.height)
      .setInteractive({ useHandCursor: true });
    zone.on("pointerdown", () => this.scene.start(targetSceneKey));

    const titleBg = this.add.image(area.x, area.y, "texture_red").setOrigin(0, 0);
    titleBg.setDisplaySize(area.width, TITLE_HEIGHT);
    titleBg.setInteractive({ useHandCursor: true });
    titleBg.on("pointerdown", () => this.scene.start(targetSceneKey));

    this.add.text(area.x + AREA_MARGIN, area.y + 12, title.toUpperCase(), {
      fontFamily: "Arial",
      fontSize: "30px",
      color: "#ffffff",
      stroke: "#1a1a1a",
      strokeThickness: 3,
    });

    const bodyX = area.x + AREA_MARGIN;
    const bodyY = area.y + TITLE_HEIGHT + AREA_MARGIN;
    const bodyW = Math.max(0, area.width - AREA_MARGIN * 2);
    const bodyH = Math.max(0, area.height - TITLE_HEIGHT - AREA_MARGIN * 2);

    const body = this.add.rectangle(bodyX, bodyY, bodyW, bodyH, bodyColor, 1).setOrigin(0, 0);
    body.setInteractive({ useHandCursor: true });
    body.on("pointerdown", () => this.scene.start(targetSceneKey));

    this.add.text(bodyX + 12, bodyY + 12, `Open ${title}`, {
      fontFamily: "Arial",
      fontSize: "18px",
      color: "#0c0c0c",
    });

    this.children.bringToTop(zone);
  }
}
