import { TestBed } from '@angular/core/testing';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';
import { DebugService } from './debug.service';

describe('DebugService', () => {
  let service: DebugService;
  let apiHttp: jasmine.SpyObj<ApiHttpService>;
  let sessionService: jasmine.SpyObj<SessionService>;

  beforeEach(() => {
    apiHttp = jasmine.createSpyObj<ApiHttpService>('ApiHttpService', ['get', 'postWithCsrf']);
    sessionService = jasmine.createSpyObj<SessionService>('SessionService', ['refreshProfile']);
    sessionService.refreshProfile.and.resolveTo();

    TestBed.configureTestingModule({
      providers: [
        DebugService,
        { provide: ApiHttpService, useValue: apiHttp },
        { provide: SessionService, useValue: sessionService },
      ],
    });

    service = TestBed.inject(DebugService);
  });

  it('loads the debug catalog', async () => {
    const response = { ok: true, data: {} } as any;
    apiHttp.get.and.resolveTo(response);

    await expectAsync(service.getCatalog()).toBeResolvedTo(response);
    expect(apiHttp.get).toHaveBeenCalledWith('/api/v1/debug/catalog');
  });

  it('grants currency and refreshes profile', async () => {
    const response = { ok: true } as any;
    apiHttp.postWithCsrf.and.resolveTo(response);

    await service.grantCurrency(50, 2);

    expect(apiHttp.postWithCsrf).toHaveBeenCalledWith('/api/v1/debug/grant/currency', { soft: 50, hard: 2 });
    expect(sessionService.refreshProfile).toHaveBeenCalledWith({ force: true });
  });

  it('resets the account and refreshes profile', async () => {
    const response = { ok: true } as any;
    apiHttp.postWithCsrf.and.resolveTo(response);

    await service.resetAccount();

    expect(apiHttp.postWithCsrf).toHaveBeenCalledWith('/api/v1/debug/reset-account', {});
    expect(sessionService.refreshProfile).toHaveBeenCalledWith({ force: true });
  });
});
