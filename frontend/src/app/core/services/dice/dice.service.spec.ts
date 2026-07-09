import { TestBed } from '@angular/core/testing';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';
import { DiceService } from './dice.service';

describe('DiceService', () => {
  let service: DiceService;
  let apiHttp: jasmine.SpyObj<ApiHttpService>;
  let sessionService: jasmine.SpyObj<SessionService>;

  beforeEach(() => {
    apiHttp = jasmine.createSpyObj<ApiHttpService>('ApiHttpService', ['postWithCsrf']);
    sessionService = jasmine.createSpyObj<SessionService>('SessionService', ['runProfileMutation']);
    sessionService.runProfileMutation.and.callFake(async <T>(operation: () => Promise<T>) => operation());

    TestBed.configureTestingModule({
      providers: [
        DiceService,
        { provide: ApiHttpService, useValue: apiHttp },
        { provide: SessionService, useValue: sessionService },
      ],
    });

    service = TestBed.inject(DiceService);
  });

  it('sells dice and refreshes the profile', async () => {
    const response = { ok: true } as any;
    apiHttp.postWithCsrf.and.resolveTo(response);

    await service.sellDice('9');

    expect(apiHttp.postWithCsrf).toHaveBeenCalledWith('/api/v1/dice/9/sell', {});
    expect(sessionService.runProfileMutation).toHaveBeenCalled();
  });
});
