import Phaser from "phaser";
import type { UnitRecord } from "../types/ApiResponse";

export type UnitListRowState = {
  /** subtle highlight (e.g. in-team) */
  highlighted?: boolean;
  /** stronger outline (e.g. placed on formation grid) */
  outlined?: boolean;
  /** disables interaction + dims row */
  disabled?: boolean;
  /** optional right-side badge text ("PLACED", "LOCKED", etc.) */
  badgeText?: string | null;
};

export type UnitListPanelConfig = {
  scene: Phaser.Scene;

  /** top-left of the panel */
  x: number;
  y: number;

  width: number;
  height: number;

  title?: string;

  /** Units to render. Can be changed later via setUnits(). */
  units?: UnitRecord[];

  /**
   * Max number of visible rows (MVP). If omitted, it will compute based on height.
   * No scrolling/pagination is implemented in this first version.
   */
  maxVisibleRows?: number;

  /**
   * Called when a unit row is clicked.
   */
  onUnitClick?: (unit: UnitRecord) => void;

  /**
   * Optional state provider used to drive row visuals and interaction.
   * If omitted, all rows are interactive and use a neutral style.
   */
  getRowState?: (unit: UnitRecord) => UnitListRowState;

  /**
   * Optional filter. If provided, units that return false are hidden.
   */
  filter?: (unit: UnitRecord) => boolean;

  /**
   * Panel + row visuals
   */
  colors?: {
    panelFill?: number;
    panelAlpha?: number;
    panelStroke?: number;
    panelStrokeAlpha?: number;

    titleColor?: string;

    rowFill?: number;
    rowFillAlt?: number; // optional alternating
    rowAlpha?: number;

    highlightedRowFill?: number;

    stroke?: number;
    strokeAlpha?: number;

    outlinedStroke?: number;
    outlinedStrokeAlpha?: number;

    disabledAlpha?: number;

    textColor?: string;
    badgeColor?: string;
  };

  /**
   * Row text style overrides. (fontFamily etc.)
   */
  textStyle?: Phaser.Types.GameObjects.Text.TextStyle;
};

type RowUi = {
  container: Phaser.GameObjects.Container;
  bg: Phaser.GameObjects.Rectangle;
  label: Phaser.GameObjects.Text;
  badge: Phaser.GameObjects.Text;
  hit: Phaser.GameObjects.Zone;
  unit: UnitRecord;
  baseFill: number;
};

export default class UnitListPanel extends Phaser.GameObjects.Container {
  private readonly panelX: number;
  private readonly panelY: number;
  private readonly panelW: number;
  private readonly panelH: number;

  private titleText?: Phaser.GameObjects.Text;
  private panelBg?: Phaser.GameObjects.Rectangle;

  private units: UnitRecord[] = [];
  private rows: RowUi[] = [];

  private readonly onUnitClick?: (unit: UnitRecord) => void;
  private getRowState?: (unit: UnitRecord) => UnitListRowState;
  private filter?: (unit: UnitRecord) => boolean;

  private readonly maxVisibleRows: number;

  private readonly colors: Required<NonNullable<UnitListPanelConfig["colors"]>>;
  private readonly textStyle?: Phaser.Types.GameObjects.Text.TextStyle;

  // Row layout
  private readonly rowH = 26;
  private readonly rowGap = 4;
  private readonly pad = 14;
  private readonly titleH = 22;

  constructor(cfg: UnitListPanelConfig) {
    super(cfg.scene, cfg.x, cfg.y);

    this.panelX = 0;
    this.panelY = 0;
    this.panelW = cfg.width;
    this.panelH = cfg.height;

    this.onUnitClick = cfg.onUnitClick;
    this.getRowState = cfg.getRowState;
    this.filter = cfg.filter;

    this.textStyle = cfg.textStyle;

    this.colors = {
      panelFill: cfg.colors?.panelFill ?? 0x000000,
      panelAlpha: cfg.colors?.panelAlpha ?? 0.25,
      panelStroke: cfg.colors?.panelStroke ?? 0xffffff,
      panelStrokeAlpha: cfg.colors?.panelStrokeAlpha ?? 0.2,

      titleColor: cfg.colors?.titleColor ?? "#ffffff",

      rowFill: cfg.colors?.rowFill ?? 0x111111,
      rowFillAlt: cfg.colors?.rowFillAlt ?? 0x111111,
      rowAlpha: cfg.colors?.rowAlpha ?? 0.9,

      highlightedRowFill: cfg.colors?.highlightedRowFill ?? 0x2a2a2a,

      stroke: cfg.colors?.stroke ?? 0xffffff,
      strokeAlpha: cfg.colors?.strokeAlpha ?? 0.2,

      outlinedStroke: cfg.colors?.outlinedStroke ?? 0xffcc00,
      outlinedStrokeAlpha: cfg.colors?.outlinedStrokeAlpha ?? 0.6,

      disabledAlpha: cfg.colors?.disabledAlpha ?? 0.45,

      textColor: cfg.colors?.textColor ?? "#ffffff",
      badgeColor: cfg.colors?.badgeColor ?? "#dddddd",
    };

    // Compute rows if not provided
    if (cfg.maxVisibleRows && cfg.maxVisibleRows > 0) {
      this.maxVisibleRows = cfg.maxVisibleRows;
    } else {
      const usableH = this.panelH - this.titleH - this.pad * 2;
      this.maxVisibleRows = Math.max(1, Math.floor(usableH / (this.rowH + this.rowGap)));
    }

    this.buildPanel(cfg.title ?? "UNITS");
    this.setUnits(cfg.units ?? []);

    cfg.scene.add.existing(this);
  }

