import Phaser from 'phaser';
import { GameStore } from '../runtime/game-store';
import { ObjectiveEntry } from '../runtime/objective-contracts';
import { RuntimeApiClient } from '../runtime/runtime-api-client';
import { RuntimeViewport, RuntimeViewportSnapshot } from '../runtime/runtime-viewport';
import { actionCursor } from './action-cursor';
import { CodexLayout, createCodexLayout } from './codex-screen';
import { GameSceneScreen } from './game-screen-navigation';
import { progressionBackground, progressionButton } from './progression-surface';

const statusLabel: Record<ObjectiveEntry['status'], string> = {
  unstarted: 'UNSTARTED', active: 'ACTIVE', completed: 'COMPLETED',
};
const statusColor: Record<ObjectiveEntry['status'], number> = {
  unstarted: 0x45514f, active: 0x456b52, completed: 0x9d792b,
};

/** Objective text is paged for display only; progress and status remain server facts. */
export function objectiveDetailPages(entry: ObjectiveEntry, layout: CodexLayout): readonly string[] {
  const body = [entry.description, '', ...entry.requirements.map((requirement) =>
    `${requirement.label}: ${requirement.current} / ${requirement.needed}`)].join('\n');
  const width = Math.max(12, Math.floor((layout.detail.width - 64) / (layout.fontSize * .7)));
  const lines: string[] = [];
  for (const source of body.split('\n')) {
    if (!source) { lines.push(''); continue; }
    let line = '';
    for (const word of source.split(' ')) {
      const candidate = line ? `${line} ${word}` : word;
      if (candidate.length > width && line) { lines.push(line); line = word; }
      else line = candidate;
      while (line.length > width) { lines.push(line.slice(0, width)); line = line.slice(width); }
    }
    lines.push(line);
  }
  const pages: string[] = [];
  for (let index = 0; index < lines.length; index += layout.bodyLineCount)
    pages.push(lines.slice(index, index + layout.bodyLineCount).join('\n'));
  return pages;
}

export class ObjectivesScreen implements GameSceneScreen {
  readonly key = 'objectives' as const;
  private root: Phaser.GameObjects.Container | null = null;
  private unsubscribe: (() => void) | null = null;
  private selectedId: string | null = null;
  private listPage = 0;
  private detailPage = 0;
  private activeLayout: CodexLayout | null = null;
  private readonly onKeyDown = (event: KeyboardEvent): void => {
    if (event.key === 'ArrowLeft') this.changeListPage(-1);
    else if (event.key === 'ArrowRight') this.changeListPage(1);
    else if (event.key === 'ArrowUp') this.changeSelection(-1);
    else if (event.key === 'ArrowDown') this.changeSelection(1);
    else if (event.key === 'PageUp') this.changeDetailPage(-1);
    else if (event.key === 'PageDown') this.changeDetailPage(1);
    else return;
    event.preventDefault();
  };

  constructor(private readonly scene: Phaser.Scene, private readonly store: GameStore,
    private readonly api: RuntimeApiClient, private readonly viewport: RuntimeViewport,
    private readonly back: () => void) {}

  get layout(): CodexLayout | null { return this.activeLayout; }
  get selectedEntryId(): string | null { return this.selectedId; }

  create(): void {
    this.unsubscribe = this.store.subscribeObjectives((state) => {
      this.reflow(this.viewport.snapshot);
      if (state.status === 'stale') void this.store.loadObjectives(this.api);
    });
    this.scene.input?.keyboard?.on('keydown', this.onKeyDown);
    this.reflow(this.viewport.snapshot);
    void this.store.loadObjectives(this.api);
  }

  destroy(): void {
    this.unsubscribe?.(); this.unsubscribe = null;
    this.scene.input?.keyboard?.off('keydown', this.onKeyDown);
    this.root?.destroy(true); this.root = null;
    this.activeLayout = null;
    this.host()?.removeAttribute('data-objectives-status');
  }

  requestBack(): void { this.back(); }
  retryRead(): void { void this.store.retryObjectives(this.api); }

  selectEntry(id: string): void {
    if (this.store.objectives.status !== 'fresh' || !this.store.objectives.data?.some((entry) => entry.id === id)) return;
    this.selectedId = id; this.detailPage = 0; this.reflow(this.viewport.snapshot);
  }

  changeListPage(delta: number): void {
    const count = this.store.objectives.data?.length ?? 0;
    const size = this.activeLayout?.rowCount ?? 1;
    this.listPage = Math.max(0, Math.min(Math.ceil(count / size) - 1, this.listPage + delta));
    this.reflow(this.viewport.snapshot);
  }

  changeSelection(delta: number): void {
    if (this.store.objectives.status !== 'fresh') return;
    const entries = this.store.objectives.data ?? [];
    const current = entries.findIndex((entry) => entry.id === this.selectedId);
    const next = Math.max(0, Math.min(entries.length - 1, current + delta));
    if (!entries[next]) return;
    this.selectedId = entries[next].id;
    this.listPage = Math.floor(next / (this.activeLayout?.rowCount ?? 1));
    this.detailPage = 0;
    this.reflow(this.viewport.snapshot);
  }

  changeDetailPage(delta: number): void {
    const entry = this.selectedEntry();
    if (!entry || !this.activeLayout) return;
    const count = objectiveDetailPages(entry, this.activeLayout).length;
    this.detailPage = Math.max(0, Math.min(count - 1, this.detailPage + delta));
    this.reflow(this.viewport.snapshot);
  }

