import Phaser from "phaser";
import SectionTitleBar from "./SectionTitleBar";
import { resolveContentFrameBodyRect } from "./contentAreaMath";

export type ContentAreaFrameConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  width: number;
  height: number;
  title: string;
  marginPx?: number;
  titleHeight?: number;
  bodyColor?: number;
  bodyImageKey?: string;
  titleBackgroundTextureKey?: string;
  useImageEdgeToEdge?: boolean;
};

const DEFAULT_TITLE_HEIGHT = 56;
const DEFAULT_BODY_COLOR = 0x202020;

export default class ContentAreaFrame extends Phaser.GameObjects.Container {
  private readonly titleBar: SectionTitleBar;
  private readonly bodyObject: Phaser.GameObjects.Rectangle | Phaser.GameObjects.Image;

  constructor(cfg: ContentAreaFrameConfig) {
    super(cfg.scene, cfg.x, cfg.y);

    const titleHeight = cfg.titleHeight ?? DEFAULT_TITLE_HEIGHT;
    const bodyRect = resolveContentFrameBodyRect({
      width: cfg.width,
      height: cfg.height,
      titleHeight,
      marginPx: cfg.marginPx,
      bodyImageKey: cfg.bodyImageKey,
      useImageEdgeToEdge: cfg.useImageEdgeToEdge,
    });

    this.titleBar = new SectionTitleBar({
      scene: cfg.scene,
      x: 0,
      y: 0,
      width: cfg.width,
      height: titleHeight,
      title: cfg.title,
      backgroundTextureKey: cfg.titleBackgroundTextureKey,
    });

    if (cfg.bodyImageKey && cfg.scene.textures.exists(cfg.bodyImageKey)) {
      this.bodyObject = cfg.scene.add.image(bodyRect.x, bodyRect.y, cfg.bodyImageKey).setOrigin(0, 0);
      this.bodyObject.setDisplaySize(bodyRect.width, bodyRect.height);
    } else {
      this.bodyObject = cfg.scene.add
        .rectangle(bodyRect.x, bodyRect.y, bodyRect.width, bodyRect.height, cfg.bodyColor ?? DEFAULT_BODY_COLOR, 1)
        .setOrigin(0, 0);
    }

    this.add([this.titleBar, this.bodyObject]);
    cfg.scene.add.existing(this);
  }
}
