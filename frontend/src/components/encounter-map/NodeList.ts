import Phaser from "phaser";
import type { CurrentRunEdge, CurrentRunNode, CurrentRunRecord } from "../../types/ApiResponse";
import Node, { type NodeClickHandler } from "./Node";

export type NodeListConfig = {
  scatterRect?: Phaser.Geom.Rectangle;
  nodeSize?: number;
  minSeparation?: number;
  maxAttemptsPerNode?: number;
  onNodeClick?: NodeClickHandler;
  debugDrawRect?: boolean;
};

const DEFAULT_SCATTER_RECT_960x540 = new Phaser.Geom.Rectangle(150, 80, 650, 360);

export default class NodeList extends Phaser.GameObjects.Container {
  private run: CurrentRunRecord;
  private nodes: CurrentRunNode[];
  private edges: CurrentRunEdge[];
  private cfg: Required<Omit<NodeListConfig, "onNodeClick">> & Pick<NodeListConfig, "onNodeClick">;
  private nodeViews: Node[] = [];

  constructor(
    scene: Phaser.Scene,
    x: number,
    y: number,
    run: CurrentRunRecord,
    nodes: CurrentRunNode[],
    edges: CurrentRunEdge[],
    config: NodeListConfig = {}
  ) {
    super(scene, x, y);

    this.run = run;
    this.nodes = nodes;
    this.edges = edges;

    const nodeSize = config.nodeSize ?? 84;
    this.cfg = {
      scatterRect: config.scatterRect ?? DEFAULT_SCATTER_RECT_960x540,
      nodeSize,
      minSeparation: config.minSeparation ?? nodeSize + 24,
      maxAttemptsPerNode: config.maxAttemptsPerNode ?? 900,
      debugDrawRect: config.debugDrawRect ?? false,
      onNodeClick: config.onNodeClick,
    };

    this.render();
    scene.add.existing(this);
  }

  public setNodes(nodes: CurrentRunNode[], edges: CurrentRunEdge[]): void {
    this.nodes = nodes;
    this.edges = edges;
    this.render();
  }

  private render(): void {
    this.removeAll(true);
    this.nodeViews = [];

    if (this.cfg.debugDrawRect) {
      const dbg = this.scene.add.graphics();
      dbg.lineStyle(2, 0xff00ff, 0.8);
      dbg.strokeRect(
        this.cfg.scatterRect.x,
        this.cfg.scatterRect.y,
        this.cfg.scatterRect.width,
        this.cfg.scatterRect.height
      );
      this.add(dbg);
    }

    const rng = makeSeededRng(this.run.seed);
    const rect = this.cfg.scatterRect;
    const half = this.cfg.nodeSize / 2;
    const contractPositions = this.buildContractGridPositions(rect, half);
    const positionsById = new Map<string, { x: number; y: number }>();

    this.nodes.forEach((record, idx) => {
      const fallback = {
        x: lerp(rect.x + half, rect.x + rect.width - half, rng()),
        y: lerp(rect.y + half, rect.y + rect.height - half, rng()),
      };

      const pos = contractPositions[idx] ?? fallback;
      positionsById.set(String(record.id), pos);

      const node = new Node(this.scene, pos.x, pos.y, record, {
        size: this.cfg.nodeSize,
        onClick: this.cfg.onNodeClick,
      });

      this.nodeViews.push(node);
      this.add(node);
    });

    this.renderEdges(positionsById);
    this.setSize(this.scene.scale.width, this.scene.scale.height);
  }

