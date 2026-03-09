import Phaser from "phaser";
import type { UnitRecord } from "../types/ApiResponse";

export type UnitCardState = {
  highlighted?: boolean;
  outlined?: boolean;
  disabled?: boolean;
  badgeText?: string | null;
};

type UnitCardGridConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  width: number;
  height: number;
  title?: string;
  units?: UnitRecord[];
  onUnitClick?: (unit: UnitRecord) => void;
  getCardState?: (unit: UnitRecord) => UnitCardState;
  filter?: (unit: UnitRecord) => boolean;
  maxVisibleCards?: number;
};

type CardUi = {
  container: Phaser.GameObjects.Container;
  bg: Phaser.GameObjects.Rectangle;
  portraitBg: Phaser.GameObjects.Rectangle;
  nameText: Phaser.GameObjects.Text;
  levelText: Phaser.GameObjects.Text;
  badgeText: Phaser.GameObjects.Text;
  hit: Phaser.GameObjects.Zone;
  unit: UnitRecord;
};

export default class UnitCardGrid extends Phaser.GameObjects.Container {
  private readonly panelW: number;
  private readonly panelH: number;
  private readonly pad = 12;
  private readonly titleH = 24;
  private readonly pagerH = 22;
  private readonly cols = 3;
  private readonly gapX = 10;
  private readonly gapY = 10;

  private readonly onUnitClick?: (unit: UnitRecord) => void;
  private getCardState?: (unit: UnitRecord) => UnitCardState;
  private filter?: (unit: UnitRecord) => boolean;

  private units: UnitRecord[] = [];
  private filteredUnits: UnitRecord[] = [];
  private cards: CardUi[] = [];
  private pageIndex = 0;
  private readonly maxVisibleCards: number;

  private titleText?: Phaser.GameObjects.Text;
  private panelBg?: Phaser.GameObjects.Rectangle;
  private prevPageText?: Phaser.GameObjects.Text;
  private nextPageText?: Phaser.GameObjects.Text;
  private pageLabelText?: Phaser.GameObjects.Text;

  constructor(cfg: UnitCardGridConfig) {
    super(cfg.scene, cfg.x, cfg.y);

    this.panelW = cfg.width;
    this.panelH = cfg.height;
    this.onUnitClick = cfg.onUnitClick;
    this.getCardState = cfg.getCardState;
    this.filter = cfg.filter;

    if (cfg.maxVisibleCards && cfg.maxVisibleCards > 0) {
      this.maxVisibleCards = cfg.maxVisibleCards;
    } else {
      const cardW = this.getCardW();
      const cardH = cardW + 34;
      const usableH = this.panelH - this.pad * 2 - this.titleH - this.pagerH;
      const rows = Math.max(1, Math.floor((usableH + this.gapY) / (cardH + this.gapY)));
      this.maxVisibleCards = rows * this.cols;
    }

    this.buildPanel(cfg.title ?? "UNITS");
    this.setUnits(cfg.units ?? []);

    cfg.scene.add.existing(this);
  }

  private getCardW(): number {
    return Math.floor((this.panelW - this.pad * 2 - this.gapX * (this.cols - 1)) / this.cols);
  }

  private buildPanel(title: string): void {
    this.removeAll(true);

    this.panelBg = this.scene.add
      .rectangle(0, 0, this.panelW, this.panelH, 0x000000, 0.2)
      .setOrigin(0, 0)
      .setStrokeStyle(2, 0xffffff, 0.2);

    this.titleText = this.scene.add
      .text(this.pad, this.pad - 2, title, {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#ffffff",
      })
      .setOrigin(0, 0);

    const pagerY = this.panelH - this.pad - 2;
    this.prevPageText = this.scene.add
      .text(this.pad, pagerY, "< Prev", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#d6d6d6",
      })
      .setOrigin(0, 1)
      .setInteractive({ useHandCursor: true });

