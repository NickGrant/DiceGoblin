import Phaser from 'phaser';
import { ClientContentRegistry } from '../runtime/client-content-registry';
import {
  GameStore,
  WarbandCacheSnapshot,
  WarbandDomainName,
  WarbandDomainState,
} from '../runtime/game-store';
import { RuntimeApiClient } from '../runtime/runtime-api-client';
import { Bounds, RuntimeViewport, RuntimeViewportSnapshot } from '../runtime/runtime-viewport';
import { WarbandDieSummary, WarbandSquadSummary, WarbandUnitSummary } from '../runtime/warband-contracts';
import { GameSceneScreen } from './game-screen-navigation';

export type WarbandTab = 'units' | 'dice' | 'squads';

export interface WarbandLayout {
  readonly mode: RuntimeViewportSnapshot['layoutClass'];
  readonly header: Bounds;
  readonly backButton: Bounds;
  readonly summaryCards: readonly [Bounds, Bounds, Bounds];
  readonly tabs: readonly [Bounds, Bounds, Bounds];
  readonly content: Bounds;
  readonly pageSize: number;
}

function box(x: number, y: number, width: number, height: number): Bounds {
  return { x, y, width, height, right: x + width, bottom: y + height };
}

export function createWarbandLayout(snapshot: RuntimeViewportSnapshot): WarbandLayout {
  const { safeBounds, layoutClass: mode } = snapshot;
  const margin = mode === 'compact' ? 22 : mode === 'wide' ? 60 : 44;
  const width = Math.max(0, safeBounds.width - margin * 2);
  const left = safeBounds.x + margin;
  const headerHeight = mode === 'compact' ? 120 : 112;
  const header = box(left, safeBounds.y + margin, width, headerHeight);
  const backButton = box(left, header.y + 8, mode === 'compact' ? 220 : 156, mode === 'compact' ? 104 : 54);
  const summaryY = header.bottom + (mode === 'compact' ? 12 : 18);
  const summaryGap = mode === 'compact' ? 12 : 18;
  const summaryHeight = mode === 'compact' ? 86 : 76;
  const summaryWidth = (width - summaryGap * 2) / 3;
  const summaryCards = [0, 1, 2].map((index) =>
    box(left + index * (summaryWidth + summaryGap), summaryY, summaryWidth, summaryHeight),
  ) as [Bounds, Bounds, Bounds];
  const tabsY = summaryY + summaryHeight + (mode === 'compact' ? 12 : 18);
  const tabHeight = mode === 'compact' ? 104 : 58;
  const tabWidth = (width - summaryGap * 2) / 3;
  const tabs = [0, 1, 2].map((index) =>
    box(left + index * (tabWidth + summaryGap), tabsY, tabWidth, tabHeight),
  ) as [Bounds, Bounds, Bounds];
  const contentY = tabsY + tabHeight + (mode === 'compact' ? 12 : 18);
  return {
    mode,
    header,
    backButton,
    summaryCards,
    tabs,
    content: box(left, contentY, width, Math.max(160, safeBounds.bottom - margin - contentY)),
    pageSize: mode === 'compact' ? 3 : mode === 'wide' ? 7 : 5,
  };
}

function statusLabel(state: { readonly status: WarbandDomainState<unknown>['status']; readonly data: readonly unknown[] | null }): string {
  if (state.status === 'fresh') return `${state.data?.length ?? 0}`;
  if (state.status === 'loading') return 'LOADING';
  if (state.status === 'error') return 'ERROR';
  if (state.status === 'stale') return `${state.data?.length ?? 0} · STALE`;
  return 'NOT LOADED';
}

/** Read-only Warband view backed by the runtime-lifetime lazy cache. */
export class WarbandScreen implements GameSceneScreen {
  readonly key = 'warband' as const;
  private root: Phaser.GameObjects.Container | null = null;
  private activeLayout: WarbandLayout | null = null;
  private unsubscribeStore: (() => void) | null = null;
  private activeTab: WarbandTab;
  private readonly pages: Record<WarbandTab, number> = { units: 0, dice: 0, squads: 0 };

  constructor(
    private readonly scene: Phaser.Scene,
    private readonly store: GameStore,
    private readonly api: RuntimeApiClient,
    private readonly content: ClientContentRegistry,
    private readonly viewport: RuntimeViewport,
    private readonly returnToCamp: () => void,
    initialTab: WarbandTab = 'squads',
  ) {
    this.activeTab = initialTab;
  }

