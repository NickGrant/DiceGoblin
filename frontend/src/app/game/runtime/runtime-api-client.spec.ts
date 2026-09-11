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
});
