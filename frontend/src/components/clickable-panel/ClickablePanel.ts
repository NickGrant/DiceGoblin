import Phaser from "phaser";

export type ClickablePanelConfig = {
  x: number;
  y: number;
  width?: number;
  height?: number;
  textureKey?: string;          
  targetSceneKey?: string;      
  dataToPanel?: Record<string, unknown> // optional panel data
  dataToPass?: Record<string, unknown>; // optional scene data
  clickHandler?: any;
};

export default class ClickablePanel extends Phaser.GameObjects.Container {
  private bg: Phaser.GameObjects.Image;
  targetSceneKey = '';
  textureKey = '';
  dataToPass;

  constructor(scene: Phaser.Scene, cfg: ClickablePanelConfig) {
    super(scene, cfg.x, cfg.y);

    if (cfg.textureKey) {
        this.textureKey = cfg.textureKey
    }

    if (cfg.targetSceneKey) {
        this.targetSceneKey = cfg.targetSceneKey;
    }

    this.dataToPass = cfg.dataToPass ?? {};

    // Background
    this.bg = scene.add.image(0, 0, this.textureKey).setOrigin(0.5, 0.5);
    this.bg.setDisplaySize(cfg.width ?? 0, cfg.height ?? 0);

    // Make entire panel clickable by defining an explicit hit area
    this.bg.setInteractive({ useHandCursor: true });

    // Basic UX states (optional but helpful)
    this.bg.on("pointerover", () => this.bg.setAlpha(0.90));
    this.bg.on("pointerout", () => this.bg.setAlpha(1));
    this.bg.on("pointerdown", () => this.bg.setAlpha(0.8));
    this.bg.on("pointerup", () => {
      this.bg.setAlpha(0.90);
      this.handleClick(scene);
    });

    this.add(this.bg);
    this.addOverlay();

    scene.add.existing(this);
  }

  /** Overload this when extending */
  addOverlay() {}

  /** Overload if needed */
  handleClick(scene: Phaser.Scene) {
    scene.scene.start(this.targetSceneKey, this.dataToPass)
  }

  updateImage(textureKey: string) {
    this.textureKey = textureKey;
    this.bg.setTexture(textureKey);
  }
}
