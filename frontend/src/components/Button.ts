import Phaser from "phaser";
import { TEXT_BUTTON } from "../const/Text";

type UiButtonConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  label: string;
  onClick: () => void;
  textStyle?: Phaser.Types.GameObjects.Text.TextStyle;

  /** If true, button renders at 75% width/height (text size unchanged). */
  small?: boolean;
};

export default class UiButton extends Phaser.GameObjects.Container {
  // Spec constants (base / normal)
  private static readonly BG_KEY = "button_background";
  private static readonly GLOW_KEY = "button_glow";
  private static readonly BG_W = 502;
  private static readonly BG_H = 165;
  private static readonly GLOW_PAD = 20; // +40 total (20px each side)
  private static readonly PRESS_OFFSET = 3;
  private static readonly PRESS_OVERLAY_COLOR = 0x1a0e0a; // #1A0E0A
  private static readonly PRESS_OVERLAY_ALPHA = 0.10;

  private static readonly HOVER_GLOW_ALPHA = 0.8;
  private static readonly HOVER_FADE_MS = 120;

  private static readonly SMALL_SCALE = 0.60;

  private readonly glow: Phaser.GameObjects.Image;
  private readonly bg: Phaser.GameObjects.Image;
  private readonly label: Phaser.GameObjects.Text;
  private readonly pressOverlay: Phaser.GameObjects.Rectangle;

  private readonly hitZone: Phaser.GameObjects.Zone;

  private readonly onClick: () => void;
  private isDown = false;

  constructor(cfg: UiButtonConfig) {
    super(cfg.scene, cfg.x, cfg.y);

    this.onClick = cfg.onClick;

    const scale = cfg.small ? UiButton.SMALL_SCALE : 1;

    const bgW = UiButton.BG_W * scale;
    const bgH = UiButton.BG_H * scale;

    const hitW = (UiButton.BG_W + UiButton.GLOW_PAD * 2) * scale;
    const hitH = (UiButton.BG_H + UiButton.GLOW_PAD * 2) * scale;

    // Glow (behind bg, hidden until hover; fades in)
    this.glow = cfg.scene.add
      .image(0, 0, UiButton.GLOW_KEY)
      .setDisplaySize(hitW, hitH)
      .setVisible(false)
      .setAlpha(0);

    // Background
    this.bg = cfg.scene.add.image(0, 0, UiButton.BG_KEY).setDisplaySize(bgW, bgH);

    // Press overlay (only visible while pressed)
    this.pressOverlay = cfg.scene.add
      .rectangle(0, 0, bgW, bgH, UiButton.PRESS_OVERLAY_COLOR, UiButton.PRESS_OVERLAY_ALPHA)
      .setVisible(false);

    // Centered label (NOT scaled)
    const defaultStyle = TEXT_BUTTON;

    this.label = cfg.scene.add
      .text(0, 0, cfg.label.toUpperCase(), { ...defaultStyle,...{wordWrap: {width: bgW}}, ...(cfg.textStyle ?? {}) })
      .setOrigin(0.5);

    // Invisible hit zone (interactive target)
    this.hitZone = cfg.scene.add.zone(0, 0, hitW, hitH);
    this.hitZone.setInteractive({ cursor: "pointer" });

    // Render order: glow behind, then bg, overlay, text, then zone (invisible)
    this.add([this.glow, this.bg, this.pressOverlay, this.label, this.hitZone]);

    // Container size (useful for layout/debug)
    this.setSize(hitW, hitH);

    this.wireEvents();

    cfg.scene.add.existing(this);

    // Optional debug:
    // cfg.scene.input.enableDebug(this.hitZone);
  }

  private wireEvents(): void {
    this.hitZone.on("pointerover", () => {
      this.fadeGlowIn();
    });

    this.hitZone.on("pointerout", () => {
      this.isDown = false;
      this.applyPressedVisual(false);
      this.applyOffset(false);
      this.fadeGlowOut();
    });

    this.hitZone.on("pointerdown", () => {
      this.isDown = true;
      this.fadeGlowIn();
      this.applyPressedVisual(true);
      this.applyOffset(true);
    });

    // Released over the zone
    this.hitZone.on("pointerup", () => {
      const wasDown = this.isDown;
      this.isDown = false;

      this.applyPressedVisual(false);
      this.applyOffset(false);

      if (wasDown) {
        this.onClick();
      }
    });

    // Released outside the zone
    this.hitZone.on("pointerupoutside", () => {
      this.isDown = false;
      this.applyPressedVisual(false);
      this.applyOffset(false);
      this.fadeGlowOut();
    });
  }

  private applyPressedVisual(pressed: boolean): void {
    this.pressOverlay.setVisible(pressed);
  }

  private applyOffset(pressed: boolean): void {
    const d = pressed ? UiButton.PRESS_OFFSET : 0;
    this.glow.setPosition(d, d);
    this.bg.setPosition(d, d);
    this.pressOverlay.setPosition(d, d);
    this.label.setPosition(d, d);
    this.hitZone.setPosition(d, d);
  }

  private fadeGlowIn(): void {
    this.scene.tweens.killTweensOf(this.glow);
    this.glow.setVisible(true);

    this.scene.tweens.add({
      targets: this.glow,
      alpha: UiButton.HOVER_GLOW_ALPHA,
      duration: UiButton.HOVER_FADE_MS,
      ease: "Quad.Out"
    });
  }

  private fadeGlowOut(): void {
    this.scene.tweens.killTweensOf(this.glow);

    this.scene.tweens.add({
      targets: this.glow,
      alpha: 0,
      duration: UiButton.HOVER_FADE_MS,
      ease: "Quad.Out",
      onComplete: () => this.glow.setVisible(false)
    });
  }

  public setText(text: string): this {
    this.label.setText(text);
    return this;
  }
}
