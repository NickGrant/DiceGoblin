import type Phaser from "phaser";
import { getPageLayout, type LayoutRect } from "../layout/pageLayout";

type ZoneConfig = {
  rect: LayoutRect;
  title: string;
  color: number;
  depth?: number;
};

type DualZoneConfig = {
  leftTitle: string;
  rightTitle: string;
  leftColor?: number;
  rightColor?: number;
};

const TITLE_HEIGHT = 56;
const MARGIN = 12;
const ZONE_DEPTH = -850;

export function drawUxZone(scene: Phaser.Scene, cfg: ZoneConfig): Phaser.GameObjects.Container {
  const depth = cfg.depth ?? ZONE_DEPTH;
  const boxX = cfg.rect.x + MARGIN;
  const boxY = cfg.rect.y + TITLE_HEIGHT + MARGIN;
  const boxW = Math.max(0, cfg.rect.width - MARGIN * 2);
  const boxH = Math.max(0, cfg.rect.height - TITLE_HEIGHT - MARGIN * 2);

  const container = scene.add.container(0, 0).setDepth(depth).setScrollFactor(0);

  const titleBg = scene.add.image(cfg.rect.x, cfg.rect.y, "texture_red").setOrigin(0, 0);
  titleBg.setDisplaySize(cfg.rect.width, TITLE_HEIGHT);

  const titleText = scene.add.text(cfg.rect.x + MARGIN, cfg.rect.y + 12, cfg.title.toUpperCase(), {
    fontFamily: "Arial",
    fontSize: "28px",
    color: "#ffffff",
    stroke: "#141414",
    strokeThickness: 3,
  });

  const body = scene.add.rectangle(boxX, boxY, boxW, boxH, cfg.color, 1).setOrigin(0, 0);
  container.add([titleBg, body, titleText]);
  return container;
}

export function drawUxDualZones(scene: Phaser.Scene, cfg: DualZoneConfig): void {
  const layout = getPageLayout(scene);
  drawUxZone(scene, {
    rect: layout.content,
    title: cfg.leftTitle,
    color: cfg.leftColor ?? 0x00f6ff,
  });
  drawUxZone(scene, {
    rect: layout.buttons,
    title: cfg.rightTitle,
    color: cfg.rightColor ?? 0x00ff72,
  });
}
