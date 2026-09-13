import { RuntimeApiClient, RuntimeApiError, RuntimeFetch } from './runtime-api-client';

describe('RuntimeApiClient', () => {
  const originalConfig = window.__DICE_GOBLIN_CONFIG__;

  afterEach(() => {
    window.__DICE_GOBLIN_CONFIG__ = originalConfig;
  });

  it('honors configured API base URL and includes browser session credentials', async () => {
    window.__DICE_GOBLIN_CONFIG__ = { apiBaseUrl: 'https://api.example.test/root/' };
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest').and.resolveTo(
      new Response(JSON.stringify({ ok: true, data: {} }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    );
    const client = new RuntimeApiClient(fetchRequest);

    await client.getBootstrap();

    expect(client.baseUrl).toBe('https://api.example.test/root');
    expect(fetchRequest).toHaveBeenCalledOnceWith(
      'https://api.example.test/root/api/v1/game/bootstrap',
      jasmine.objectContaining({ method: 'GET', credentials: 'include' }),
    );
  });

  it('reports unauthorized responses without exposing their body', async () => {
    const fetchRequest = jasmine
      .createSpy<RuntimeFetch>('fetchRequest')
      .and.resolveTo(new Response('sensitive backend detail', { status: 401 }));

    await expectAsync(new RuntimeApiClient(fetchRequest, '').getBootstrap()).toBeRejectedWith(
      jasmine.objectContaining<RuntimeApiError>({ kind: 'unauthorized', status: 401 }),
    );
  });

  it('calls each lazy Warband query directly with the browser session', async () => {
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest').and.callFake(async () =>
      new Response(JSON.stringify({ ok: true, data: {} }), { status: 200 }),
    );
    const client = new RuntimeApiClient(fetchRequest, '/root');

    await client.getUnits();
    await client.getDice();
    await client.getSquads();

    expect(fetchRequest.calls.allArgs().map(([url]) => url)).toEqual([
      '/root/api/v1/units', '/root/api/v1/dice', '/root/api/v1/squads',
    ]);
    for (const [, init] of fetchRequest.calls.allArgs()) {
      expect(init).toEqual(jasmine.objectContaining({ method: 'GET', credentials: 'include', headers: { Accept: 'application/json' } }));
    }
  });

  it('preserves network, HTTP, and malformed-response distinctions for lazy queries', async () => {
    const network = jasmine.createSpy<RuntimeFetch>('network').and.rejectWith(new Error('offline'));
    await expectAsync(new RuntimeApiClient(network, '').getUnits()).toBeRejectedWith(jasmine.objectContaining({ kind: 'network' }));
    const http = jasmine.createSpy<RuntimeFetch>('http').and.resolveTo(new Response('', { status: 503 }));
    await expectAsync(new RuntimeApiClient(http, '').getDice()).toBeRejectedWith(jasmine.objectContaining({ kind: 'http', status: 503 }));
    const malformed = jasmine.createSpy<RuntimeFetch>('malformed').and.resolveTo(new Response('not json', { status: 200 }));
    await expectAsync(new RuntimeApiClient(malformed, '').getSquads()).toBeRejectedWith(jasmine.objectContaining({ kind: 'malformed-response' }));
  });

  it('sends direct squad commands with credentials, CSRF, complete JSON, and create-only idempotency', async () => {
    const success = { ok: true, data: {
      squad: { id: '31', name: 'Raiders', is_active: true, formation: Array(9).fill(null) },
      active_squad_id: '31', player_revision: 8,
    } };
    const deleted = { ok: true, data: { deleted_squad_id: '31', active_squad_id: null, player_revision: 9 } };
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest').and.callFake(async (_url, init) =>
      new Response(JSON.stringify(init?.method === 'DELETE' ? deleted : success), { status: 200 }),
    );
    const client = new RuntimeApiClient(fetchRequest, '/root');
    const payload = { name: 'Raiders', formation: Array<string | null>(9).fill(null) };

    await client.createSquad(payload, 'csrf-token', 'squad:create:12345678');
    await client.updateSquad('31', payload, 'csrf-token');
    await client.activateSquad('31', 'csrf-token');
    await client.deleteSquad('31', 'csrf-token');

    const calls = fetchRequest.calls.allArgs();
    expect(calls.map(([url]) => url)).toEqual([
      '/root/api/v1/squads', '/root/api/v1/squads/31', '/root/api/v1/squads/31/activate', '/root/api/v1/squads/31',
    ]);
    for (const [, init] of calls) expect(init?.credentials).toBe('include');
    expect(calls[0][1]?.headers).toEqual(jasmine.objectContaining({
      'X-CSRF-Token': 'csrf-token', 'Idempotency-Key': 'squad:create:12345678', 'Content-Type': 'application/json',
    }));
    expect(calls[0][1]?.body).toBe(JSON.stringify(payload));
    expect(calls[1][1]?.headers).not.toEqual(jasmine.objectContaining({ 'Idempotency-Key': jasmine.anything() }));
    expect(calls[2][1]?.body).toBeUndefined();
    expect(calls[3][1]?.body).toBeUndefined();
  });

  it('turns malformed mutation success into an integrity-safe API failure and retains safe error codes', async () => {
    const malformed = jasmine.createSpy<RuntimeFetch>('malformed').and.resolveTo(new Response(JSON.stringify({ ok: true, data: {} }), { status: 200 }));
    await expectAsync(new RuntimeApiClient(malformed, '').activateSquad('31', 'csrf')).toBeRejectedWith(
      jasmine.objectContaining({ kind: 'malformed-response' }),
    );
    const conflict = jasmine.createSpy<RuntimeFetch>('conflict').and.resolveTo(new Response(JSON.stringify({
      ok: false, error: { code: 'active_squad_delete_forbidden', message: 'server detail' },
    }), { status: 409 }));
    await expectAsync(new RuntimeApiClient(conflict, '').deleteSquad('31', 'csrf')).toBeRejectedWith(
      jasmine.objectContaining({ kind: 'http', status: 409, code: 'active_squad_delete_forbidden' }),
    );
  });
});
