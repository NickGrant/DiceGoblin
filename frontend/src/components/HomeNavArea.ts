import type Phaser from "phaser";
import type { LayoutRect } from "../layout/pageLayout";
import HomeNavigationPanel from "./navigation/HomeNavigationPanel";

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
    new HomeNavigationPanel({
      scene: cfg.scene,
      areaRect: cfg.area,
      title: cfg.title,
      targetSceneKey: cfg.targetSceneKey,
      bodyImageKey: cfg.bodyImageKey,
      bodyColor: cfg.bodyColor,
    });
  }
}
