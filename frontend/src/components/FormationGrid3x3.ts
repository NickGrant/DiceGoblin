import Phaser from "phaser";

export type FormationCell = "A1" | "B1" | "C1" | "A2" | "B2" | "C2" | "A3" | "B3" | "C3";
export type FormationMap = Record<FormationCell, string | null>;

export type FormationGrid3x3Config = {
  scene: Phaser.Scene;
  x: number; // top-left
  y: number; // top-left

  cellSize?: number; // default 96
  gap?: number; // default 10

  /**
   * Called on every cell click (including double-clicks).
   * Use this to set selection or "armed" behavior.
   */
  onCellClick?: (cell: FormationCell) => void;

  /**
   * Called when the same cell is clicked twice within `doubleClickMs`.
   */
  onCellDoubleClick?: (cell: FormationCell) => void;

  /**
   * Optional label generator. If not provided, the grid shows `cell` and "(Empty)" / unitId.
   * Keep it cheap; this is called when cells refresh.
   */
  getCellLabel?: (cell: FormationCell, unitId: string | null) => string;

  /**
   * Double click threshold in ms (default 320)
   */
  doubleClickMs?: number;

  /**
   * Visual customizations (optional)
   */
  colors?: {
    cellFill?: number; // default 0x111111
    cellFillAlpha?: number; // default 0.95
    stroke?: number; // default 0xffffff
    strokeAlpha?: number; // default 0.25
    selectedStroke?: number; // default 0xffcc00
    selectedStrokeAlpha?: number; // default 0.9
    text?: string; // default "#ffffff"
  };

  textStyle?: Phaser.Types.GameObjects.Text.TextStyle;

  /**
   * Initial formation (optional). Any missing cells will be initialized to null.
   */
  formation?: Partial<FormationMap>;

  /**
   * Initial selected cell (optional).
   */
  selectedCell?: FormationCell | null;
};

const CELLS: FormationCell[] = ["A1", "B1", "C1", "A2", "B2", "C2", "A3", "B3", "C3"];

function cellToRowCol(cell: FormationCell): { row: number; col: number } {
  const colChar = cell[0] || 'A'; // A..C
  const rowChar = cell[1] || '1'; // 1..3
  return { col: colChar.charCodeAt(0) - 65, row: parseInt(rowChar, 10) - 1 };
}

export default class FormationGrid3x3 extends Phaser.GameObjects.Container {
  private readonly cellSize: number;
  private readonly gap: number;

  private readonly colors: Required<NonNullable<FormationGrid3x3Config["colors"]>>;
  private readonly textStyle?: Phaser.Types.GameObjects.Text.TextStyle;

  private formation: FormationMap;
  private selectedCell: FormationCell | null;

  private readonly onCellClick?: (cell: FormationCell) => void;
  private readonly onCellDoubleClick?: (cell: FormationCell) => void;
  private readonly getCellLabel?: (cell: FormationCell, unitId: string | null) => string;

  private readonly doubleClickMs: number;
  private lastClickAtMs = 0;
  private lastClickedCell: FormationCell | null = null;

  private cellRects: Record<FormationCell, Phaser.GameObjects.Rectangle> = {} as any;
  private cellTexts: Record<FormationCell, Phaser.GameObjects.Text> = {} as any;

  constructor(cfg: FormationGrid3x3Config) {
    super(cfg.scene, cfg.x, cfg.y);

    this.cellSize = cfg.cellSize ?? 96;
    this.gap = cfg.gap ?? 10;

    this.doubleClickMs = cfg.doubleClickMs ?? 320;

    this.colors = {
      cellFill: cfg.colors?.cellFill ?? 0x111111,
      cellFillAlpha: cfg.colors?.cellFillAlpha ?? 0.95,
      stroke: cfg.colors?.stroke ?? 0xffffff,
      strokeAlpha: cfg.colors?.strokeAlpha ?? 0.25,
      selectedStroke: cfg.colors?.selectedStroke ?? 0xffcc00,
      selectedStrokeAlpha: cfg.colors?.selectedStrokeAlpha ?? 0.9,
      text: cfg.colors?.text ?? "#ffffff",
    };

    this.textStyle = cfg.textStyle;

    this.onCellClick = cfg.onCellClick;
    this.onCellDoubleClick = cfg.onCellDoubleClick;
    this.getCellLabel = cfg.getCellLabel;

    // Initialize formation map
    this.formation = {} as FormationMap;
    for (const c of CELLS) this.formation[c] = null;
    if (cfg.formation) {
      for (const c of CELLS) {
        if (Object.prototype.hasOwnProperty.call(cfg.formation, c)) {
          this.formation[c] = cfg.formation[c] ?? null;
        }
      }
    }

    this.selectedCell = cfg.selectedCell ?? null;

    this.build();

    // Set container bounds (useful for layout)
    const totalW = this.cellSize * 3 + this.gap * 2;
    const totalH = this.cellSize * 3 + this.gap * 2;
    this.setSize(totalW, totalH);

    cfg.scene.add.existing(this);
  }

