import { TestBed } from '@angular/core/testing';
import { AcademyService } from './academy.service';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';

describe('AcademyService', () => {
  let service: AcademyService;
  let apiHttp: jasmine.SpyObj<ApiHttpService>;
  let sessionService: jasmine.SpyObj<SessionService>;

  beforeEach(() => {
    apiHttp = jasmine.createSpyObj<ApiHttpService>('ApiHttpService', ['get', 'postWithCsrf']);
    sessionService = jasmine.createSpyObj<SessionService>('SessionService', ['runProfileMutation']);
    sessionService.runProfileMutation.and.callFake(async <T>(operation: () => Promise<T>) => operation());

    TestBed.configureTestingModule({
      providers: [
        AcademyService,
        { provide: ApiHttpService, useValue: apiHttp },
        { provide: SessionService, useValue: sessionService },
      ],
    });

    service = TestBed.inject(AcademyService);
  });

  it('loads the academy catalog', async () => {
    const response = { ok: true as const, data: { currency_soft: 500, unit_unlocks: [] } };
    apiHttp.get.and.resolveTo(response);

    await expectAsync(service.getCatalog()).toBeResolvedTo(response);
    expect(apiHttp.get).toHaveBeenCalledWith('/api/v1/academy');
  });

  it('purchases a unit unlock and refreshes profile state', async () => {
    const response = {
      ok: true as const,
      data: { unit_type_slug: 'support_banner_t1', cost: 250, currency_soft: 50 },
    };
    apiHttp.postWithCsrf.and.resolveTo(response);

    await expectAsync(service.unlockUnitType('support_banner_t1')).toBeResolvedTo(response);
    expect(apiHttp.postWithCsrf).toHaveBeenCalledWith('/api/v1/academy/unlock-unit-type', {
      unit_type_slug: 'support_banner_t1',
    });
    expect(sessionService.runProfileMutation).toHaveBeenCalled();
  });
});
