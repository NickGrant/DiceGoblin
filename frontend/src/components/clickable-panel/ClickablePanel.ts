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
  clickHandler?: (() => void) | null;
  enabled?: boolean;
  deferOverlay?: boolean;
};

export default class ClickablePanel extends Phaser.GameObjects.Container {
  protected bg: Phaser.GameObjects.Image;
  targetSceneKey = '';
  textureKey = '';
  dataToPass;
  protected clickHandler: (() => void) | null;
  protected enabled = true;

  constructor(scene: Phaser.Scene, cfg: ClickablePanelConfig) {
    super(scene, cfg.x, cfg.y);

    if (cfg.textureKey) {
        this.textureKey = cfg.textureKey
    }

    if (cfg.targetSceneKey) {
        this.targetSceneKey = cfg.targetSceneKey;
    }

    this.dataToPass = cfg.dataToPass ?? {};
    this.clickHandler = cfg.clickHandler ?? null;
    this.enabled = cfg.enabled ?? true;

    // Background
    this.bg = scene.add.image(0, 0, this.textureKey).setOrigin(0, 0);
    this.bg.setDisplaySize(cfg.width ?? 0, cfg.height ?? 0);

    // Make entire panel clickable by defining an explicit hit area
    this.bg.setInteractive({ useHandCursor: true });

    // Basic UX states (optional but helpful)
    this.bg.on("pointerover", () => {
      if (!this.enabled) return;
      this.bg.setAlpha(0.90);
    });
    this.bg.on("pointerout", () => this.bg.setAlpha(this.enabled ? 1 : 0.55));
    this.bg.on("pointerdown", () => {
      if (!this.enabled) return;
      this.bg.setAlpha(0.8);
    });
    this.bg.on("pointerup", () => {
      if (!this.enabled) return;
      this.bg.setAlpha(0.90);
      this.handleClick(scene);
    });

    this.add(this.bg);
    if (!cfg.deferOverlay) {
      this.addOverlay();
    }

    scene.add.existing(this);
    this.setEnabled(this.enabled);
  }

  /** Overload this when extending */
  addOverlay() {}

  /** Overload if needed */
  handleClick(scene: Phaser.Scene) {
    if (this.clickHandler) {
      this.clickHandler();
      return;
    }
    scene.scene.start(this.targetSceneKey, this.dataToPass)
  }

  updateImage(textureKey: string) {
    this.textureKey = textureKey;
    this.bg.setTexture(textureKey);
  }

  setEnabled(enabled: boolean): this {
    this.enabled = enabled;
    this.setAlpha(enabled ? 1 : 0.55);
    if (enabled) {
      this.bg.setInteractive({ useHandCursor: true });
    } else {
      this.bg.disableInteractive();
      this.bg.setAlpha(0.55);
    }
    return this;
  }

  getEnabled(): boolean {
    return this.enabled;
  }
}

