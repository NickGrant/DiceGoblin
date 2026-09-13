import Phaser from 'phaser';
import { ClientContentRegistry } from '../runtime/client-content-registry';
import { GameBootstrapData, GameStore } from '../runtime/game-store';
import { RuntimeApiClient } from '../runtime/runtime-api-client';
import { RuntimeViewport, calculateRuntimeViewport } from '../runtime/runtime-viewport';
import { WarbandScreen, createWarbandLayout } from './warband-screen';

describe('WarbandScreen', () => {
  function snapshot(width: number, height: number) {
    return calculateRuntimeViewport({ cssWidth: width, cssHeight: height, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 }, coarsePointer: true, noHover: true });
  }

  function content(): ClientContentRegistry {
    return new ClientContentRegistry({ revision: 'a'.repeat(64), content: { gameplay: { run_energy_cost: 10 },
      regions: {}, kin: { 'kin.goblin': { id: 'kin.goblin', display_name: 'Cave Goblin', description: 'Scrappy.', art_key: 'goblin', trait_summary: 'Quick.', stat_modifiers: { hp: 0, attack: 0, defense: 0, precision: 0, resolve: 0 } } },
      unit_types: { 'unit_type.bruiser': { id: 'unit_type.bruiser', display_name: 'Bruiser', description: 'Strong.', art_key: 'bruiser', role: 'frontline', tier: 1, base_stats: { hp: 10, attack: 4, defense: 2, precision: 1, resolve: 1 }, growth_per_level: { hp: 1, attack: 1, defense: 1, precision: 0, resolve: 0 }, ability_ids: ['ability.bash'] } },
      abilities: { 'ability.bash': { id: 'ability.bash', kind: 'active', display_name: 'Bash', description: 'Bonk.', icon_key: 'bash', dice_slot_count: 1 } },
      dice_materials: { 'dice_material.bone': { id: 'dice_material.bone', display_name: 'Bone', description: 'Bone.', art_key: 'bone', allowed_sizes: [6] } }, dice_aspects: {},
      dice_profiles: { 'dice_profile.bone': { id: 'dice_profile.bone', display_name: 'Knucklebone', material_id: 'dice_material.bone', rarity: 'rare', aspect_ids: [], allowed_sizes: [6] } },
      run_node_types: {},
    } });
  }

  function bootstrap(): GameBootstrapData {
    return { account: { id: '1', display_name: 'Goblin', role: 'user' }, player: { teeth: 1, raw_chaos: 2, player_revision: 9, energy: { current: 10, normal_max: 50, regeneration_per_hour: 12, regeneration_interval_seconds: 300, last_regeneration_at: '2026-01-01T00:00:00Z', next_regeneration_at: null, fully_regenerated_at: null } }, session: { authenticated: true, csrf_token: 'csrf' }, server_time: '2026-01-01T00:00:00Z', content_revision: 'a'.repeat(64), progression: { unlock_ids: [] }, active_squad: { id: '31', name: 'Raiders', is_active: true, formation: ['11', null, null, null, null, null, null, null, null], units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 2, xp: 4, lifecycle_status: 'active' }] }, active_run: null };
  }

  function api(): jasmine.SpyObj<RuntimeApiClient> {
    const api = jasmine.createSpyObj<RuntimeApiClient>('api', ['getBootstrap', 'getUnits', 'getUnitDetail', 'getDice', 'getSquads']);
    api.getUnits.and.resolveTo({ ok: true, data: { units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 2, xp: 4, lifecycle_status: 'active' }] } });
    api.getDice.and.resolveTo({ ok: true, data: { dice: [{ id: '21', size: 6, profile_id: 'dice_profile.bone', lifecycle_status: 'active', bindings: [{ unit_id: '11', ability_id: 'ability.bash', slot_index: 0 }] }] } });
    api.getSquads.and.resolveTo({ ok: true, data: { squads: [{ id: '31', name: 'Raiders', is_active: true, formation: ['11', null, null, null, null, null, null, null, null] }] } });
    return api;
  }

  function sceneHarness(): { scene: Phaser.Scene; textValues: string[] } {
    const textValues: string[] = [];
    const chain = (): Record<string, jasmine.Spy> => {
      const value: Record<string, jasmine.Spy> = {};
      for (const method of ['setScale', 'destroy', 'fillGradientStyle', 'fillRect', 'fillStyle', 'fillCircle', 'fillRoundedRect', 'lineStyle', 'strokeRoundedRect', 'setInteractive', 'on', 'setOrigin']) {
        value[method] = jasmine.createSpy(method).and.returnValue(value);
      }
      value['add'] = jasmine.createSpy('add').and.returnValue(value);
      return value;
    };
    const parent = document.createElement('div');
    const canvas = document.createElement('canvas');
    parent.appendChild(canvas);
    return {
      textValues,
      scene: {
        add: {
          container: () => chain(),
          graphics: () => chain(),
          text: (_x: number, _y: number, value: string) => { textValues.push(value); return chain(); },
        },
        sys: { game: { canvas } },
      } as unknown as Phaser.Scene,
    };
  }

  it('keeps all required regions within Compact, Standard, and Wide safe bounds', () => {
    for (const view of [snapshot(844, 390), snapshot(1600, 900), snapshot(2560, 1080)]) {
      const layout = createWarbandLayout(view);
      for (const region of [layout.header, layout.backButton, ...layout.summaryCards, ...layout.tabs, layout.content]) {
        expect(region.x).toBeGreaterThanOrEqual(view.safeBounds.x);
        expect(region.y).toBeGreaterThanOrEqual(view.safeBounds.y);
        expect(region.right).toBeLessThanOrEqual(view.safeBounds.right);
        expect(region.bottom).toBeLessThanOrEqual(view.safeBounds.bottom);
      }
    }
  });

  it('is a GameScene screen, renders authored names and the nine-position active formation, and never calls mutations', async () => {
    const store = new GameStore();
    store.hydrateBootstrap(bootstrap());
    const client = api();
    const registry = content();
    await store.loadWarbandDomains(client, registry);
    const harness = sceneHarness();
    const viewport = new RuntimeViewport();
    const screen = new WarbandScreen(harness.scene, store, client, registry, viewport, () => undefined, () => undefined, 'squads');

    screen.create();

    expect(screen instanceof Phaser.Scene).toBeFalse();
    expect(screen.key).toBe('warband');
    expect(harness.textValues).toContain('WARBAND');
    expect(harness.textValues).toContain('Raiders');
    expect(harness.textValues).toContain('Grub');
    expect(harness.textValues.filter((value) => value === 'Open').length).toBe(8);
    expect(client.getUnits).toHaveBeenCalledTimes(1);
    expect(client.getDice).toHaveBeenCalledTimes(1);
    expect(client.getSquads).toHaveBeenCalledTimes(1);
  });

  it('renders a legitimate fresh empty account differently from loading and errors', async () => {
    const store = new GameStore();
    const empty = bootstrap();
    store.hydrateBootstrap({ ...empty, active_squad: null });
    const client = api();
    client.getUnits.and.resolveTo({ ok: true, data: { units: [] } });
    client.getDice.and.resolveTo({ ok: true, data: { dice: [] } });
    client.getSquads.and.resolveTo({ ok: true, data: { squads: [] } });
    await store.loadWarbandDomains(client, content());
    const harness = sceneHarness();
    const screen = new WarbandScreen(harness.scene, store, client, content(), new RuntimeViewport(), () => undefined, () => undefined, 'units');
    screen.create();
    expect(harness.textValues).toContain('Nothing here yet');
    expect(harness.textValues).toContain('No goblins have joined your warband yet.');
    expect(harness.textValues).not.toContain('Loading warband records…');
  });

  it('forwards New, Edit, Activate, and Delete intent without issuing mutations itself', async () => {
    const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    const client = api(); const registry = content();
    await store.loadWarbandDomains(client, registry);
    const openEditor = jasmine.createSpy('openEditor');
    const screen = new WarbandScreen(sceneHarness().scene, store, client, registry, new RuntimeViewport(), () => undefined, openEditor, 'squads');
    screen.create();

    screen.editSelectedSquad();
    screen.activateSelectedSquad();
    screen.deleteSelectedSquad();
    screen.createSquad();

    const squad = store.warband.squads.data?.[0];
    expect(openEditor.calls.argsFor(0)).toEqual([squad]);
    expect(openEditor.calls.argsFor(1)).toEqual([squad, 'activate']);
    expect(openEditor.calls.argsFor(2)).toEqual([squad, 'delete']);
    expect(openEditor.calls.argsFor(3)).toEqual([null]);
    expect(client.getUnits).toHaveBeenCalledTimes(1);
    expect(client.getDice).toHaveBeenCalledTimes(1);
    expect(client.getSquads).toHaveBeenCalledTimes(1);
  });

  it('forwards a roster unit to configuration without eagerly loading full detail', async () => {
    const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    const client = api(); const registry = content();
    await store.loadWarbandDomains(client, registry);
    const openUnit = jasmine.createSpy('openUnit');
    const screen = new WarbandScreen(sceneHarness().scene, store, client, registry, new RuntimeViewport(), () => undefined, () => undefined, 'units', openUnit);
    screen.create();
    expect(client.getUnitDetail).not.toHaveBeenCalled();
    screen.openUnit('11');
    expect(openUnit).toHaveBeenCalledOnceWith('11');
    expect(client.getUnitDetail).not.toHaveBeenCalled();
  });
});