  private renderEdges(positionsById: Map<string, { x: number; y: number }>): void {
    if (!this.edges.length) return;

    const nodeById = new Map<string, CurrentRunNode>();
    for (const n of this.nodes) nodeById.set(String(n.id), n);

    const g = this.scene.add.graphics();
    this.addAt(g, 0);

    for (const edge of this.edges) {
      const from = positionsById.get(String(edge.from_node_id));
      const to = positionsById.get(String(edge.to_node_id));
      if (!from || !to) continue;

      const toNode = nodeById.get(String(edge.to_node_id));
      const isUnlockedPath = !!toNode && (toNode.status === "available" || toNode.status === "cleared");
      const color = isUnlockedPath ? 0xf2d489 : 0x5c5c5c;
      const alpha = isUnlockedPath ? 0.7 : 0.35;
      const width = isUnlockedPath ? 3 : 2;

      g.lineStyle(width, color, alpha);
      g.beginPath();
      g.moveTo(from.x, from.y);
      g.lineTo(to.x, to.y);
      g.strokePath();

      const angle = Phaser.Math.Angle.Between(from.x, from.y, to.x, to.y);
      const arrowSize = 8;
      const backX = to.x - Math.cos(angle) * 14;
      const backY = to.y - Math.sin(angle) * 14;
      const leftX = backX - Math.cos(angle - Math.PI / 2) * arrowSize * 0.5;
      const leftY = backY - Math.sin(angle - Math.PI / 2) * arrowSize * 0.5;
      const rightX = backX - Math.cos(angle + Math.PI / 2) * arrowSize * 0.5;
      const rightY = backY - Math.sin(angle + Math.PI / 2) * arrowSize * 0.5;

      g.fillStyle(color, alpha);
      g.beginPath();
      g.moveTo(to.x, to.y);
      g.lineTo(leftX, leftY);
      g.lineTo(rightX, rightY);
      g.closePath();
      g.fillPath();
    }
  }

  private buildContractGridPositions(
    rect: Phaser.Geom.Rectangle,
    half: number
  ): Array<{ x: number; y: number } | null> {
    const extracted = this.nodes.map((node) => extractNodeGrid(node));
    if (extracted.some((meta) => meta === null)) {
      return [];
    }

    const concrete = extracted.filter((meta): meta is { col: number; row: number } => meta !== null);
    const cols = concrete.map((meta) => meta.col);
    const rows = concrete.map((meta) => meta.row);
    const minCol = Math.min(...cols);
    const maxCol = Math.max(...cols);
    const minRow = Math.min(...rows);
    const maxRow = Math.max(...rows);

    return concrete.map((meta) => {
      const colSpan = Math.max(1, maxCol - minCol);
      const rowSpan = Math.max(1, maxRow - minRow);
      const colNorm = (meta.col - minCol) / colSpan;
      const rowNorm = (meta.row - minRow) / rowSpan;

      return {
        x: Phaser.Math.Clamp(
          lerp(rect.x + half, rect.x + rect.width - half, colNorm),
          rect.x + half,
          rect.x + rect.width - half
        ),
        y: Phaser.Math.Clamp(
          lerp(rect.y + half, rect.y + rect.height - half, rowNorm),
          rect.y + half,
          rect.y + rect.height - half
        ),
      };
    });
  }
}

function extractNodeGrid(node: CurrentRunNode): { col: number; row: number } | null {
  const parse = (meta: unknown): { col: number; row: number } | null => {
    if (!meta || typeof meta !== "object") return null;
    const col = Number((meta as Record<string, unknown>).col);
    const row = Number((meta as Record<string, unknown>).row);
    if (!Number.isFinite(col) || !Number.isFinite(row)) return null;
    return { col, row };
  };

  if (node.meta && typeof node.meta === "object") {
    const fromMeta = parse(node.meta);
    if (fromMeta) return fromMeta;
  }

  if (typeof node.meta_json === "string" && node.meta_json.trim() !== "") {
    try {
      return parse(JSON.parse(node.meta_json));
    } catch {
      return null;
    }
  }

  return null;
}

function lerp(a: number, b: number, t: number): number {
  return a + (b - a) * t;
}

function makeSeededRng(seedStr: string): () => number {
  const seed = xfnv1a(seedStr)();
  return mulberry32(seed);
}

function xfnv1a(str: string): () => number {
  let h = 2166136261 >>> 0;
  for (let i = 0; i < str.length; i++) {
    h ^= str.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return function () {
    h += h << 13;
    h ^= h >>> 7;
    h += h << 3;
    h ^= h >>> 17;
    h += h << 5;
    return h >>> 0;
  };
}

function mulberry32(a: number): () => number {
  return function () {
    let t = (a += 0x6d2b79f5);
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}
