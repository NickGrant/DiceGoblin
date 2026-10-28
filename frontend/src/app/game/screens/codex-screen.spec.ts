import Phaser from 'phaser';
import { GameStore } from '../runtime/game-store';
import { CodexEntry } from '../runtime/codex-contracts';
import { RuntimeApiClient } from '../runtime/runtime-api-client';
import { RuntimeViewport, calculateRuntimeViewport } from '../runtime/runtime-viewport';
import { CodexScreen, codexBodyPages, createCodexLayout } from './codex-screen';

describe('CodexScreen', () => {
  const entries: readonly CodexEntry[] = [
    { id: 'codex.farm.mudking', title: 'The Mudking', body: 'Authoritative Mudking knowledge.', owned: true },
    { id: 'codex.mountains.engineer', title: 'Chief Engineer', body: null, owned: false },
  ];

  function snapshot(width: number, height: number) {
    return calculateRuntimeViewport({ cssWidth: width, cssHeight: height,
      safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: false, noHover: false });
  }

  function harness() {
    const texts: string[] = [];
    const attributes = new Map<string, string>();
    const host = { setAttribute: (key: string, value: string) => attributes.set(key, value),
      removeAttribute: (key: string) => attributes.delete(key) };
    const chain = () => {
      const value: Record<string, unknown> = {};
      for (const method of ['setScale', 'destroy', 'fillGradientStyle', 'fillRect', 'fillStyle',
        'fillRoundedRect', 'setInteractive', 'on', 'setOrigin', 'add']) value[method] = () => value;
      return value;
    };
    const scene = { add: { container: () => chain(), graphics: () => chain(),
      text: (_x: number, _y: number, value: string) => { texts.push(value); return chain(); } },
      sys: { game: { canvas: { parentElement: host } } } } as unknown as Phaser.Scene;
    return { scene, texts, attributes };
  }

  function setup(client: jasmine.SpyObj<RuntimeApiClient>) {
    const store = new GameStore();
    const ui = harness();
    const back = jasmine.createSpy('back');
    const screen = new CodexScreen(ui.scene, store, client, new RuntimeViewport(), back);
    return { store, ui, screen, back };
  }

  it('loads lazily, displays authoritative order, and reuses the fresh cache on reopen', async () => {
    const client = jasmine.createSpyObj<RuntimeApiClient>('api', ['getCodex']);
    let finish!: (value: readonly CodexEntry[]) => void;
    client.getCodex.and.returnValue(new Promise((resolve) => { finish = resolve; }));
    const { ui, screen, store } = setup(client);
    expect(client.getCodex).not.toHaveBeenCalled();
    screen.create();
    expect(ui.texts).toContain('Loading Codex…');
    expect(client.getCodex).toHaveBeenCalledTimes(1);
    finish(entries); await Promise.resolve(); await Promise.resolve();
    expect(store.codex.status).toBe('fresh');
    expect(ui.texts.indexOf('The Mudking')).toBeLessThan(ui.texts.indexOf('Chief Engineer'));
    expect(ui.texts).toContain('Authoritative Mudking knowledge.');
    expect(ui.texts).toContain('DISCOVERED');
    expect(ui.texts).toContain('UNDISCOVERED');
    screen.destroy(); screen.create(); screen.destroy(); screen.create();
    expect(client.getCodex).toHaveBeenCalledTimes(1);
    screen.destroy();
    expect(ui.attributes.has('data-codex-status')).toBeFalse();
    const rendered = ui.texts.length;
    store.markCodexStale();
    expect(ui.texts.length).toBe(rendered);
  });

  it('keeps undiscovered body absent and selection local', async () => {
    const client = jasmine.createSpyObj<RuntimeApiClient>('api', ['getCodex']);
    client.getCodex.and.resolveTo(entries);
    const { ui, screen, store } = setup(client);
    screen.create(); await store.loadCodex(client);
    screen.selectEntry(entries[1].id);
    expect(screen.selectedEntryId).toBe(entries[1].id);
    expect(ui.texts.at(-1)).toBe('This entry has not been discovered.');
    expect(ui.texts).not.toContain('Undiscovered capture knowledge.');
    screen.selectEntry(entries[0].id);
    expect(ui.texts).toContain('Authoritative Mudking knowledge.');
    screen.destroy();
  });

  it('retries a failed read and reloads after revision invalidation', async () => {
    const client = jasmine.createSpyObj<RuntimeApiClient>('api', ['getCodex']);
    client.getCodex.and.returnValues(Promise.reject(new Error('offline')), Promise.resolve(entries),
      Promise.resolve([{ ...entries[0], body: 'Revised knowledge.' }, entries[1]]));
    const { ui, screen, store } = setup(client);
    screen.create(); await store.loadCodex(client);
    expect(ui.texts).toContain('Codex could not be verified');
    screen.retryRead(); await store.loadCodex(client);
    expect(ui.texts).toContain('Authoritative Mudking knowledge.');
    store.markCodexStale();
    await store.loadCodex(client);
    screen.destroy(); screen.create();
    expect(client.getCodex).toHaveBeenCalledTimes(3);
    expect(ui.texts).toContain('Revised knowledge.');
    screen.destroy();
  });

  it('returns to Camp and unsubscribes after close', async () => {
    const client = jasmine.createSpyObj<RuntimeApiClient>('api', ['getCodex']);
    client.getCodex.and.resolveTo(entries);
    const { ui, screen, store, back } = setup(client);
    screen.create(); await store.loadCodex(client);
    screen.requestBack(); expect(back).toHaveBeenCalledTimes(1);
    screen.destroy();
    const rendered = ui.texts.length;
    store.markCodexStale();
    expect(ui.texts.length).toBe(rendered);
  });

  it('keeps long text and lists accessible across Compact, Standard, and Wide', () => {
    for (const [width, height] of [[844, 390], [1600, 900], [2560, 1080]]) {
      const state = snapshot(width, height);
      const layout = createCodexLayout(state);
      expect(layout.list.x).toBeGreaterThanOrEqual(state.safeBounds.x);
      expect(layout.detail.right).toBeLessThanOrEqual(state.safeBounds.right);
      expect(layout.list.bottom).toBeLessThanOrEqual(state.safeBounds.bottom);
      expect(layout.rowCount).toBeGreaterThan(0);
      const body = 'Long authored knowledge. '.repeat(100);
      const pages = codexBodyPages(body, layout);
      expect(pages.length).toBeGreaterThan(1);
      expect(pages.join('')).toBe(body);
    }
  });
});
