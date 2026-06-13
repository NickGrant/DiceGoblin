import { TestBed } from '@angular/core/testing';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';
import { ShopService } from './shop.service';

describe('ShopService', () => {
  let service: ShopService;
  let apiHttp: jasmine.SpyObj<ApiHttpService>;
  let sessionService: jasmine.SpyObj<SessionService>;

  beforeEach(() => {
    apiHttp = jasmine.createSpyObj<ApiHttpService>('ApiHttpService', ['get', 'postWithCsrf']);
    sessionService = jasmine.createSpyObj<SessionService>('SessionService', ['refreshProfile']);
    sessionService.refreshProfile.and.resolveTo();

    TestBed.configureTestingModule({
      providers: [
        ShopService,
        { provide: ApiHttpService, useValue: apiHttp },
        { provide: SessionService, useValue: sessionService },
      ],
    });

    service = TestBed.inject(ShopService);
  });

  it('loads the shop catalog', async () => {
    const response = { ok: true, data: {} } as any;
    apiHttp.get.and.resolveTo(response);

    await expectAsync(service.getCatalog()).toBeResolvedTo(response);
    expect(apiHttp.get).toHaveBeenCalledWith('/api/v1/shop');
  });

  it('purchases an item and refreshes profile state', async () => {
    const response = { ok: true } as any;
    apiHttp.postWithCsrf.and.resolveTo(response);

    await expectAsync(service.purchase('daily_deal', '7')).toBeResolvedTo(response);
    expect(apiHttp.postWithCsrf).toHaveBeenCalledWith('/api/v1/shop/purchase', {
      item_type: 'daily_deal',
      product_id: '7',
    });
    expect(sessionService.refreshProfile).toHaveBeenCalledWith({ force: true });
  });

  it('supports feature unlock purchases', async () => {
    const response = { ok: true } as any;
    apiHttp.postWithCsrf.and.resolveTo(response);

    await expectAsync(service.purchase('feature_unlock', 'academy')).toBeResolvedTo(response);
    expect(apiHttp.postWithCsrf).toHaveBeenCalledWith('/api/v1/shop/purchase', {
      item_type: 'feature_unlock',
      product_id: 'academy',
    });
  });
});
