import Phaser from "phaser";
import type { DiceDetailsViewModel } from "../adapters/profileViewModels";
import { DICE_ATLAS_KEY, getDiceFrameName } from "../assets/diceAtlas";

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
  maxVisibleCards?: number;
};

const RARITY_TO_MATERIAL: Record<string, "cardboard" | "wood" | "bone" | "metal" | "gemstone"> = {
  common: "cardboard",
  uncommon: "wood",
  rare: "bone",
  epic: "metal",
  legendary: "gemstone",
};

export default class DiceCardGrid extends Phaser.GameObjects.Container {
  private readonly cfg: DiceCardGridConfig;
  private readonly pad = 12;
  private readonly titleH = 24;
  private readonly pagerH = 22;
  private readonly cols = 3;
  private readonly gapX = 10;
  private readonly gapY = 10;

  private readonly pageSize: number;
  private pageIndex = 0;

  private prevPageText?: Phaser.GameObjects.Text;
  private nextPageText?: Phaser.GameObjects.Text;
  private pageLabelText?: Phaser.GameObjects.Text;
  private readonly dynamicObjects: Phaser.GameObjects.GameObject[] = [];

  constructor(cfg: DiceCardGridConfig) {
    super(cfg.scene, cfg.x, cfg.y);
    this.cfg = cfg;

    const cardW = this.getCardW();
    const cardH = cardW + 46;
    if (cfg.maxVisibleCards && cfg.maxVisibleCards > 0) {
      this.pageSize = cfg.maxVisibleCards;
    } else {
      const usableH = cfg.height - this.pad * 2 - this.titleH - this.pagerH;
      const rows = Math.max(1, Math.floor((usableH + this.gapY) / (cardH + this.gapY)));
      this.pageSize = rows * this.cols;
    }

    this.buildPanel();
    this.renderPage();
    cfg.scene.add.existing(this);
  }

  private getCardW(): number {
    return Math.floor((this.cfg.width - this.pad * 2 - this.gapX * (this.cols - 1)) / this.cols);
  }

  private buildPanel(): void {
    const bg = this.cfg.scene.add
      .rectangle(0, 0, this.cfg.width, this.cfg.height, 0x000000, 0.2)
      .setOrigin(0, 0)
      .setStrokeStyle(2, 0xffffff, 0.2);

    const title = this.cfg.scene
      .add.text(this.pad, this.pad - 2, this.cfg.title ?? "DICE", {
        fontFamily: "Arial",
        fontSize: "16px",
        color: "#ffffff",
      })
      .setOrigin(0, 0);

    const pagerY = this.cfg.height - this.pad - 2;
    this.prevPageText = this.cfg.scene
      .add.text(this.pad, pagerY, "< Prev", {
        fontFamily: "Arial",
        fontSize: "13px",
        color: "#d6d6d6",
      })
      .setOrigin(0, 1)
      .setInteractive({ useHandCursor: true });

    this.pageLabelText = this.cfg.scene
      .add.text(this.cfg.width / 2, pagerY, "Page 1/1", {
        fontFamily: "Arial",
        fontSize: "13px",
        color: "#cfcfcf",
      })
      .setOrigin(0.5, 1);

    this.nextPageText = this.cfg.scene
      .add.text(this.cfg.width - this.pad, pagerY, "Next >", {
        fontFamily: "Arial",
        fontSize: "13px",
        color: "#d6d6d6",
      })
      .setOrigin(1, 1)
      .setInteractive({ useHandCursor: true });

    this.prevPageText.on("pointerdown", () => this.setPage(this.pageIndex - 1));
    this.nextPageText.on("pointerdown", () => this.setPage(this.pageIndex + 1));

    this.add([bg, title, this.prevPageText, this.pageLabelText, this.nextPageText]);
  }

  private clearCards(): void {
    for (const obj of this.dynamicObjects) {
      obj.destroy();
    }
    this.dynamicObjects.length = 0;
  }

