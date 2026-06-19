import { TestBed } from '@angular/core/testing';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';
import { UnitService } from './unit.service';

describe('UnitService', () => {
  let service: UnitService;
  let apiHttp: jasmine.SpyObj<ApiHttpService>;
  let sessionService: jasmine.SpyObj<SessionService>;

  beforeEach(() => {
    apiHttp = jasmine.createSpyObj<ApiHttpService>('ApiHttpService', ['get', 'patchWithCsrf', 'postWithCsrf', 'putWithCsrf', 'deleteWithCsrf']);
    sessionService = jasmine.createSpyObj<SessionService>('SessionService', ['refreshProfile']);
    sessionService.refreshProfile.and.resolveTo();

    TestBed.configureTestingModule({
      providers: [
        UnitService,
        { provide: ApiHttpService, useValue: apiHttp },
        { provide: SessionService, useValue: sessionService },
      ],
    });

    service = TestBed.inject(UnitService);
  });

  it('loads promotion options', async () => {
    const response = { ok: true } as any;
    apiHttp.get.and.resolveTo(response);

    await expectAsync(service.getPromotionOptions('7')).toBeResolvedTo(response);
    expect(apiHttp.get).toHaveBeenCalledWith('/api/v1/units/7/promotion-options');
  });

  it('renames a unit and refreshes profile state', async () => {
    apiHttp.patchWithCsrf.and.resolveTo({ ok: true } as any);

    await service.renameUnit('7', 'Fang');

    expect(apiHttp.patchWithCsrf).toHaveBeenCalledWith('/api/v1/units/7/name', {
      display_name: 'Fang',
    });
    expect(sessionService.refreshProfile).toHaveBeenCalledWith({ force: true });
  });

  it('sends promotion payloads without run context', async () => {
    apiHttp.postWithCsrf.and.resolveTo({ ok: true } as any);

    await service.promoteUnit('1', ['2', '3'], '4');

    expect(apiHttp.postWithCsrf).toHaveBeenCalledWith('/api/v1/units/1/promote', {
      primary_unit_instance_id: 1,
      secondary_unit_instance_ids: [2, 3],
      destination_unit_type_id: 4,
    });
  });

  it('selects a capstone and refreshes profile state', async () => {
    apiHttp.putWithCsrf.and.resolveTo({ ok: true } as any);

    await service.selectCapstone('1', 'finisher');

    expect(apiHttp.putWithCsrf).toHaveBeenCalledWith('/api/v1/units/1/capstone', {
      ability_id: 'finisher',
    });
    expect(sessionService.refreshProfile).toHaveBeenCalledWith({ force: true });
  });

  it('clears an ability slot die and refreshes profile', async () => {
    apiHttp.deleteWithCsrf.and.resolveTo({ ok: true } as any);

    await service.clearAbilitySlotDie('1', 'ability-7', 2);

    expect(apiHttp.deleteWithCsrf).toHaveBeenCalledWith('/api/v1/units/1/abilities/ability-7/slots/2/dice', {});
  });
});
