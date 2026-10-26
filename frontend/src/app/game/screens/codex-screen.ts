import Phaser from 'phaser';
import { GameStore } from '../runtime/game-store';
import { CodexEntry } from '../runtime/codex-contracts';
import { RuntimeApiClient } from '../runtime/runtime-api-client';
import { Bounds, RuntimeViewport, RuntimeViewportSnapshot } from '../runtime/runtime-viewport';
import { actionCursor } from './action-cursor';
import { GameSceneScreen } from './game-screen-navigation';
import { progressionBackground, progressionButton } from './progression-surface';

const box = (x: number, y: number, width: number, height: number): Bounds =>
  ({ x, y, width, height, right: x + width, bottom: y + height });

export interface CodexLayout {
  readonly panel: Bounds;
  readonly back: Bounds;
  readonly list: Bounds;
  readonly detail: Bounds;
  readonly listPrevious: Bounds;
  readonly listNext: Bounds;
  readonly bodyPrevious: Bounds;
  readonly bodyNext: Bounds;
  readonly rowHeight: number;
  readonly rowCount: number;
  readonly fontSize: number;
  readonly bodyLineCount: number;
}

export function createCodexLayout(snapshot: RuntimeViewportSnapshot): CodexLayout {
  const compact = snapshot.layoutClass === 'compact';
  const margin = compact ? 26 : 48;
  const safe = snapshot.safeBounds;
  const panel = box(safe.x + margin, safe.y + margin, safe.width - margin * 2, safe.height - margin * 2);
  const inner = compact ? 28 : 38;
  const top = panel.y + (compact ? 108 : 100);
  const bottom = panel.bottom - (compact ? 28 : 32);
  const gap = compact ? 22 : 28;
  const listWidth = Math.min(compact ? 590 : 470, Math.max(300, panel.width * .37));
  const list = box(panel.x + inner, top, listWidth, bottom - top);
  const detail = box(list.right + gap, top, panel.right - inner - list.right - gap, bottom - top);
  const navHeight = compact ? 58 : 46;
  const rowHeight = compact ? 78 : 64;
  const rowCount = Math.max(1, Math.floor((list.height - navHeight - 80) / rowHeight));
  const buttonWidth = compact ? 155 : 116;
  const navigation = (region: Bounds, right: boolean) => box(
    right ? region.right - buttonWidth - 16 : region.x + 16,
    region.bottom - navHeight - 12, buttonWidth, navHeight);
  return {
    panel, back: box(panel.x + inner, panel.y + 22, compact ? 170 : 140, compact ? 60 : 48),
    list, detail, listPrevious: navigation(list, false), listNext: navigation(list, true),
    bodyPrevious: navigation(detail, false), bodyNext: navigation(detail, true),
    rowHeight, rowCount, fontSize: compact ? 28 : 21,
    bodyLineCount: Math.max(2, Math.floor((detail.height - navHeight - 230) / (compact ? 38 : 30))),
  };
}

/** Splits only for presentation. Every source character remains on exactly one page. */
export function codexBodyPages(body: string, layout: CodexLayout): readonly string[] {
  const lineWidth = Math.max(12, Math.floor((layout.detail.width - 64) / (layout.fontSize * .7)));
  const pageCapacity = lineWidth * layout.bodyLineCount;
  const pages: string[] = [];
  let remaining = body;
  while (remaining.length > pageCapacity) {
    const space = remaining.lastIndexOf(' ', pageCapacity);
    const newline = remaining.lastIndexOf('\n', pageCapacity);
    const breakAt = Math.max(space, newline);
    const length = breakAt > pageCapacity / 2 ? breakAt + 1 : pageCapacity;
    pages.push(remaining.slice(0, length));
    remaining = remaining.slice(length);
  }
  pages.push(remaining);
  return pages;
}

export class CodexScreen implements GameSceneScreen {
  readonly key = 'codex' as const;
  private root: Phaser.GameObjects.Container | null = null;
  private unsubscribe: (() => void) | null = null;
  private selectedId: string | null = null;
  private listPage = 0;
  private bodyPage = 0;
  private activeLayout: CodexLayout | null = null;

  constructor(private readonly scene: Phaser.Scene, private readonly store: GameStore,
    private readonly api: RuntimeApiClient, private readonly viewport: RuntimeViewport,
    private readonly back: () => void) {}

