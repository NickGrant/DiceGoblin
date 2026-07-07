import { TestBed } from '@angular/core/testing';
import { ApiHttpService } from './api-http.service';

describe('ApiHttpService', () => {
  let service: ApiHttpService;
  let fetchSpy: jasmine.Spy;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(ApiHttpService);
    fetchSpy = spyOn(window, 'fetch');
  });

  it('adds json content type when a request has a body', async () => {
    fetchSpy.and.resolveTo(
      new Response(JSON.stringify({ ok: true }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    );

    await service.post('/api/v1/test', { value: 7 });

    const [, init] = fetchSpy.calls.mostRecent().args as [string, RequestInit];
    const headers = new Headers(init.headers);
    expect(headers.get('Content-Type')).toBe('application/json');
    expect(init.credentials).toBe('include');
  });

  it('throws a detailed error when the response is not ok', async () => {
    fetchSpy.and.resolveTo(new Response('problem', { status: 500, statusText: 'Server Error' }));

    await expectAsync(service.get('/api/v1/fail')).toBeRejectedWithError('API 500 Server Error: problem');
  });

  it('includes csrf token headers for csrf-protected requests', async () => {
    fetchSpy.and.callFake(async () =>
      new Response(JSON.stringify({ ok: true, data: { csrf_token: 'csrf-123' } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    );

    await service.postWithCsrf('/api/v1/test', { value: 1 });

    expect(fetchSpy.calls.count()).toBe(2);
    const [, init] = fetchSpy.calls.mostRecent().args as [string, RequestInit];
    const headers = new Headers(init.headers);
    expect(headers.get('X-CSRF-Token')).toBe('csrf-123');
  });

  it('retries once after auth recovery succeeds on a 401', async () => {
    let callCount = 0;
    service.registerAuthRecovery({
      refreshSession: async () => true,
      handleSessionExpired: async () => {},
    });
    fetchSpy.and.callFake(async () => {
      callCount += 1;
      if (callCount === 1) {
        return new Response('unauthorized', { status: 401, statusText: 'Unauthorized' });
      }

      return new Response(JSON.stringify({ ok: true }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    });

    await expectAsync(service.get('/api/v1/profile')).toBeResolvedTo({ ok: true } as any);
    expect(fetchSpy.calls.count()).toBe(2);
  });

  it('does not attempt auth recovery for the session endpoint itself', async () => {
    const refreshSession = jasmine.createSpy('refreshSession').and.resolveTo(true);
    service.registerAuthRecovery({
      refreshSession,
      handleSessionExpired: async () => {},
    });
    fetchSpy.and.resolveTo(new Response('unauthorized', { status: 401, statusText: 'Unauthorized' }));

    await expectAsync(service.get('/api/v1/session')).toBeRejected();
    expect(refreshSession).not.toHaveBeenCalled();
  });
});
