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
});
