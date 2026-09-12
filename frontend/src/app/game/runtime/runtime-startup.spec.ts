import { ClientContentLoadError, ClientContentLoader } from './client-content-registry';
import { GameStore } from './game-store';
import { RuntimeApiClient, RuntimeApiError } from './runtime-api-client';
import { RuntimeStartup } from './runtime-startup';
import { GAME_SCENE_KEY, nextSceneForStartup, startupMessage } from '../scenes/runtime-scenes';

describe('RuntimeStartup', () => {
  const revision = 'a'.repeat(64);

  function projection(contentRevision = revision): unknown {
    return {
      revision: contentRevision,
      content: {
        regions: {
          'region.the_farm': {
            id: 'region.the_farm',
            display_name: 'The Farm',
            art_key: 'farm',
          },
        },
        kin: {},
        unit_types: {},
        abilities: {},
        dice_materials: {},
        dice_aspects: {},
        dice_profiles: {},
      },
    };
  }

  function bootstrap(contentRevision = revision, energyCurrent = 41): unknown {
    return {
      ok: true,
      data: {
        account: { id: '42', display_name: 'Test Goblin', role: 'user' },
        player: {
          teeth: 123,
          raw_chaos: 7,
          energy: {
            current: energyCurrent,
            normal_max: 50,
            regeneration_per_hour: 12,
            regeneration_interval_seconds: 300,
            last_regeneration_at: '2026-09-11T00:00:00Z',
            next_regeneration_at: '2026-09-11T00:05:00Z',
            fully_regenerated_at: '2026-09-11T00:45:00Z',
          },
          player_revision: 29,
        },
        session: { authenticated: true, csrf_token: 'csrf-test' },
        server_time: '2026-09-11T00:01:00Z',
        content_revision: contentRevision,
        progression: { unlock_ids: [] },
        active_squad: null,
        active_run: null,
      },
    };
  }

  function activeSquadBootstrap(): unknown {
    const value = bootstrap() as { data: Record<string, unknown> };
    value.data['active_squad'] = {
      id: '17', name: 'Raiders', is_active: true,
      formation: ['31', null, null, null, '32', null, null, null, null],
      units: [
        { id: '31', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 3, xp: 120, lifecycle_status: 'active' },
        { id: '32', display_name: 'Moss', unit_type_id: 'unit_type.guardian', kin_id: 'kin.pig', level: 2, xp: 45, lifecycle_status: 'active' },
      ],
    };
    return value;
  }

  function harness(
    projectionResult: unknown = projection(),
    bootstrapResult: unknown = bootstrap(),
  ): {
    startup: RuntimeStartup;
    apiClient: jasmine.SpyObj<RuntimeApiClient>;
    contentLoader: jasmine.SpyObj<ClientContentLoader>;
    store: GameStore;
  } {
    const apiClient = jasmine.createSpyObj<RuntimeApiClient>('RuntimeApiClient', ['getBootstrap']);
    apiClient.getBootstrap.and.resolveTo(bootstrapResult);
    const contentLoader = jasmine.createSpyObj<ClientContentLoader>('ClientContentLoader', [
      'loadProjection',
    ]);
    contentLoader.loadProjection.and.resolveTo(projectionResult);
    const store = new GameStore();
    return {
      startup: new RuntimeStartup(apiClient, contentLoader, store),
      apiClient,
      contentLoader,
      store,
    };
  }

  it('hydrates the authoritative GameStore and activates content before becoming ready', async () => {
    const { startup, store } = harness();

    const state = await startup.start();

    expect(state).toEqual({ status: 'ready' });
    expect(startup.contentRegistry?.get('region.the_farm')?.display_name).toBe('The Farm');
    expect(store.bootstrap?.account.display_name).toBe('Test Goblin');
    expect(store.bootstrap?.player.teeth).toBe(123);
    expect(store.bootstrap?.player.raw_chaos).toBe(7);
    expect(store.bootstrap?.player.energy.current).toBe(41);
    expect(store.bootstrap?.session.csrf_token).toBe('csrf-test');
    expect(store.bootstrap?.server_time).toBe('2026-09-11T00:01:00Z');
    expect(store.bootstrap?.progression.unlock_ids).toEqual([]);
    expect(store.bootstrap?.active_squad).toBeNull();
    expect(store.bootstrap?.active_run).toBeNull();
    expect(store.playerRevision).toBe(29);
    expect(nextSceneForStartup(state)).toBe(GAME_SCENE_KEY);
  });

  it('accepts and preserves authoritative overcap Energy through compatible startup', async () => {
    const { startup, store, apiClient, contentLoader } = harness(
      projection(),
      bootstrap(revision, 57),
    );

    const firstState = await startup.start();
    const secondState = await startup.start();

    expect(firstState).toEqual({ status: 'ready' });
    expect(secondState).toBe(firstState);
    expect(store.bootstrap?.player.energy.current).toBe(57);
    expect(store.bootstrap?.player.energy.normal_max).toBe(50);
    expect(startup.contentRegistry?.revision).toBe(revision);
    expect(store.bootstrap?.content_revision).toBe(revision);
    expect(nextSceneForStartup(firstState)).toBe(GAME_SCENE_KEY);
    expect(contentLoader.loadProjection).toHaveBeenCalledTimes(1);
    expect(apiClient.getBootstrap).toHaveBeenCalledTimes(1);
  });

  it('accepts and retains a strict authoritative active squad', async () => {
    const { startup, store } = harness(projection(), activeSquadBootstrap());
    expect(await startup.start()).toEqual({ status: 'ready' });
    expect(store.bootstrap?.active_squad?.name).toBe('Raiders');
    expect(store.bootstrap?.active_squad?.formation).toEqual(['31', null, null, null, '32', null, null, null, null]);
    expect(store.bootstrap?.active_squad?.units.map((unit) => unit.id)).toEqual(['31', '32']);
  });

  it('rejects malformed active squad shapes without hydrating the store', async () => {
    const malformedPayloads = [
      (() => {
        const value = activeSquadBootstrap() as { data: { active_squad: { formation: unknown[] } } };
        value.data.active_squad.formation = ['31', '31', null, null, null, null, null, null, null];
        return value;
      })(),
      (() => {
        const value = activeSquadBootstrap() as { data: { active_squad: { formation: unknown[] } } };
        value.data.active_squad.formation.pop();
        return value;
      })(),
      (() => {
        const value = activeSquadBootstrap() as { data: { active_squad: { units: unknown[] } } };
        value.data.active_squad.units = [];
        return value;
      })(),
      (() => {
        const value = activeSquadBootstrap() as { data: { active_squad: Record<string, unknown> } };
        value.data.active_squad['unexpected'] = true;
        return value;
      })(),
    ];
    for (const malformed of malformedPayloads) {
      const { startup, store } = harness(projection(), malformed);
      expect(await startup.start()).toEqual({ status: 'failure', reason: 'bootstrap-malformed' });
      expect(store.bootstrap).toBeNull();
    }
  });

  it('still rejects negative current Energy as malformed bootstrap', async () => {
    const { startup, store } = harness(projection(), bootstrap(revision, -1));

    expect(await startup.start()).toEqual({ status: 'failure', reason: 'bootstrap-malformed' });
    expect(store.bootstrap).toBeNull();
    expect(startup.contentRegistry).toBeNull();
    expect(nextSceneForStartup(startup.state)).toBeNull();
  });

  it('fails safely when the client projection is malformed and never requests bootstrap', async () => {
    const { startup, apiClient } = harness({ revision, content: { regions: [] } });

    expect(await startup.start()).toEqual({
      status: 'failure',
      reason: 'client-content-malformed',
    });
    expect(apiClient.getBootstrap).not.toHaveBeenCalled();
    expect(nextSceneForStartup(startup.state)).toBeNull();
  });

  it('fails safely when bootstrap is malformed', async () => {
    const { startup, store } = harness(projection(), {
      ok: true,
      data: { content_revision: revision },
    });

    expect(await startup.start()).toEqual({ status: 'failure', reason: 'bootstrap-malformed' });
    expect(store.bootstrap).toBeNull();
    expect(startup.contentRegistry).toBeNull();
    expect(nextSceneForStartup(startup.state)).toBeNull();
  });

  it('blocks GameScene and exposes content-mismatch when revisions differ', async () => {
    const serverRevision = 'b'.repeat(64);
    const { startup, store } = harness(projection(), bootstrap(serverRevision));

    expect(await startup.start()).toEqual({
      status: 'content-mismatch',
      clientRevision: revision,
      serverRevision,
    });
    expect(store.bootstrap).toBeNull();
    expect(startup.contentRegistry).toBeNull();
    expect(nextSceneForStartup(startup.state)).toBeNull();
  });

  it('never selects GameScene for loading or any startup failure state', () => {
    expect(nextSceneForStartup({ status: 'loading' })).toBeNull();
    expect(nextSceneForStartup({ status: 'failure', reason: 'bootstrap-malformed' })).toBeNull();
    expect(
      nextSceneForStartup({
        status: 'content-mismatch',
        clientRevision: revision,
        serverRevision: 'b'.repeat(64),
      }),
    ).toBeNull();
  });

  it('provides safe Phaser-owned loading, mismatch, and failure messages', () => {
    expect(startupMessage({ status: 'loading' })).toBe('Loading game…');
    expect(
      startupMessage({
        status: 'content-mismatch',
        clientRevision: revision,
        serverRevision: 'b'.repeat(64),
      }),
    ).toContain('update is required');
    expect(startupMessage({ status: 'failure', reason: 'unauthorized' })).toContain(
      'session has expired',
    );
    expect(startupMessage({ status: 'failure', reason: 'bootstrap-request' })).not.toContain(
      'bootstrap',
    );
  });

  it('controls client-content request failures', async () => {
    const { startup, contentLoader } = harness();
    contentLoader.loadProjection.and.rejectWith(new ClientContentLoadError('request'));

    expect(await startup.start()).toEqual({ status: 'failure', reason: 'client-content-request' });
    expect(nextSceneForStartup(startup.state)).toBeNull();
  });

  it('controls bootstrap HTTP and unauthorized failures', async () => {
    const unauthorized = harness();
    unauthorized.apiClient.getBootstrap.and.rejectWith(new RuntimeApiError('unauthorized', 401));
    expect(await unauthorized.startup.start()).toEqual({
      status: 'failure',
      reason: 'unauthorized',
    });

    const unavailable = harness();
    unavailable.apiClient.getBootstrap.and.rejectWith(new RuntimeApiError('http', 503));
    expect(await unavailable.startup.start()).toEqual({
      status: 'failure',
      reason: 'bootstrap-request',
    });
  });

  it('controls unexpected startup failures and never enters GameScene', async () => {
    const unexpectedContent = harness();
    unexpectedContent.contentLoader.loadProjection.and.rejectWith(new Error('unexpected content failure'));
    expect(await unexpectedContent.startup.start()).toEqual({
      status: 'failure',
      reason: 'unexpected',
    });
    expect(nextSceneForStartup(unexpectedContent.startup.state)).toBeNull();

    const unexpectedBootstrap = harness();
    unexpectedBootstrap.apiClient.getBootstrap.and.rejectWith(new Error('unexpected bootstrap failure'));
    expect(await unexpectedBootstrap.startup.start()).toEqual({
      status: 'failure',
      reason: 'unexpected',
    });
    expect(nextSceneForStartup(unexpectedBootstrap.startup.state)).toBeNull();
  });

  it('performs at most one content and bootstrap request when startup is observed repeatedly', async () => {
    const { startup, apiClient, contentLoader } = harness();

    const [first, second, third] = await Promise.all([
      startup.start(),
      startup.start(),
      startup.start(),
    ]);

    expect(first).toBe(second);
    expect(second).toBe(third);
    expect(contentLoader.loadProjection).toHaveBeenCalledTimes(1);
    expect(apiClient.getBootstrap).toHaveBeenCalledTimes(1);
  });
});
