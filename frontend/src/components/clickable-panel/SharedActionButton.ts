import type Phaser from "phaser";
import { TEXT_BUTTON } from "../../const/Text";
import ClickablePanel, { type ClickablePanelConfig } from "./ClickablePanel";

export type SharedButtonVariant = "default" | "accept" | "reject" | "metal";

type SharedActionButtonConfig = Omit<
  ClickablePanelConfig,
  "width" | "height" | "textureKey" | "targetSceneKey" | "clickHandler"
> & {
  scene: Phaser.Scene;
  label: string;
  iconKey?: string;
  onClick?: () => void;
  targetSceneKey?: string;
  dataToPass?: Record<string, unknown>;
  textStyle?: Phaser.Types.GameObjects.Text.TextStyle;
  variant?: SharedButtonVariant;
};

type VariantTokens = {
  textureKey: string;
  width: number;
  height: number;
  iconSize: number;
  iconX: number;
  iconY: number;
  labelXWithIcon: number;
  labelXWithoutIcon: number;
  labelYWithIcon: number;
  labelYWithoutIcon: number;
  labelOriginY: number;
  fontSize: string;
  listRowHeight: number;
  listWidth: number;
  defaultTextStyle?: Phaser.Types.GameObjects.Text.TextStyle;
};

const VARIANT_TOKENS: Record<SharedButtonVariant, VariantTokens> = {
  default: {
    textureKey: "manifest_strip",
    width: 280,
    height: 64,
    iconSize: 82,
    iconX: 0,
    iconY: -8,
    labelXWithIcon: 92,
    labelXWithoutIcon: 16,
    labelYWithIcon: 14,
    labelYWithoutIcon: 16,
    labelOriginY: 0,
    fontSize: "24px",
    listRowHeight: 64,
    listWidth: 280,
  },
  accept: {
    textureKey: "manifest_strip",
    width: 280,
    height: 64,
    iconSize: 82,
    iconX: 0,
    iconY: -8,
    labelXWithIcon: 92,
    labelXWithoutIcon: 16,
    labelYWithIcon: 14,
    labelYWithoutIcon: 16,
    labelOriginY: 0,
    fontSize: "24px",
    listRowHeight: 64,
    listWidth: 280,
    defaultTextStyle: {
      color: "#1f2f1f",
      stroke: "rgba(206,255,206,0.55)",
    },
  },
  reject: {
    textureKey: "manifest_strip",
    width: 280,
    height: 64,
    iconSize: 82,
    iconX: 0,
    iconY: -8,
    labelXWithIcon: 92,
    labelXWithoutIcon: 16,
    labelYWithIcon: 14,
    labelYWithoutIcon: 16,
    labelOriginY: 0,
    fontSize: "24px",
    listRowHeight: 64,
    listWidth: 280,
    defaultTextStyle: {
      color: "#3b1f1f",
      stroke: "rgba(255,206,206,0.65)",
    },
  },
  metal: {
    textureKey: "metal_strip",
    width: 280,
    height: 64,
    iconSize: 50,
    iconX: 8,
    iconY: 8,
    labelXWithIcon: 78,
    labelXWithoutIcon: 24,
    labelYWithIcon: 34,
    labelYWithoutIcon: 34,
    labelOriginY: 0.5,
    fontSize: "21px",
    listRowHeight: 75,
    listWidth: 300,
  },
};

export default class SharedActionButton extends ClickablePanel {
  private labelObj?: Phaser.GameObjects.Text;
  private iconObj?: Phaser.GameObjects.Image;
  private readonly labelText: string;
  private readonly iconKey?: string;
  private readonly customTextStyle?: Phaser.Types.GameObjects.Text.TextStyle;
  private readonly tokens: VariantTokens;

  constructor(cfg: SharedActionButtonConfig) {
    const variant = cfg.variant ?? "default";
    const tokens = VARIANT_TOKENS[variant];

    super(cfg.scene, {
      ...cfg,
      width: tokens.width,
      height: tokens.height,
      textureKey: tokens.textureKey,
      targetSceneKey: cfg.targetSceneKey,
      dataToPass: cfg.dataToPass,
      clickHandler: cfg.onClick ?? null,
      enabled: cfg.enabled ?? true,
      deferOverlay: true,
    });

    this.tokens = tokens;
    this.labelText = cfg.label;
    this.iconKey = cfg.iconKey;
    this.customTextStyle = cfg.textStyle;
    this.addOverlay();
  }

  public static getVariantMetrics(variant: SharedButtonVariant): {
    width: number;
    height: number;
    listRowHeight: number;
    listWidth: number;
  } {
    const tokens = VARIANT_TOKENS[variant];
    return {
      width: tokens.width,
      height: tokens.height,
      listRowHeight: tokens.listRowHeight,
      listWidth: tokens.listWidth,
    };
  }

  override addOverlay(): void {
    if (this.iconKey) {
      this.iconObj = this.scene.add
        .image(this.tokens.iconX, this.tokens.iconY, this.iconKey)
        .setDisplaySize(this.tokens.iconSize, this.tokens.iconSize)
        .setOrigin(0, 0);
      this.add(this.iconObj);
    }

    const labelX = this.iconKey ? this.tokens.labelXWithIcon : this.tokens.labelXWithoutIcon;
    const labelY = this.iconKey ? this.tokens.labelYWithIcon : this.tokens.labelYWithoutIcon;
    const labelWidth = this.tokens.width - labelX - 12;

    this.labelObj = this.scene.add
      .text(labelX, labelY, this.labelText.toUpperCase(), {
        ...TEXT_BUTTON,
        fontSize: this.tokens.fontSize,
        wordWrap: { width: labelWidth },
        ...(this.tokens.defaultTextStyle ?? {}),
        ...(this.customTextStyle ?? {}),
      })
      .setOrigin(0, this.tokens.labelOriginY);
    this.add(this.labelObj);
  }

  setText(text: string): this {
    this.labelObj?.setText(text.toUpperCase());
    return this;
  }
}