  reflow(snapshot: RuntimeViewportSnapshot): void {
    this.root?.destroy(true);
    const layout = createCodexLayout(snapshot); this.activeLayout = layout;
    const root = this.scene.add.container(0, 0).setScale(snapshot.gameScale); this.root = root;
    progressionBackground(this.scene, root, snapshot, layout.panel);
    progressionButton(this.scene, root, layout.back, 'RETURN', () => this.requestBack());
    root.add(this.scene.add.text(layout.back.right + 28, layout.panel.y + 28, 'OBJECTIVES', {
      color: '#f5e8c8', fontFamily: 'Georgia, serif', fontSize: snapshot.layoutClass === 'compact' ? '42px' : '38px',
      fontStyle: 'bold', stroke: '#3a2a1a', strokeThickness: 5,
    }));
    const state = this.store.objectives;
    this.host()?.setAttribute('data-objectives-status', state.status);
    if (state.status !== 'fresh' || state.data === null) {
      this.label(root, layout.panel.x + 60, layout.panel.y + 180,
        state.status === 'error' ? 'Objectives could not be verified' : 'Loading Objectives…', 30, '#3a2a1a');
      if (state.status === 'error') progressionButton(this.scene, root, layout.bodyNext,
        'RETRY', () => this.retryRead());
      return;
    }
    const entries = state.data;
    if (!entries.length) {
      this.label(root, layout.panel.x + 60, layout.panel.y + 180, 'No objectives available', 30, '#3a2a1a');
      return;
    }
    if (!entries.some((entry) => entry.id === this.selectedId)) { this.selectedId = entries[0].id; this.detailPage = 0; }
    this.listPage = Math.max(0, Math.min(Math.ceil(entries.length / layout.rowCount) - 1, this.listPage));
    this.panel(root, layout.list, 0x293b34); this.panel(root, layout.detail, 0xefe2c2);
    this.label(root, layout.list.x + 22, layout.list.y + 18, 'OBJECTIVES', layout.fontSize, '#f5e8c8');
    const start = this.listPage * layout.rowCount;
    entries.slice(start, start + layout.rowCount).forEach((entry, index) => {
      const row = { x: layout.list.x + 14, y: layout.list.y + 58 + index * layout.rowHeight,
        width: layout.list.width - 28, height: layout.rowHeight - 7 };
      const button = this.scene.add.graphics(); button.fillStyle(entry.id === this.selectedId ? 0xc9972b : statusColor[entry.status], 1);
      button.fillRoundedRect(row.x, row.y, row.width, row.height, 8);
      button.setInteractive(new Phaser.Geom.Rectangle(row.x, row.y, row.width, row.height),
        Phaser.Geom.Rectangle.Contains).on('pointerup', () => this.selectEntry(entry.id));
      actionCursor(button); root.add(button);
      const titleLength = Math.max(10, Math.floor((row.width - 24) / (layout.fontSize * .64)));
      const title = entry.title.length > titleLength
        ? `${entry.title.slice(0, titleLength - 1).trimEnd()}…` : entry.title;
      this.label(root, row.x + 12, row.y + 6, title, layout.fontSize, '#fff4d3', row.width - 24);
      this.label(root, row.x + 12, row.y + row.height - 22, statusLabel[entry.status],
        snapshot.layoutClass === 'compact' ? 16 : 13, '#f5e8c8');
    });
    const listPages = Math.ceil(entries.length / layout.rowCount);
    if (listPages > 1) {
      progressionButton(this.scene, root, layout.listPrevious, 'PREV', () => this.changeListPage(-1), this.listPage > 0);
      progressionButton(this.scene, root, layout.listNext, 'NEXT', () => this.changeListPage(1), this.listPage < listPages - 1);
      this.label(root, layout.list.x + layout.list.width / 2 - 28, layout.list.bottom - 48,
        `${this.listPage + 1}/${listPages}`, 17, '#f5e8c8');
    }
    const selected = this.selectedEntry(); if (!selected) return;
    this.label(root, layout.detail.x + 28, layout.detail.y + 28, selected.title,
      snapshot.layoutClass === 'compact' ? 31 : 28, '#3a2a1a', layout.detail.width - 56);
    this.label(root, layout.detail.x + 28, layout.detail.y + 126,
      `${selected.category.toUpperCase()} · ${statusLabel[selected.status]}`, 16, '#315c39');
    const pages = objectiveDetailPages(selected, layout);
    this.detailPage = Math.min(this.detailPage, pages.length - 1);
    this.label(root, layout.detail.x + 28, layout.detail.y + 180, pages[this.detailPage],
      layout.fontSize, '#3a2a1a', layout.detail.width - 56);
    if (pages.length > 1) {
      progressionButton(this.scene, root, layout.bodyPrevious, 'PREV', () => this.changeDetailPage(-1), this.detailPage > 0);
      progressionButton(this.scene, root, layout.bodyNext, 'NEXT', () => this.changeDetailPage(1), this.detailPage < pages.length - 1);
      this.label(root, layout.detail.x + layout.detail.width / 2 - 28, layout.detail.bottom - 48,
        `${this.detailPage + 1}/${pages.length}`, 17, '#3a2a1a');
    }
  }

  private selectedEntry(): ObjectiveEntry | null {
    return this.store.objectives.data?.find((entry) => entry.id === this.selectedId) ?? null;
  }

  private panel(root: Phaser.GameObjects.Container, bounds: CodexLayout['panel'], color: number): void {
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
