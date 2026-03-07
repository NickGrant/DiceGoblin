import type Phaser from "phaser";
import HomeButton from "../HomeButton";

export type HomeCornerButtonConfig = {
  scene: Phaser.Scene;
  x?: number;
  y?: number;
  targetSceneKey?: string;
};

export default class HomeCornerButton extends HomeButton {
  constructor(cfg: HomeCornerButtonConfig) {
    super(cfg.scene, {
      x: cfg.x ?? 0,
      y: cfg.y ?? 0,
      targetSceneKey: cfg.targetSceneKey ?? "HomeScene",
    });
  }
}
