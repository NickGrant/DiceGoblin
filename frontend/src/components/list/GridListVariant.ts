import Phaser from "phaser";

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
  items: GridListItem<T>[];
  columns?: number;
  gapX?: number;
  gapY?: number;
  cardHeight?: number;
  onSelect?: (item: T) => void;
  cardRenderer: GridCardRenderer<T>;
};

export default class GridListVariant<T> extends Phaser.GameObjects.Container {
  constructor(cfg: GridListVariantConfig<T>) {
    super(cfg.scene, cfg.x, cfg.y);

    const columns = Math.max(1, cfg.columns ?? 3);
    const gapX = cfg.gapX ?? 10;
    const gapY = cfg.gapY ?? 10;
    const cardW = Math.floor((cfg.width - gapX * (columns - 1)) / columns);
    const cardH = cfg.cardHeight ?? cardW;

    for (let i = 0; i < cfg.items.length; i += 1) {
      const row = cfg.items[i];
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

    cfg.scene.add.existing(this);
  }
}
