import Phaser from "phaser";
import type { UnitRecord } from "../types/ApiResponse";
import GridListVariant, { type GridListItem } from "./list/GridListVariant";
import ListContainer from "./list/ListContainer";
import { fitPortraitImage, resolveUnitPortraitKey } from "../assets/unitPortraits";

export type UnitCardState = {
  highlighted?: boolean;
  outlined?: boolean;
  disabled?: boolean;
  badgeText?: string | null;
  cornerColor?: number;
  cornerAlpha?: number;
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
  columns?: number;
  maxCardWidth?: number;
  footerHeight?: number;
  gapX?: number;
  gapY?: number;
};

const DEFAULT_COLUMNS = 3;
const DEFAULT_CARD_GAP_X = 10;
const DEFAULT_CARD_GAP_Y = 12;
const DEFAULT_CARD_FOOTER_HEIGHT = 50;

export default class UnitCardGrid extends Phaser.GameObjects.Container {
  private readonly sceneRef: Phaser.Scene;
  private readonly panelW: number;
  private readonly panelH: number;
  private readonly title: string;
  private readonly onUnitClick?: (unit: UnitRecord) => void;

  private units: UnitRecord[] = [];
  private filter?: (unit: UnitRecord) => boolean;
  private getCardState?: (unit: UnitRecord) => UnitCardState;
  private pageIndex = 0;
  private readonly maxVisibleCards?: number;
  private readonly columns: number;
  private readonly maxCardWidth: number;
  private readonly footerHeight: number;
  private readonly gapX: number;
  private readonly gapY: number;

  private listContainer: ListContainer<UnitRecord>;