  get layout(): CodexLayout | null { return this.activeLayout; }
  get selectedEntryId(): string | null { return this.selectedId; }

  create(): void {
    this.unsubscribe = this.store.subscribeCodex((state) => {
      this.reflow(this.viewport.snapshot);
      if (state.status === 'stale') void this.store.loadCodex(this.api);
    });
    this.reflow(this.viewport.snapshot);
    void this.store.loadCodex(this.api);
  }

  destroy(): void {
    this.unsubscribe?.(); this.unsubscribe = null;
    this.root?.destroy(true); this.root = null;
    this.host()?.removeAttribute('data-codex-status');
  }

  requestBack(): void { this.back(); }
  retryRead(): void { void this.store.retryCodex(this.api); }

  selectEntry(id: string): void {
    if (this.store.codex.status !== 'fresh' || !this.store.codex.data?.some((entry) => entry.id === id)) return;
    this.selectedId = id; this.bodyPage = 0; this.reflow(this.viewport.snapshot);
  }

  changeListPage(delta: number): void {
    const count = this.store.codex.data?.length ?? 0;
    const size = this.activeLayout?.rowCount ?? 1;
    this.listPage = Math.max(0, Math.min(Math.ceil(count / size) - 1, this.listPage + delta));
    this.reflow(this.viewport.snapshot);
  }

  changeBodyPage(delta: number): void {
    const entry = this.selectedEntry();
    if (!entry?.owned || entry.body === null || !this.activeLayout) return;
    const count = codexBodyPages(entry.body, this.activeLayout).length;
    this.bodyPage = Math.max(0, Math.min(count - 1, this.bodyPage + delta));
    this.reflow(this.viewport.snapshot);
  }

