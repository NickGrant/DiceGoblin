import Phaser from "phaser";
import { DICE_ATLAS_KEY, getDiceFrameName } from "../assets/diceAtlas";
import type { DiceRecord, UnitRecord } from "../types/ApiResponse";
import GridListVariant, { type GridListItem } from "./list/GridListVariant";
import ListContainer from "./list/ListContainer";

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
  diceVisualsById?: Map<string, Pick<DiceRecord, "rarity" | "sides">>;
};

const DEFAULT_COLUMNS = 3;
const CARD_GAP_X = 10;
const CARD_GAP_Y = 12;
const CARD_FOOTER_HEIGHT = 50;
const DICE_SLOT_ICON_SIZE = 16;
const DICE_SLOT_GAP = 3;
const DICE_SLOT_TOP = 8;
const DICE_SLOT_RIGHT = 8;
const RARITY_TO_MATERIAL: Record<string, "cardboard" | "wood" | "bone" | "metal" | "gemstone"> = {
  common: "cardboard",
  uncommon: "wood",
  rare: "bone",
  epic: "metal",
  legendary: "gemstone",
};

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
  private readonly diceVisualsById?: Map<string, Pick<DiceRecord, "rarity" | "sides">>;

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
    this.diceVisualsById = cfg.diceVisualsById;

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
          columns: DEFAULT_COLUMNS,
          gapX: CARD_GAP_X,
          gapY: CARD_GAP_Y,
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
              .setDisplaySize(Math.min(width - 26, 70), Math.min(width - 26, 70))
              .setOrigin(0.5, 0.5)
              .setAlpha(0.9);

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

            const diceSlotObjects = this.createDiceSlotObjects(cardScene, item, width);

            card.add([bg, portraitBg, cornerMarker, portraitIcon, levelText, nameText, badgeText, ...diceSlotObjects]);
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
          columns: DEFAULT_COLUMNS,
          gapY: CARD_GAP_Y,
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
    const cardWidth = Math.min(132, Math.floor((contentWidth - CARD_GAP_X * (DEFAULT_COLUMNS - 1)) / DEFAULT_COLUMNS));
    return cardWidth + CARD_FOOTER_HEIGHT;
  }

  private createDiceSlotObjects(
    scene: Phaser.Scene,
    unit: UnitRecord,
    cardWidth: number,
  ): Phaser.GameObjects.GameObject[] {
    const slotCount = this.getUnitDiceSlotCount(unit);
    const equippedSlots = new Set<number>(
      Array.isArray(unit.equipped_dice)
        ? unit.equipped_dice
            .map((entry) => Number(entry?.slot_index))
            .filter((slotIndex) => Number.isFinite(slotIndex) && slotIndex >= 0)
        : [],
    );
    const objects: Phaser.GameObjects.GameObject[] = [];
    const totalWidth = slotCount * DICE_SLOT_ICON_SIZE + Math.max(0, slotCount - 1) * DICE_SLOT_GAP;
    let slotX = cardWidth - DICE_SLOT_RIGHT - totalWidth;

    for (let i = 0; i < slotCount; i += 1) {
      const isFilled = equippedSlots.has(i);
      if (isFilled && scene.textures.exists(DICE_ATLAS_KEY)) {
        const equippedDie = Array.isArray(unit.equipped_dice)
          ? unit.equipped_dice.find((entry) => Number(entry?.slot_index) === i)
          : undefined;
        const visual = equippedDie ? this.diceVisualsById?.get(equippedDie.dice_instance_id) : undefined;
        const rarity = String(visual?.rarity ?? "common").toLowerCase();
        const sides = Number(visual?.sides ?? 6);
        const material = RARITY_TO_MATERIAL[rarity] ?? "cardboard";
        const size = ([4, 6, 8, 10, 12, 20] as const).includes(sides as 4 | 6 | 8 | 10 | 12 | 20)
          ? (`d${sides}` as "d4" | "d6" | "d8" | "d10" | "d12" | "d20")
          : "d6";
        const frame = getDiceFrameName(material, size);
        const icon = scene.add
          .image(slotX + DICE_SLOT_ICON_SIZE / 2, DICE_SLOT_TOP + DICE_SLOT_ICON_SIZE / 2, DICE_ATLAS_KEY, frame)
          .setDisplaySize(DICE_SLOT_ICON_SIZE, DICE_SLOT_ICON_SIZE)
          .setOrigin(0.5, 0.5)
          .setAlpha(0.98);
        objects.push(icon);
      } else {
        const empty = scene.add
          .rectangle(slotX, DICE_SLOT_TOP, DICE_SLOT_ICON_SIZE, DICE_SLOT_ICON_SIZE, 0xffffff, 0.12)
          .setOrigin(0, 0)
          .setStrokeStyle(1, 0xffffff, 0.85);
        objects.push(empty);
      }
      slotX += DICE_SLOT_ICON_SIZE + DICE_SLOT_GAP;
    }

    return objects;
  }

  private getUnitDiceSlotCount(unit: UnitRecord): number {
    const explicit = Number((unit as Record<string, unknown>).max_equipped_dice ?? 0);
    if (Number.isFinite(explicit) && explicit > 0) {
      return Math.max(1, Math.floor(explicit));
    }

    const equipped = Array.isArray(unit.equipped_dice) ? unit.equipped_dice : [];
    const maxSlotIndex = equipped.reduce((max, item) => {
      const slotIndex = Number(item?.slot_index ?? -1);
      return Number.isFinite(slotIndex) ? Math.max(max, slotIndex) : max;
    }, -1);

    return Math.max(2, maxSlotIndex + 1);
  }
}
