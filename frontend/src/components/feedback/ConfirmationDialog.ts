import Phaser from "phaser";
import AcceptButton from "../clickable-panel/AcceptButton";
import RejectButton from "../clickable-panel/RejectButton";
import { TEXT_OVERLAY_BODY, TEXT_OVERLAY_TITLE } from "../../const/Text";

export type ConfirmationDialogConfig = {
  scene: Phaser.Scene;
  title: string;
  message: string;
  acceptLabel?: string;
  rejectLabel?: string;
  onAccept: () => void | Promise<void>;
  onReject: () => void;
  width?: number;
  height?: number;
};

export default class ConfirmationDialog extends Phaser.GameObjects.Container {
  private readonly overlay: Phaser.GameObjects.Rectangle;
  private readonly panel: Phaser.GameObjects.Rectangle;
  private readonly titleText: Phaser.GameObjects.Text;
  private readonly messageText: Phaser.GameObjects.Text;
  private readonly acceptButton: AcceptButton;
  private readonly rejectButton: RejectButton;

  constructor(cfg: ConfirmationDialogConfig) {
    super(cfg.scene, 0, 0);

    const width = cfg.width ?? 520;
    const height = cfg.height ?? 280;
    const centerX = cfg.scene.scale.width / 2;
    const centerY = cfg.scene.scale.height / 2;
    const left = centerX - width / 2;
    const top = centerY - height / 2;

    this.overlay = cfg.scene.add
      .rectangle(0, 0, cfg.scene.scale.width, cfg.scene.scale.height, 0x000000, 0.55)
      .setOrigin(0, 0)
      .setInteractive();
    this.panel = cfg.scene.add
      .rectangle(left, top, width, height, 0x1d1d1d, 0.98)
      .setOrigin(0, 0)
      .setStrokeStyle(2, 0xffffff, 0.25);

    this.titleText = cfg.scene.add.text(left + 20, top + 18, cfg.title, {
      ...TEXT_OVERLAY_TITLE,
    }).setOrigin(0, 0);

    this.messageText = cfg.scene.add.text(left + 20, top + 66, cfg.message, {
      ...TEXT_OVERLAY_BODY,
      wordWrap: { width: width - 40 },
    }).setOrigin(0, 0);

    this.acceptButton = new AcceptButton({
      scene: cfg.scene,
      x: left + 20,
      y: top + height - 95,
      label: cfg.acceptLabel ?? "Accept",
      onClick: () => {
        void cfg.onAccept();
      },
    });
    this.rejectButton = new RejectButton({
      scene: cfg.scene,
      x: left + width - 320,
      y: top + height - 95,
      label: cfg.rejectLabel ?? "Cancel",
      onClick: () => {
        cfg.onReject();
        this.close();
      },
    });

    this.add([
      this.overlay,
      this.panel,
      this.titleText,
      this.messageText,
      this.acceptButton,
      this.rejectButton,
    ]);
    cfg.scene.add.existing(this);
    this.setDepth(1200);
  }

  close(): void {
    this.destroy();
  }
}

