import Phaser from "phaser";
import { TEXT_LIST_ROW, UI_TEXT_COLORS } from "../../const/Text";

export type NameLinkListItem<T> = {
  item: T;
  label: string;
  disabled?: boolean;
  selected?: boolean;
};

export type NameLinkListVariantConfig<T> = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  width: number;
  items: NameLinkListItem<T>[];
  onSelect?: (item: T) => void;
  rowHeight?: number;
};

export default class NameLinkListVariant<T> extends Phaser.GameObjects.Container {
  constructor(cfg: NameLinkListVariantConfig<T>) {
    super(cfg.scene, cfg.x, cfg.y);

    const rowHeight = cfg.rowHeight ?? 38;
    for (let i = 0; i < cfg.items.length; i += 1) {
      const row = cfg.items[i];
      if (!row) continue;
      const y = i * rowHeight;

      const bg = cfg.scene.add
        .rectangle(0, y, cfg.width, rowHeight - 2, row.selected ? 0x28322b : 0x1a1a1a, 0.88)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0xffffff, row.selected ? 0.35 : 0.15);

      const text = cfg.scene.add
        .text(10, y + 8, row.label, {
          ...TEXT_LIST_ROW,
          color: row.disabled ? UI_TEXT_COLORS.onDarkDisabled : UI_TEXT_COLORS.onDarkPrimary,
          wordWrap: { width: Math.max(0, cfg.width - 20) },
        })
        .setOrigin(0, 0);

      const hit = cfg.scene.add
        .zone(cfg.width / 2, y + rowHeight / 2, cfg.width, rowHeight)
        .setOrigin(0.5, 0.5);

      if (!row.disabled && cfg.onSelect) {
        hit.setInteractive({ useHandCursor: true });
        hit.on("pointerdown", () => cfg.onSelect?.(row.item));
      }

      this.add([bg, text, hit]);
    }

    cfg.scene.add.existing(this);
  }
}

