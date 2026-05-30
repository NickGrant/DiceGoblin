import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap } from '@angular/router';
import { UnitDetailsPageComponent } from './unit-details-page.component';
import { DiceService } from '../../core/services/dice/dice.service';
import { SessionService } from '../../core/services/session/session.service';
import { UnitService } from '../../core/services/unit/unit.service';

class SessionServiceStub {
  readonly units = signal([
    {
      id: 'u1',
      name: 'Fang',
      equipped_dice: [{ dice_instance_id: 'd1' }],
    },
  ] as any[]);
}

class UnitServiceStub {
  getPromotionOptions = jasmine.createSpy('getPromotionOptions').and.resolveTo({
    ok: true,
    data: {
      options: [{ target_unit_type_id: 'dest-1' }],
    },
  });
  renameUnit = jasmine.createSpy('renameUnit').and.resolveTo({ ok: true });
  promoteUnit = jasmine.createSpy('promoteUnit').and.resolveTo({ ok: true });
}

class DiceServiceStub {
  unequipDice = jasmine.createSpy('unequipDice').and.resolveTo({ ok: true });
}

describe('UnitDetailsPageComponent', () => {
  it('loads promotion options on startup and can rename a unit', async () => {
    await TestBed.configureTestingModule({
      imports: [UnitDetailsPageComponent],
      providers: [
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: UnitService, useClass: UnitServiceStub },
        { provide: DiceService, useClass: DiceServiceStub },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: convertToParamMap({ unitId: 'u1' }),
              queryParamMap: convertToParamMap({}),
            },
          },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(UnitDetailsPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    fixture.componentInstance.renameValue = 'Rex';
    await fixture.componentInstance.renameUnit();

    const unitService = TestBed.inject(UnitService) as unknown as UnitServiceStub;
    expect(unitService.getPromotionOptions).toHaveBeenCalledWith('u1');
    expect(unitService.renameUnit).toHaveBeenCalledWith('u1', 'Rex');
    expect(fixture.componentInstance.message()).toBe('Unit renamed.');
  });
});
