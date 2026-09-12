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
});
