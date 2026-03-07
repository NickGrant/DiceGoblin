import Phaser from "phaser";

export type ToastSeverity = "info" | "success" | "warning" | "error";

export type ToastMessageConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  message: string;
  severity?: ToastSeverity;
  durationMs?: number;
};

const SEVERITY_COLOR: Record<ToastSeverity, number> = {
  info: 0x1f3a52,
  success: 0x1f5226,
  warning: 0x5b4820,
  error: 0x5b2020,
};

export default class ToastMessage extends Phaser.GameObjects.Container {
  private hideTimer?: Phaser.Time.TimerEvent;

  constructor(cfg: ToastMessageConfig) {
    super(cfg.scene, cfg.x, cfg.y);

    const severity = cfg.severity ?? "info";
    const label = cfg.scene.add.text(0, 0, cfg.message, {
      fontFamily: "Arial",
      fontSize: "14px",
      color: "#ffffff",
      wordWrap: { width: 420 },
      padding: { left: 8, right: 8, top: 6, bottom: 6 },
    }).setOrigin(0, 0);

    const bg = cfg.scene.add
      .rectangle(0, 0, label.width, label.height, SEVERITY_COLOR[severity], 0.95)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xffffff, 0.25);

    this.add([bg, label]);
    cfg.scene.add.existing(this);
    this.setDepth(1100);

    const durationMs = Math.max(0, cfg.durationMs ?? 2200);
    if (durationMs > 0) {
      this.hideTimer = cfg.scene.time.delayedCall(durationMs, () => this.destroy());
    }
  }

  override destroy(fromScene?: boolean): void {
    this.hideTimer?.remove(false);
    super.destroy(fromScene);
  }
}
