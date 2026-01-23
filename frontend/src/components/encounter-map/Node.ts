import Phaser from "phaser";
import type { CurrentRunNode } from "../../types/ApiResponse";

export type NodeClickHandler = (node: CurrentRunNode) => void;

export type NodeConfig = {
  size?: number; // default 32
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
      size: config.size ?? 24,
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
    //const size = this.cfg.size;
    const size = 128;
    const scale = .75;

    // Icon centered on container origin
    this.icon = this.scene.add.image(0, 0, "__MISSING__").setOrigin(0.5, 0.5);
    this.icon.setScale(scale);

    // Container hit area (64x64 by default)
    this.setSize(size, size);
    this.setInteractive({useHandCursor: true});

    this.on("pointerover", () => this.icon.setScale(scale + .05));
    this.on("pointerout", () => this.icon.setScale(scale));
    this.on("pointerup", () => {
      if(this.record.status !== 'locked') {
        this.cfg.onClick?.(this.record);
        // Optional event hook if you prefer listening externally:
        this.emit("node:click", this.record);
        console.log('clicked-em');
      }
    });

    this.add(this.icon);
  }

  private refresh(): void {
    const textureKey = this.pickTextureKey(this.record);
    this.icon.setTexture(textureKey);

    // Slightly dim locked nodes
    const locked = this.record.status === "locked";
    this.icon.setAlpha(locked ? 0.65 : 1.0);
  }

  private pickTextureKey(node: CurrentRunNode): string {
    let status = node.status === 'locked' ? 'locked' : 'combat';
    if(['boss', 'combat', 'loot', 'rest'].indexOf(node.node_type) > -1 && node.status !== 'locked'){
      status = node.node_type;
    }
    return `icon_encounter_${status}`;
  }
}
