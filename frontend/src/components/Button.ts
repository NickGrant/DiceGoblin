import Phaser from "phaser";
import { TEXT_BUTTON } from "../const/Text";

export type UiButtonSize = "normal" | "small" | "tiny";

type UiButtonConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  label: string;
  onClick: () => void;
  textStyle?: Phaser.Types.GameObjects.Text.TextStyle;

  /**
   * Named size for the button.
   * - "normal": default artwork size
   * - "small": smaller footprint (existing behavior for `small: true`)
   * - "tiny": compact footprint for toolbars / dense UI
   */
  size?: UiButtonSize;

  /**
   * Backwards-compatible alias.
   * If provided and `size` is not set, `small: true` maps to `size: "small"`.
   */
  small?: boolean;

  /**
   * Whether the button is interactive.
   * Disabled buttons do not hover/press/click and are visually dimmed.
   */
  enabled?: boolean;
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

  // Named size scales
  private static readonly SIZE_SCALE: Record<UiButtonSize, number> = {
    normal: 1,
    small: 0.60, // existing SMALL_SCALE behavior
    tiny: 0.45,  // compact: good for toolbars (SAVE/CLEAR, pagination, etc.)
  };

  // Disabled visuals
  private static readonly DISABLED_ALPHA = 0.55;

  private readonly glow: Phaser.GameObjects.Image;
  private readonly bg: Phaser.GameObjects.Image;
  private readonly label: Phaser.GameObjects.Text;
  private readonly pressOverlay: Phaser.GameObjects.Rectangle;

  private readonly hitZone: Phaser.GameObjects.Zone;
  private readonly onClick: () => void;

  private isDown = false;
  private enabled = true;
  private size: UiButtonSize;

  constructor(cfg: UiButtonConfig) {
    super(cfg.scene, cfg.x, cfg.y);

    this.onClick = cfg.onClick;

    // Resolve size:
    // - prefer explicit `size`
    // - else map legacy `small: true` to "small"
    // - else "normal"
    this.size = cfg.size ?? (cfg.small ? "small" : "normal");
    const scale = UiButton.SIZE_SCALE[this.size];

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
      .text(0, 0, cfg.label.toUpperCase(), {
        ...defaultStyle,
        ...{ wordWrap: { width: bgW } },
        ...(cfg.textStyle ?? {}),
      })
      .setOrigin(0.5);

    // Invisible hit zone (interactive target)
    this.hitZone = cfg.scene.add.zone(0, 0, hitW, hitH);

    // Render order: glow behind, then bg, overlay, text, then zone (invisible)
    this.add([this.glow, this.bg, this.pressOverlay, this.label, this.hitZone]);

    // Container size (useful for layout/debug)
    this.setSize(hitW, hitH);

    this.wireEvents();

    cfg.scene.add.existing(this);

    // Enable/disable after wiring events (so toggling works consistently)
    this.setEnabled(cfg.enabled ?? true);

    // Optional debug:
    // cfg.scene.input.enableDebug(this.hitZone);
  }

  private wireEvents(): void {
    this.hitZone.on("pointerover", () => {
      if (!this.enabled) return;
      this.fadeGlowIn();
    });

    this.hitZone.on("pointerout", () => {
      if (!this.enabled) return;
      this.isDown = false;
      this.applyPressedVisual(false);
      this.applyOffset(false);
      this.fadeGlowOut();
    });

    this.hitZone.on("pointerdown", () => {
      if (!this.enabled) return;
      this.isDown = true;
      this.fadeGlowIn();
      this.applyPressedVisual(true);
      this.applyOffset(true);
    });

    // Released over the zone
    this.hitZone.on("pointerup", () => {
      if (!this.enabled) return;

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
      if (!this.enabled) return;

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
      ease: "Quad.Out",
    });
  }

  private fadeGlowOut(): void {
    this.scene.tweens.killTweensOf(this.glow);

    this.scene.tweens.add({
      targets: this.glow,
      alpha: 0,
      duration: UiButton.HOVER_FADE_MS,
      ease: "Quad.Out",
      onComplete: () => this.glow.setVisible(false),
    });
  }

  private forceGlowHidden(): void {
    this.scene.tweens.killTweensOf(this.glow);
    this.glow.setAlpha(0);
    this.glow.setVisible(false);
  }

  /**
   * Enable/disable the button.
   * Disabled buttons do not receive pointer events and are visually dimmed.
   */
  public setEnabled(enabled: boolean): this {
    this.enabled = enabled;

    // Reset pressed state any time enabled toggles
    this.isDown = false;
    this.applyPressedVisual(false);
    this.applyOffset(false);

    if (enabled) {
      this.setAlpha(1);
      this.hitZone.setInteractive({ cursor: "pointer" });
    } else {
      this.setAlpha(UiButton.DISABLED_ALPHA);
      this.hitZone.disableInteractive();
      this.forceGlowHidden();
    }

    return this;
  }

  public getEnabled(): boolean {
    return this.enabled;
  }

  public getSize(): UiButtonSize {
    return this.size;
  }

  public setText(text: string): this {
    this.label.setText(text.toUpperCase());
    return this;
  }
}