    this.pageLabelText = this.scene.add
      .text(this.panelW / 2, pagerY, "Page 1/1", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#cfcfcf",
      })
      .setOrigin(0.5, 1);

    this.nextPageText = this.scene.add
      .text(this.panelW - this.pad, pagerY, "Next >", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#d6d6d6",
      })
      .setOrigin(1, 1)
      .setInteractive({ useHandCursor: true });

    this.prevPageText.on("pointerdown", () => this.setPage(this.pageIndex - 1));
    this.nextPageText.on("pointerdown", () => this.setPage(this.pageIndex + 1));

    this.add([this.panelBg, this.titleText, this.prevPageText, this.pageLabelText, this.nextPageText]);
  }

  private clearCards(): void {
    for (const c of this.cards) {
      c.hit.removeAllListeners();
      c.container.destroy(true);
    }
    this.cards = [];
  }

  private buildCards(): void {
    this.clearCards();

    this.filteredUnits = this.filter ? this.units.filter(this.filter) : [...this.units];
    const pageCount = Math.max(1, Math.ceil(this.filteredUnits.length / this.maxVisibleCards));
    if (this.pageIndex > pageCount - 1) this.pageIndex = pageCount - 1;
    const startIdx = this.pageIndex * this.maxVisibleCards;
    const visibleUnits = this.filteredUnits.slice(startIdx, startIdx + this.maxVisibleCards);
    this.updatePagerUi(pageCount);

    const cardW = this.getCardW();
    const cardH = cardW + 34;

    for (let i = 0; i < visibleUnits.length; i += 1) {
      const unit = visibleUnits[i];
      if (!unit) continue;
      const col = i % this.cols;
      const row = Math.floor(i / this.cols);
      const x = this.pad + col * (cardW + this.gapX);
      const y = this.pad + this.titleH + row * (cardH + this.gapY);

      const card = this.scene.add.container(x, y);

      const bg = this.scene.add
        .rectangle(0, 0, cardW, cardH, 0x121212, 0.92)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0xffffff, 0.2);

      const portraitBg = this.scene.add
        .rectangle(6, 6, cardW - 12, cardW - 12, 0x252525, 1)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0xffffff, 0.15);

      const portraitIcon = this.scene.add
        .image(cardW / 2, 6 + (cardW - 12) / 2, "icon_warband")
        .setDisplaySize(Math.min(cardW - 26, 70), Math.min(cardW - 26, 70))
        .setOrigin(0.5, 0.5)
        .setAlpha(0.9);

      const level = typeof unit.level === "number" ? unit.level : 1;
      const levelText = this.scene.add
        .text(cardW - 10, cardW - 10, `Lv ${level}`, {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "12px",
          color: "#fff2c9",
          backgroundColor: "rgba(0,0,0,0.45)",
          padding: { left: 4, right: 4, top: 1, bottom: 1 },
        })
        .setOrigin(1, 1);

      const nameText = this.scene.add
        .text(cardW / 2, cardW + 4, unit.name ?? `Unit ${unit.id}`, {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "13px",
          color: "#f0f0f0",
          align: "center",
          wordWrap: { width: cardW - 10 },
        })
        .setOrigin(0.5, 0);

      const badgeText = this.scene.add
        .text(8, 8, "", {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "11px",
          color: "#ffe07e",
          backgroundColor: "rgba(0,0,0,0.55)",
          padding: { left: 3, right: 3, top: 1, bottom: 1 },
        })
        .setOrigin(0, 0);

      const hit = this.scene.add.zone(cardW / 2, cardH / 2, cardW, cardH).setInteractive({ useHandCursor: true });

      card.add([bg, portraitBg, portraitIcon, levelText, nameText, badgeText, hit]);

      const ui: CardUi = { container: card, bg, portraitBg, nameText, levelText, badgeText, hit, unit };
      this.applyCardState(ui);

      hit.on("pointerdown", () => {
        const state = this.getCardState?.(unit);
        if (state?.disabled) return;
        this.onUnitClick?.(unit);
      });

      hit.on("pointerover", () => {
        const state = this.getCardState?.(unit);
        if (state?.disabled) return;
        ui.bg.setStrokeStyle(1, 0xffffff, 0.4);
      });

      hit.on("pointerout", () => {
        this.applyCardState(ui);
      });

      this.cards.push(ui);
      this.add(card);
    }
  }

  private updatePagerUi(pageCount: number): void {
    const canPage = this.filteredUnits.length > this.maxVisibleCards;
    const prevEnabled = canPage && this.pageIndex > 0;
    const nextEnabled = canPage && this.pageIndex < pageCount - 1;

    this.pageLabelText?.setText(`Page ${this.pageIndex + 1}/${pageCount}`);
    this.pageLabelText?.setAlpha(canPage ? 1 : 0.55);
    this.prevPageText?.setAlpha(prevEnabled ? 1 : 0.35);
    this.nextPageText?.setAlpha(nextEnabled ? 1 : 0.35);
  }

  private applyCardState(card: CardUi): void {
    const state = this.getCardState ? this.getCardState(card.unit) : ({} as UnitCardState);

    const fill = state.highlighted ? 0x2a2f2f : 0x121212;
    const stroke = state.outlined ? 0xffcc00 : 0xffffff;
    const strokeAlpha = state.outlined ? 0.7 : 0.2;

    card.bg.setFillStyle(fill, 0.92);
    card.bg.setStrokeStyle(1, stroke, strokeAlpha);
    card.badgeText.setText(state.badgeText ?? "");
    card.container.setAlpha(state.disabled ? 0.45 : 1);
  }

  public setUnits(units: UnitRecord[]): this {
    this.units = units ?? [];
    this.pageIndex = 0;
    this.buildCards();
    return this;
  }

  public setFilter(filter?: (unit: UnitRecord) => boolean): this {
    this.filter = filter;
    this.pageIndex = 0;
    this.buildCards();
    return this;
  }

  public setCardStateProvider(getCardState?: (unit: UnitRecord) => UnitCardState): this {
    this.getCardState = getCardState;
    this.refreshCardStates();
    return this;
  }

  public refreshCardStates(): this {
    for (const card of this.cards) {
      this.applyCardState(card);
    }
    return this;
  }

  public setPage(pageIndex: number): this {
    const total = this.filter ? this.units.filter(this.filter).length : this.units.length;
    const pageCount = Math.max(1, Math.ceil(total / this.maxVisibleCards));
    this.pageIndex = Phaser.Math.Clamp(pageIndex, 0, pageCount - 1);
    this.buildCards();
    return this;
  }

  public override destroy(fromScene?: boolean): void {
    this.clearCards();
    super.destroy(fromScene);
  }
}

