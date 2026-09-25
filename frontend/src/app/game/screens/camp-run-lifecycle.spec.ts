import Phaser from 'phaser';
import { ClientContentRegistry } from '../runtime/client-content-registry';
import { GameBootstrapData, GameStore } from '../runtime/game-store';
import { RuntimeApiClient, RuntimeApiError } from '../runtime/runtime-api-client';
import { RuntimeViewport } from '../runtime/runtime-viewport';
import { CampScreen, createCampViewModel } from './camp-screen';

describe('Camp run lifecycle', () => {
  function content(): ClientContentRegistry {
    return new ClientContentRegistry({ revision: 'a'.repeat(64), content: { gameplay: { run_energy_cost: 10 },
      regions: {
        'region.the_farm': { id: 'region.the_farm', display_name: 'The Farm', art_key: 'farm' },
        'region.mountains': { id: 'region.mountains', display_name: 'Mountains', art_key: 'mountains' },
      },
      kin: {}, unit_types: {}, abilities: {}, dice_materials: {}, dice_aspects: {}, dice_profiles: {}, run_node_types: {} } });
  }
  function bootstrap(active = false, mountains = false): GameBootstrapData { return {
    account: { id: '1', display_name: 'Goblin', role: 'user' }, player: { teeth: 0, raw_chaos: 0, player_revision: 7,
      energy: { current: 50, normal_max: 50, regeneration_per_hour: 12, regeneration_interval_seconds: 300,
        last_regeneration_at: '2026-09-13T12:00:00Z', next_regeneration_at: null, fully_regenerated_at: null } },
    session: { authenticated: true, csrf_token: 'csrf' }, server_time: '2026-09-13T12:00:00Z', content_revision: 'a'.repeat(64),
    progression: { unlock_ids: mountains ? ['unlock.region.mountains'] : [],
      available_region_ids: mountains ? ['region.the_farm', 'region.mountains'] : ['region.the_farm'] },
    active_squad: { id: '31', name: 'Raiders', is_active: true,
      formation: ['11', null, null, null, null, null, null, null, null], units: [{ id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' }] },
    active_run: active ? { id: '41', region_id: mountains ? 'region.mountains' : 'region.the_farm', squad_id: '31', status: 'active' } : null,
  }; }
  function harness(active = false, mountains = false) {
    const store = new GameStore(); store.hydrateBootstrap(bootstrap(active, mountains));
    const api = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['startRun']);
    const enterRun = jasmine.createSpy('enterRun');
    let keyNumber = 0;
    const createKey = jasmine.createSpy('createIdempotencyKey').and.callFake(
      () => `run:start:${++keyNumber === 1 ? 'one-attempt' : `attempt-${keyNumber}`}`,
    );
    const screen = new CampScreen({} as Phaser.Scene, store, new RuntimeViewport(), () => undefined, enterRun, api, content(), createKey);
    spyOn(screen, 'reflow').and.stub();
    screen.create();
    return { store, api, enterRun, screen, createKey };
  }
  const success = { run: { id: '41', region_id: 'region.the_farm', squad_id: '31', status: 'active' as const },
    energy: { ...bootstrap().player.energy, current: 40 }, playerRevision: 8 };
  const mountainsSuccess = { ...success, run: { ...success.run, region_id: 'region.mountains' } };

  it('presents canonical projected cost alongside authoritative Energy', () => {
    const store = new GameStore(); store.hydrateBootstrap(bootstrap());
    expect(createCampViewModel(store, content())).toEqual(jasmine.objectContaining({ runEnergyCost: 10, energyCurrent: 50, hasActiveRun: false }));
  });

  it('uses only authoritative available regions and starts the selected Mountains identity', async () => {
    const { api, screen, enterRun } = harness(false, true);
    expect(screen.viewModel?.availableRegions.map((region) => region.id)).toEqual(['region.the_farm', 'region.mountains']);
    expect(screen.selectedRunRegionId).toBe('region.the_farm');

    screen.selectRegion('region.mountains');
    api.startRun.and.resolveTo(mountainsSuccess);
    await screen.startRun();

    expect(api.startRun).toHaveBeenCalledOnceWith('region.mountains', 'csrf', 'run:start:one-attempt', jasmine.anything());
    expect(enterRun).toHaveBeenCalledTimes(1);
  });

  it('does not infer Mountains availability from owned unlock IDs', () => {
    const data = bootstrap();
    const store = new GameStore(); store.hydrateBootstrap({ ...data,
      progression: { unlock_ids: ['unlock.region.mountains'], available_region_ids: ['region.the_farm'] } });
    const view = createCampViewModel(store, content());
    expect(view.availableRegions.map((region) => region.id)).toEqual(['region.the_farm']);
  });

  it('retains one ambiguous Mountains key and region while preventing selection changes', async () => {
    const { api, screen, createKey } = harness(false, true);
    screen.selectRegion('region.mountains');
    api.startRun.and.returnValues(Promise.reject(new RuntimeApiError('network')), Promise.resolve(mountainsSuccess));

    await screen.startRun();
    screen.selectRegion('region.the_farm');
    expect(screen.selectedRunRegionId).toBe('region.mountains');
    await screen.startRun();

    expect(api.startRun.calls.allArgs().map((args) => [args[0], args[2]])).toEqual([
      ['region.mountains', 'run:start:one-attempt'], ['region.mountains', 'run:start:one-attempt'],
    ]);
    expect(createKey).toHaveBeenCalledTimes(1);
  });

  it('clears a definitive rejection so a different region receives a new key', async () => {
    const { api, screen, createKey } = harness(false, true);
    screen.selectRegion('region.mountains');
    api.startRun.and.rejectWith(new RuntimeApiError('http', 403, 'run_region_locked'));
    await screen.startRun();
    screen.selectRegion('region.the_farm');
    api.startRun.and.resolveTo(success);
    await screen.startRun();

    expect(api.startRun.calls.allArgs().map((args) => [args[0], args[2]])).toEqual([
      ['region.mountains', 'run:start:one-attempt'], ['region.the_farm', 'run:start:attempt-2'],
    ]);
    expect(createKey).toHaveBeenCalledTimes(2);
  });

  it('requires recovery for a successful response from the wrong region without posting again', async () => {
    const { api, screen, enterRun } = harness(false, true);
    screen.selectRegion('region.mountains');
    api.startRun.and.resolveTo(success);

    await screen.startRun(); await screen.startRun();

    expect(screen.runActionState).toBe('recovery-required');
    expect(api.startRun).toHaveBeenCalledTimes(1);
    expect(enterRun).not.toHaveBeenCalled();
  });

  it('reuses one key after ambiguous transport failure and commits only after success', async () => {
    const { store, api, enterRun, screen } = harness();
    api.startRun.and.rejectWith(new RuntimeApiError('network'));
    await screen.startRun();
    expect(store.bootstrap?.active_run).toBeNull(); expect(store.bootstrap?.player.energy.current).toBe(50);
    expect(screen.runActionState).toBe('retryable');
    api.startRun.and.resolveTo(success); await screen.startRun();
    expect(api.startRun.calls.allArgs().map((args) => args[2])).toEqual(['run:start:one-attempt', 'run:start:one-attempt']);
    expect(store.bootstrap?.player.energy.current).toBe(40); expect(enterRun).toHaveBeenCalledTimes(1);
  });

  it('reuses one key after a malformed response', async () => {
    const { api, screen, createKey } = harness();
    api.startRun.and.rejectWith(new RuntimeApiError('malformed-response', 200));
    await screen.startRun(); await screen.startRun();
    expect(screen.runActionState).toBe('retryable');
    expect(api.startRun.calls.allArgs().map((args) => args[2])).toEqual([
      'run:start:one-attempt', 'run:start:one-attempt',
    ]);
    expect(createKey).toHaveBeenCalledTimes(1);
  });

  it('reuses one key after an HTTP 5xx response', async () => {
    const { api, screen, createKey } = harness();
    api.startRun.and.rejectWith(new RuntimeApiError('http', 503));
    await screen.startRun(); await screen.startRun();
    expect(screen.runActionState).toBe('retryable');
    expect(api.startRun.calls.allArgs().map((args) => args[2])).toEqual([
      'run:start:one-attempt', 'run:start:one-attempt',
    ]);
    expect(createKey).toHaveBeenCalledTimes(1);
  });

  it('never rotates the key through repeated ambiguous failures and reconciles once after success', async () => {
    const { store, api, enterRun, screen, createKey } = harness();
    const reconcile = spyOn(store, 'reconcileRunStart').and.callThrough();
    api.startRun.and.returnValues(
      Promise.reject(new RuntimeApiError('network')),
      Promise.reject(new RuntimeApiError('malformed-response', 200)),
      Promise.reject(new RuntimeApiError('http', 500)),
      Promise.resolve(success),
    );

    await screen.startRun(); await screen.startRun(); await screen.startRun();
    expect(store.bootstrap?.active_run).toBeNull();
    expect(store.bootstrap?.player.energy.current).toBe(50);
    await screen.startRun();

    expect(api.startRun.calls.allArgs().map((args) => args[2])).toEqual([
      'run:start:one-attempt', 'run:start:one-attempt', 'run:start:one-attempt', 'run:start:one-attempt',
    ]);
    expect(createKey).toHaveBeenCalledTimes(1);
    expect(reconcile).toHaveBeenCalledTimes(1);
    expect(enterRun).toHaveBeenCalledTimes(1);
  });

  it('treats a deliberate 4xx rejection as definitive and permits a later new attempt', async () => {
    const { api, screen, createKey } = harness();
    api.startRun.and.rejectWith(new RuntimeApiError('http', 422, 'insufficient_energy'));
    await screen.startRun();
    expect(screen.runActionState).toBe('rejected');
    await screen.startRun();
    expect(api.startRun.calls.allArgs().map((args) => args[2])).toEqual([
      'run:start:one-attempt', 'run:start:attempt-2',
    ]);
    expect(createKey).toHaveBeenCalledTimes(2);
  });

  it('requires reload after valid server success fails local reconciliation and blocks another POST', async () => {
    const { store, api, enterRun, screen, createKey } = harness();
    api.startRun.and.resolveTo({ ...success, run: { ...success.run, squad_id: '99' } });

    await screen.startRun();
    expect(screen.runActionState).toBe('recovery-required');
    expect(store.bootstrap?.active_run).toBeNull();
    expect(store.bootstrap?.player.energy.current).toBe(50);
    expect(enterRun).not.toHaveBeenCalled();
    await screen.startRun();

    expect(api.startRun).toHaveBeenCalledTimes(1);
    expect(createKey).toHaveBeenCalledTimes(1);
  });

  it('prevents duplicate submission while a start is in flight', async () => {
    const { api, screen } = harness(); let resolve!: (value: typeof success) => void;
    api.startRun.and.returnValue(new Promise((done) => { resolve = done; }));
    const first = screen.startRun(); const second = screen.startRun();
    expect(api.startRun).toHaveBeenCalledTimes(1); resolve(success); await Promise.all([first, second]);
  });

  it('resumes an active run without issuing another start request', () => {
    const { api, enterRun, screen } = harness(true); screen.resumeRun();
    expect(enterRun).toHaveBeenCalledTimes(1); expect(api.startRun).not.toHaveBeenCalled();
  });

  it('resumes an active Mountains run without issuing a start request', () => {
    const { api, enterRun, screen } = harness(true, true);
    expect(screen.viewModel?.activeRunRegionName).toBe('Mountains');
    screen.resumeRun();
    expect(enterRun).toHaveBeenCalledTimes(1); expect(api.startRun).not.toHaveBeenCalled();
  });
});
