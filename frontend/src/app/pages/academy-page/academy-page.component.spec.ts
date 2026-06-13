import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { AcademyPageComponent } from './academy-page.component';
import { AcademyService } from '../../core/services/academy/academy.service';
import { SessionService } from '../../core/services/session/session.service';
import { UnitService } from '../../core/services/unit/unit.service';

class SessionServiceStub {
  readonly units = signal([
    {
      id: 'u1',
      name: 'Fang',
      unit_type_id: 'bruiser',
      unit_type_slug: 'frontline_bruiser_t1',
      unit_type_name: 'Bruiser',
      tier: 1,
      level: 6,
      max_level: 6,
      locked: false,
    },
    {
      id: 'u2',
      name: 'Moss',
      unit_type_id: 'bruiser',
      unit_type_slug: 'frontline_bruiser_t1',
      unit_type_name: 'Bruiser',
      tier: 1,
      level: 6,
      max_level: 6,
      locked: false,
    },
    {
      id: 'u3',
      name: 'Twig',
      unit_type_id: 'bruiser',
      unit_type_slug: 'frontline_bruiser_t1',
      unit_type_name: 'Bruiser',
      tier: 1,
      level: 6,
      max_level: 6,
      locked: false,
    },
    {
      id: 'u4',
      name: 'Radley',
      unit_type_id: 'banner',
      unit_type_slug: 'support_banner_t1',
      unit_type_name: 'Bannerbearer',
      tier: 1,
      level: 6,
      max_level: 6,
      locked: false,
    },
  ] as any[]);
  readonly activeSquad = signal({ id: 's1', unit_ids: ['u1'] } as any);
  readonly hasActiveRun = signal(false);
  readonly profile = signal({
    energyCurrent: 12,
    energyMax: 20,
    softCurrency: 500,
    activeRunId: null,
    squadCount: 1,
    unitCount: 4,
    activeSquadName: 'Alpha Squad',
  });
}

class AcademyServiceStub {
  getCatalog = jasmine.createSpy('getCatalog').and.resolveTo({
    ok: true,
    data: {
      currency_soft: 500,
      unit_unlocks: [
        {
          unit_type_slug: 'frontline_bruiser_t1',
          name: 'Bruiser',
          role: 'frontline',
          cost: 500,
          is_unlocked: true,
        },
        {
          unit_type_slug: 'support_banner_t1',
          name: 'Bannerbearer',
          role: 'support',
          cost: 250,
          is_unlocked: false,
        },
      ],
    },
  });
  unlockUnitType = jasmine.createSpy('unlockUnitType').and.resolveTo({
    ok: true,
    data: {
      unit_type_slug: 'support_banner_t1',
      cost: 250,
      currency_soft: 250,
    },
  });
}

class UnitServiceStub {
  getPromotionOptions = jasmine.createSpy('getPromotionOptions').and.resolveTo({
    ok: true,
    data: {
      options: [
        {
          branch_unit_type_id: 'bruiser',
          branch_unit_type_slug: 'frontline_bruiser_t1',
          branch_unit_type_name: 'Bruiser',
          target_unit_type_id: 'enforcer',
          target_unit_type_slug: 'frontline_bruiser_t2',
          target_unit_type_name: 'Enforcer',
          target_tier: 2,
          mode: 'chain',
        },
      ],
    },
  });
  promoteUnit = jasmine.createSpy('promoteUnit').and.resolveTo({ ok: true });
}

describe('AcademyPageComponent', () => {
  async function createComponent() {
    await TestBed.configureTestingModule({
      imports: [AcademyPageComponent],
      providers: [
        provideRouter([]),
        { provide: AcademyService, useClass: AcademyServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: UnitService, useClass: UnitServiceStub },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              queryParamMap: convertToParamMap({ unitId: 'u1' }),
              paramMap: convertToParamMap({}),
            },
          },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(AcademyPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();
    return fixture;
  }

  it('loads unlock catalog plus promotion options for an eligible unit', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const academyService = TestBed.inject(AcademyService) as unknown as AcademyServiceStub;
    const unitService = TestBed.inject(UnitService) as unknown as UnitServiceStub;

    expect(academyService.getCatalog).toHaveBeenCalled();
    expect(component.unitUnlockCatalog().length).toBe(2);
    expect(component.availableUnitUnlocks().map((entry) => entry.unit_type_slug)).toEqual(['support_banner_t1']);
    expect(component.unitUnlockDescription('support_banner_t1')).toContain('support specialist');
    component.selectedUnitId.set('u1');
    await fixture.whenStable();
    fixture.detectChanges();

    expect(component.selectedUnit()?.id).toBe('u1');
    expect(component.promotableUnits().map((unit) => unit.id)).toEqual(['u1', 'u2', 'u3']);
    expect(unitService.getPromotionOptions).toHaveBeenCalledWith('u1');
    expect(component.eligiblePromotionCandidates().map((unit) => unit.id)).toEqual(['u2', 'u3']);
    expect(component.promotionOptionLabel(component.promotionOptions()[0])).toBe('Enforcer - chain');
    expect(component.selectedDestination()).toBe('');
  });

  it('promotes the selected unit using two chosen secondaries', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const unitService = TestBed.inject(UnitService) as unknown as UnitServiceStub;

    component.selectedUnitId.set('u1');
    await fixture.whenStable();
    component.toggleSecondary('u2');
    component.toggleSecondary('u3');
    await component.promoteUnit();

    expect(unitService.promoteUnit).toHaveBeenCalledWith('u1', ['u2', 'u3'], undefined);
    expect(component.message()).toBe('Promotion complete.');
  });

  it('unlocks a unit type through the academy service', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const academyService = TestBed.inject(AcademyService) as unknown as AcademyServiceStub;

    await component.unlockUnitType('support_banner_t1');

    expect(academyService.unlockUnitType).toHaveBeenCalledWith('support_banner_t1');
    expect(component.message()).toBe('Unit type unlocked.');
  });
});