  private build(): void {
    // Clear prior children (if rebuilding)
    this.removeAll(true);

    for (const cell of CELLS) {
      const { row, col } = cellToRowCol(cell);
      const x = col * (this.cellSize + this.gap);
      const y = row * (this.cellSize + this.gap);

      const rect = this.scene.add
        .rectangle(x, y, this.cellSize, this.cellSize, this.colors.cellFill, this.colors.cellFillAlpha)
        .setOrigin(0, 0)
        .setStrokeStyle(2, this.colors.stroke, this.colors.strokeAlpha)
        .setInteractive({ useHandCursor: true });

      const text = this.scene.add
        .text(x + 8, y + 8, this.makeCellLabel(cell), {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "12px",
          color: this.colors.text,
          wordWrap: { width: this.cellSize - 16 },
          ...(this.textStyle ?? {}),
        })
        .setOrigin(0, 0);

      rect.on("pointerdown", () => this.handleCellPointerDown(cell));

      this.cellRects[cell] = rect;
      this.cellTexts[cell] = text;

      this.add([rect, text]);
    }

    this.refreshHighlightsAndLabels();
  }

  private handleCellPointerDown(cell: FormationCell): void {
    const now = this.scene.time.now;
    const isSameCell = this.lastClickedCell === cell;
    const isDoubleClick = isSameCell && now - this.lastClickAtMs <= this.doubleClickMs;

    this.lastClickedCell = cell;
    this.lastClickAtMs = now;

    this.selectedCell = cell;

    // Always refresh visuals first so selection feels immediate.
    this.refreshHighlightsAndLabels();

    // Notify click
    this.onCellClick?.(cell);

    if (isDoubleClick) {
      this.onCellDoubleClick?.(cell);
    }
  }

  private makeCellLabel(cell: FormationCell): string {
    const unitId = this.formation[cell];
    if (this.getCellLabel) return this.getCellLabel(cell, unitId);

    if (!unitId) return `${cell}\n(Empty)`;
    return `${cell}\n${unitId}`;
  }

  private refreshHighlightsAndLabels(): void {
    for (const cell of CELLS) {
      const rect = this.cellRects[cell];
      const txt = this.cellTexts[cell];

      const isSelected = this.selectedCell === cell;
      rect.setStrokeStyle(
        2,
        isSelected ? this.colors.selectedStroke : this.colors.stroke,
        isSelected ? this.colors.selectedStrokeAlpha : this.colors.strokeAlpha
      );

      txt.setText(this.makeCellLabel(cell));
    }
  }

  // ---------------------------
  // Public API
  // ---------------------------

  /**
   * Replace the whole formation map. Missing cells are set to null.
   */
  public setFormation(next: Partial<FormationMap>): this {
    for (const c of CELLS) {
      this.formation[c] = Object.prototype.hasOwnProperty.call(next, c) ? (next[c] ?? null) : null;
    }
    this.refreshHighlightsAndLabels();
    return this;
  }

  /**
   * Get a defensive copy of the current formation map.
   */
  public getFormation(): FormationMap {
    const out = {} as FormationMap;
    for (const c of CELLS) out[c] = this.formation[c];
    return out;
  }

  public setCell(cell: FormationCell, unitId: string | null): this {
    this.formation[cell] = unitId;
    this.refreshHighlightsAndLabels();
    return this;
  }

  public getCell(cell: FormationCell): string | null {
    return this.formation[cell];
  }

  public setSelectedCell(cell: FormationCell | null): this {
    this.selectedCell = cell;
    this.refreshHighlightsAndLabels();
    return this;
  }

  public getSelectedCell(): FormationCell | null {
    return this.selectedCell;
  }

  /**
   * If you change label rules dynamically (e.g. once units load), call this.
   */
  public refresh(): this {
    this.refreshHighlightsAndLabels();
    return this;
  }

  /**
   * Cleanly destroy internal children and detach event listeners.
   */
  public override destroy(fromScene?: boolean): void {
    // Ensure interactive objects are destroyed properly
    for (const cell of CELLS) {
      this.cellRects[cell]?.removeAllListeners();
    }
    super.destroy(fromScene);
  }
}

