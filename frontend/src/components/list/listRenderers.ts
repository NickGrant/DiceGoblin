import type Phaser from "phaser";
import GridListVariant, { type GridListItem, type GridListVariantConfig } from "./GridListVariant";
import NameLinkListVariant, { type NameLinkListItem } from "./NameLinkListVariant";

export function renderNameLinkList<T>(params: {
  scene: Phaser.Scene;
  parent: Phaser.GameObjects.Container;
  x: number;
  y: number;
  width: number;
  items: NameLinkListItem<T>[];
  onSelect?: (item: T) => void;
}): Phaser.GameObjects.GameObject[] {
  const variant = new NameLinkListVariant({
    scene: params.scene,
    x: params.x,
    y: params.y,
    width: params.width,
    items: params.items,
    onSelect: params.onSelect,
  });
  params.parent.add(variant);
  return [variant];
}

export function renderGridList<T>(params: {
  scene: Phaser.Scene;
  parent: Phaser.GameObjects.Container;
  x: number;
  y: number;
  width: number;
  items: GridListItem<T>[];
  onSelect?: (item: T) => void;
  columns?: number;
  cardHeight?: number;
  cardRenderer: GridListVariantConfig<T>["cardRenderer"];
}): Phaser.GameObjects.GameObject[] {
  const variant = new GridListVariant({
    scene: params.scene,
    x: params.x,
    y: params.y,
    width: params.width,
    items: params.items,
    onSelect: params.onSelect,
    columns: params.columns,
    cardHeight: params.cardHeight,
    cardRenderer: params.cardRenderer,
  });
  params.parent.add(variant);
  return [variant];
}
