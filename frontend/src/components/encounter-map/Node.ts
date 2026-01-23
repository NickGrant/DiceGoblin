import Phaser from "phaser";
import type { CurrentRunNode } from "../../types/ApiResponse";
import { TEXT_HEADER } from "../../const/Text";

export type NodeViewConfig = {
  bgKey?: string; // default: "torn_corner_patch"
  scale?: number; // default: 0.5 (use 0.75 for bigger nodes)
  padding?: number; // default: 16
  lineSpacing?: number; // default: 6
  textStyle?: Phaser.Types.GameObjects.Text.TextStyle; // optional override
};

const DEFAULTS: Required<NodeViewConfig> = {
  bgKey: "torn_corner_patch",
  scale: 0.5,
  padding: 30,
  lineSpacing: 6,
  textStyle: {...TEXT_HEADER, fontSize: "14px"},
};

export default class Node extends Phaser.GameObjects.Container {
  private record: CurrentRunNode;
  private cfg: Required<NodeViewConfig>;

  private bg!: Phaser.GameObjects.Image;
  private txtId!: Phaser.GameObjects.Text;
  private txtType!: Phaser.GameObjects.Text;
  private txtStatus!: Phaser.GameObjects.Text;

  // For external layout consumers (NodeList).
  public nodeWidth = 0;
  public nodeHeight = 0;

  constructor(
    scene: Phaser.Scene,
    x: number,
    y: number,
    record: CurrentRunNode,
    config: NodeViewConfig = {}
  ) {
    super(scene, x, y);

    this.record = record;
    this.cfg = { ...DEFAULTS, ...config };

    this.build();
    this.refresh();

    scene.add.existing(this);
  }

  public setRecord(record: CurrentRunNode): void {
    this.record = record;
    this.refresh();
  }

  public setScaleFactor(scale: number): void {
    this.cfg.scale = scale;

    if (this.bg) {
      this.bg.setScale(scale);
      this.nodeWidth = this.bg.displayWidth;
      this.nodeHeight = this.bg.displayHeight;
      this.setSize(this.nodeWidth, this.nodeHeight);
    }

    this.layoutText();
    this.refresh();
  }

  private build(): void {
    // Background (anchored to top-left within this container)
    this.bg = this.scene.add.image(0, 0, this.cfg.bgKey).setOrigin(0, 0);
    this.bg.setScale(this.cfg.scale);

    this.nodeWidth = this.bg.displayWidth;
    this.nodeHeight = this.bg.displayHeight;
    this.setSize(this.nodeWidth, this.nodeHeight);

    // Text (three lines)
    this.txtId = this.scene.add.text(0, 0, "", this.cfg.textStyle).setOrigin(0, 0);
    this.txtType = this.scene.add.text(0, 0, "", this.cfg.textStyle).setOrigin(0, 0);
    this.txtStatus = this.scene.add.text(0, 0, "", this.cfg.textStyle).setOrigin(0, 0);

    this.add([this.bg, this.txtId, this.txtType, this.txtStatus]);

    this.layoutText();
  }

  private layoutText(): void {
    const p = this.cfg.padding;
    const lineH = this.txtId.height + this.cfg.lineSpacing;

    this.txtId.setPosition(p, p);
    this.txtType.setPosition(p, p + lineH);
    this.txtStatus.setPosition(p, p + lineH * 2);

    // Keep text inside the patch
    const maxW = Math.max(0, this.nodeWidth - p * 2);
    this.txtId.setWordWrapWidth(maxW, true);
    this.txtType.setWordWrapWidth(maxW, true);
    this.txtStatus.setWordWrapWidth(maxW, true);
  }

  private refresh(): void {
    const { id, node_type, status } = this.record;

    this.txtId.setText(`ID: ${id}`);
    this.txtType.setText(`TYPE: ${String(node_type).toUpperCase()}`);
    this.txtStatus.setText(`STATUS: ${String(status).toUpperCase()}`);

    // Lightweight visual hinting (optional but useful)
    if (status === "locked") {
      this.bg.setAlpha(0.65);
    } else if (status === "cleared") {
      this.bg.setAlpha(0.85);
    } else {
      this.bg.setAlpha(1);
    }
  }
}
