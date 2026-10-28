import Phaser from 'phaser';
import { GameStore } from '../runtime/game-store';
import { ObjectiveEntry } from '../runtime/objective-contracts';
import { RuntimeApiClient } from '../runtime/runtime-api-client';
import { RuntimeViewport, calculateRuntimeViewport } from '../runtime/runtime-viewport';
import { ObjectivesScreen, objectiveDetailPages } from './objectives-screen';
import { createCodexLayout } from './codex-screen';

describe('ObjectivesScreen', () => {
  const entries: readonly ObjectiveEntry[] = [
    { id: 'objective.farm', title: 'Farm Work', description: 'Complete the Farm expedition.',
      category: 'objective', status: 'active', requirements: [
        { id: 'victories', label: 'Victories', current: 2, needed: 3 },
        { id: 'boss', label: 'Boss victory', current: 0, needed: 1 },
      ] },
    { id: 'objective.mountains', title: 'Mountain Path', description: 'Find the path.',
      category: 'objective', status: 'unstarted', requirements: [
        { id: 'visits', label: 'Visits', current: 0, needed: 1 },
      ] },
    { id: 'objective.victory', title: 'First Victory', description: 'Victory is yours.',
      category: 'bounty', status: 'completed', requirements: [
        { id: 'wins', label: 'Wins', current: 1, needed: 1 },
      ] },
  ];

  function setup(api: jasmine.SpyObj<RuntimeApiClient>) {
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
    const keyboard = { on: jasmine.createSpy('on'), off: jasmine.createSpy('off') };
    const scene = { add: { container: () => chain(), graphics: () => chain(),
      text: (_x: number, _y: number, value: string) => { texts.push(value); return chain(); } },
      input: { keyboard }, sys: { game: { canvas: { parentElement: host } } } } as unknown as Phaser.Scene;
    const store = new GameStore(); const back = jasmine.createSpy('back');
    const screen = new ObjectivesScreen(scene, store, api, new RuntimeViewport(), back);
    return { texts, attributes, keyboard, store, back, screen };
  }

  it('loads lazily, preserves server order and progress/status, and reuses a fresh cache', async () => {
    const api = jasmine.createSpyObj<RuntimeApiClient>('api', ['getObjectives']);
    api.getObjectives.and.resolveTo(entries);
    const { texts, store, screen, attributes, keyboard } = setup(api);
    expect(api.getObjectives).not.toHaveBeenCalled();
    screen.create(); await store.loadObjectives(api);
    expect(api.getObjectives).toHaveBeenCalledTimes(1);
    expect(texts.indexOf('Farm Work')).toBeLessThan(texts.indexOf('Mountain Path'));
    expect(texts.indexOf('Mountain Path')).toBeLessThan(texts.indexOf('First Victory'));
    expect(texts).toContain('ACTIVE'); expect(texts).toContain('UNSTARTED'); expect(texts).toContain('COMPLETED');
    expect(texts.some((text) => text.includes('Victories: 2 / 3'))).toBeTrue();
    expect(texts).not.toContain('CLAIM');
    screen.destroy(); screen.create(); screen.destroy(); screen.create();
    expect(api.getObjectives).toHaveBeenCalledTimes(1);
    screen.destroy();
    expect(keyboard.on).toHaveBeenCalledTimes(3);
    expect(keyboard.off).toHaveBeenCalledTimes(3);
    expect(attributes.has('data-objectives-status')).toBeFalse();
    const rendered = texts.length;
    store.markObjectivesStale();
    expect(texts.length).toBe(rendered);
  });

  it('retries errors and reloads stale data without showing old progress as current', async () => {
    const api = jasmine.createSpyObj<RuntimeApiClient>('api', ['getObjectives']);
    api.getObjectives.and.returnValues(Promise.reject(new Error('offline')), Promise.resolve(entries),
      Promise.resolve([{ ...entries[0], requirements: [{ ...entries[0].requirements[0], current: 3 }, entries[0].requirements[1]] },
        entries[1], entries[2]]));
    const { texts, store, screen } = setup(api);
    screen.create(); await store.loadObjectives(api);
    expect(texts).toContain('Objectives could not be verified');
    screen.retryRead(); await store.loadObjectives(api);
    expect(store.objectives.status).toBe('fresh');
    store.markObjectivesStale();
    expect(texts.at(-1)).toBe('Loading Objectives…');
    await store.loadObjectives(api);
    expect(texts.some((text) => text.includes('Victories: 3 / 3'))).toBeTrue();
    screen.destroy();
  });

  it('pages long lists and requirements, and cleans up keyboard and store listeners', async () => {
    const many = Array.from({ length: 30 }, (_, i) => ({ ...entries[0], id: `objective.item_${i.toString().padStart(2, '0')}` }));
    many[0] = { ...many[0], requirements: Array.from({ length: 35 }, (_, i) =>
      ({ id: `step_${i}`, label: `Step ${i}`, current: i, needed: 40 })) };
    const api = jasmine.createSpyObj<RuntimeApiClient>('api', ['getObjectives']); api.getObjectives.and.resolveTo(many);
    const { screen, store, keyboard, texts, back } = setup(api);
    screen.create(); await store.loadObjectives(api);
    const layout = screen.layout!;
    expect(objectiveDetailPages(many[0], layout).length).toBeGreaterThan(1);
    screen.changeDetailPage(1); expect(texts).toContain('2/' + objectiveDetailPages(many[0], layout).length);
    screen.changeListPage(1); expect(texts).toContain('2/' + Math.ceil(many.length / layout.rowCount));
    screen.requestBack(); expect(back).toHaveBeenCalledTimes(1);
    screen.destroy(); expect(keyboard.off).toHaveBeenCalledTimes(1);
    const rendered = texts.length; store.markObjectivesStale(); expect(texts.length).toBe(rendered);
  });

  it('keeps panels within Compact, Standard and Wide safe bounds', () => {
    for (const [width, height] of [[844, 390], [1600, 900], [2560, 1080]]) {
      const snapshot = calculateRuntimeViewport({ cssWidth: width, cssHeight: height,
        safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: false, noHover: false });
      const layout = createCodexLayout(snapshot);
      expect(layout.list.x).toBeGreaterThanOrEqual(snapshot.safeBounds.x);
      expect(layout.detail.right).toBeLessThanOrEqual(snapshot.safeBounds.right);
      expect(layout.detail.bottom).toBeLessThanOrEqual(snapshot.safeBounds.bottom);
    }
  });
});
