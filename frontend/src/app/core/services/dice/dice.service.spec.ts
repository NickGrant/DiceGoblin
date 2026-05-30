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
    sessionService = jasmine.createSpyObj<SessionService>('SessionService', ['refreshProfile']);
    sessionService.refreshProfile.and.resolveTo();

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
    expect(sessionService.refreshProfile).toHaveBeenCalledWith({ force: true });
  });

  it('includes run and node context when equipping dice', async () => {
    const response = { ok: true } as any;
    apiHttp.postWithCsrf.and.resolveTo(response);

    await service.equipDice('4', '12', { runId: '5', nodeId: '6' });

    expect(apiHttp.postWithCsrf).toHaveBeenCalledWith('/api/v1/units/4/dice/equip', {
      dice_instance_id: 12,
      run_id: 5,
      node_id: 6,
    });
  });

  it('unequips dice without optional context', async () => {
    const response = { ok: true } as any;
    apiHttp.postWithCsrf.and.resolveTo(response);

    await service.unequipDice('4', '12');

    expect(apiHttp.postWithCsrf).toHaveBeenCalledWith('/api/v1/units/4/dice/unequip', {
      dice_instance_id: 12,
    });
  });
});
