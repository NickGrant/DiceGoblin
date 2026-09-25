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
      session: { authenticated: true, csrf_token: 'csrf-authoritative' }, server_time: '2026-01-01T00:00:00Z', content_revision: 'a'.repeat(64), progression: { unlock_ids: [], available_region_ids: ['region.the_farm'] },
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

  function interactionHarness() {
    const graphics: Array<{ input: { cursor: string } | null; hit: Phaser.Geom.Rectangle | null; pointerUp: (() => void) | null }> = [];
    const textValues: string[] = [];
    const chain = () => {
      const value = { input: null as { cursor: string } | null, hit: null as Phaser.Geom.Rectangle | null,
        pointerUp: null as (() => void) | null } as Record<string, unknown>;
      for (const method of ['setScale', 'destroy', 'fillGradientStyle', 'fillRect', 'fillStyle', 'fillRoundedRect', 'lineStyle', 'strokeRoundedRect', 'setOrigin']) {
        value[method] = jasmine.createSpy(method).and.returnValue(value);
      }
      value['setInteractive'] = jasmine.createSpy('setInteractive').and.callFake((hit: Phaser.Geom.Rectangle) => {
        value['hit'] = hit; value['input'] = { cursor: '' }; return value;
      });
      value['on'] = jasmine.createSpy('on').and.callFake((event: string, action: () => void) => {
        if (event === 'pointerup') value['pointerUp'] = action; return value;
      });
      value['add'] = jasmine.createSpy('add').and.returnValue(value);
      return value;
    };
    const parent = document.createElement('div'); const canvas = document.createElement('canvas'); parent.appendChild(canvas);
    const scene = { add: { container: () => chain(), graphics: () => {
      const graphic = chain(); graphics.push(graphic as typeof graphics[number]); return graphic;
    }, text: (_x: number, _y: number, text: string) => { textValues.push(text); return chain(); } },
      sys: { game: { canvas } } } as unknown as Phaser.Scene;
    return { scene, graphics, textValues };
  }

  function domHarness(draft: SquadEditorDraft, client: jasmine.SpyObj<RuntimeApiClient>) {
    const scene = sceneHarness();
    const canvas = (scene.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game!.canvas;
    const parent = canvas.parentElement!;
    document.body.appendChild(parent);
    const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    const viewport = new RuntimeViewport();
    const screen = new SquadEditorScreen(scene, store, client, viewport, draft, () => undefined);
    screen.create();
    const input = parent.querySelector<HTMLInputElement>('[data-squad-name-input="true"]')!;
    return { screen, store, viewport, parent, input };
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

  it('blocks focused native name editing during save and restores it after failure without losing the draft', async () => {
    const client = api();
    let reject!: (reason: unknown) => void;
    client.updateSquad.and.returnValue(new Promise((_resolve, rejectPromise) => { reject = rejectPromise; }));
    const draft = SquadEditorDraft.edit({ id: '31', name: 'Raiders', isActive: false, formation: Array(9).fill(null) });
    const { screen, parent, input } = domHarness(draft, client);
    input.value = 'Submitted Raiders'; input.dispatchEvent(new Event('input'));
    input.focus();

    const pending = screen.save();
    expect(input.disabled).toBeTrue();
    expect(input.readOnly).toBeTrue();
    expect(document.activeElement).not.toBe(input);
    input.value = 'Changed while saving'; input.dispatchEvent(new Event('input'));
    expect(draft.name).toBe('Submitted Raiders');
    expect(input.value).toBe('Submitted Raiders');

    reject(new RuntimeApiError('network'));
    await pending;
    expect(input.disabled).toBeFalse();
    expect(input.readOnly).toBeFalse();
    expect(draft.name).toBe('Submitted Raiders');
    input.value = 'Editable after failure'; input.dispatchEvent(new Event('input'));
    expect(draft.name).toBe('Editable after failure');
    screen.destroy(); parent.remove();
  });

  it('blocks and hides native name input during confirmation', () => {
    const draft = SquadEditorDraft.edit({ id: '31', name: 'Raiders', isActive: false, formation: Array(9).fill(null) });
    draft.setName('Dirty Raiders');
    const { screen, parent, input } = domHarness(draft, api());
    screen.requestBack();
    expect(input.disabled).toBeTrue();
    expect(input.readOnly).toBeTrue();
    expect(input.style.display).toBe('none');
    input.value = 'Changed under confirmation'; input.dispatchEvent(new Event('input'));
    expect(draft.name).toBe('Dirty Raiders');
    screen.cancelConfirmation();
    expect(input.disabled).toBeFalse();
    expect(input.readOnly).toBeFalse();
    expect(input.style.display).toBe('block');
    expect(input.value).toBe('Dirty Raiders');
    screen.destroy(); parent.remove();
  });

  it('locks participating formation/delete but allows name-only save and dirty discard', async () => {
    const client = api();
    const formation = ['11', null, null, null, null, null, null, null, null];
    const draft = SquadEditorDraft.edit({ id: '31', name: 'Raiders', isActive: true, formation });
    const { screen, store, returned } = harness(draft, client);
    store.hydrateBootstrap({ ...bootstrap(), active_squad: { id: '31', name: 'Raiders', is_active: true,
      formation, units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' }] },
      active_run: { id: '41', region_id: 'region.the_farm', squad_id: '31', status: 'active' } });
    screen.requestDelete(); await screen.confirmDelete(); await screen.activate();
    expect(client.deleteSquad).not.toHaveBeenCalled(); expect(client.activateSquad).not.toHaveBeenCalled();
    draft.setName('Renamed Raiders');
    screen.requestBack(); expect(returned).not.toHaveBeenCalled(); screen.cancelConfirmation();
    client.updateSquad.and.resolveTo({ squad: { id: '31', name: 'Renamed Raiders', isActive: true, formation }, activeSquadId: '31', playerRevision: 8 });
    spyOn(store, 'reconcileSquadMutation');
    await screen.save();
    expect(client.updateSquad).toHaveBeenCalledOnceWith('31', { name: 'Renamed Raiders', formation }, 'csrf-authoritative');
    expect(returned).toHaveBeenCalledTimes(1);
  });

  it('renders participating formation cells/roster as informational while leaving naming actions clickable', () => {
    const formation = ['11', null, null, null, null, null, null, null, null];
    const draft = SquadEditorDraft.edit({ id: '31', name: 'Raiders', isActive: true, formation });
    const store = new GameStore(); store.hydrateBootstrap({ ...bootstrap(), active_squad: { id: '31', name: 'Raiders', is_active: true,
      formation, units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' }] },
      active_run: { id: '41', region_id: 'region.the_farm', squad_id: '31', status: 'active' } });
    const viewport = new RuntimeViewport(); const harness = interactionHarness();
    const screen = new SquadEditorScreen(harness.scene, store, api(), viewport, draft, () => undefined);
    screen.reflow(viewport.snapshot);
    const layout = createSquadEditorLayout(viewport.snapshot);
    const formationControls = harness.graphics.filter((graphic) => graphic.hit && graphic.hit.x >= layout.formation.x
      && graphic.hit.x < layout.formation.right && graphic.hit.y >= layout.formation.y && graphic.hit.y < layout.formation.bottom);
    expect(formationControls.length).toBe(0);
    expect(harness.textValues.some((text) => text.includes('You can still rename this squad'))).toBeTrue();
    expect(harness.graphics.some((graphic) => graphic.input?.cursor === 'pointer')).toBeTrue();
    expect(draft.formation).toEqual(formation);
    for (const [width, height] of [[844, 390], [2560, 1080], [390, 844]]) {
      harness.graphics.length = 0;
      const responsive = calculateRuntimeViewport({ cssWidth: width, cssHeight: height, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: width === 390, noHover: width === 390 });
      screen.reflow(responsive);
      const responsiveLayout = createSquadEditorLayout(responsive);
      expect(harness.graphics.some((graphic) => graphic.hit && graphic.hit.x >= responsiveLayout.formation.x
        && graphic.hit.x < responsiveLayout.formation.right && graphic.hit.y >= responsiveLayout.formation.y
        && graphic.hit.y < responsiveLayout.formation.bottom)).toBeFalse();
    }
  });

  it('makes participating-squad Save actionable as soon as the editable name changes', () => {
    const formation = ['11', null, null, null, null, null, null, null, null];
    const draft = SquadEditorDraft.edit({ id: '31', name: 'Raiders', isActive: true, formation });
    const store = new GameStore(); store.hydrateBootstrap({ ...bootstrap(), active_squad: { id: '31', name: 'Raiders', is_active: true,
      formation, units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' }] },
      active_run: { id: '41', region_id: 'region.the_farm', squad_id: '31', status: 'active' } });
    const viewport = new RuntimeViewport(); const harness = interactionHarness();
    const parent = (harness.scene.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game!.canvas.parentElement!;
    document.body.appendChild(parent);
    const screen = new SquadEditorScreen(harness.scene, store, api(), viewport, draft, () => undefined);
    screen.create();
    const input = parent.querySelector<HTMLInputElement>('[data-squad-name-input="true"]')!;
    const layout = createSquadEditorLayout(viewport.snapshot);
    const saveX = layout.actions.x + Math.min(190, (layout.actions.width - 48) / 5) + 12;
    expect(harness.graphics.some((graphic) => graphic.hit?.x === saveX)).toBeFalse();
    input.value = 'Renamed Raiders'; input.dispatchEvent(new Event('input'));
    expect(harness.graphics.some((graphic) => graphic.hit?.x === saveX && graphic.input?.cursor === 'pointer')).toBeTrue();
    expect(input.disabled).toBeFalse();
    screen.destroy(); parent.remove();
  });

  it('keeps a different saved squad formation actionable during an active run', () => {
    const draft = SquadEditorDraft.edit({ id: '32', name: 'Brawlers', isActive: false, formation: Array(9).fill(null) });
    const store = new GameStore(); store.hydrateBootstrap({ ...bootstrap(), active_squad: { id: '31', name: 'Raiders', is_active: true,
      formation: ['11', null, null, null, null, null, null, null, null],
      units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' }] },
      active_run: { id: '41', region_id: 'region.the_farm', squad_id: '31', status: 'active' } });
    const viewport = new RuntimeViewport(); const harness = interactionHarness();
    const screen = new SquadEditorScreen(harness.scene, store, api(), viewport, draft, () => undefined);
    screen.reflow(viewport.snapshot);
    const layout = createSquadEditorLayout(viewport.snapshot);
    const cell = harness.graphics.find((graphic) => graphic.hit && graphic.hit.x >= layout.formation.x
      && graphic.hit.x < layout.formation.right && graphic.hit.y >= layout.formation.y && graphic.hit.y < layout.formation.bottom);
    expect(cell?.input?.cursor).toBe('pointer');
    draft.selectUnit('12'); cell?.pointerUp?.();
    expect(draft.formation[0]).toBe('12');
  });

  it('blocks known different-squad activation but retains normal formation editing and 409 fallback text', async () => {
    const client = api();
    const draft = SquadEditorDraft.edit({ id: '32', name: 'Brawlers', isActive: false, formation: Array(9).fill(null) });
    const { screen, store } = harness(draft, client);
    store.hydrateBootstrap({ ...bootstrap(), active_squad: { id: '31', name: 'Raiders', is_active: true,
      formation: ['11', null, null, null, null, null, null, null, null, null],
      units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' }] },
      active_run: { id: '41', region_id: 'region.the_farm', squad_id: '31', status: 'active' } });
    await screen.activate(); expect(client.activateSquad).not.toHaveBeenCalled();
    draft.selectUnit('12'); draft.placeSelected(4); expect(draft.formation[4]).toBe('12');
    client.updateSquad.and.rejectWith(new RuntimeApiError('http', 409, 'active_run_configuration_locked'));
    await screen.save();
    expect((screen as unknown as { message: string }).message).toContain('formation is locked');
    expect(draft.formation[4]).toBe('12');
  });

  it('gates the same native input in touch portrait and restores landscape editing with the full draft intact', () => {
    const draft = SquadEditorDraft.edit({ id: '31', name: 'Raiders', isActive: false, formation: Array(9).fill(null) });
    draft.setName('Rotation Raiders'); draft.selectUnit('11'); draft.placeSelected(8);
    const { screen, parent, input } = domHarness(draft, api());
    const portrait = calculateRuntimeViewport({ cssWidth: 390, cssHeight: 844, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: true, noHover: true });
    const landscape = calculateRuntimeViewport({ cssWidth: 844, cssHeight: 390, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: true, noHover: true });

    screen.reflow(portrait);
    expect(portrait.portraitGateActive).toBeTrue();
    expect(input.disabled).toBeTrue();
    input.value = 'Portrait mutation'; input.dispatchEvent(new Event('input'));
    expect(draft.name).toBe('Rotation Raiders');
    expect(draft.formation[8]).toBe('11');
    expect(draft.selectedUnitId).toBe('11');

    screen.reflow(landscape);
    expect(parent.querySelector('[data-squad-name-input="true"]')).toBe(input);
    expect(input.disabled).toBeFalse();
    expect(input.readOnly).toBeFalse();
    expect(input.value).toBe('Rotation Raiders');
    input.value = 'Landscape restored'; input.dispatchEvent(new Event('input'));
    expect(draft.name).toBe('Landscape restored');
    expect(draft.formation[8]).toBe('11');
    expect(draft.selectedUnitId).toBe('11');
    expect(draft.dirty).toBeTrue();
    screen.destroy(); parent.remove();
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