  get tab(): WarbandTab {
    return this.activeTab;
  }

  get layout(): WarbandLayout | null {
    return this.activeLayout;
  }

  create(): void {
    this.unsubscribeStore = this.store.subscribeWarband(() => this.reflow(this.viewport.snapshot));
    this.reflow(this.viewport.snapshot);
    void this.store.loadWarbandDomains(this.api, this.content);
  }

  reflow(snapshot: RuntimeViewportSnapshot): void {
    this.root?.destroy(true);
    this.root = null;
    this.activeLayout = createWarbandLayout(snapshot);
    this.render(snapshot, this.activeLayout, this.store.warband);
    this.publishReadyState();
  }

  destroy(): void {
    this.unsubscribeStore?.();
    this.unsubscribeStore = null;
    this.root?.destroy(true);
    this.root = null;
    this.activeLayout = null;
    const parent = (this.scene.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game?.canvas.parentElement;
    if (parent) delete parent.dataset['warbandReady'];
  }

  selectTab(tab: WarbandTab): void {
    if (this.activeTab === tab) return;
    this.activeTab = tab;
    this.reflow(this.viewport.snapshot);
  }

  private render(snapshot: RuntimeViewportSnapshot, layout: WarbandLayout, cache: WarbandCacheSnapshot): void {
    const root = this.scene.add.container(0, 0).setScale(snapshot.gameScale);
    this.root = root;
    const background = this.scene.add.graphics();
    background.fillGradientStyle(0x0d2422, 0x18362d, 0x09191b, 0x10252a, 1);
    background.fillRect(0, 0, snapshot.logicalWidth, snapshot.logicalHeight);
    background.fillStyle(0x8db341, 0.07);
    background.fillCircle(snapshot.logicalWidth * 0.82, snapshot.logicalHeight * 0.28, snapshot.logicalHeight * 0.62);
    root.add(background);

    this.addButton(root, layout.backButton, 'RETURN TO CAMP', this.returnToCamp, false);
    const titleX = layout.backButton.right + (layout.mode === 'compact' ? 30 : 42);
    const title = this.scene.add.text(titleX, layout.header.y + 3, 'WARBAND', {
      color: '#f5e8c8', fontFamily: 'Georgia, serif',
      fontSize: layout.mode === 'compact' ? '52px' : '50px', fontStyle: 'bold',
      stroke: '#302015', strokeThickness: 6,
    });
    const subtitle = this.scene.add.text(titleX + 2, layout.header.y + (layout.mode === 'compact' ? 66 : 64), 'Your goblins, dice, and battle formations', {
      color: '#c8b98f', fontFamily: 'system-ui, sans-serif',
      fontSize: layout.mode === 'compact' ? '26px' : '18px', fontStyle: 'bold',
    });
    root.add([title, subtitle]);

    const labels: readonly [string, string, string] = ['GOBLINS', 'OWNED DICE', 'SAVED SQUADS'];
    const domains: readonly WarbandDomainName[] = ['units', 'dice', 'squads'];
    layout.summaryCards.forEach((region, index) => {
      const state = cache[domains[index]];
      const card = this.scene.add.graphics();
      card.fillStyle(0x2d2118, 0.98);
      card.fillRoundedRect(region.x, region.y, region.width, region.height, 13);
      card.lineStyle(3, state.status === 'error' ? 0xd65a43 : 0x8a6a35, 1);
      card.strokeRoundedRect(region.x + 1, region.y + 1, region.width - 2, region.height - 2, 13);
      root.add(card);
      root.add(this.scene.add.text(region.x + 18, region.y + 12, labels[index], {
        color: '#c8b98f', fontFamily: 'system-ui, sans-serif', fontSize: layout.mode === 'compact' ? '21px' : '13px', fontStyle: 'bold',
      }));
      root.add(this.scene.add.text(region.right - 18, region.y + region.height / 2, statusLabel(state), {
        color: state.status === 'error' ? '#ff9b87' : '#f5e8c8', fontFamily: 'Georgia, serif', fontSize: layout.mode === 'compact' ? '31px' : '25px', fontStyle: 'bold',
      }).setOrigin(1, 0.5));
    });

    const tabLabels: readonly [string, string, string] = ['UNIT ROSTER', 'DICE INVENTORY', 'SQUADS & FORMATION'];
    layout.tabs.forEach((region, index) => {
      const tab = domains[index];
      this.addButton(root, region, tabLabels[index], () => this.selectTab(tab), this.activeTab === tab);
    });

    const panel = this.scene.add.graphics();
    panel.fillStyle(0xf2e4c1, 0.97);
    panel.fillRoundedRect(layout.content.x, layout.content.y, layout.content.width, layout.content.height, 18);
    panel.lineStyle(4, 0x8a5a34, 1);
    panel.strokeRoundedRect(layout.content.x + 2, layout.content.y + 2, layout.content.width - 4, layout.content.height - 4, 18);
    root.add(panel);
    if (this.activeTab === 'units') this.renderUnits(root, layout, cache.units);
    else if (this.activeTab === 'dice') this.renderDice(root, layout, cache.dice, cache.units);
    else this.renderSquads(root, layout, cache.squads, cache.units);
  }

  private renderUnits(root: Phaser.GameObjects.Container, layout: WarbandLayout, state: WarbandDomainState<WarbandUnitSummary>): void {
    if (!this.renderDomainState(root, layout, 'units', state, 'No goblins have joined your warband yet.')) return;
    const items = this.pageItems('units', state.data ?? [], layout.pageSize);
    this.renderRows(root, layout, items.map((unit) => ({
      title: unit.displayName,
      detail: `${unit.unitType.display_name} · ${unit.kin.display_name} · Level ${unit.level}`,
      badge: unit.unitType.role.toUpperCase(),
    })), (state.data?.length ?? 0) > layout.pageSize);
    this.renderPager(root, layout, 'units', state.data?.length ?? 0);
  }

  private renderDice(
    root: Phaser.GameObjects.Container,
    layout: WarbandLayout,
    state: WarbandDomainState<WarbandDieSummary>,
    units: WarbandDomainState<WarbandUnitSummary>,
  ): void {
    if (!this.renderDomainState(root, layout, 'dice', state, 'No dice are waiting in your inventory.')) return;
    const unitNames = new Map((units.data ?? []).map((unit) => [unit.id, unit.displayName]));
    const items = this.pageItems('dice', state.data ?? [], layout.pageSize);
    this.renderRows(root, layout, items.map((die) => {
      const binding = die.bindings[0];
      const equipped = binding ? `Bound to ${unitNames.get(binding.unitId) ?? 'a warband unit'} · ${binding.ability.display_name}` : 'Ready to equip';
      return {
        title: `d${die.size} ${die.profile.display_name}`,
        detail: `${die.material.display_name} · ${die.aspects.map((aspect) => aspect.display_name).join(', ') || 'Unaspected'} · ${equipped}`,
        badge: die.profile.rarity.toUpperCase(),
      };
    }), (state.data?.length ?? 0) > layout.pageSize);
    this.renderPager(root, layout, 'dice', state.data?.length ?? 0);
  }

  private renderSquads(
    root: Phaser.GameObjects.Container,
    layout: WarbandLayout,
    state: WarbandDomainState<WarbandSquadSummary>,
    units: WarbandDomainState<WarbandUnitSummary>,
  ): void {
    if (!this.renderDomainState(root, layout, 'squads', state, 'No saved squads are assembled yet.')) return;
    const squads = this.pageItems('squads', state.data ?? [], Math.min(3, layout.pageSize));
    const unitNames = new Map((units.data ?? []).map((unit) => [unit.id, unit.displayName]));
    const active = (state.data ?? []).find((squad) => squad.isActive) ?? squads[0];
    const split = layout.mode === 'compact' ? 0.38 : 0.34;
    const listWidth = layout.content.width * split;
    const rowsLayout = { ...layout, content: box(layout.content.x, layout.content.y, listWidth, layout.content.height) };
    this.renderRows(root, rowsLayout, squads.map((squad) => ({
      title: squad.name,
      detail: `${squad.formation.filter(Boolean).length} of 9 positions filled`,
      badge: squad.isActive ? 'ACTIVE' : 'SAVED',
    })), (state.data?.length ?? 0) > Math.min(3, layout.pageSize));
    if (active) this.renderFormation(root, layout, active, unitNames, listWidth);
    this.renderPager(root, layout, 'squads', state.data?.length ?? 0, Math.min(3, layout.pageSize));
  }

  private renderFormation(
    root: Phaser.GameObjects.Container,
    layout: WarbandLayout,
    squad: WarbandSquadSummary,
    unitNames: ReadonlyMap<string, string>,
    listWidth: number,
  ): void {
    const x = layout.content.x + listWidth + 28;
    const availableWidth = layout.content.right - x - 28;
    const label = this.scene.add.text(x, layout.content.y + 22, `${squad.isActive ? 'ACTIVE FORMATION' : 'FORMATION'} · ${squad.name}`, {
      color: '#6a321f', fontFamily: 'system-ui, sans-serif', fontSize: layout.mode === 'compact' ? '23px' : '18px', fontStyle: 'bold',
    });
    root.add(label);
    const gap = layout.mode === 'compact' ? 8 : 12;
    const gridTop = layout.content.y + (layout.mode === 'compact' ? 58 : 66);
    const cellWidth = (availableWidth - gap * 2) / 3;
    const cellHeight = Math.min((layout.content.bottom - gridTop - 22 - gap * 2) / 3, layout.mode === 'compact' ? 82 : 106);
    squad.formation.forEach((unitId, index) => {
      const column = index % 3;
      const row = Math.floor(index / 3);
      const cell = box(x + column * (cellWidth + gap), gridTop + row * (cellHeight + gap), cellWidth, cellHeight);
      const graphic = this.scene.add.graphics();
      graphic.fillStyle(unitId ? 0x315947 : 0xd7c7a3, 1);
      graphic.fillRoundedRect(cell.x, cell.y, cell.width, cell.height, 11);
      graphic.lineStyle(2, unitId ? 0xc9972b : 0xaa9670, 1);
      graphic.strokeRoundedRect(cell.x + 1, cell.y + 1, cell.width - 2, cell.height - 2, 11);
      root.add(graphic);
      root.add(this.scene.add.text(cell.x + cell.width / 2, cell.y + cell.height / 2,
        unitId ? (unitNames.get(unitId) ?? 'Occupied') : 'Open', {
          align: 'center', color: unitId ? '#fff4d3' : '#74664b', fontFamily: 'system-ui, sans-serif',
          fontSize: layout.mode === 'compact' ? '21px' : '16px', fontStyle: unitId ? 'bold' : 'normal',
          wordWrap: { width: cell.width - 12 },
        }).setOrigin(0.5));
    });
  }

  private renderDomainState<T>(
    root: Phaser.GameObjects.Container,
    layout: WarbandLayout,
    domain: WarbandDomainName,
    state: WarbandDomainState<T>,
    emptyMessage: string,
  ): boolean {
    if (state.status === 'fresh' && (state.data?.length ?? 0) > 0) return true;
    const centerX = layout.content.x + layout.content.width / 2;
    const centerY = layout.content.y + layout.content.height / 2;
    let title = 'Loading warband records…';
    let detail = 'Fetching this collection from the goblin ledgers.';
    if (state.status === 'fresh') {
      title = 'Nothing here yet';
      detail = emptyMessage;
    } else if (state.status === 'error') {
      title = state.error === 'unauthorized' ? 'Your session has expired' : 'This ledger could not be read';
      detail = state.error === 'integrity'
        ? 'The returned records did not match the current game content.'
        : 'Camp and the other ledgers remain available.';
    } else if (state.status === 'stale' && state.data) {
      return true;
    }
    root.add(this.scene.add.text(centerX, centerY - 35, title, {
      color: '#5b351f', fontFamily: 'Georgia, serif', fontSize: layout.mode === 'compact' ? '27px' : '31px', fontStyle: 'bold',
    }).setOrigin(0.5));
    root.add(this.scene.add.text(centerX, centerY + 8, detail, {
      align: 'center', color: '#74664b', fontFamily: 'system-ui, sans-serif', fontSize: layout.mode === 'compact' ? '16px' : '17px',
    }).setOrigin(0.5));
    if (state.status === 'error') {
      this.addButton(root, box(centerX - 80, centerY + 45, 160, 54), 'RETRY', () => {
        void this.store.retryWarbandDomain(domain, this.api, this.content);
      }, true);
    }
    return false;
  }

  private renderRows(
    root: Phaser.GameObjects.Container,
    layout: WarbandLayout,
    rows: readonly { title: string; detail: string; badge: string }[],
    reservePager = false,
  ): void {
    const gap = layout.mode === 'compact' ? 8 : 11;
    const top = layout.content.y + 18;
    const rowHeight = Math.max(54, (layout.content.height - 36 - (reservePager ? 74 : 0) - gap * Math.max(0, rows.length - 1)) / Math.max(rows.length, 1));
    rows.forEach((row, index) => {
      const y = top + index * (rowHeight + gap);
      const width = layout.content.width - 36;
      const card = this.scene.add.graphics();
      card.fillStyle(index % 2 === 0 ? 0xe2d2ab : 0xeadcba, 1);
      card.fillRoundedRect(layout.content.x + 18, y, width, rowHeight, 10);
      card.lineStyle(2, 0xb69a65, 0.8);
      card.strokeRoundedRect(layout.content.x + 19, y + 1, width - 2, rowHeight - 2, 10);
      root.add(card);
      root.add(this.scene.add.text(layout.content.x + 34, y + 10, row.title, {
        color: '#3a2a1a', fontFamily: 'Georgia, serif', fontSize: layout.mode === 'compact' ? '28px' : '21px', fontStyle: 'bold',
      }));
      root.add(this.scene.add.text(layout.content.x + 34, y + (layout.mode === 'compact' ? 50 : 40), row.detail, {
        color: '#74664b', fontFamily: 'system-ui, sans-serif', fontSize: layout.mode === 'compact' ? '20px' : '14px',
        wordWrap: { width: Math.max(100, width - 190) },
      }));
      root.add(this.scene.add.text(layout.content.x + width, y + rowHeight / 2, row.badge, {
        color: row.badge === 'ACTIVE' ? '#356b43' : '#8a5a34', fontFamily: 'system-ui, sans-serif', fontSize: layout.mode === 'compact' ? '20px' : '13px', fontStyle: 'bold',
      }).setOrigin(1, 0.5));
    });
  }

  private renderPager(
    root: Phaser.GameObjects.Container,
    layout: WarbandLayout,
    tab: WarbandTab,
    itemCount: number,
    pageSize = layout.pageSize,
  ): void {
    const pages = Math.max(1, Math.ceil(itemCount / pageSize));
    this.pages[tab] = Math.min(this.pages[tab], pages - 1);
    if (pages <= 1) return;
    const y = layout.content.bottom - 62;
    this.addButton(root, box(layout.content.right - 244, y, 96, 46), '‹ PREV', () => this.changePage(tab, -1, pages), false);
    this.addButton(root, box(layout.content.right - 138, y, 96, 46), 'NEXT ›', () => this.changePage(tab, 1, pages), false);
    root.add(this.scene.add.text(layout.content.right - 270, y + 23, `${this.pages[tab] + 1} / ${pages}`, {
      color: '#5b351f', fontFamily: 'system-ui, sans-serif', fontSize: '14px', fontStyle: 'bold',
    }).setOrigin(1, 0.5));
  }

  private pageItems<T>(tab: WarbandTab, items: readonly T[], pageSize: number): readonly T[] {
    const pages = Math.max(1, Math.ceil(items.length / pageSize));
    this.pages[tab] = Math.min(this.pages[tab], pages - 1);
    const start = this.pages[tab] * pageSize;
    return items.slice(start, start + pageSize);
  }

  private changePage(tab: WarbandTab, direction: -1 | 1, pages: number): void {
    this.pages[tab] = (this.pages[tab] + direction + pages) % pages;
    this.reflow(this.viewport.snapshot);
  }

  private addButton(
    root: Phaser.GameObjects.Container,
    region: Bounds,
    label: string,
    action: () => void,
    active: boolean,
  ): void {
    const button = this.scene.add.graphics();
    button.fillStyle(active ? 0x8a5a34 : 0x273e35, 1);
    button.fillRoundedRect(region.x, region.y, region.width, region.height, 12);
    button.lineStyle(3, active ? 0xf2c14e : 0x8a6a45, 1);
    button.strokeRoundedRect(region.x + 1, region.y + 1, region.width - 2, region.height - 2, 12);
    button.setInteractive(new Phaser.Geom.Rectangle(region.x, region.y, region.width, region.height), Phaser.Geom.Rectangle.Contains);
    button.on('pointerup', action);
    const text = this.scene.add.text(region.x + region.width / 2, region.y + region.height / 2, label, {
      align: 'center', color: '#fff4d3', fontFamily: 'system-ui, sans-serif',
      fontSize: region.height >= 80 ? '24px' : region.height >= 60 ? '18px' : '14px', fontStyle: 'bold',
    }).setOrigin(0.5);
    root.add([button, text]);
  }

  private publishReadyState(): void {
    const parent = (this.scene.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game?.canvas.parentElement;
    const ready = (['units', 'dice', 'squads'] as const).every((domain) => this.store.warband[domain].status !== 'loading');
    if (parent) parent.dataset['warbandReady'] = ready ? 'true' : 'false';
  }
}
