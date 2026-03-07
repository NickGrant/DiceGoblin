import Phaser from "phaser";
import ActionButton from "./ActionButton";
import AcceptButton from "./AcceptButton";
import RejectButton from "./RejectButton";

type ActionButtonConfig = ConstructorParameters<typeof ActionButton>[0];

export type ActionButtonListItem = Omit<ActionButtonConfig, "scene" | "x" | "y"> & {
  buttonType?: "default" | "accept" | "reject";
};

type ActionButtonListConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  buttons: ActionButtonListItem[];
  gapY?: number;
};

export default class ActionButtonList extends Phaser.GameObjects.Container {
  private readonly buttons: ActionButton[] = [];
  private readonly gapY: number;

  constructor(cfg: ActionButtonListConfig) {
    super(cfg.scene, cfg.x, cfg.y);
    this.gapY = cfg.gapY ?? 25;

    let offsetY = 0;
    for (const buttonCfg of cfg.buttons) {
      const button = this.createButton({
        ...buttonCfg,
        scene: cfg.scene,
        x: 0,
        y: offsetY,
      });
      this.buttons.push(button);
      this.add(button);
      offsetY += 75 + this.gapY;
    }

    this.setSize(300, Math.max(0, offsetY - this.gapY));
    cfg.scene.add.existing(this);
  }

  public getButtons(): ActionButton[] {
    return this.buttons;
  }

  private createButton(cfg: ActionButtonConfig & { buttonType?: "default" | "accept" | "reject" }): ActionButton {
    if (cfg.buttonType === "accept") return new AcceptButton(cfg);
    if (cfg.buttonType === "reject") return new RejectButton(cfg);
    return new ActionButton(cfg);
  }
}
