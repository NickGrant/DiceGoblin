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
      max_level: 10,
      promotion_level: 6,
      promotion_eligible: true,
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
      max_level: 10,
      promotion_level: 6,
      promotion_eligible: true,
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
      max_level: 10,
      promotion_level: 6,
      promotion_eligible: true,
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
      max_level: 10,
      promotion_level: 6,
      promotion_eligible: true,
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
          cost: 250,
          is_unlocked: true,
          is_available: true,
          requirements: [],
          total_attack: 6,
          total_defense: 4,
          total_precision: 5,
          total_resolve: 5,
          max_hp: 24,
        },
        {
          unit_type_slug: 'support_banner_t1',
          name: 'Bannerbearer',
          role: 'support',
          cost: 250,
          is_unlocked: false,
          is_available: true,
          requirements: [],
          total_attack: 2,
          total_defense: 4,
          total_precision: 5,
          total_resolve: 6,
          max_hp: 20,
        },
        {
          unit_type_slug: 'frontline_bruiser_t2',
          name: 'Enforcer',
          role: 'frontline',
          cost: 500,
          is_unlocked: false,
          is_available: false,
          requirements: [
            {
              type: 'completed_run',
              label: 'Complete any run',
              is_met: false,
              progress_current: 0,
              progress_target: 1,
            },
          ],
          total_attack: 8,
          total_defense: 5,
          total_precision: 5,
          total_resolve: 6,
          max_hp: 28,
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
      promotion_eligible: true,
      is_mastered: true,
      current_capstone_state: 'ready_to_select',
      capstone_choices: [
        { ability_id: 'brawl_hardened' },
        { ability_id: 'finisher' },
      ],
      selected_capstone: null,
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
          promotion_grants: {
            actives: ['skullcrack'],
            passives: ['menacing_follow_through'],
          },
          will_skip_current_capstone: true,
        },
      ],
    },
  });
  promoteUnit = jasmine.createSpy('promoteUnit').and.resolveTo({ ok: true });
  selectCapstone = jasmine.createSpy('selectCapstone').and.resolveTo({ ok: true });
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
    expect(component.unitUnlockCatalog().length).toBe(3);
    expect(component.availableUnitUnlocks().map((entry) => entry.unit_type_slug)).toEqual([
      'support_banner_t1',
      'frontline_bruiser_t2',
    ]);
    expect(component.unitUnlockDescription('support_banner_t1')).toContain('support specialist');
    expect(component.unitUnlockDescription('frontline_bruiser_t2')).toContain('heavier execution damage');
    expect(component.unitUnlockRequirementLabel(component.availableUnitUnlocks()[0])).toBe('');
    expect(component.unitUnlockRequirementLabel(component.availableUnitUnlocks()[1])).toBe('Requires: Complete any run (0/1)');
    expect(component.unitUnlockActionLabel(component.availableUnitUnlocks()[0])).toBe('Unlock');
    expect(component.unitUnlockActionLabel(component.availableUnitUnlocks()[1])).toBe('Locked');
    expect(component.unitUnlockMetaLabel(component.availableUnitUnlocks()[0])).toBe('Support unit type - Tier I');
    expect(component.unitUnlockTierLabel(component.availableUnitUnlocks()[0].unit_type_slug)).toBe('I');
    expect(component.unitUnlockTierClass(component.availableUnitUnlocks()[1])).toContain('dg-tier-indicator--2');
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('PRC');
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('RES');
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Support unit type - Tier I');
    expect((fixture.nativeElement as HTMLElement).textContent).not.toContain('adds future recruit and reward drops');
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Requires: Complete any run (0/1)');
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Unlock');
    expect((fixture.nativeElement as HTMLElement).querySelector('.academy-wallet')).toBeNull();
    expect((fixture.nativeElement as HTMLElement).querySelector('.academy-unlock-card__role-icon')).not.toBeNull();
    expect((fixture.nativeElement as HTMLElement).querySelector('.academy-unlock-card__tier.dg-tier-indicator--1')).not.toBeNull();
    component.selectedUnitId.set('u1');
    await fixture.whenStable();
    fixture.detectChanges();

    expect(component.selectedUnit()?.id).toBe('u1');
    expect(component.promotableUnits().map((unit) => unit.id)).toEqual(['u1', 'u2', 'u3']);
    expect(unitService.getPromotionOptions).toHaveBeenCalledWith('u1');
    expect(component.eligiblePromotionCandidates().map((unit) => unit.id)).toEqual(['u2', 'u3']);
    expect(component.promotionOptionLabel(component.promotionOptions()[0])).toBe('Enforcer - chain');
    expect(component.selectedDestination()).toBe('enforcer');
    expect(component.mustChooseCapstoneBeforePromotion()).toBeTrue();
  });

  it('simplifies sideways labels when the branch and result share the same name', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;

    expect(component.promotionOptionLabel({
      branch_unit_type_id: 'banner',
      branch_unit_type_slug: 'support_banner_t1',
      branch_unit_type_name: 'Bannerbearer',
      target_unit_type_id: 'warcaller',
      target_unit_type_slug: 'support_banner_t2',
      target_unit_type_name: 'Bannerbearer',
      target_tier: 2,
      mode: 'sideways',
    })).toBe('Bannerbearer - sideways');
  });

  it('shows unlocked Tier 1 sideways branches as same-depth promotions', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;

    expect(component.promotionOptionLabel({
      branch_unit_type_id: 'marksman',
      branch_unit_type_slug: 'backline_marksman_t1',
      branch_unit_type_name: 'Marksman',
      target_unit_type_id: 'marksman',
      target_unit_type_slug: 'backline_marksman_t1',
      target_unit_type_name: 'Marksman',
      target_tier: 2,
      mode: 'sideways',
    })).toBe('Marksman - sideways');
  });

  it('labels sideways destinations with both result and branch when they differ', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;

    expect(component.promotionOptionLabel({
      branch_unit_type_id: 'banner',
      branch_unit_type_slug: 'support_banner_t1',
      branch_unit_type_name: 'Bannerbearer',
      target_unit_type_id: 'warcaller',
      target_unit_type_slug: 'support_banner_t2',
      target_unit_type_name: 'Warcaller',
      target_tier: 2,
      mode: 'sideways',
    })).toBe('Warcaller - sideways via Bannerbearer');
  });

  it('promotes the selected unit using two chosen secondaries', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const unitService = TestBed.inject(UnitService) as unknown as UnitServiceStub;

    component.selectedUnitId.set('u1');
    await fixture.whenStable();
    component['promotionContext'].update((value) => value ? { ...value, selected_capstone: { ability_id: 'finisher' } as any, current_capstone_state: 'selected' } : value);
    component.toggleSecondary('u2');
    component.toggleSecondary('u3');
    await component.promoteUnit();

    expect(unitService.promoteUnit).toHaveBeenCalledWith('u1', ['u2', 'u3'], 'enforcer');
    expect(component.message()).toBe('Promotion complete.');
  });

  it('blocks promotion until a mastered unit chooses a capstone', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const unitService = TestBed.inject(UnitService) as unknown as UnitServiceStub;

    component.selectedUnitId.set('u1');
    await fixture.whenStable();
    component.toggleSecondary('u2');
    component.toggleSecondary('u3');
    await component.promoteUnit();

    expect(component.error()).toBe('Choose a capstone for this mastered unit before confirming promotion.');
    expect(unitService.promoteUnit).not.toHaveBeenCalled();
  });

  it('renders promotion preview warnings and immediate grant summaries', async () => {
    const fixture = await createComponent();
    await fixture.whenStable();
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    expect(host.textContent).toContain('Promoting now will skip the current class capstone unless it has already been selected and inherited.');
    expect(host.textContent).toContain('Immediate Promotion Grants');
    expect(host.textContent).toContain('Skullcrack');
    expect(host.textContent).toContain('Menacing Follow Through');
  });

  it('unlocks a unit type through the academy service', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const academyService = TestBed.inject(AcademyService) as unknown as AcademyServiceStub;

    await component.unlockUnitType('support_banner_t1');

    expect(academyService.unlockUnitType).toHaveBeenCalledWith('support_banner_t1');
    expect(component.message()).toBe('Unit type unlocked.');
  });

  it('disables research while backend-authored requirements are unmet', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const host: HTMLElement = fixture.nativeElement;
    const lockedEntry = component.availableUnitUnlocks().find((entry) => entry.unit_type_slug === 'frontline_bruiser_t2');

    expect(lockedEntry).toBeTruthy();
    expect(component.unitUnlockDisabled(lockedEntry!)).toBeTrue();
    expect(host.textContent).toContain('Requires: Complete any run (0/1)');

    const buttons = Array.from(host.querySelectorAll<HTMLButtonElement>('.academy-unlock-card'));
    const tierTwoButton = buttons.find((button) => button.textContent?.includes('Enforcer'));

    expect(tierTwoButton?.disabled).toBeTrue();
  });
});