  constructor(cfg: UnitCardGridConfig) {
    super(cfg.scene, cfg.x, cfg.y);

    this.sceneRef = cfg.scene;
    this.panelW = cfg.width;
    this.panelH = cfg.height;
    this.title = cfg.title ?? "UNITS";
    this.onUnitClick = cfg.onUnitClick;
    this.filter = cfg.filter;
    this.getCardState = cfg.getCardState;
    this.maxVisibleCards = cfg.maxVisibleCards;
    this.units = cfg.units ?? [];
    this.columns = Math.max(1, cfg.columns ?? DEFAULT_COLUMNS);
    this.maxCardWidth = Math.max(72, cfg.maxCardWidth ?? 132);
    this.footerHeight = Math.max(28, cfg.footerHeight ?? DEFAULT_CARD_FOOTER_HEIGHT);
    this.gapX = Math.max(0, cfg.gapX ?? DEFAULT_CARD_GAP_X);
    this.gapY = Math.max(0, cfg.gapY ?? DEFAULT_CARD_GAP_Y);

    this.listContainer = new ListContainer<UnitRecord>({
      scene: this.sceneRef,
      x: 0,
      y: 0,
      width: this.panelW,
      height: this.panelH,
      title: this.title,
      items: this.getVisibleUnits(),
      loadState: "ready",
      pageIndex: this.pageIndex,
      renderItems: ({ scene, parent, items, contentX, contentY, contentWidth, contentHeight, onSelect }) => {
        const mappedItems: GridListItem<UnitRecord>[] = items.map((unit) => {
          const state = this.getCardState ? this.getCardState(unit) : undefined;
          return {
            item: unit,
            selected: Boolean(state?.highlighted),
            disabled: Boolean(state?.disabled),
          };
        });

        const variant = new GridListVariant<UnitRecord>({
          scene,
          x: contentX,
          y: contentY,
          width: contentWidth,
          height: contentHeight,
          columns: this.columns,
          gapX: this.gapX,
          gapY: this.gapY,
          cardHeight: this.getCardHeight(contentWidth),
          items: mappedItems,
          onSelect,
          cardRenderer: ({ scene: cardScene, item, x, y, width, height, selected, disabled }) => {
            const state = this.getCardState ? this.getCardState(item) : {};
            const outlined = Boolean(state?.outlined);
            const badgeTextValue = state?.badgeText ?? "";
            const cornerColor = state?.cornerColor ?? 0x111111;
            const cornerAlpha = state?.cornerAlpha ?? 0.95;

            const card = cardScene.add.container(x, y);
            const fillColor = selected ? 0x2a2f2f : 0x121212;
            const strokeColor = outlined || selected ? 0xffcc00 : 0xffffff;
            const strokeAlpha = outlined || selected ? 0.7 : 0.2;

            const bg = cardScene.add
              .rectangle(0, 0, width, height, fillColor, 0.92)
              .setOrigin(0, 0)
              .setStrokeStyle(1, strokeColor, strokeAlpha);

            const portraitBg = cardScene.add
              .rectangle(6, 6, width - 12, width - 12, 0x252525, 1)
              .setOrigin(0, 0)
              .setStrokeStyle(1, 0xffffff, 0.15);

            const cornerMarker = cardScene.add
              .rectangle(10, 10, 10, 10, cornerColor, cornerAlpha)
              .setOrigin(0, 0)
              .setStrokeStyle(1, 0xffffff, cornerColor === 0x111111 ? 0.08 : 0.35);

            const portraitIcon = cardScene.add
              .image(width / 2, 6 + (width - 12) / 2, "icon_warband")
              .setOrigin(0.5, 0.5)
              .setAlpha(0.9);
            fitPortraitImage(
              portraitIcon,
              resolveUnitPortraitKey(item.unit_type_slug, item.unit_type_name),
              Math.min(width - 26, 70),
              Math.min(width - 26, 70),
            );

            const level = typeof item.level === "number" ? item.level : 1;
            const levelText = cardScene.add
              .text(width - 10, width - 10, `Lv ${level}`, {
                fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
                fontSize: "12px",
                color: "#fff2c9",
                backgroundColor: "rgba(0,0,0,0.45)",
                padding: { left: 4, right: 4, top: 1, bottom: 1 },
              })
              .setOrigin(1, 1);

            const nameText = cardScene.add
              .text(width / 2, width + 1, item.name ?? `Unit ${item.id}`, {
                fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
                fontSize: "13px",
                color: "#f0f0f0",
                align: "center",
                wordWrap: { width: width - 10 },
              })
              .setOrigin(0.5, 0);

            const badgeText = cardScene.add
              .text(8, 8, badgeTextValue, {
                fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
                fontSize: "11px",
                color: "#ffe07e",
                backgroundColor: "rgba(0,0,0,0.55)",
                padding: { left: 3, right: 3, top: 1, bottom: 1 },
              })
              .setOrigin(0, 0);

            card.add([bg, portraitBg, cornerMarker, portraitIcon, levelText, nameText, badgeText]);
            card.setAlpha(disabled ? 0.45 : 1);
            return card;
          },
        });

        parent.add(variant);
        return [variant];
      },
      getPageSize: ({ contentWidth, contentHeight }) => {
        if (this.maxVisibleCards && this.maxVisibleCards > 0) {
          return this.maxVisibleCards;
        }
        const cardHeight = this.getCardHeight(contentWidth);
        return GridListVariant.computeVisibleCapacity({
          width: contentWidth,
          height: contentHeight,
          columns: this.columns,
          gapY: this.gapY,
          cardHeight,
        });
      },
      onSelect: (unit) => this.onUnitClick?.(unit),
      emptyMessage: "No units found.",
    });

    this.add(this.listContainer);
    cfg.scene.add.existing(this);
  }

  public setUnits(units: UnitRecord[]): this {
    this.units = units ?? [];
    this.pageIndex = 0;
    this.syncList();
    return this;
  }

  public setFilter(filter?: (unit: UnitRecord) => boolean): this {
    this.filter = filter;
    this.pageIndex = 0;
    this.syncList();
    return this;
  }

  public setCardStateProvider(getCardState?: (unit: UnitRecord) => UnitCardState): this {
    this.getCardState = getCardState;
    this.syncList();
    return this;
  }

  public refreshCardStates(): this {
    this.syncList();
    return this;
  }

  public setPage(pageIndex: number): this {
    this.pageIndex = Math.max(0, pageIndex);
    this.syncList();
    return this;
  }

  private syncList(): void {
    this.listContainer.updateState({
      items: this.getVisibleUnits(),
      loadState: "ready",
      pageIndex: this.pageIndex,
    });
  }

  private getVisibleUnits(): UnitRecord[] {
    return this.filter ? this.units.filter(this.filter) : this.units;
  }

  private getCardHeight(contentWidth: number): number {
    const cardWidth = Math.min(this.maxCardWidth, Math.floor((contentWidth - this.gapX * (this.columns - 1)) / this.columns));
    return cardWidth + this.footerHeight;
  }
}
