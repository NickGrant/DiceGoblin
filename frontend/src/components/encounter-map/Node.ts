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
  private hoverHalo!: Phaser.GameObjects.Image;
  private isHovered = false;

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
      size: config.size ?? 64,
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

    this.hoverHalo = this.scene.add.image(0, 0, "__MISSING__").setOrigin(0.5, 0.5);
    this.hoverHalo
      .setDisplaySize(Math.floor(size * 1.2), Math.floor(size * 1.2))
      .setAlpha(0.22)
      .setTint(0xfff1b8)
      .setVisible(false);

    // Icon centered on container origin for robust bounds/clamping math.
    this.icon = this.scene.add.image(0, 0, "__MISSING__").setOrigin(0.5, 0.5);
    this.icon.setDisplaySize(size, size);

    // Container hit area follows icon size.
    this.setSize(size, size);
    this.setInteractive(new Phaser.Geom.Rectangle(0, 0, size, size), Phaser.Geom.Rectangle.Contains);

    this.on("pointerover", () => {
      this.isHovered = true;
      this.updateHoverState();
    });
    this.on("pointerout", () => {
      this.isHovered = false;
      this.updateHoverState();
    });
    this.on("pointerup", () => {
      if (this.record.status === "available") {
        this.cfg.onClick?.(this.record);
        this.emit("node:click", this.record);
      }
    });

    this.add(this.hoverHalo);
    this.add(this.icon);
  }

  private refresh(): void {
    const textureKey = this.pickTextureKey(this.record);
    this.hoverHalo.setTexture(textureKey);
    this.hoverHalo.setDisplaySize(Math.floor(this.cfg.size * 1.2), Math.floor(this.cfg.size * 1.2));
    this.icon.setTexture(textureKey);
    this.icon.setDisplaySize(this.cfg.size, this.cfg.size);

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
      this.setInteractive(new Phaser.Geom.Rectangle(0, 0, this.cfg.size, this.cfg.size), Phaser.Geom.Rectangle.Contains);
      if (this.input) {
        this.input.cursor = "pointer";
      }
    }

    this.updateHoverState();
  }

  private updateHoverState(): void {
    const isAvailable = this.record.status === "available";
    const showHalo = isAvailable && this.isHovered;
    this.hoverHalo.setVisible(showHalo);
    this.icon.setAlpha(showHalo ? 0.92 : 1);
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

