import Phaser from "phaser";
import type { CurrentRunNode } from "../../types/ApiResponse";

export type NodeClickHandler = (node: CurrentRunNode) => void;

export type NodeConfig = {
  size?: number; // display size in px
  onClick?: NodeClickHandler;
};

export default class Node extends Phaser.GameObjects.Container {
  private record: CurrentRunNode;
  private cfg: Required<Omit<NodeConfig, "onClick">> & Pick<NodeConfig, "onClick">;

  private icon!: Phaser.GameObjects.Image;

  constructor(
    scene: Phaser.Scene,
    x: number,
    y: number,
    record: CurrentRunNode,
    config: NodeConfig = {}
  ) {
    super(scene, x, y);

    this.record = record;
    this.cfg = {
      size: config.size ?? 84,
      onClick: config.onClick,
    };

    this.build();
    this.refresh();

    scene.add.existing(this);
  }

  public setRecord(record: CurrentRunNode): void {
    this.record = record;
    this.refresh();
  }

  private build(): void {
    const size = this.cfg.size;

    // Icon centered on container origin for robust bounds/clamping math.
    this.icon = this.scene.add.image(0, 0, "__MISSING__").setOrigin(0.5, 0.5);
    this.icon.setDisplaySize(size, size);

    // Container hit area follows icon size.
    this.setSize(size, size);
    this.setInteractive(new Phaser.Geom.Rectangle(-size / 2, -size / 2, size, size), Phaser.Geom.Rectangle.Contains);

    this.on("pointerover", () => this.icon.setScale(1.05));
    this.on("pointerout", () => this.icon.setScale(1));
    this.on("pointerup", () => {
      if (this.record.status === "available") {
        this.cfg.onClick?.(this.record);
        this.emit("node:click", this.record);
      }
    });

    this.add(this.icon);
  }

  private refresh(): void {
    const textureKey = this.pickTextureKey(this.record);
    this.icon.setTexture(textureKey);

    const status = this.record.status;
    const isExit = this.record.node_type === "exit";
    const isLocked = status === "locked";
    const isAvailable = status === "available";
    const isCleared = status === "cleared";

    this.icon.clearTint();
    this.icon.setAlpha(isLocked ? 0.55 : 1.0);
    if (isExit && isLocked) {
      this.icon.setTint(0x4f8aa8);
      this.icon.setAlpha(0.7);
    } else if (isExit && isAvailable) {
      this.icon.setTint(0x73f3ff);
      this.icon.setAlpha(1.0);
    } else if (isExit && isCleared) {
      this.icon.setTint(0xa7ffcf);
      this.icon.setAlpha(0.9);
    } else if (isCleared) {
      this.icon.setTint(0x8fd38a);
      this.icon.setAlpha(0.9);
    }

    this.disableInteractive();
    if (isAvailable) {
      this.setInteractive(new Phaser.Geom.Rectangle(-this.cfg.size / 2, -this.cfg.size / 2, this.cfg.size, this.cfg.size), Phaser.Geom.Rectangle.Contains);
      if (this.input) {
        this.input.cursor = "pointer";
      }
    }
  }

  private pickTextureKey(node: CurrentRunNode): string {
    if (node.node_type === "exit") {
      return "icon_encounter_boss";
    }

    if (node.status === "locked") {
      return "icon_encounter_locked";
    }

    const type = String(node.node_type);
    if (type === "combat" || type === "loot" || type === "rest" || type === "boss") {
      return `icon_encounter_${type}`;
    }

    return "icon_encounter_combat";
  }
}

