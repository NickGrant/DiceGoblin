import type Phaser from "phaser";
import { TEXT_BUTTON } from "../../const/Text";
import ClickablePanel, { type ClickablePanelConfig } from "./ClickablePanel";

type ActionButtonConfig = Omit<ClickablePanelConfig, "width" | "height" | "textureKey" | "targetSceneKey" | "clickHandler"> & {
  scene: Phaser.Scene;
  label: string;
  iconKey?: string;
  onClick?: () => void;
  targetSceneKey?: string;
  dataToPass?: Record<string, unknown>;
  textStyle?: Phaser.Types.GameObjects.Text.TextStyle;
};

export default class ActionButton extends ClickablePanel {
  private static readonly WIDTH = 300;
  private static readonly HEIGHT = 75;
  private static readonly ICON_SIZE = 100;
  private static readonly ICON_OVERFLOW = 12.5;

  private labelObj?: Phaser.GameObjects.Text;
  private iconObj?: Phaser.GameObjects.Image;
  private readonly labelText: string;
  private readonly iconKey?: string;
  private readonly customTextStyle?: Phaser.Types.GameObjects.Text.TextStyle;

  constructor(cfg: ActionButtonConfig) {
    const labelText = cfg.label;
    const iconKey = cfg.iconKey;
    const customTextStyle = cfg.textStyle;
    super(cfg.scene, {
      ...cfg,
      width: ActionButton.WIDTH,
      height: ActionButton.HEIGHT,
      textureKey: "manifest_strip",
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
        .image(0, -ActionButton.ICON_OVERFLOW, this.iconKey)
        .setDisplaySize(ActionButton.ICON_SIZE, ActionButton.ICON_SIZE)
        .setOrigin(0, 0);
      this.add(this.iconObj);
    }

    const labelX = this.iconKey ? ActionButton.ICON_SIZE + 10 : 16;
    const labelWidth = this.iconKey
      ? ActionButton.WIDTH - labelX - 12
      : ActionButton.WIDTH - 24;

    this.labelObj = this.scene.add
      .text(labelX, 20, this.labelText.toUpperCase(), {
        ...TEXT_BUTTON,
        fontSize: "30px",
        wordWrap: { width: labelWidth },
        ...(this.customTextStyle ?? {}),
      })
      .setOrigin(0, 0);
    this.add(this.labelObj);
  }

  setText(text: string): this {
    this.labelObj?.setText(text.toUpperCase());
    return this;
  }
}
