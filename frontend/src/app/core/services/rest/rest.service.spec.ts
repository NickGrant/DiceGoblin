import { TestBed } from '@angular/core/testing';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';
import { RestService } from './rest.service';

describe('RestService', () => {
  let service: RestService;
  let apiHttp: jasmine.SpyObj<ApiHttpService>;
  let sessionService: jasmine.SpyObj<SessionService>;

  beforeEach(() => {
    apiHttp = jasmine.createSpyObj<ApiHttpService>('ApiHttpService', ['postWithCsrf']);
    sessionService = jasmine.createSpyObj<SessionService>('SessionService', ['refreshProfile']);
    sessionService.refreshProfile.and.resolveTo();

    TestBed.configureTestingModule({
      providers: [
        RestService,
        { provide: ApiHttpService, useValue: apiHttp },
        { provide: SessionService, useValue: sessionService },
      ],
    });

    service = TestBed.inject(RestService);
  });

  it('purchases a rest store item and refreshes the profile', async () => {
    const response = { ok: true } as any;
    apiHttp.postWithCsrf.and.resolveTo(response);

    await service.purchaseStoreItem('run-1', 'node-2', 'basic_unit');

    expect(apiHttp.postWithCsrf).toHaveBeenCalledWith('/api/v1/runs/run-1/nodes/node-2/rest/store/purchase', {
      item_type: 'basic_unit',
    });
    expect(sessionService.refreshProfile).toHaveBeenCalledWith({ force: true });
  });
});