  private buildPanel(title: string): void {
    this.removeAll(true);

    this.panelBg = this.scene.add
      .rectangle(this.panelX, this.panelY, this.panelW, this.panelH, this.colors.panelFill, this.colors.panelAlpha)
      .setOrigin(0, 0)
      .setStrokeStyle(2, this.colors.panelStroke, this.colors.panelStrokeAlpha);

    this.titleText = this.scene.add
      .text(this.panelX + this.pad, this.panelY + this.pad - 2, title, {
        fontFamily: "Arial",
        fontSize: "14px",
        color: this.colors.titleColor,
        ...(this.textStyle ?? {}),
      })
      .setOrigin(0, 0);

    this.add([this.panelBg, this.titleText]);
  }

  private clearRows(): void {
    for (const r of this.rows) {
      r.hit.removeAllListeners();
      r.container.destroy(true);
    }
    this.rows = [];
  }

  private buildRows(): void {
    this.clearRows();

    const visibleUnits = (this.filter ? this.units.filter(this.filter) : this.units).slice(0, this.maxVisibleRows);

    const startX = this.panelX + this.pad;
    let y = this.panelY + this.pad + this.titleH;

    const rowW = this.panelW - this.pad * 2;

    let idx = 0;
    for (const unit of visibleUnits) {
      const rowContainer = this.scene.add.container(startX, y);

      const baseFill = idx % 2 === 0 ? this.colors.rowFill : this.colors.rowFillAlt;

      const bg = this.scene.add
        .rectangle(0, 0, rowW, this.rowH, baseFill, this.colors.rowAlpha)
        .setOrigin(0, 0.5)
        .setStrokeStyle(1, this.colors.panelStroke, this.colors.strokeAlpha);

      const label = this.scene.add
        .text(8, 0, this.getDefaultLabel(unit), {
          fontFamily: "Arial",
          fontSize: "12px",
          color: this.colors.textColor,
          ...(this.textStyle ?? {}),
        })
        .setOrigin(0, 0.5);

      const badge = this.scene.add
        .text(rowW - 8, 0, "", {
          fontFamily: "Arial",
          fontSize: "11px",
          color: this.colors.badgeColor,
          ...(this.textStyle ?? {}),
        })
        .setOrigin(1, 0.5);

      const hit = this.scene.add.zone(rowW / 2, 0, rowW, this.rowH).setInteractive({ useHandCursor: true });

      rowContainer.add([bg, label, badge, hit]);

      const rowUi: RowUi = { container: rowContainer, bg, label, badge, hit, unit, baseFill };

      hit.on("pointerdown", () => {
        const state = this.getRowState?.(unit);
        if (state?.disabled) return;
        this.onUnitClick?.(unit);
      });

      hit.on("pointerover", () => {
        const state = this.getRowState?.(unit);
        if (state?.disabled) return;
        bg.setStrokeStyle(1, this.colors.panelStroke, 0.35);
      });

      hit.on("pointerout", () => {
        const state = this.getRowState?.(unit);
        if (state?.disabled) return;
        bg.setStrokeStyle(1, this.colors.panelStroke, this.colors.strokeAlpha);
      });

      this.applyRowState(rowUi);

      this.rows.push(rowUi);
      this.add(rowContainer);

      y += this.rowH + this.rowGap;
      idx++;
    }
  }

  private getDefaultLabel(unit: UnitRecord): string {
    return `${unit.name}  (Lv ${unit.level})`;
  }

  private applyRowState(row: RowUi): void {
    const state = this.getRowState ? this.getRowState(row.unit) : ({} as UnitListRowState);

    // Reset fill each time, then apply highlight if needed
    const fill = state.highlighted ? this.colors.highlightedRowFill : row.baseFill;
    row.bg.setFillStyle(fill, this.colors.rowAlpha);

    // Outline changes if outlined
    const strokeColor = state.outlined ? this.colors.outlinedStroke : this.colors.panelStroke;
    const strokeAlpha = state.outlined ? this.colors.outlinedStrokeAlpha : this.colors.strokeAlpha;
    row.bg.setStrokeStyle(1, strokeColor, strokeAlpha);

    // Badge
    row.badge.setText(state.badgeText ?? "");

    // Disabled behavior
    const disabled = !!state.disabled;
    row.container.setAlpha(disabled ? this.colors.disabledAlpha : 1);

    row.hit.disableInteractive();
    if (!disabled) {
      row.hit.setInteractive({ useHandCursor: true });
    }
  }

  // ---------------------------
  // Public API
  // ---------------------------

  public setTitle(title: string): this {
    this.titleText?.setText(title);
    return this;
  }

  public setUnits(units: UnitRecord[]): this {
    this.units = units ?? [];
    this.buildRows();
    return this;
  }

  public setFilter(filter?: (unit: UnitRecord) => boolean): this {
    this.filter = filter;
    this.buildRows();
    return this;
  }

  public setRowStateProvider(getRowState?: (unit: UnitRecord) => UnitListRowState): this {
    this.getRowState = getRowState;
    this.refreshRowStates();
    return this;
  }

  public refreshRowStates(): this {
    for (const r of this.rows) {
      this.applyRowState(r);
    }
    return this;
  }

  public getVisibleUnits(): UnitRecord[] {
    const visible = this.filter ? this.units.filter(this.filter) : this.units;
    return visible.slice(0, this.maxVisibleRows);
  }

  public override destroy(fromScene?: boolean): void {
    this.clearRows();
    super.destroy(fromScene);
  }
}
