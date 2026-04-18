import Phaser from "phaser";
import type { DiceDetailsViewModel } from "../adapters/profileViewModels";
import { DICE_ATLAS_KEY, getDiceFrameName } from "../assets/diceAtlas";
import GridListVariant, { type GridListItem } from "./list/GridListVariant";
import ListContainer from "./list/ListContainer";

type DiceCardGridConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  width: number;
  height: number;
  title?: string;
  dice: DiceDetailsViewModel[];
  selectedDiceId?: string | null;
  onDiceClick?: (die: DiceDetailsViewModel) => void;
  onDiceHover?: (die: DiceDetailsViewModel) => void;
  onDiceHoverEnd?: (die: DiceDetailsViewModel) => void;
  maxVisibleCards?: number;
};

const CARD_GAP_X = 10;
const CARD_GAP_Y = 8;
const MIN_CARD_WIDTH = 86;
const MAX_CARD_WIDTH = 112;
const CARD_FOOTER_HEIGHT = 42;

const RARITY_TO_MATERIAL: Record<string, "cardboard" | "wood" | "bone" | "metal" | "gemstone"> = {
  common: "cardboard",
  uncommon: "wood",
  rare: "bone",
  epic: "metal",
  legendary: "gemstone",
};

function labelFromId(id: string): string {
  return id
    .replace(/[_-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatEquippedLabel(die: DiceDetailsViewModel): string {
  if (!die.equipped) {
    return `${die.rarity} • ${die.affixes.filter((affix) => !affix.empty).length} affix`;
  }

  const slotLabel = `S${die.equipped.slotIndex + 1}`;
  if (die.equipped.abilityId) {
    return `${die.equipped.unitName} • ${labelFromId(die.equipped.abilityId)} ${slotLabel}`;
  }

  return `${die.equipped.unitName} • ${slotLabel}`;
}

export default class DiceCardGrid extends Phaser.GameObjects.Container {
  private readonly sceneRef: Phaser.Scene;
  private readonly panelW: number;
  private readonly panelH: number;
  private readonly title: string;
  private readonly onDiceClick?: (die: DiceDetailsViewModel) => void;
  private readonly onDiceHover?: (die: DiceDetailsViewModel) => void;
  private readonly onDiceHoverEnd?: (die: DiceDetailsViewModel) => void;

  private dice: DiceDetailsViewModel[] = [];
  private selectedDiceId: string | null = null;
  private pageIndex = 0;
  private readonly maxVisibleCards?: number;

  private listContainer: ListContainer<DiceDetailsViewModel>;

  constructor(cfg: DiceCardGridConfig) {
    super(cfg.scene, cfg.x, cfg.y);

    this.sceneRef = cfg.scene;
    this.panelW = cfg.width;
    this.panelH = cfg.height;
    this.title = cfg.title ?? "DICE";
    this.onDiceClick = cfg.onDiceClick;
    this.onDiceHover = cfg.onDiceHover;
    this.onDiceHoverEnd = cfg.onDiceHoverEnd;
    this.maxVisibleCards = cfg.maxVisibleCards;
    this.dice = cfg.dice;
    this.selectedDiceId = cfg.selectedDiceId ?? null;

    this.listContainer = new ListContainer<DiceDetailsViewModel>({
      scene: this.sceneRef,
      x: 0,
      y: 0,
      width: this.panelW,
      height: this.panelH,
      title: this.title,
      items: this.dice,
      loadState: "ready",
      pageIndex: this.pageIndex,
      renderItems: ({ scene, parent, items, contentX, contentY, contentWidth, contentHeight, onSelect }) => {
        const mappedItems: GridListItem<DiceDetailsViewModel>[] = items.map((die) => ({
          item: die,
          selected: die.id === this.selectedDiceId,
          disabled: false,
        }));

        const { columns, cardHeight } = this.resolveGridMetrics(contentWidth);

        const variant = new GridListVariant<DiceDetailsViewModel>({
          scene,
          x: contentX,
          y: contentY,
          width: contentWidth,
          height: contentHeight,
          columns,
          gapX: CARD_GAP_X,
          gapY: CARD_GAP_Y,
          cardHeight,
          items: mappedItems,
          onSelect,
          onHover: (die) => this.onDiceHover?.(die),
          onHoverEnd: (die) => this.onDiceHoverEnd?.(die),
          cardRenderer: ({ scene: cardScene, item, x, y, width, height, selected }) => {
            const card = cardScene.add.container(x, y);

            const bg = cardScene.add
              .rectangle(0, 0, width, height, selected ? 0x2a2f2f : 0x121212, 0.92)
              .setOrigin(0, 0)
              .setStrokeStyle(1, selected ? 0xffcc00 : 0xffffff, selected ? 0.7 : 0.2);

            const portraitBg = cardScene.add
              .rectangle(6, 6, width - 12, width - 12, 0x252525, 1)
              .setOrigin(0, 0)
              .setStrokeStyle(1, 0xffffff, 0.15);

            const sprite = cardScene.add
              .image(width / 2, 6 + (width - 12) / 2, DICE_ATLAS_KEY, this.pickFrame(item))
              .setDisplaySize(Math.min(width - 20, 68), Math.min(width - 20, 68))
              .setOrigin(0.5, 0.5);

            const title = cardScene.add
              .text(width / 2, width + 2, item.displayName, {
                fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
                fontSize: "12px",
                color: "#f0f0f0",
                align: "center",
                wordWrap: { width: width - 8 },
              })
              .setOrigin(0.5, 0);

            const subtitle = cardScene.add
              .text(width / 2, height - 14, formatEquippedLabel(item), {
                fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
                fontSize: "10px",
                color: item.equipped ? "#ccffcc" : "#c8c8c8",
                align: "center",
                wordWrap: { width: width - 8 },
              })
              .setOrigin(0.5, 0.5);

            card.add([bg, portraitBg, sprite, title, subtitle]);
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
        const { columns, cardHeight } = this.resolveGridMetrics(contentWidth);
        return GridListVariant.computeVisibleCapacity({
          width: contentWidth,
          height: contentHeight,
          columns,
          gapY: CARD_GAP_Y,
          cardHeight,
        });
      },
      onSelect: (die) => this.onDiceClick?.(die),
      emptyMessage: "No dice found.",
    });

    this.add(this.listContainer);
    cfg.scene.add.existing(this);
  }

  public setDice(dice: DiceDetailsViewModel[], selectedDiceId: string | null): this {
    this.dice = dice;
    this.selectedDiceId = selectedDiceId;
    this.pageIndex = 0;
    this.syncList();
    return this;
  }

  public setSelectedDiceId(selectedDiceId: string | null): this {
    this.selectedDiceId = selectedDiceId;
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
      items: this.dice,
      loadState: "ready",
      pageIndex: this.pageIndex,
    });
  }

  private resolveGridMetrics(contentWidth: number): { columns: number; cardHeight: number } {
    const safeWidth = Math.max(1, contentWidth);
    const minColumnsForMaxWidth = Math.max(
      1,
      Math.ceil((safeWidth + CARD_GAP_X) / (MAX_CARD_WIDTH + CARD_GAP_X))
    );
    const maxColumnsForMinWidth = Math.max(
      1,
      Math.floor((safeWidth + CARD_GAP_X) / (MIN_CARD_WIDTH + CARD_GAP_X))
    );
    const columns = minColumnsForMaxWidth > maxColumnsForMinWidth
      ? maxColumnsForMinWidth
      : minColumnsForMaxWidth;
    const cardWidth = Math.floor((safeWidth - CARD_GAP_X * (columns - 1)) / columns);
    return {
      columns,
      cardHeight: cardWidth + CARD_FOOTER_HEIGHT,
    };
  }

  private pickFrame(die: DiceDetailsViewModel): string {
    const material = RARITY_TO_MATERIAL[(die.rarity || "common").toLowerCase()] ?? "cardboard";
    const size = (die.sizeLabel || "d6").toLowerCase() as "d4" | "d6" | "d8" | "d10" | "d12" | "d20";
    const atlas = this.sceneRef.textures.get(DICE_ATLAS_KEY);
    const frame = getDiceFrameName(material, size === "d4" || size === "d6" || size === "d8" || size === "d10" || size === "d12" || size === "d20" ? size : "d6");
    return atlas.has(frame) ? frame : "cardboard_d6";
  }
}
