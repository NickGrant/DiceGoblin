import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import { apiClient } from "../services/apiClient";
import { getPageLayout } from "../layout/pageLayout";
import type { ShopCatalogData } from "../types/ApiResponse";

export default class ShopScene extends Phaser.Scene {
  private loadingText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;
  private contentObjects: Phaser.GameObjects.GameObject[] = [];
  private static readonly SECTION_BG = 0x39454f;
  private static readonly SECTION_BORDER = 0x8da0af;
  private static readonly ROW_BG = 0x2c353d;
  private static readonly ROW_BORDER = 0x6f838f;
  private static readonly BUY_BG = 0xb8873a;
  private static readonly BUY_BG_HOVER = 0xd29b42;
  private static readonly TOOTH_ICON_SIZE = 34;

  constructor() {
    super({ key: "ShopScene" });
  }

  create(): void {
    new BackgroundImage(this);
    mountBottomCommandStrip(this);
    const layout = getPageLayout(this);
    const contentFrame = new ContentAreaFrame({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
      title: "Shop",
      bodyColor: 0x4f5a65,
    });
    contentFrame.setDepth(-800);

    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Actions",
      bodyColor: 0x006f7a,
    });
    actionsFrame.setDepth(-800);

    this.loadingText = this.add.text(layout.content.x + 16, layout.content.y + 120, "Loading shop...", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "20px",
      color: "#ffffff",
    });

    new SharedActionButton({
      scene: this,
      x: layout.buttons.x + Math.max(10, Math.floor((layout.buttons.width - 280) / 2)),
      y: layout.buttons.y + 84,
      label: "Back",
      onClick: () => this.scene.start("HomeScene"),
    });

    void this.loadShop();
  }

  private async loadShop(): Promise<void> {
    try {
      const response = await apiClient.getShopCatalog();
      if (!response.ok) {
        throw new Error(response.error.message);
      }

      this.loadingText?.destroy();
      this.loadingText = undefined;
      this.renderCatalog(response.data);
    } catch (error) {
      this.loadingText?.setText(`Shop unavailable.\n${error instanceof Error ? error.message : "Unknown error"}`);
    }
  }

  private renderCatalog(catalog: ShopCatalogData): void {
    for (const obj of this.contentObjects) {
      obj.destroy();
    }
    this.contentObjects = [];

    const layout = getPageLayout(this);
    const contentX = layout.content.x + 18;
    const contentY = layout.content.y + 84;
    const contentWidth = layout.content.width - 36;
    const topSectionHeight = 314;
    const sectionGap = 18;
    const columnWidth = Math.floor((contentWidth - sectionGap) / 2);
    const dailyY = contentY + topSectionHeight + 16;
    const dailyHeight = Math.max(78, layout.content.height - (dailyY - layout.content.y) - 18);

    if (this.textures.exists("icon_tooth_large")) {
      const toothIcon = this.add
        .image(contentX + 18, contentY + 20, "icon_tooth_large")
        .setOrigin(0.5, 0.5)
        .setDisplaySize(ShopScene.TOOTH_ICON_SIZE, ShopScene.TOOTH_ICON_SIZE);
      this.contentObjects.push(toothIcon);
    }

    const title = this.add.text(contentX + 44, contentY, `Teeth: ${catalog.currency_soft}`, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "25px",
      color: "#f5f5f5",
      stroke: "#1b2228",
      strokeThickness: 3,
    });
    this.contentObjects.push(title);

    this.renderSectionCard({
      x: contentX,
      y: contentY + 42,
      width: columnWidth,
      height: topSectionHeight,
      title: "Basic Dice",
    });
    this.renderSectionCard({
      x: contentX + columnWidth + sectionGap,
      y: contentY + 42,
      width: columnWidth,
      height: topSectionHeight,
      title: "Basic Units",
    });
    this.renderSectionCard({
      x: contentX,
      y: dailyY,
      width: contentWidth,
      height: dailyHeight,
      title: "Deal of the Day",
    });

    this.renderDiceColumn(contentX + 14, contentY + 84, columnWidth - 28, catalog);
    this.renderUnitColumn(contentX + columnWidth + sectionGap + 14, contentY + 84, columnWidth - 28, catalog);
    this.renderDailyDeal(contentX + 14, dailyY + 42, contentWidth - 28, dailyHeight - 56, catalog);
  }

  private renderSectionCard(cfg: { x: number; y: number; width: number; height: number; title: string }): void {
    const panel = this.add
      .rectangle(cfg.x, cfg.y, cfg.width, cfg.height, ShopScene.SECTION_BG, 0.95)
      .setOrigin(0, 0)
      .setStrokeStyle(2, ShopScene.SECTION_BORDER, 0.35);
    const header = this.add.text(cfg.x + 14, cfg.y + 12, cfg.title, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "20px",
      color: "#ffe8b0",
      stroke: "#1b2228",
      strokeThickness: 2,
    });

    this.contentObjects.push(panel, header);
  }

  private renderDiceColumn(startX: number, startY: number, width: number, catalog: ShopCatalogData): void {
    let rowY = startY;
    for (const item of catalog.basic_dice) {
      this.renderCatalogRow({
        x: startX,
        y: rowY,
        width,
        title: item.label,
        subtitle: `${item.cost} teeth`,
        buttonLabel: "Buy",
        onClick: () => void this.purchase("basic_dice", item.product_id),
      });
      rowY += 48;
    }
  }

  private renderUnitColumn(startX: number, startY: number, width: number, catalog: ShopCatalogData): void {
    let rowY = startY;
    for (const item of catalog.basic_units) {
      const roleLabel = item.role.length > 0 ? item.role : "unit";
      this.renderCatalogRow({
        x: startX,
        y: rowY,
        width,
        title: item.name,
        subtitle: `${roleLabel}  |  ${item.cost} teeth`,
        buttonLabel: "Hire",
        onClick: () => void this.purchase("basic_unit", item.product_id),
      });
      rowY += 48;
    }
  }

  private renderDailyDeal(startX: number, startY: number, width: number, height: number, catalog: ShopCatalogData): void {
    const daily = catalog.daily_deal;
    if (!daily) {
      const emptyText = this.add.text(startX, startY + 8, "The daily slot is currently empty.", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#f2f2f2",
      });
      this.contentObjects.push(emptyText);
      return;
    }

    const gap = 18;
    const actionWidth = 156;
    const offerWidth = 250;
    const affixWidth = Math.max(180, width - offerWidth - actionWidth - gap * 2);

    const offerTitle = this.add.text(startX, startY + 4, `${daily.rarity.toUpperCase()} d${daily.sides}`, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "24px",
      color: "#f5f5f5",
      stroke: "#1b2228",
      strokeThickness: 2,
    });
    const offerMeta = this.add.text(startX, startY + 34, `${daily.cost} teeth  |  ${daily.shop_date}`, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "15px",
      color: "#dbe4e8",
    });

    const affixX = startX + offerWidth + gap;
    const affixTitle = this.add.text(affixX, startY + 6, `${daily.affix.name} (${daily.affix.value})`, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "18px",
      color: "#ffe8b0",
      stroke: "#1b2228",
      strokeThickness: 1,
    });
    const affixBody = this.add.text(affixX, startY + 32, daily.affix.description, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "15px",
      color: "#f2f2f2",
      lineSpacing: 3,
      wordWrap: { width: affixWidth },
    });

    const actionX = affixX + affixWidth + gap;
    const statusText = this.add.text(actionX, startY + 10, daily.is_purchased ? "Sold Today" : "Available", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: daily.is_purchased ? "#d4dde3" : "#ccf1bf",
      stroke: "#1b2228",
      strokeThickness: 1,
    });

    this.contentObjects.push(offerTitle, offerMeta, affixTitle, affixBody, statusText);

    if (!daily.is_purchased) {
      this.renderMiniButton(actionX, startY + 34, 132, 34, "Buy Deal", () => void this.purchase("daily_deal", daily.product_id));
    }
  }

  private renderCatalogRow(cfg: {
    x: number;
    y: number;
    width: number;
    title: string;
    subtitle: string;
    buttonLabel: string;
    onClick: () => void;
  }): void {
    const row = this.add
      .rectangle(cfg.x, cfg.y, cfg.width, 38, ShopScene.ROW_BG, 0.98)
      .setOrigin(0, 0)
      .setStrokeStyle(1, ShopScene.ROW_BORDER, 0.35);
    const title = this.add.text(cfg.x + 10, cfg.y + 6, cfg.title, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "17px",
      color: "#f5f5f5",
      stroke: "#1b2228",
      strokeThickness: 1,
    });
    const subtitle = this.add.text(cfg.x + 170, cfg.y + 8, cfg.subtitle, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "15px",
      color: "#dbe4e8",
    });

    this.contentObjects.push(row, title, subtitle);
    this.renderMiniButton(cfg.x + cfg.width - 78, cfg.y + 4, 68, 28, cfg.buttonLabel, cfg.onClick);
  }

  private renderMiniButton(
    x: number,
    y: number,
    width: number,
    height: number,
    label: string,
    onClick: () => void,
  ): void {
    const bg = this.add
      .rectangle(x, y, width, height, ShopScene.BUY_BG, 1)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xf3d28f, 0.65)
      .setInteractive({ useHandCursor: true });
    const text = this.add.text(x + Math.floor(width / 2), y + Math.floor(height / 2), label.toUpperCase(), {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "16px",
      color: "#1c1507",
      stroke: "#f3e0b3",
      strokeThickness: 1,
    }).setOrigin(0.5, 0.5);

    bg.on("pointerover", () => bg.setFillStyle(ShopScene.BUY_BG_HOVER, 1));
    bg.on("pointerout", () => bg.setFillStyle(ShopScene.BUY_BG, 1));
    bg.on("pointerdown", () => onClick());

    this.contentObjects.push(bg, text);
  }

  private async purchase(itemType: "basic_unit" | "basic_dice" | "daily_deal", productId: string): Promise<void> {
    try {
      const response = await apiClient.purchaseShopItem(itemType, productId);
      if (!response.ok) {
        throw new Error(response.error.message);
      }
      this.showToast("Purchase complete.", "#ccffcc");
      await this.loadShop();
    } catch (error) {
      this.showToast(this.toPlayerFacingMessage(error instanceof Error ? error.message : "Purchase failed."));
    }
  }

  private toPlayerFacingMessage(message: string): string {
    return message
      .replace(/soft currency/gi, "teeth")
      .replace(/\bcurrency\b/gi, "teeth");
  }

  private showToast(message: string, color = "#ffcccc"): void {
    this.toastText?.destroy();
    const layout = getPageLayout(this);
    this.toastText = this.add.text(layout.content.x + 16, layout.content.y + layout.content.height - 24, message, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "13px",
      color,
    });
    this.time.delayedCall(2500, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }
}
