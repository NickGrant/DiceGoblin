import Phaser from "phaser";
import type { UnitRecord } from "../types/ApiResponse";

export type UnitListRowState = {
  highlighted?: boolean;
  outlined?: boolean;
  disabled?: boolean;
  badgeText?: string | null;
};

export type UnitListPanelConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  width: number;
  height: number;
  title?: string;
  units?: UnitRecord[];
  maxVisibleRows?: number;
  onUnitClick?: (unit: UnitRecord) => void;
  getRowState?: (unit: UnitRecord) => UnitListRowState;
  filter?: (unit: UnitRecord) => boolean;
  colors?: {
    panelFill?: number;
    panelAlpha?: number;
    panelStroke?: number;
    panelStrokeAlpha?: number;
    titleColor?: string;
    rowFill?: number;
    rowFillAlt?: number;
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
  private prevPageText?: Phaser.GameObjects.Text;
  private nextPageText?: Phaser.GameObjects.Text;
  private pageLabelText?: Phaser.GameObjects.Text;

  private units: UnitRecord[] = [];
  private filteredUnits: UnitRecord[] = [];
  private rows: RowUi[] = [];
  private pageIndex = 0;

  private readonly onUnitClick?: (unit: UnitRecord) => void;
  private getRowState?: (unit: UnitRecord) => UnitListRowState;
  private filter?: (unit: UnitRecord) => boolean;

  private readonly maxVisibleRows: number;

  private readonly colors: Required<NonNullable<UnitListPanelConfig["colors"]>>;
  private readonly textStyle?: Phaser.Types.GameObjects.Text.TextStyle;

  private readonly rowH = 28;
  private readonly rowGap = 4;
  private readonly pad = 14;
  private readonly titleH = 22;
  private readonly pagerH = 24;

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

    if (cfg.maxVisibleRows && cfg.maxVisibleRows > 0) {
      this.maxVisibleRows = cfg.maxVisibleRows;
    } else {
      const usableH = this.panelH - this.titleH - this.pad * 2 - this.pagerH;
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
        fontSize: "16px",
        color: this.colors.titleColor,
        ...(this.textStyle ?? {}),
      })
      .setOrigin(0, 0);

    const pagerY = this.panelH - this.pad - 10;
    this.prevPageText = this.scene.add
      .text(this.panelX + this.pad, pagerY, "< Prev", {
        fontFamily: "Arial",
        fontSize: "13px",
        color: "#d6d6d6",
      })
      .setOrigin(0, 1)
      .setInteractive({ useHandCursor: true });

    this.pageLabelText = this.scene.add
      .text(this.panelX + this.panelW / 2, pagerY, "Page 1/1", {
        fontFamily: "Arial",
        fontSize: "13px",
        color: "#cfcfcf",
      })
      .setOrigin(0.5, 1);

    this.nextPageText = this.scene.add
      .text(this.panelX + this.panelW - this.pad, pagerY, "Next >", {
        fontFamily: "Arial",
        fontSize: "13px",
        color: "#d6d6d6",
      })
      .setOrigin(1, 1)
      .setInteractive({ useHandCursor: true });

    this.prevPageText.on("pointerdown", () => this.setPage(this.pageIndex - 1));
    this.nextPageText.on("pointerdown", () => this.setPage(this.pageIndex + 1));

    this.add([this.panelBg, this.titleText, this.prevPageText, this.pageLabelText, this.nextPageText]);
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

    this.filteredUnits = this.filter ? this.units.filter(this.filter) : [...this.units];
    const pageCount = Math.max(1, Math.ceil(this.filteredUnits.length / this.maxVisibleRows));
    if (this.pageIndex > pageCount - 1) this.pageIndex = pageCount - 1;
    const startIdx = this.pageIndex * this.maxVisibleRows;
    const visibleUnits = this.filteredUnits.slice(startIdx, startIdx + this.maxVisibleRows);
    this.updatePagerUi(pageCount);

    const startX = this.panelX + this.pad;
    let y = this.panelY + this.pad + this.titleH;
    const rowW = this.panelW - this.pad * 2;

    let idx = 0;
    for (const unit of visibleUnits) {
      const rowContainer = this.scene.add.container(startX, y);
      const baseFill = idx % 2 === 0 ? this.colors.rowFill : this.colors.rowFillAlt;

      const bg = this.scene.add
        .rectangle(0, 0, rowW, this.rowH, baseFill, this.colors.rowAlpha)
        .setOrigin(0, 0)
        .setStrokeStyle(1, this.colors.panelStroke, this.colors.strokeAlpha);

      const label = this.scene.add
        .text(8, 0, this.getDefaultLabel(unit), {
          fontFamily: "Arial",
          fontSize: "14px",
          color: this.colors.textColor,
          ...(this.textStyle ?? {}),
        })
        .setOrigin(0, 0);

      const badge = this.scene.add
        .text(rowW - 8, 0, "", {
          fontFamily: "Arial",
          fontSize: "12px",
          color: this.colors.badgeColor,
          ...(this.textStyle ?? {}),
        })
        .setOrigin(1, 0);

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

  private updatePagerUi(pageCount: number): void {
    const canPage = this.filteredUnits.length > this.maxVisibleRows;
    const prevEnabled = canPage && this.pageIndex > 0;
    const nextEnabled = canPage && this.pageIndex < pageCount - 1;

    this.pageLabelText?.setText(`Page ${this.pageIndex + 1}/${pageCount}`);
    this.pageLabelText?.setAlpha(canPage ? 1 : 0.55);
    this.prevPageText?.setAlpha(prevEnabled ? 1 : 0.35);
    this.nextPageText?.setAlpha(nextEnabled ? 1 : 0.35);
  }

  private applyRowState(row: RowUi): void {
    const state = this.getRowState ? this.getRowState(row.unit) : ({} as UnitListRowState);

    const fill = state.highlighted ? this.colors.highlightedRowFill : row.baseFill;
    row.bg.setFillStyle(fill, this.colors.rowAlpha);

    const strokeColor = state.outlined ? this.colors.outlinedStroke : this.colors.panelStroke;
    const strokeAlpha = state.outlined ? this.colors.outlinedStrokeAlpha : this.colors.strokeAlpha;
    row.bg.setStrokeStyle(1, strokeColor, strokeAlpha);

    row.badge.setText(state.badgeText ?? "");

    const disabled = !!state.disabled;
    row.container.setAlpha(disabled ? this.colors.disabledAlpha : 1);

    row.hit.disableInteractive();
    if (!disabled) {
      row.hit.setInteractive({ useHandCursor: true });
    }
  }

  public setTitle(title: string): this {
    this.titleText?.setText(title);
    return this;
  }

  public setUnits(units: UnitRecord[]): this {
    this.units = units ?? [];
    this.pageIndex = 0;
    this.buildRows();
    return this;
  }

  public setFilter(filter?: (unit: UnitRecord) => boolean): this {
    this.filter = filter;
    this.pageIndex = 0;
    this.buildRows();
    return this;
  }

  public setPage(pageIndex: number): this {
    const total = this.filter ? this.units.filter(this.filter).length : this.units.length;
    const pageCount = Math.max(1, Math.ceil(total / this.maxVisibleRows));
    this.pageIndex = Phaser.Math.Clamp(pageIndex, 0, pageCount - 1);
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
    const startIdx = this.pageIndex * this.maxVisibleRows;
    return visible.slice(startIdx, startIdx + this.maxVisibleRows);
  }

  public override destroy(fromScene?: boolean): void {
    this.clearRows();
    super.destroy(fromScene);
  }
}
