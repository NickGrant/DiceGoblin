import Phaser from "phaser";

export type FormationCell = "A1" | "B1" | "C1" | "A2" | "B2" | "C2" | "A3" | "B3" | "C3";
export type FormationMap = Record<FormationCell, string | null>;

export type FormationStatusIndicator = {
  label: string | number;
  color: number;
};

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
   * Optional hover events used by read-only views.
   */
  onCellHover?: (cell: FormationCell, unitId: string | null, pointer: Phaser.Input.Pointer) => void;
  onCellOut?: (cell: FormationCell, unitId: string | null) => void;

  /**
   * Allow cell selection/click behavior (default true).
   */
  allowSelection?: boolean;

  /**
   * Optional label generator. If not provided, the grid shows `cell` and "(Empty)" / unitId.
   * Keep it cheap; this is called when cells refresh.
   */
  getCellLabel?: (cell: FormationCell, unitId: string | null) => string;

  /**
   * Optional HP percentage hook for rendering a bottom HP bar on occupied cells.
   * Return null/undefined for no bar.
   */
  getCellHpPercent?: (cell: FormationCell, unitId: string | null) => number | null | undefined;

  /**
   * Optional status chips (top-right) for occupied cells.
   * Each chip uses a solid color and short label text, e.g. remaining rounds.
   */
  getCellStatusIndicators?: (
    cell: FormationCell,
    unitId: string | null
  ) => FormationStatusIndicator[] | null | undefined;

  /**
   * Optional stroke override for tactical emphasis (e.g. acted this tick).
   * Return null/undefined to use default stroke behavior.
   */
  getCellOutlineColor?: (cell: FormationCell, unitId: string | null) => number | null | undefined;

  /**
   * Optional damage indicator text for this cell (e.g. "-12").
   */
  getCellDamageText?: (cell: FormationCell, unitId: string | null) => string | null | undefined;

  /**
   * Optional occupant icon visibility override per cell.
   */
  getCellShowIcon?: (cell: FormationCell, unitId: string | null) => boolean | null | undefined;

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

const CELLS: FormationCell[] = ["A1", "A2", "A3", "B1", "B2", "B3", "C1", "C2", "C3"];

