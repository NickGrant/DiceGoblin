import Phaser from "phaser";
import { TEXT_OVERLAY_BODY, TEXT_OVERLAY_TITLE } from "../../const/Text";

export type BaseModalConfig = {
  scene: Phaser.Scene;
  title: string;
  message?: string;
  width?: number;
  height?: number;
};

export type ModalButtonLayout = {
  sideBySide: boolean;
  leftButtonX: number;
  rightButtonX: number;
  rowY: number;
  stackedTopY: number;
  stackedBottomY: number;
};

export default class BaseModal extends Phaser.GameObjects.Container {
  protected readonly sceneRef: Phaser.Scene;
  protected readonly widthPx: number;
  protected readonly heightPx: number;
  protected readonly leftPx: number;
  protected readonly topPx: number;

  protected readonly overlay: Phaser.GameObjects.Rectangle;
  protected readonly panel: Phaser.GameObjects.Rectangle;
  protected readonly titleText: Phaser.GameObjects.Text;
  protected readonly messageText: Phaser.GameObjects.Text;

  constructor(cfg: BaseModalConfig) {
    super(cfg.scene, 0, 0);
    this.sceneRef = cfg.scene;

    this.widthPx = cfg.width ?? 520;
    this.heightPx = cfg.height ?? 280;
    const centerX = cfg.scene.scale.width / 2;
    const centerY = cfg.scene.scale.height / 2;
    this.leftPx = centerX - this.widthPx / 2;
    this.topPx = centerY - this.heightPx / 2;

    this.overlay = cfg.scene.add
      .rectangle(0, 0, cfg.scene.scale.width, cfg.scene.scale.height, 0x000000, 0.55)
      .setOrigin(0, 0)
      .setInteractive();
    this.panel = cfg.scene.add
      .rectangle(this.leftPx, this.topPx, this.widthPx, this.heightPx, 0x1d1d1d, 0.98)
      .setOrigin(0, 0)
      .setStrokeStyle(2, 0xffffff, 0.25);

    this.titleText = cfg.scene.add.text(this.leftPx + 20, this.topPx + 18, cfg.title, {
      ...TEXT_OVERLAY_TITLE,
    }).setOrigin(0, 0);

    this.messageText = cfg.scene.add.text(this.leftPx + 20, this.topPx + 66, cfg.message ?? "", {
      ...TEXT_OVERLAY_BODY,
      wordWrap: { width: this.widthPx - 40 },
    }).setOrigin(0, 0);

    this.add([this.overlay, this.panel, this.titleText, this.messageText]);
    cfg.scene.add.existing(this);
    this.setDepth(1200);
  }

  protected getButtonLayout(buttonWidth = 280, buttonGap = 16): ModalButtonLayout {
    const sideBySide = this.widthPx >= buttonWidth * 2 + buttonGap + 40;
    const rowY = this.topPx + this.heightPx - 95;
    const stackedTopY = this.topPx + this.heightPx - 164;
    const stackedBottomY = this.topPx + this.heightPx - 86;
    const leftButtonX = sideBySide
      ? this.leftPx + 20
      : this.leftPx + Math.max(0, Math.floor((this.widthPx - buttonWidth) / 2));
    const rightButtonX = sideBySide
      ? this.leftPx + this.widthPx - buttonWidth - 20
      : leftButtonX;

    return {
      sideBySide,
      leftButtonX,
      rightButtonX,
      rowY,
      stackedTopY,
      stackedBottomY,
    };
  }

  close(): void {
    this.destroy();
  }
}
