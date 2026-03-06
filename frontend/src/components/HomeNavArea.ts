import type Phaser from "phaser";
import type { LayoutRect } from "../layout/pageLayout";

const AREA_MARGIN = 12;
const TITLE_HEIGHT = 56;

type HomeNavAreaConfig = {
  scene: Phaser.Scene;
  area: LayoutRect;
  title: string;
  bodyColor: number;
  targetSceneKey: string;
  bodyImageKey?: string;
};

export default class HomeNavArea {
  constructor(cfg: HomeNavAreaConfig) {
    const { scene, area, title, bodyColor, targetSceneKey, bodyImageKey } = cfg;

    const zone = scene.add.zone(area.x + area.width / 2, area.y + area.height / 2, area.width, area.height)
      .setInteractive({ useHandCursor: true });
    zone.on("pointerdown", () => scene.scene.start(targetSceneKey));

    const titleBg = scene.add.image(area.x, area.y, "texture_red").setOrigin(0, 0);
    titleBg.setDisplaySize(area.width, TITLE_HEIGHT);
    titleBg.setInteractive({ useHandCursor: true });
    titleBg.on("pointerdown", () => scene.scene.start(targetSceneKey));

    scene.add.text(area.x + AREA_MARGIN, area.y + 12, title.toUpperCase(), {
      fontFamily: "Arial",
      fontSize: "30px",
      color: "#ffffff",
      stroke: "#1a1a1a",
      strokeThickness: 3,
    });

    if (bodyImageKey) {
      const bodyX = area.x;
      const bodyY = area.y + TITLE_HEIGHT;
      const bodyW = area.width;
      const bodyH = Math.max(0, area.height - TITLE_HEIGHT);
      const bodyImage = scene.add.image(bodyX, bodyY, bodyImageKey).setOrigin(0, 0);
      bodyImage.setDisplaySize(bodyW, bodyH);
      bodyImage.setInteractive({ useHandCursor: true });
      bodyImage.on("pointerdown", () => scene.scene.start(targetSceneKey));
    } else {
      const bodyX = area.x + AREA_MARGIN;
      const bodyY = area.y + TITLE_HEIGHT + AREA_MARGIN;
      const bodyW = Math.max(0, area.width - AREA_MARGIN * 2);
      const bodyH = Math.max(0, area.height - TITLE_HEIGHT - AREA_MARGIN * 2);
      const body = scene.add.rectangle(bodyX, bodyY, bodyW, bodyH, bodyColor, 1).setOrigin(0, 0);
      body.setInteractive({ useHandCursor: true });
      body.on("pointerdown", () => scene.scene.start(targetSceneKey));

      scene.add.text(bodyX + 12, bodyY + 12, `Open ${title}`, {
        fontFamily: "Arial",
        fontSize: "18px",
        color: "#0c0c0c",
      });
    }

    scene.children.bringToTop(zone);
  }
}
