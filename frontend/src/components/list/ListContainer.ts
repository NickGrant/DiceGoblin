import Phaser from "phaser";
import { TEXT_LIST_ACTION, TEXT_LIST_MESSAGE, TEXT_LIST_META, UI_TEXT_COLORS } from "../../const/Text";
import {
  computePagination,
  deriveListState,
  type ListLoadState,
  type PaginationResult,
} from "./listState";
import { resolveListContentLayout } from "./listContainerLayout";

export type ListRenderPayload<T> = {
  scene: Phaser.Scene;
  parent: Phaser.GameObjects.Container;
  items: T[];
  contentX: number;
  contentY: number;
  contentWidth: number;
  contentHeight: number;
  onSelect?: (item: T) => void;
};

export type ListContainerConfig<T> = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  width: number;
  height: number;
  items: T[];
  loadState: ListLoadState;
  errorMessage?: string;
  pageSize?: number;
  pageIndex?: number;
  renderItems: (payload: ListRenderPayload<T>) => Phaser.GameObjects.GameObject[];
  getPageSize?: (params: { contentWidth: number; contentHeight: number }) => number;
  onSelect?: (item: T) => void;
  onRetry?: () => void;
  emptyMessage?: string;
  title?: string;
};

const PAD = 12;
const TITLE_HEIGHT = 24;
const CONTENT_GAP = 8;
const PAGINATION_HEIGHT = 22;

export default class ListContainer<T> extends Phaser.GameObjects.Container {
  private readonly cfg: ListContainerConfig<T>;
  private pageIndex: number;
  private pageSize: number;
  private prevText?: Phaser.GameObjects.Text;
  private nextText?: Phaser.GameObjects.Text;
  private pageText?: Phaser.GameObjects.Text;
  private titleText?: Phaser.GameObjects.Text;
  private dynamicChildren: Phaser.GameObjects.GameObject[] = [];

  constructor(cfg: ListContainerConfig<T>) {
    super(cfg.scene, cfg.x, cfg.y);
    this.cfg = cfg;
    this.pageIndex = Math.max(0, cfg.pageIndex ?? 0);
    this.pageSize = Math.max(1, cfg.pageSize ?? 9);
    cfg.scene.add.existing(this);
    this.render();
  }

  updateState(params: {
    items?: T[];
    loadState?: ListLoadState;
    errorMessage?: string;
    pageIndex?: number;
    pageSize?: number;
  }): this {
    if (params.items !== undefined) this.cfg.items = params.items;
    if (params.loadState) this.cfg.loadState = params.loadState;
    if (typeof params.errorMessage === "string") this.cfg.errorMessage = params.errorMessage;
    if (typeof params.pageIndex === "number") this.pageIndex = Math.max(0, params.pageIndex);
    if (typeof params.pageSize === "number") this.pageSize = Math.max(1, params.pageSize);
    this.render();
    return this;
  }

  private render(): void {
    this.clearDynamic();
    this.removePagination();
    this.removeTitle();

    const hasTitle = (this.cfg.title ?? "").trim().length > 0;
    if (hasTitle) {
      this.titleText = this.cfg.scene.add.text(PAD, PAD - 2, this.cfg.title ?? "", {
        ...TEXT_LIST_META,
        color: UI_TEXT_COLORS.onDarkHigh,
        fontSize: "16px",
      }).setOrigin(0, 0);
      this.add(this.titleText);
    }

    const { contentX, contentY, contentWidth, contentHeight } = resolveListContentLayout({
      width: this.cfg.width,
      height: this.cfg.height,
      hasTitle,
    });

    if (this.cfg.getPageSize) {
      this.pageSize = Math.max(1, this.cfg.getPageSize({ contentWidth, contentHeight }));
    }

    const state = deriveListState(this.cfg.loadState, this.cfg.items.length);
    if (state === "loading") {
      this.renderMessage("Loading...", contentX, contentY, contentWidth);
      return;
    }
    if (state === "error") {
      this.renderMessage(this.cfg.errorMessage ?? "Unable to load list.", contentX, contentY, contentWidth);
      if (this.cfg.onRetry) this.renderRetry(contentX, contentY);
      return;
    }
    if (state === "empty") {
      this.renderMessage(this.cfg.emptyMessage ?? "No items found.", contentX, contentY, contentWidth);
      return;
    }

    const pagination = computePagination(this.cfg.items.length, {
      pageIndex: this.pageIndex,
      pageSize: this.pageSize,
    });
    this.pageIndex = pagination.pageIndex;

    const visible = this.cfg.items.slice(pagination.start, pagination.end);
    const rendered = this.cfg.renderItems({
      scene: this.cfg.scene,
      parent: this as Phaser.GameObjects.Container,
      items: visible,
      contentX,
      contentY,
      contentWidth,
      contentHeight,
      onSelect: this.cfg.onSelect,
    });
    this.dynamicChildren.push(...rendered);

    if (pagination.totalPages > 1) {
      this.renderPagination(pagination);
    }
  }

  private renderMessage(text: string, x: number, y: number, width: number): void {
    const message = this.cfg.scene.add.text(x, y, text, {
      ...TEXT_LIST_MESSAGE,
      wordWrap: { width: Math.max(0, width) },
    });
    this.dynamicChildren.push(message);
    this.add(message);
  }

  private renderRetry(x: number, y: number): void {
    const retry = this.cfg.scene.add
      .text(x, y + 30, "Retry", {
        ...TEXT_LIST_ACTION,
      })
      .setInteractive({ useHandCursor: true });
    retry.on("pointerdown", () => this.cfg.onRetry?.());
    this.dynamicChildren.push(retry);
    this.add(retry);
  }

  private renderPagination(pagination: PaginationResult): void {
    const y = this.cfg.height - PAGINATION_HEIGHT - PAD;
    this.prevText = this.cfg.scene.add
      .text(PAD, y, "< Prev", {
        ...TEXT_LIST_META,
      })
      .setOrigin(0, 0)
      .setInteractive({ useHandCursor: true });
    this.nextText = this.cfg.scene.add
      .text(this.cfg.width - PAD, y, "Next >", {
        ...TEXT_LIST_META,
      })
      .setOrigin(1, 0)
      .setInteractive({ useHandCursor: true });
    this.pageText = this.cfg.scene.add.text(this.cfg.width / 2, y, `Page ${pagination.pageIndex + 1}/${pagination.totalPages}`, {
      ...TEXT_LIST_META,
      color: UI_TEXT_COLORS.onDarkMuted,
    }).setOrigin(0.5, 0);

    this.prevText.setAlpha(pagination.canPrev ? 1 : 0.35);
    this.nextText.setAlpha(pagination.canNext ? 1 : 0.35);
    this.prevText.on("pointerdown", () => this.changePage(-1));
    this.nextText.on("pointerdown", () => this.changePage(1));

    this.add([this.prevText, this.nextText, this.pageText]);
  }

  private changePage(delta: number): void {
    this.pageIndex = Math.max(0, this.pageIndex + delta);
    this.render();
  }

  private removePagination(): void {
    this.prevText?.destroy();
    this.nextText?.destroy();
    this.pageText?.destroy();
    this.prevText = undefined;
    this.nextText = undefined;
    this.pageText = undefined;
  }

  private removeTitle(): void {
    this.titleText?.destroy();
    this.titleText = undefined;
  }

  private clearDynamic(): void {
    for (const obj of this.dynamicChildren) obj.destroy();
    this.dynamicChildren = [];
  }
}

