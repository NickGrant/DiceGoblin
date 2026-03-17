import Phaser from "phaser";
import { computeGridVisibleCapacity } from "./gridListMath";

export type GridCardRenderer<T> = (params: {
  scene: Phaser.Scene;
  item: T;
  x: number;
  y: number;
  width: number;
  height: number;
  selected: boolean;
  disabled: boolean;
}) => Phaser.GameObjects.GameObject | Phaser.GameObjects.GameObject[];

export type GridListItem<T> = {
  item: T;
  selected?: boolean;
  disabled?: boolean;
};

export type GridListVariantConfig<T> = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  width: number;
  height?: number;
  items: GridListItem<T>[];
  columns?: number;
  gapX?: number;
  gapY?: number;
  cardHeight?: number;
  onSelect?: (item: T) => void;
  cardRenderer: GridCardRenderer<T>;
};

export default class GridListVariant<T> extends Phaser.GameObjects.Container {
  public static computeVisibleCapacity(params: {
    width: number;
    height: number;
    columns?: number;
    gapY?: number;
    cardHeight: number;
  }): number {
    return computeGridVisibleCapacity({
      height: params.height,
      columns: params.columns,
      gapY: params.gapY,
      cardHeight: params.cardHeight,
    });
  }

  constructor(cfg: GridListVariantConfig<T>) {
    super(cfg.scene, cfg.x, cfg.y);

    const columns = Math.max(1, cfg.columns ?? 3);
    const gapX = cfg.gapX ?? 10;
    const gapY = cfg.gapY ?? 10;
    const cardW = Math.floor((cfg.width - gapX * (columns - 1)) / columns);
    const cardH = cfg.cardHeight ?? cardW;
    const visibleCapacity = typeof cfg.height === "number"
      ? GridListVariant.computeVisibleCapacity({
        width: cfg.width,
        height: cfg.height,
        columns,
        gapY,
        cardHeight: cardH,
      })
      : cfg.items.length;

    const visibleItems = cfg.items.slice(0, Math.max(0, visibleCapacity));

    for (let i = 0; i < visibleItems.length; i += 1) {
      const row = visibleItems[i];
      if (!row) continue;
      const col = i % columns;
      const line = Math.floor(i / columns);
      const x = col * (cardW + gapX);
      const y = line * (cardH + gapY);
      const selected = row.selected ?? false;
      const disabled = row.disabled ?? false;

      const rendered = cfg.cardRenderer({
        scene: cfg.scene,
        item: row.item,
        x,
        y,
        width: cardW,
        height: cardH,
        selected,
        disabled,
      });

      if (Array.isArray(rendered)) this.add(rendered);
      else this.add(rendered);

      const hit = cfg.scene.add.zone(x + cardW / 2, y + cardH / 2, cardW, cardH).setOrigin(0.5, 0.5);
      if (!disabled && cfg.onSelect) {
        hit.setInteractive({ useHandCursor: true });
        hit.on("pointerdown", () => cfg.onSelect?.(row.item));
      }
      this.add(hit);
    }

    const renderedRows = Math.max(1, Math.ceil(visibleItems.length / columns));
    const renderedHeight = renderedRows * cardH + Math.max(0, renderedRows - 1) * gapY;
    this.setSize(cfg.width, renderedHeight);

    cfg.scene.add.existing(this);
  }
}
