import Phaser from "phaser";

export type SectionTitleBarConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  width: number;
  height?: number;
  title: string;
  backgroundTextureKey?: string;
  textStyle?: Phaser.Types.GameObjects.Text.TextStyle;
};

const DEFAULT_HEIGHT = 56;
const FALLBACK_COLOR = 0x8f2929;

export function resolveSectionTitleTexture(scene: Phaser.Scene, textureKey: string): string | null {
  return scene.textures.exists(textureKey) ? textureKey : null;
}

export default class SectionTitleBar extends Phaser.GameObjects.Container {
  private readonly bg: Phaser.GameObjects.Image | Phaser.GameObjects.Rectangle;
  private readonly label: Phaser.GameObjects.Text;

  constructor(cfg: SectionTitleBarConfig) {
    super(cfg.scene, cfg.x, cfg.y);

    const height = cfg.height ?? DEFAULT_HEIGHT;
    const textureKey = resolveSectionTitleTexture(cfg.scene, cfg.backgroundTextureKey ?? "texture_red");
    if (textureKey) {
      this.bg = cfg.scene.add.image(0, 0, textureKey).setOrigin(0, 0);
      this.bg.setDisplaySize(cfg.width, height);
    } else {
      this.bg = cfg.scene.add.rectangle(0, 0, cfg.width, height, FALLBACK_COLOR, 1).setOrigin(0, 0);
    }

    this.label = cfg.scene.add.text(12, 12, cfg.title.toUpperCase(), {
      fontFamily: "Arial",
      fontSize: "30px",
      color: "#ffffff",
      stroke: "#1a1a1a",
      strokeThickness: 3,
      ...(cfg.textStyle ?? {}),
    });

    this.add([this.bg, this.label]);
    cfg.scene.add.existing(this);
  }
}
