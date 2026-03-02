import Phaser from "phaser";
import type { CurrentRunNode, CurrentRunRecord } from "../../types/ApiResponse";
import Node, { type NodeClickHandler } from "./Node";

export type NodeListConfig = {
  /**
   * Billboard/scatter area in *NodeList local coordinates*.
   * Defaults tuned for your background resized to 960x540.
   */
  scatterRect?: Phaser.Geom.Rectangle;

  /**
   * Node icon size (default 64).
   */
  nodeSize?: number;

  /**
   * Minimum distance between node centers.
   * Default is nodeSize + 14 (spreads them out but still fits a lot).
   */
  minSeparation?: number;

  /**
   * Max placement attempts per node before fallback logic.
   */
  maxAttemptsPerNode?: number;

  /**
   * Optional click handler for nodes.
   */
  onNodeClick?: NodeClickHandler;

  /**
   * If true, draws the scatter rect for debugging.
   */
  debugDrawRect?: boolean;
};

const DEFAULT_SCATTER_RECT_960x540 = new Phaser.Geom.Rectangle(
  150, // x
  80,  // y
  650, // width
  360  // height
);

export default class NodeList extends Phaser.GameObjects.Container {
  private run: CurrentRunRecord;
  private nodes: CurrentRunNode[];
  private cfg: Required<Omit<NodeListConfig, "onNodeClick">> & Pick<NodeListConfig, "onNodeClick">;

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

    const nodeSize = config.nodeSize ?? 24;

    this.cfg = {
      scatterRect: config.scatterRect ?? DEFAULT_SCATTER_RECT_960x540,
      nodeSize,
      minSeparation: config.minSeparation ?? (nodeSize + 24),
      maxAttemptsPerNode: config.maxAttemptsPerNode ?? 900,
      debugDrawRect: config.debugDrawRect ?? false,
      onNodeClick: config.onNodeClick,
    };

    this.render();

    scene.add.existing(this);
  }

  public setNodes(nodes: CurrentRunNode[]): void {
    this.nodes = nodes;
    this.render();
  }

  private render(): void {
    this.removeAll(true);
    this.nodeViews = [];

    if (this.cfg.debugDrawRect) {
      const g = this.scene.add.graphics();
      g.lineStyle(2, 0xff00ff, 0.8);
      g.strokeRect(
        this.cfg.scatterRect.x,
        this.cfg.scatterRect.y,
        this.cfg.scatterRect.width,
        this.cfg.scatterRect.height
      );
      this.add(g);
    }

    const rng = makeSeededRng(this.run.seed);
    const size = this.cfg.nodeSize;
    const half = size / 2;

    const rect = this.cfg.scatterRect;

    // We place by center points to make non-overlap checks straightforward
    const placed: Array<{ x: number; y: number }> = [];

    const tryPlaceOne = (): { x: number; y: number } | null => {
      for (let attempt = 0; attempt < this.cfg.maxAttemptsPerNode; attempt++) {
        const x = lerp(rect.x + half, rect.x + rect.width - half, rng());
        const y = lerp(rect.y + half, rect.y + rect.height - half, rng());

        let ok = true;
        for (const p of placed) {
          const dx = x - p.x;
          const dy = y - p.y;
          if (dx * dx + dy * dy < this.cfg.minSeparation * this.cfg.minSeparation) {
            ok = false;
            break;
          }
        }
        if (ok) return { x, y };
      }
      return null;
    };

    // Primary: rejection sampling to scatter without overlap.
    // Fallback: coarse grid fill (still “scattered” by jitter).
    const fallbackGridPositions = this.buildFallbackGridPositions(rng);

    this.nodes.forEach((record, idx) => {
      const pos = tryPlaceOne() ?? fallbackGridPositions[idx] ?? {
        x: rect.x + half,
        y: rect.y + half,
      };

      placed.push(pos);

      const node = new Node(this.scene, pos.x, pos.y, record, {
        size: this.cfg.nodeSize,
        onClick: this.cfg.onNodeClick,
      });

      this.nodeViews.push(node);
      this.add(node);
    });

    // Helpful if you later add scrolling/masking
    this.setSize(this.scene.scale.width, this.scene.scale.height);
  }

  private buildFallbackGridPositions(rng: () => number): Array<{ x: number; y: number }> {
    const rect = this.cfg.scatterRect;
    const size = this.cfg.nodeSize;
    const half = size / 2;

    // Conservative spacing so we still avoid overlap.
    const step = Math.max(this.cfg.minSeparation, size + 8);

    const cols = Math.max(1, Math.floor((rect.width - size) / step) + 1);
    const rows = Math.max(1, Math.floor((rect.height - size) / step) + 1);

    const positions: Array<{ x: number; y: number }> = [];
    for (let r = 0; r < rows; r++) {
      for (let c = 0; c < cols; c++) {
        const baseX = rect.x + half + c * step;
        const baseY = rect.y + half + r * step;

        // Small jitter so it doesn’t look like a strict grid
        const jitter = Math.min(10, step * 0.15);
        const x = baseX + (rng() - 0.5) * 2 * jitter;
        const y = baseY + (rng() - 0.5) * 2 * jitter;

        // Ensure within bounds
        const clampedX = Phaser.Math.Clamp(x, rect.x + half, rect.x + rect.width - half);
        const clampedY = Phaser.Math.Clamp(y, rect.y + half, rect.y + rect.height - half);

        positions.push({ x: clampedX, y: clampedY });
      }
    }

    // Shuffle positions for more “random” fill order
    for (let i = positions.length - 1; i > 0; i--) {
      const j = Math.floor(rng() * (i + 1));
      const left = positions[i];
      const right = positions[j];
      if (!left || !right) continue;
      positions[i] = right;
      positions[j] = left;
    }

    return positions;
  }
}

/** Helpers **/

function lerp(a: number, b: number, t: number): number {
  return a + (b - a) * t;
}

/**
 * Seeded RNG using:
 * - xfnv1a string hash -> 32-bit seed
 * - mulberry32 PRNG
 */
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
    // final avalanche
    h += h << 13; h ^= h >>> 7;
    h += h << 3;  h ^= h >>> 17;
    h += h << 5;
    return h >>> 0;
  };
}

function mulberry32(a: number): () => number {
  return function () {
    let t = (a += 0x6D2B79F5);
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}
