import type Phaser from "phaser";
import { TEXT_BUTTON } from "../../const/Text";
import ClickablePanel, { type ClickablePanelConfig } from "./ClickablePanel";

type MetalActionButtonConfig = Omit<ClickablePanelConfig, "width" | "height" | "textureKey" | "targetSceneKey" | "clickHandler"> & {
  scene: Phaser.Scene;
  label: string;
  iconKey?: string;
  onClick?: () => void;
  targetSceneKey?: string;
  dataToPass?: Record<string, unknown>;
  textStyle?: Phaser.Types.GameObjects.Text.TextStyle;
};

export default class MetalActionButton extends ClickablePanel {
  private static readonly WIDTH = 300;
  private static readonly HEIGHT = 75;
  private static readonly ICON_SIZE = 60;

  private labelObj?: Phaser.GameObjects.Text;
  private iconObj?: Phaser.GameObjects.Image;
  private readonly labelText: string;
  private readonly iconKey?: string;
  private readonly customTextStyle?: Phaser.Types.GameObjects.Text.TextStyle;

  constructor(cfg: MetalActionButtonConfig) {
    const labelText = cfg.label;
    const iconKey = cfg.iconKey;
    const customTextStyle = cfg.textStyle;
    super(cfg.scene, {
      ...cfg,
      width: MetalActionButton.WIDTH,
      height: MetalActionButton.HEIGHT,
      textureKey: "metal_strip",
      targetSceneKey: cfg.targetSceneKey,
      dataToPass: cfg.dataToPass,
      clickHandler: cfg.onClick ?? null,
      enabled: cfg.enabled ?? true,
      deferOverlay: true,
    });

    this.labelText = labelText;
    this.iconKey = iconKey;
    this.customTextStyle = customTextStyle;
    this.addOverlay();
  }

  override addOverlay(): void {
    if (this.iconKey) {
      this.iconObj = this.scene.add
        .image(8, 8, this.iconKey)
        .setDisplaySize(MetalActionButton.ICON_SIZE, MetalActionButton.ICON_SIZE)
        .setOrigin(0, 0);
      this.add(this.iconObj);
    }

    const labelX = this.iconKey ? 91 : 31;
    const labelWidth = MetalActionButton.WIDTH - labelX - 12;
    this.labelObj = this.scene.add
      .text(labelX, MetalActionButton.HEIGHT / 2 + 2, this.labelText.toUpperCase(), {
        ...TEXT_BUTTON,
        fontSize: "24px",
        wordWrap: { width: labelWidth },
        ...(this.customTextStyle ?? {}),
      })
      .setOrigin(0, 0.5);
    this.add(this.labelObj);
  }

  setText(text: string): this {
    this.labelObj?.setText(text.toUpperCase());
    return this;
  }
}
