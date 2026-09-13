import Phaser from 'phaser';
import { GameBootstrapData, GameStore } from '../runtime/game-store';
import { RuntimeApiClient, RuntimeApiError } from '../runtime/runtime-api-client';
import { RuntimeViewport, calculateRuntimeViewport } from '../runtime/runtime-viewport';
import { SquadEditorDraft } from './squad-editor-model';
import { SquadEditorScreen, createSquadEditorLayout } from './squad-editor-screen';

describe('SquadEditorScreen', () => {
  function bootstrap(): GameBootstrapData {
    return {
      account: { id: '1', display_name: 'Goblin', role: 'user' },
      player: { teeth: 0, raw_chaos: 0, player_revision: 7, energy: { current: 5, normal_max: 50, regeneration_per_hour: 12, regeneration_interval_seconds: 300, last_regeneration_at: '2026-01-01T00:00:00Z', next_regeneration_at: null, fully_regenerated_at: null } },
      session: { authenticated: true, csrf_token: 'csrf-authoritative' }, server_time: '2026-01-01T00:00:00Z', content_revision: 'a'.repeat(64), progression: { unlock_ids: [] },
      active_squad: null, active_run: null,
    };
  }

  function harness(draft: SquadEditorDraft, api: jasmine.SpyObj<RuntimeApiClient>, returned = jasmine.createSpy('returned')) {
    const store = new GameStore();
    store.hydrateBootstrap(bootstrap());
    const screen = new SquadEditorScreen({} as Phaser.Scene, store, api, new RuntimeViewport(), draft, returned);
    spyOn(screen, 'reflow');
    return { screen, store, returned };
  }

  function api(): jasmine.SpyObj<RuntimeApiClient> {
    return jasmine.createSpyObj<RuntimeApiClient>('api', ['createSquad', 'updateSquad', 'activateSquad', 'deleteSquad']);
  }

  function sceneHarness(): Phaser.Scene {
    const chain = (): Record<string, jasmine.Spy> => {
      const value: Record<string, jasmine.Spy> = {};
      for (const method of ['setScale', 'destroy', 'fillGradientStyle', 'fillRect', 'fillStyle', 'fillRoundedRect', 'lineStyle', 'strokeRoundedRect', 'setInteractive', 'on', 'setOrigin']) {
        value[method] = jasmine.createSpy(method).and.returnValue(value);
      }
      value['add'] = jasmine.createSpy('add').and.returnValue(value);
      return value;
    };
    const parent = document.createElement('div'); const canvas = document.createElement('canvas'); parent.appendChild(canvas);
    return { add: { container: () => chain(), graphics: () => chain(), text: () => chain() }, sys: { game: { canvas } } } as unknown as Phaser.Scene;
  }

  it('keeps editor regions and actions inside Compact, Standard, and Wide safe bounds', () => {
    for (const [width, height] of [[844, 390], [1600, 900], [2560, 1080]]) {
      const snapshot = calculateRuntimeViewport({ cssWidth: width, cssHeight: height, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: true, noHover: true });
      const layout = createSquadEditorLayout(snapshot);
      for (const region of [layout.title, layout.name, layout.formation, layout.roster, layout.actions]) {
        expect(region.x).toBeGreaterThanOrEqual(snapshot.safeBounds.x);
        expect(region.y).toBeGreaterThanOrEqual(snapshot.safeBounds.y);
        expect(region.right).toBeLessThanOrEqual(snapshot.safeBounds.right);
        expect(region.bottom).toBeLessThanOrEqual(snapshot.safeBounds.bottom);
      }
    }
  });

  it('preserves draft name, formation, selection, and dirty state through responsive reflow', () => {
    const draft = SquadEditorDraft.edit({ id: '31', name: 'Raiders', isActive: false, formation: Array(9).fill(null) });
    draft.setName('Responsive Raiders'); draft.selectUnit('11'); draft.placeSelected(8);
    const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    const screen = new SquadEditorScreen(sceneHarness(), store, api(), new RuntimeViewport(), draft, () => undefined);
    screen.reflow(calculateRuntimeViewport({ cssWidth: 844, cssHeight: 390, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: true, noHover: true }));
    screen.reflow(calculateRuntimeViewport({ cssWidth: 2560, cssHeight: 1080, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: false, noHover: false }));
    expect(draft.name).toBe('Responsive Raiders');
    expect(draft.formation[8]).toBe('11');
    expect(draft.selectedUnitId).toBe('11');
    expect(draft.dirty).toBeTrue();
  });

  it('submits one complete update, deduplicates pending input, and reconciles only after success', async () => {
    const client = api();
    let resolve!: (value: Awaited<ReturnType<RuntimeApiClient['updateSquad']>>) => void;
    client.updateSquad.and.returnValue(new Promise((done) => { resolve = done; }));
    const draft = SquadEditorDraft.edit({ id: '31', name: 'Raiders', isActive: false, formation: Array(9).fill(null) });
    draft.setName('Night Raiders');
    draft.selectUnit('11'); draft.placeSelected(4);
    const { screen, store, returned } = harness(draft, client);
    const reconcile = spyOn(store, 'reconcileSquadMutation');

    const pending = screen.save();
    void screen.save();
    expect(client.updateSquad).toHaveBeenCalledOnceWith('31', jasmine.objectContaining({ name: 'Night Raiders', formation: jasmine.any(Array) }), 'csrf-authoritative');
    expect(reconcile).not.toHaveBeenCalled();
    expect(returned).not.toHaveBeenCalled();
    resolve({ squad: { id: '31', name: 'Night Raiders', isActive: false, formation: draft.payload().formation }, activeSquadId: null, playerRevision: 8 });
    await pending;
    expect(reconcile).toHaveBeenCalledOnceWith(jasmine.anything(), 'update');
    expect(returned).toHaveBeenCalledTimes(1);
  });

  it('preserves a failed create draft and reuses its key only for an ambiguous exact retry', async () => {
    const client = api();
    client.createSquad.and.rejectWith(new RuntimeApiError('network'));
    const draft = SquadEditorDraft.create(); draft.setName('Fresh');
    const { screen, store, returned } = harness(draft, client);
    const reconcile = spyOn(store, 'reconcileSquadMutation');
    await screen.save();
    const firstKey = client.createSquad.calls.mostRecent().args[2];
    await screen.save();
    expect(client.createSquad.calls.mostRecent().args[2]).toBe(firstKey);
    draft.setName('Changed');
    await screen.save();
    expect(client.createSquad.calls.mostRecent().args[2]).not.toBe(firstKey);
    expect(draft.name).toBe('Changed');
    expect(reconcile).not.toHaveBeenCalled();
    expect(returned).not.toHaveBeenCalled();
  });

  it('prevents simultaneous create submissions from repeated input', async () => {
    const client = api();
    let resolve!: (value: Awaited<ReturnType<RuntimeApiClient['createSquad']>>) => void;
    client.createSquad.and.returnValue(new Promise((done) => { resolve = done; }));
    const draft = SquadEditorDraft.create(); draft.setName('Only Once');
    const { screen, store } = harness(draft, client);
    spyOn(store, 'reconcileSquadMutation');
    const pending = screen.save(); void screen.save();
    expect(client.createSquad).toHaveBeenCalledTimes(1);
    resolve({ squad: { id: '41', name: 'Only Once', isActive: true, formation: Array(9).fill(null) }, activeSquadId: '41', playerRevision: 8 });
    await pending;
  });

  it('requires explicit discard and delete confirmation and preserves state on active-delete conflict', async () => {
    const client = api();
    client.deleteSquad.and.rejectWith(new RuntimeApiError('http', 409, 'active_squad_delete_forbidden'));
    const draft = SquadEditorDraft.edit({ id: '31', name: 'Raiders', isActive: true, formation: Array(9).fill(null) });
    draft.setName('Dirty Raiders');
    const { screen, store, returned } = harness(draft, client);
    spyOn(store, 'reconcileSquadDelete');
    screen.requestBack();
    expect(returned).not.toHaveBeenCalled();
    screen.confirmDiscard();
    expect(returned).toHaveBeenCalledTimes(1);

    returned.calls.reset();
    screen.requestDelete();
    expect(client.deleteSquad).not.toHaveBeenCalled();
    await screen.confirmDelete();
    expect(client.deleteSquad).toHaveBeenCalledOnceWith('31', 'csrf-authoritative');
    expect(store.reconcileSquadDelete).not.toHaveBeenCalled();
    expect(draft.name).toBe('Dirty Raiders');
    expect(returned).not.toHaveBeenCalled();
  });
});