  private renderPage(): void {
    this.clearCards();

    const pageCount = Math.max(1, Math.ceil(this.cfg.dice.length / this.pageSize));
    if (this.pageIndex > pageCount - 1) this.pageIndex = pageCount - 1;

    const start = this.pageIndex * this.pageSize;
    const visible = this.cfg.dice.slice(start, start + this.pageSize);

    const cardW = this.getCardW();
    const cardH = cardW + 46;

    for (let i = 0; i < visible.length; i += 1) {
      const die = visible[i];
      if (!die) continue;
      const col = i % this.cols;
      const row = Math.floor(i / this.cols);
      const x = this.pad + col * (cardW + this.gapX);
      const y = this.pad + this.titleH + row * (cardH + this.gapY);

      const selected = die.id === this.cfg.selectedDiceId;
      const bg = this.cfg.scene.add
        .rectangle(x, y, cardW, cardH, selected ? 0x2a2f2f : 0x121212, 0.92)
        .setOrigin(0, 0)
        .setStrokeStyle(1, selected ? 0xffcc00 : 0xffffff, selected ? 0.7 : 0.2);

      const portraitBg = this.cfg.scene.add
        .rectangle(x + 6, y + 6, cardW - 12, cardW - 12, 0x252525, 1)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0xffffff, 0.15);

      const frame = this.pickFrame(die);
      const sprite = this.cfg.scene.add.image(x + cardW / 2, y + 6 + (cardW - 12) / 2, DICE_ATLAS_KEY, frame)
        .setDisplaySize(Math.min(cardW - 20, 84), Math.min(cardW - 20, 84))
        .setOrigin(0.5, 0.5);

      const title = this.cfg.scene.add
        .text(x + cardW / 2, y + cardW + 2, die.displayName, {
          fontFamily: "Arial",
          fontSize: "12px",
          color: "#f0f0f0",
          align: "center",
          wordWrap: { width: cardW - 8 },
        })
        .setOrigin(0.5, 0);

      const sub = this.cfg.scene.add
        .text(x + cardW / 2, y + cardH - 14, die.equipped ? `Equipped: ${die.equipped.unitName}` : die.rarity, {
          fontFamily: "Arial",
          fontSize: "11px",
          color: die.equipped ? "#ccffcc" : "#c8c8c8",
          align: "center",
          wordWrap: { width: cardW - 8 },
        })
        .setOrigin(0.5, 0.5);

      const hit = this.cfg.scene.add.zone(x + cardW / 2, y + cardH / 2, cardW, cardH).setInteractive({ useHandCursor: true });
      hit.on("pointerdown", () => this.cfg.onDiceClick?.(die));

      this.add([bg, portraitBg, sprite, title, sub, hit]);
      this.dynamicObjects.push(bg, portraitBg, sprite, title, sub, hit);
    }

    const canPage = this.cfg.dice.length > this.pageSize;
    const prevEnabled = canPage && this.pageIndex > 0;
    const nextEnabled = canPage && this.pageIndex < pageCount - 1;
    this.pageLabelText?.setText(`Page ${this.pageIndex + 1}/${pageCount}`);
    this.pageLabelText?.setAlpha(canPage ? 1 : 0.55);
    this.prevPageText?.setAlpha(prevEnabled ? 1 : 0.35);
    this.nextPageText?.setAlpha(nextEnabled ? 1 : 0.35);
  }

  private pickFrame(die: DiceDetailsViewModel): string {
    const material = RARITY_TO_MATERIAL[(die.rarity || "common").toLowerCase()] ?? "cardboard";
    const size = (die.sizeLabel || "d6").toLowerCase() as "d4" | "d6" | "d8" | "d10" | "d12" | "d20";
    const atlas = this.cfg.scene.textures.get(DICE_ATLAS_KEY);
    const frame = getDiceFrameName(material, size === "d4" || size === "d6" || size === "d8" || size === "d10" || size === "d12" || size === "d20" ? size : "d6");
    return atlas.has(frame) ? frame : "cardboard_d6";
  }

  public setDice(dice: DiceDetailsViewModel[], selectedDiceId: string | null): this {
    this.cfg.dice = dice;
    this.cfg.selectedDiceId = selectedDiceId;
    this.pageIndex = 0;
    this.renderPage();
    return this;
  }

  public setSelectedDiceId(selectedDiceId: string | null): this {
    this.cfg.selectedDiceId = selectedDiceId;
    this.renderPage();
    return this;
  }

  public setPage(pageIndex: number): this {
    const pageCount = Math.max(1, Math.ceil(this.cfg.dice.length / this.pageSize));
    this.pageIndex = Phaser.Math.Clamp(pageIndex, 0, pageCount - 1);
    this.renderPage();
    return this;
  }
}