  reflow(snapshot: RuntimeViewportSnapshot): void {
    this.root?.destroy(true);
    const layout = createCodexLayout(snapshot);
    this.activeLayout = layout;
    const root = this.scene.add.container(0, 0).setScale(snapshot.gameScale); this.root = root;
    progressionBackground(this.scene, root, snapshot, layout.panel);
    progressionButton(this.scene, root, layout.back, 'RETURN', () => this.requestBack());
    root.add(this.scene.add.text(layout.back.right + 28, layout.panel.y + 28, 'CODEX', {
      color: '#f5e8c8', fontFamily: 'Georgia, serif', fontSize: snapshot.layoutClass === 'compact' ? '42px' : '38px',
      fontStyle: 'bold', stroke: '#3a2a1a', strokeThickness: 5,
    }));
    const state = this.store.codex;
    this.host()?.setAttribute('data-codex-status', state.status);
    if (state.status !== 'fresh' || state.data === null) {
      const title = state.status === 'error' ? 'Codex could not be verified' : 'Loading Codex…';
      const subtitle = state.status === 'error' ? 'Retry the authoritative read.' : 'Gathering known entries.';
      this.center(root, layout.panel, title, subtitle);
      if (state.status === 'error') progressionButton(this.scene, root,
        box(layout.panel.x + layout.panel.width / 2 - 100, layout.panel.bottom - 104, 200, 56),
        'RETRY', () => this.retryRead());
      return;
    }
    const entries = state.data;
    if (!entries.length) { this.center(root, layout.panel, 'No Codex entries', 'Knowledge will appear here.'); return; }
    if (!entries.some((entry) => entry.id === this.selectedId)) this.selectedId = entries[0].id;
    this.listPage = Math.max(0, Math.min(Math.ceil(entries.length / layout.rowCount) - 1, this.listPage));
    this.panel(root, layout.list, 0x293b34);
    this.panel(root, layout.detail, 0xefe2c2);
    this.label(root, layout.list.x + 22, layout.list.y + 18, 'ENTRIES', layout.fontSize, '#f5e8c8');
    const start = this.listPage * layout.rowCount;
    entries.slice(start, start + layout.rowCount).forEach((entry, index) => {
      const row = box(layout.list.x + 14, layout.list.y + 58 + index * layout.rowHeight,
        layout.list.width - 28, layout.rowHeight - 7);
      const selected = entry.id === this.selectedId;
      const fill = selected ? 0xc9972b : entry.owned ? 0x456b52 : 0x45514f;
      const button = this.scene.add.graphics(); button.fillStyle(fill, 1);
      button.fillRoundedRect(row.x, row.y, row.width, row.height, 8);
      button.setInteractive(new Phaser.Geom.Rectangle(row.x, row.y, row.width, row.height),
        Phaser.Geom.Rectangle.Contains).on('pointerup', () => this.selectEntry(entry.id));
      actionCursor(button); root.add(button);
      const titleLength = Math.max(10, Math.floor((row.width - 24) / (layout.fontSize * .64)));
      const shortTitle = entry.title.length > titleLength
        ? `${entry.title.slice(0, titleLength - 1).trimEnd()}…` : entry.title;
      this.label(root, row.x + 12, row.y + 8, shortTitle, layout.fontSize, '#fff4d3', row.width - 24);
      this.label(root, row.x + 12, row.bottom - (snapshot.layoutClass === 'compact' ? 24 : 20),
        entry.owned ? 'DISCOVERED' : 'UNDISCOVERED', snapshot.layoutClass === 'compact' ? 16 : 13,
        entry.owned ? '#d6f2ce' : '#d2d1c8');
    });
    const listPages = Math.ceil(entries.length / layout.rowCount);
    if (listPages > 1) {
      progressionButton(this.scene, root, layout.listPrevious, 'PREV', () => this.changeListPage(-1), this.listPage > 0);
      progressionButton(this.scene, root, layout.listNext, 'NEXT', () => this.changeListPage(1), this.listPage < listPages - 1);
      this.label(root, layout.list.x + layout.list.width / 2 - 28, layout.list.bottom - 48,
        `${this.listPage + 1}/${listPages}`, 17, '#f5e8c8');
    }
    const selected = this.selectedEntry();
    if (!selected) return;
    this.label(root, layout.detail.x + 28, layout.detail.y + 28, selected.title,
      snapshot.layoutClass === 'compact' ? 31 : 28, '#3a2a1a', layout.detail.width - 56);
    this.label(root, layout.detail.x + 28, layout.detail.y + 140,
      selected.owned ? 'DISCOVERED' : 'UNDISCOVERED', 16,
      selected.owned ? '#315c39' : '#765a45');
    if (!selected.owned || selected.body === null) {
      this.label(root, layout.detail.x + 28, layout.detail.y + 195,
        'This entry has not been discovered.', layout.fontSize, '#5b4b3c', layout.detail.width - 56);
      return;
    }
    const pages = codexBodyPages(selected.body, layout);
    this.bodyPage = Math.min(this.bodyPage, pages.length - 1);
    this.label(root, layout.detail.x + 28, layout.detail.y + 195, pages[this.bodyPage],
      layout.fontSize, '#3a2a1a', layout.detail.width - 56);
    if (pages.length > 1) {
      progressionButton(this.scene, root, layout.bodyPrevious, 'PREV', () => this.changeBodyPage(-1), this.bodyPage > 0);
      progressionButton(this.scene, root, layout.bodyNext, 'NEXT', () => this.changeBodyPage(1), this.bodyPage < pages.length - 1);
      this.label(root, layout.detail.x + layout.detail.width / 2 - 28, layout.detail.bottom - 48,
        `${this.bodyPage + 1}/${pages.length}`, 17, '#3a2a1a');
    }
  }

  private selectedEntry(): CodexEntry | null {
    return this.store.codex.data?.find((entry) => entry.id === this.selectedId) ?? null;
  }

  private center(root: Phaser.GameObjects.Container, bounds: Bounds, title: string, subtitle: string): void {
    this.label(root, bounds.x + bounds.width / 2 - 220, bounds.y + bounds.height / 2 - 30,
      title, 29, '#3a2a1a', 440);
    this.label(root, bounds.x + bounds.width / 2 - 220, bounds.y + bounds.height / 2 + 20,
      subtitle, 19, '#5b4b3c', 440);
  }

  private panel(root: Phaser.GameObjects.Container, bounds: Bounds, color: number): void {
    const graphic = this.scene.add.graphics(); graphic.fillStyle(color, 1);
    graphic.fillRoundedRect(bounds.x, bounds.y, bounds.width, bounds.height, 12); root.add(graphic);
  }

  private label(root: Phaser.GameObjects.Container, x: number, y: number, value: string,
    size: number, color: string, width?: number): void {
    root.add(this.scene.add.text(x, y, value, { color, fontFamily: 'system-ui, sans-serif',
      fontSize: `${size}px`, wordWrap: width ? { width } : undefined }));
  }

  private host(): HTMLElement | null {
    return (this.scene.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game?.canvas.parentElement ?? null;
  }
}
