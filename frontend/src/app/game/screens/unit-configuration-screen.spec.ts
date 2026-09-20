import Phaser from 'phaser';
import { ClientContentRegistry } from '../runtime/client-content-registry';
import { GameBootstrapData, GameStore } from '../runtime/game-store';
import { RuntimeApiClient, RuntimeApiError } from '../runtime/runtime-api-client';
import { RuntimeViewport, calculateRuntimeViewport } from '../runtime/runtime-viewport';
import { UnitConfigurationScreen, createUnitConfigurationLayout } from './unit-configuration-screen';

describe('UnitConfigurationScreen', () => {
  function bootstrap(): GameBootstrapData {
    return {
      account: { id: '1', display_name: 'Goblin', role: 'user' },
      player: { teeth: 0, raw_chaos: 0, player_revision: 7, energy: { current: 5, normal_max: 50, regeneration_per_hour: 12, regeneration_interval_seconds: 300, last_regeneration_at: '2026-01-01T00:00:00Z', next_regeneration_at: null, fully_regenerated_at: null } },
      session: { authenticated: true, csrf_token: 'csrf-authoritative' }, server_time: '2026-01-01T00:00:00Z', content_revision: 'a'.repeat(64), progression: { unlock_ids: [] },
      active_squad: { id: '31', name: 'Raiders', is_active: true, formation: ['11', null, null, null, null, null, null, null, null], units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' }] }, active_run: null,
    };
  }

  function content(): ClientContentRegistry {
    const stat = { hp: 1, attack: 1, defense: 1, precision: 1, resolve: 1 };
    return new ClientContentRegistry({ revision: 'a'.repeat(64), content: { gameplay: { run_energy_cost: 10 },
      regions: {}, kin: { 'kin.goblin': { id: 'kin.goblin', display_name: 'Goblin', description: 'Goblin.', art_key: 'goblin', trait_summary: 'Quick.', stat_modifiers: { hp: 0, attack: 0, defense: 0, precision: 0, resolve: 0 } } },
      unit_types: { 'unit_type.bruiser': { id: 'unit_type.bruiser', display_name: 'Bruiser', description: 'Bruiser detail.', art_key: 'bruiser', role: 'frontline', tier: 1, base_stats: stat, growth_per_level: stat, ability_ids: ['ability.bash'] } },
      abilities: {
        'ability.bash': { id: 'ability.bash', kind: 'active', display_name: 'Bash', description: 'Bash.', icon_key: 'bash', dice_slot_count: 1 },
        'ability.smash': { id: 'ability.smash', kind: 'active', display_name: 'Smash', description: 'Smash.', icon_key: 'smash', dice_slot_count: 1 },
        'ability.thick': { id: 'ability.thick', kind: 'passive', display_name: 'Thick Hide', description: 'Thick.', icon_key: 'thick', dice_slot_count: 0 },
      },
      dice_materials: { 'dice_material.bone': { id: 'dice_material.bone', display_name: 'Bone', description: 'Bone.', art_key: 'bone', allowed_sizes: [6] } }, dice_aspects: {},
      dice_profiles: { 'dice_profile.bone': { id: 'dice_profile.bone', display_name: 'Bone Die', material_id: 'dice_material.bone', rarity: 'common', aspect_ids: [], allowed_sizes: [6] } },
      run_node_types: {},
    } });
  }

  function api(): jasmine.SpyObj<RuntimeApiClient> {
    const client = jasmine.createSpyObj<RuntimeApiClient>('api', ['getUnits', 'getDice', 'getSquads', 'getUnitDetail', 'renameUnit', 'replaceUnitLoadout']);
    client.getUnits.and.resolveTo({ ok: true, data: { units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' }] } });
    client.getDice.and.resolveTo({ ok: true, data: { dice: [
      { id: '21', size: 6, profile_id: 'dice_profile.bone', lifecycle_status: 'active', bindings: [{ unit_id: '11', ability_id: 'ability.bash', slot_index: 0 }] },
      { id: '22', size: 6, profile_id: 'dice_profile.bone', lifecycle_status: 'active', bindings: [] },
      { id: '23', size: 6, profile_id: 'dice_profile.bone', lifecycle_status: 'active', bindings: [{ unit_id: '99', ability_id: 'ability.smash', slot_index: 0 }] },
    ] } });
    client.getSquads.and.resolveTo({ ok: true, data: { squads: [{ id: '31', name: 'Raiders', is_active: true, formation: ['11', null, null, null, null, null, null, null, null] }] } });
    client.getUnitDetail.and.resolveTo({ ok: true, data: { unit: {
      id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active', promotion_history: [], owned_ability_ids: ['ability.bash', 'ability.smash', 'ability.thick'], ability_loadout: [{ ability_id: 'ability.bash', equip_order: 0 }], dice_bindings: [{ ability_id: 'ability.bash', slot_index: 0, dice_instance_id: '21' }],
    } } });
    return client;
  }

  function sceneHarness(): Phaser.Scene {
    const graphics: Array<{ hit: Phaser.Geom.Rectangle | null; input: { cursor: string } | null }> = [];
    const chain = (): Record<string, jasmine.Spy> => {
      const value: Record<string, jasmine.Spy> = {};
      for (const method of ['setScale', 'destroy', 'fillGradientStyle', 'fillRect', 'fillStyle', 'fillRoundedRect', 'lineStyle', 'strokeRoundedRect', 'on', 'setOrigin']) value[method] = jasmine.createSpy(method).and.returnValue(value);
      value['setInteractive'] = jasmine.createSpy('setInteractive').and.callFake((hit: Phaser.Geom.Rectangle) => {
        (value as unknown as { hit: Phaser.Geom.Rectangle; input: { cursor: string } }).hit = hit;
        (value as unknown as { input: { cursor: string } }).input = { cursor: '' };
        return value;
      });
      value['add'] = jasmine.createSpy('add').and.returnValue(value);
      return value;
    };
    const parent = document.createElement('div'); const canvas = document.createElement('canvas'); parent.appendChild(canvas);
    return { add: { container: () => chain(), graphics: () => {
      const graphic = chain(); graphics.push(graphic as unknown as typeof graphics[number]); return graphic;
    }, text: () => chain() }, sys: { game: { canvas } }, graphics } as unknown as Phaser.Scene;
  }

  async function readyHarness() {
    const registry = content(); const client = api(); const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    await store.loadWarbandDomains(client, registry); await store.loadUnitDetail('11', client, registry);
    const scene = sceneHarness(); const parent = (scene.sys as Phaser.Scenes.Systems & { game?: Phaser.Game }).game!.canvas.parentElement!;
    document.body.appendChild(parent);
    const viewport = new RuntimeViewport(); const returned = jasmine.createSpy('returned');
    const screen = new UnitConfigurationScreen(scene, store, client, registry, viewport, '11', returned);
    screen.create();
    return { screen, store, client, registry, viewport, returned, parent, scene, input: parent.querySelector<HTMLInputElement>('[data-unit-name-input="true"]')! };
  }

  it('keeps every major region inside Compact, Standard, and Wide safe bounds', () => {
    for (const [width, height] of [[844, 390], [1600, 900], [2560, 1080]]) {
      const snapshot = calculateRuntimeViewport({ cssWidth: width, cssHeight: height, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: true, noHover: true });
      const layout = createUnitConfigurationLayout(snapshot);
      for (const region of [layout.header, layout.back, layout.name, layout.identity, layout.configuration, layout.actions]) {
        expect(region.x).toBeGreaterThanOrEqual(snapshot.safeBounds.x);
        expect(region.y).toBeGreaterThanOrEqual(snapshot.safeBounds.y);
        expect(region.right).toBeLessThanOrEqual(snapshot.safeBounds.right);
        expect(region.bottom).toBeLessThanOrEqual(snapshot.safeBounds.bottom);
      }
    }
  });

  it('preserves detail, rename/loadout drafts, slot selection, and dirty state across reflow', async () => {
    const { screen, parent } = await readyHarness();
    expect(parent.dataset['unitLoadoutLocked']).toBe('false');
    screen.draft!.setName('Responsive Grub'); screen.addAbility('ability.smash'); screen.selectSlot('ability.smash', 0); screen.assignDie('22');
    const compact = calculateRuntimeViewport({ cssWidth: 844, cssHeight: 390, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: true, noHover: true });
    const wide = calculateRuntimeViewport({ cssWidth: 2560, cssHeight: 1080, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: false, noHover: false });
    screen.reflow(compact); screen.reflow(wide);
    expect(screen.draft!.name).toBe('Responsive Grub');
    expect(screen.draft!.loadout.map((entry) => entry.ability.id)).toEqual(['ability.bash', 'ability.smash']);
    expect(screen.draft!.selectedSlot).toEqual({ abilityId: 'ability.smash', slotIndex: 0 });
    expect(screen.draft!.dirty).toBeTrue();
    screen.destroy(); parent.remove();
  });

  it('requires explicit discard for dirty navigation and blocks/hides the native input during confirmation', async () => {
    const { screen, returned, input, parent } = await readyHarness();
    input.value = 'Dirty Grub'; input.dispatchEvent(new Event('input')); screen.requestBack();
    expect(returned).not.toHaveBeenCalled();
    expect(input.disabled).toBeTrue(); expect(input.readOnly).toBeTrue(); expect(input.style.display).toBe('none');
    input.value = 'Mutation under overlay'; input.dispatchEvent(new Event('input'));
    expect(screen.draft!.name).toBe('Dirty Grub');
    screen.cancelConfirmation(); expect(input.disabled).toBeFalse(); expect(input.value).toBe('Dirty Grub');
    screen.requestBack(); screen.confirmDiscard(); expect(returned).toHaveBeenCalledTimes(1);
    screen.destroy(); parent.remove();
  });

  it('gates focused rename input during a command and restores the preserved local draft after failure', async () => {
    const { screen, client, store, input, parent } = await readyHarness();
    let reject!: (reason: unknown) => void;
    client.renameUnit.and.returnValue(new Promise((_resolve, rejectPromise) => { reject = rejectPromise; }));
    input.value = 'Submitted Grub'; input.dispatchEvent(new Event('input')); input.focus();
    const committed = store.unitDetail('11').data;
    const pending = screen.saveRename();
    expect(input.disabled).toBeTrue(); expect(input.readOnly).toBeTrue(); expect(document.activeElement).not.toBe(input);
    input.value = 'Late mutation'; input.dispatchEvent(new Event('input'));
    expect(screen.draft!.name).toBe('Submitted Grub'); expect(store.unitDetail('11').data).toBe(committed);
    reject(new RuntimeApiError('network')); await pending;
    expect(input.disabled).toBeFalse(); expect(input.readOnly).toBeFalse(); expect(screen.draft!.name).toBe('Submitted Grub');
    screen.destroy(); parent.remove();
  });

  it('preserves committed state and the complete local draft when loadout save fails', async () => {
    const { screen, client, store, parent } = await readyHarness();
    screen.addAbility('ability.smash'); screen.selectSlot('ability.smash', 0); screen.assignDie('22');
    let reject!: (reason: unknown) => void;
    client.replaceUnitLoadout.and.returnValue(new Promise((_resolve, rejectPromise) => { reject = rejectPromise; }));
    const committed = store.unitDetail('11').data;
    const pending = screen.saveLoadout(); void screen.saveLoadout();
    expect(client.replaceUnitLoadout).toHaveBeenCalledTimes(1);
    expect(store.unitDetail('11').data).toBe(committed);
    reject(new RuntimeApiError('http', 422, 'invalid_unit_configuration')); await pending;
    expect(screen.draft!.loadout.map((entry) => entry.diceInstanceIds)).toEqual([['21'], ['22']]);
    expect(store.unitDetail('11').data).toBe(committed);
    screen.destroy(); parent.remove();
  });

  it('blocks the same native input in touch portrait and restores landscape editing without replacing draft/input', async () => {
    const { screen, input, parent } = await readyHarness();
    screen.draft!.setName('Rotation Grub'); screen.addAbility('ability.smash'); screen.selectSlot('ability.smash', 0); screen.assignDie('22');
    const portrait = calculateRuntimeViewport({ cssWidth: 390, cssHeight: 844, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: true, noHover: true });
    const landscape = calculateRuntimeViewport({ cssWidth: 844, cssHeight: 390, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: true, noHover: true });
    screen.reflow(portrait); expect(input.disabled).toBeTrue();
    input.value = 'Portrait mutation'; input.dispatchEvent(new Event('input')); expect(screen.draft!.name).toBe('Rotation Grub');
    screen.reflow(landscape);
    expect(parent.querySelector('[data-unit-name-input="true"]')).toBe(input); expect(input.disabled).toBeFalse(); expect(input.value).toBe('Rotation Grub');
    expect(screen.draft!.loadout[1].diceInstanceIds).toEqual(['22']); expect(screen.draft!.selectedSlot).toEqual({ abilityId: 'ability.smash', slotIndex: 0 });
    screen.destroy(); parent.remove();
  });

  it('keeps participating loadout/dice committed and non-mutable while allowing rename', async () => {
    const { screen, store, client, input, parent, scene } = await readyHarness();
    store.hydrateBootstrap({ ...bootstrap(), active_run: { id: '41', region_id: 'region.the_farm', squad_id: '31', status: 'active' } });
    screen.reflow(new RuntimeViewport().snapshot);
    expect(parent.dataset['unitLoadoutLocked']).toBe('true');
    const original = screen.draft!.loadout.map((entry) => [entry.ability.id, [...entry.diceInstanceIds]]);
    screen.addAbility('ability.smash'); screen.removeAbility('ability.bash'); screen.moveAbility('ability.bash', 1);
    screen.selectSlot('ability.bash', 0); screen.assignDie('22');
    const portrait = calculateRuntimeViewport({ cssWidth: 390, cssHeight: 844, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: true, noHover: true });
    const compact = calculateRuntimeViewport({ cssWidth: 844, cssHeight: 390, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: true, noHover: true });
    screen.reflow(portrait); screen.addAbility('ability.smash'); screen.reflow(compact); screen.addAbility('ability.smash');
    expect(screen.draft!.loadout.map((entry) => [entry.ability.id, [...entry.diceInstanceIds]])).toEqual(original);
    expect(screen.draft!.selectedSlot).toBeNull();
    await screen.saveLoadout(); expect(client.replaceUnitLoadout).not.toHaveBeenCalled();
    input.value = 'Farm Grub'; input.dispatchEvent(new Event('input'));
    const graphics = (scene as unknown as { graphics: Array<{ hit: Phaser.Geom.Rectangle | null; input: { cursor: string } | null }> }).graphics;
    const saveNameX = createUnitConfigurationLayout(new RuntimeViewport().snapshot).actions.x + 202;
    expect(graphics.some((graphic) => graphic.hit?.x === saveNameX && graphic.input?.cursor === 'pointer')).toBeTrue();
    const current = store.unitDetail('11').data!;
    client.renameUnit.and.resolveTo({ unit: { ...current, displayName: 'Farm Grub' }, playerRevision: 8 });
    await screen.saveRename();
    expect(client.renameUnit).toHaveBeenCalledTimes(1);
    expect(screen.draft!.name).toBe('Farm Grub');
    screen.destroy(); parent.remove();
  });

  it('shows understandable active-run 409 fallback and preserves a stale-tab loadout draft', async () => {
    const { screen, client, parent } = await readyHarness();
    screen.addAbility('ability.smash'); screen.selectSlot('ability.smash', 0); screen.assignDie('22');
    client.replaceUnitLoadout.and.rejectWith(new RuntimeApiError('http', 409, 'active_run_configuration_locked'));
    await screen.saveLoadout();
    expect((screen as unknown as { message: string }).message).toContain('loadout and dice are locked');
    expect(screen.draft!.loadout[1].diceInstanceIds).toEqual(['22']);
    screen.destroy(); parent.remove();
  });
});