function cellToRowCol(cell: FormationCell): { row: number; col: number } {
  const rowChar = cell[0] || 'A'; // A..C (top to bottom lanes)
  const colChar = cell[1] || '1'; // 1..3 (front to back depth)
  return { col: 3 - parseInt(colChar, 10), row: rowChar.charCodeAt(0) - 65 };
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
  private readonly onCellHover?: (cell: FormationCell, unitId: string | null, pointer: Phaser.Input.Pointer) => void;
  private readonly onCellOut?: (cell: FormationCell, unitId: string | null) => void;
  private readonly allowSelection: boolean;
  private readonly getCellLabel?: (cell: FormationCell, unitId: string | null) => string;
  private readonly getCellHpPercent?: (cell: FormationCell, unitId: string | null) => number | null | undefined;
  private readonly getCellStatusIndicators?: (
    cell: FormationCell,
    unitId: string | null
  ) => FormationStatusIndicator[] | null | undefined;
  private readonly getCellOutlineColor?: (cell: FormationCell, unitId: string | null) => number | null | undefined;
  private readonly getCellDamageText?: (cell: FormationCell, unitId: string | null) => string | null | undefined;
  private readonly getCellShowIcon?: (cell: FormationCell, unitId: string | null) => boolean | null | undefined;

  private readonly doubleClickMs: number;
  private lastClickAtMs = 0;
  private lastClickedCell: FormationCell | null = null;

  private cellRects: Record<FormationCell, Phaser.GameObjects.Rectangle> = {} as any;
  private cellTexts: Record<FormationCell, Phaser.GameObjects.Text> = {} as any;
  private cellIcons: Record<FormationCell, Phaser.GameObjects.Image> = {} as any;
  private cellHpBg: Record<FormationCell, Phaser.GameObjects.Rectangle> = {} as any;
  private cellHpFill: Record<FormationCell, Phaser.GameObjects.Rectangle> = {} as any;
  private cellStatusContainers: Record<FormationCell, Phaser.GameObjects.Container> = {} as any;
  private cellDamageTexts: Record<FormationCell, Phaser.GameObjects.Text> = {} as any;

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
    this.onCellHover = cfg.onCellHover;
    this.onCellOut = cfg.onCellOut;
    this.allowSelection = cfg.allowSelection ?? true;
    this.getCellLabel = cfg.getCellLabel;
    this.getCellHpPercent = cfg.getCellHpPercent;
    this.getCellStatusIndicators = cfg.getCellStatusIndicators;
    this.getCellOutlineColor = cfg.getCellOutlineColor;
    this.getCellDamageText = cfg.getCellDamageText;
    this.getCellShowIcon = cfg.getCellShowIcon;

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

      const icon = this.scene.add
        .image(x + this.cellSize / 2, y + this.cellSize / 2, "icon_warband")
        .setDisplaySize(Math.min(this.cellSize - 34, 64), Math.min(this.cellSize - 34, 64))
        .setOrigin(0.5, 0.5)
        .setVisible(false)
        .setAlpha(0.9);

      const hpBg = this.scene.add
        .rectangle(x + 6, y + this.cellSize - 10, this.cellSize - 12, 6, 0x2a2a2a, 0.9)
        .setOrigin(0, 0)
        .setVisible(false);

      const hpFill = this.scene.add
        .rectangle(x + 6, y + this.cellSize - 10, this.cellSize - 12, 6, 0x60d26b, 0.95)
        .setOrigin(0, 0)
        .setVisible(false);

      const statusContainer = this.scene.add
        .container(0, 0)
        .setVisible(false);

      const damageText = this.scene.add
        .text(x + 6, y + this.cellSize - 22, "", {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "11px",
          color: "#ff8f8f",
          fontStyle: "bold",
        })
        .setOrigin(0, 0)
        .setVisible(false);

      if (this.allowSelection) {
        rect.on("pointerdown", () => this.handleCellPointerDown(cell));
      }
      rect.on("pointerover", (pointer: Phaser.Input.Pointer) => {
        this.onCellHover?.(cell, this.formation[cell], pointer);
      });
      rect.on("pointerout", () => {
        this.onCellOut?.(cell, this.formation[cell]);
      });

      this.cellRects[cell] = rect;
      this.cellTexts[cell] = text;
      this.cellIcons[cell] = icon;
      this.cellHpBg[cell] = hpBg;
      this.cellHpFill[cell] = hpFill;
      this.cellStatusContainers[cell] = statusContainer;
      this.cellDamageTexts[cell] = damageText;

      this.add([rect, icon, hpBg, hpFill, statusContainer, damageText, text]);
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
      const icon = this.cellIcons[cell];
      const hpBg = this.cellHpBg[cell];
      const hpFill = this.cellHpFill[cell];
      const statusContainer = this.cellStatusContainers[cell];
      const damageText = this.cellDamageTexts[cell];

      if (!rect || !txt || !icon || !hpBg || !hpFill || !statusContainer || !damageText) {
        continue;
      }

      const occupied = this.formation[cell] !== null;

      const isSelected = this.selectedCell === cell;
      const strokeColor = isSelected
        ? this.colors.selectedStroke
        : occupied
          ? 0xffcc00
          : this.colors.stroke;
      const strokeAlpha = isSelected
        ? this.colors.selectedStrokeAlpha
        : occupied
          ? 0.75
          : this.colors.strokeAlpha;

      const outlineOverride = this.getCellOutlineColor?.(cell, this.formation[cell]);
      const finalStrokeColor = outlineOverride ?? strokeColor;
      const finalStrokeAlpha = outlineOverride != null ? 0.95 : strokeAlpha;

      rect.setFillStyle(occupied ? 0x1c2428 : this.colors.cellFill, occupied ? 0.98 : this.colors.cellFillAlpha);
      rect.setStrokeStyle(
        2,
        finalStrokeColor,
        finalStrokeAlpha
      );

      const showIcon = occupied && (this.getCellShowIcon?.(cell, this.formation[cell]) ?? true);
      icon.setVisible(showIcon);

      const hpPercentRaw = this.getCellHpPercent?.(cell, this.formation[cell]);
      const hpPercent = typeof hpPercentRaw === "number"
        ? Phaser.Math.Clamp(hpPercentRaw, 0, 1)
        : null;
      const showHp = occupied && hpPercent !== null;
      hpBg.setVisible(showHp);
      hpFill.setVisible(showHp);
      if (showHp && hpPercent !== null) {
        const totalBarWidth = this.cellSize - 12;
        hpFill.setDisplaySize(Math.max(1, Math.round(totalBarWidth * hpPercent)), 6);
      }

      this.refreshCellStatusIndicators(cell, statusContainer, occupied);

      const damageLabel = this.getCellDamageText?.(cell, this.formation[cell]);
      const showDamage = occupied && typeof damageLabel === "string" && damageLabel.trim() !== "";
      damageText.setVisible(showDamage);
      if (showDamage) {
        damageText.setText(damageLabel as string);
      }

      txt.setText(this.makeCellLabel(cell));
    }
  }

  private refreshCellStatusIndicators(
    cell: FormationCell,
    container: Phaser.GameObjects.Container,
    occupied: boolean
  ): void {
    container.removeAll(true);

    const { row, col } = cellToRowCol(cell);
    const cellX = col * (this.cellSize + this.gap);
    const cellY = row * (this.cellSize + this.gap);
    container.setPosition(cellX, cellY);

    if (!occupied) {
      container.setVisible(false);
      return;
    }

    const indicators = this.getCellStatusIndicators?.(cell, this.formation[cell]) ?? [];
    if (!Array.isArray(indicators) || indicators.length === 0) {
      container.setVisible(false);
      return;
    }

    const chipSize = 14;
    const chipGap = 3;
    const maxChips = 3;
    const visibleIndicators = indicators.slice(0, maxChips);

    visibleIndicators.forEach((indicator, index) => {
      const label = String(indicator.label ?? "").slice(0, 2);
      const chipX = this.cellSize - 6 - (index + 1) * chipSize - index * chipGap;
      const chipY = 6;

      const bg = this.scene.add
        .rectangle(chipX, chipY, chipSize, chipSize, indicator.color, 0.95)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0x101010, 0.8);
      const txt = this.scene.add
        .text(chipX + chipSize / 2, chipY + chipSize / 2, label, {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "9px",
          color: "#ffffff",
          align: "center",
        })
        .setOrigin(0.5, 0.5);

      container.add([bg, txt]);
    });

    container.setVisible(true);
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
      this.onCellOut?.(cell, this.formation[cell]);
    }
    super.destroy(fromScene);
  }
}

