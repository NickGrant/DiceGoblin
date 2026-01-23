import Phaser from "phaser";
import type { CurrentRunNode, CurrentRunRecord } from "../../types/ApiResponse";
import Node, { type NodeViewConfig } from "./Node";

export type NodeListConfig = {
  // If omitted, uses the remaining screen width from this container's x position.
  maxWidth?: number;

  // Force a specific column count (otherwise auto-calculated).
  columns?: number;

  gapX?: number; // default: 18
  gapY?: number; // default: 18

  nodeConfig?: NodeViewConfig; // e.g. { scale: 0.75 }
};

const DEFAULTS: Required<Omit<NodeListConfig, "columns" | "maxWidth">> & {
  columns?: number;
  maxWidth?: number;
} = {
  maxWidth: undefined,
  columns: undefined,
  gapX: 18,
  gapY: 18,
  nodeConfig: { scale: 0.5 }, // default: 50% to fit more nodes in 960px
};

export default class NodeList extends Phaser.GameObjects.Container {
  private run: CurrentRunRecord;
  private nodes: CurrentRunNode[];
  private cfg: typeof DEFAULTS;

  private nodeViews: Node[] = [];

  constructor(
    scene: Phaser.Scene,
    x: number,
    y: number,
    run: CurrentRunRecord,
    nodes: CurrentRunNode[],
    config: NodeListConfig = {}
  ) {
    super(scene, x, y);

    this.run = run;
    this.nodes = nodes;
    this.cfg = {
      ...DEFAULTS,
      ...config,
      nodeConfig: { ...DEFAULTS.nodeConfig, ...(config.nodeConfig ?? {}) },
    };

    this.render();

    scene.add.existing(this);
  }

  public setNodes(nodes: CurrentRunNode[]): void {
    this.nodes = nodes;
    this.render();
  }

  private render(): void {
    // Destroy old children
    this.removeAll(true);
    this.nodeViews = [];

    // Compute node dimensions from known asset size * scale.
    // torn_corner_patch is 321x331 (per your note)
    const scale = this.cfg.nodeConfig.scale ?? 0.5;
    const nodeW = 321 * scale;
    const nodeH = 331 * scale;

    const gapX = this.cfg.gapX;
    const gapY = this.cfg.gapY;

    const availableWidth =
      this.cfg.maxWidth ??
      Math.max(1, this.scene.scale.width - this.x); // remaining width from list's x position

    const columns =
      this.cfg.columns ??
      Math.max(1, Math.floor((availableWidth + gapX) / (nodeW + gapX)));

    this.nodes.forEach((record, idx) => {
      const col = idx % columns;
      const row = Math.floor(idx / columns);

      const px = col * (nodeW + gapX);
      const py = row * (nodeH + gapY);

      const node = new Node(this.scene, px, py, record, this.cfg.nodeConfig);
      this.nodeViews.push(node);
      this.add(node);
    });

    // Container bounds are optional but useful if you later add masks/scrolling
    const rows = Math.max(1, Math.ceil(this.nodes.length / columns));
    const totalW = Math.min(availableWidth, columns * nodeW + (columns - 1) * gapX);
    const totalH = rows * nodeH + (rows - 1) * gapY;
    this.setSize(totalW, totalH);
  }
}
