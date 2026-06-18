import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap } from '@angular/router';
import { AbilityCatalogService } from '../../core/services/ability-catalog/ability-catalog.service';
import { SessionService } from '../../core/services/session/session.service';
import { UnitService } from '../../core/services/unit/unit.service';
import { UnitDetailsPageComponent } from './unit-details-page.component';

class SessionServiceStub {
  readonly units = signal([
    {
      id: 'u1',
      name: 'Fang',
      unit_type_id: 'goblin-bruiser-1',
      unit_type_slug: 'frontline_bruiser_t1',
      unit_type_name: 'Goblin Bruiser',
      tier: 1,
      level: 3,
      max_level: 3,
      locked: false,
      abilities: [
        { ability_id: 'heavy_strike', type: 'active' },
        { ability_id: 'guard', type: 'active' },
        { ability_id: 'thick_hide', type: 'passive' },
      ],
      unlocked_abilities: [
        { ability_id: 'heavy_strike' },
        { ability_id: 'guard' },
        { ability_id: 'thick_hide' },
      ],
      equipped_abilities: [
        { ability_id: 'heavy_strike', equip_order: 0, speed_cost: 4 },
        { ability_id: 'heavy_strike', equip_order: 1, speed_cost: 4 },
      ],
      ability_dice: [{ ability_id: 'heavy_strike', slot_index: 0, dice_instance_id: 'd1' }],
    },
    {
      id: 'u2',
      name: 'Moss',
      unit_type_id: 'goblin-bruiser-1',
      unit_type_slug: 'frontline_bruiser_t1',
      unit_type_name: 'Goblin Bruiser',
      tier: 1,
      level: 3,
      max_level: 3,
      locked: false,
      ability_dice: [{ ability_id: 'guard', slot_index: 0, dice_instance_id: 'd3' }],
    },
    {
      id: 'u3',
      name: 'Twig',
      unit_type_id: 'goblin-bruiser-1',
      unit_type_slug: 'frontline_bruiser_t1',
      unit_type_name: 'Goblin Bruiser',
      tier: 1,
      level: 2,
      max_level: 3,
      locked: false,
      ability_dice: [],
    },
    {
      id: 'u4',
      name: 'Bramble',
      unit_type_id: 'fox-1',
      locked: false,
      tier: 1,
      level: 3,
      max_level: 3,
      ability_dice: [],
    },
    {
      id: 'u5',
      name: 'Radley',
      unit_type_id: 'support-banner-1',
      unit_type_slug: 'support_banner_t1',
      unit_type_name: 'Bannerbearer',
      tier: 1,
      level: 3,
      max_level: 3,
      locked: false,
      ability_dice: [],
    },
  ] as any[]);
  readonly dice = signal([
    { id: 'd1', rarity: 'rare', sides: 8, affixes: [] },
    { id: 'd2', rarity: 'common', sides: 4, affixes: [] },
    { id: 'd3', rarity: 'epic', sides: 10, affixes: [] },
  ] as any[]);
  readonly activeSquad = signal({ id: 's1', unit_ids: ['u1'] } as any);
  readonly hasActiveRun = signal(false);
  readonly academyUnlocked = signal(true);
}

class UnitServiceStub {
  renameUnit = jasmine.createSpy('renameUnit').and.resolveTo({ ok: true });
  replaceEquippedAbilities = jasmine.createSpy('replaceEquippedAbilities').and.resolveTo({ ok: true });
  assignAbilitySlotDie = jasmine.createSpy('assignAbilitySlotDie').and.resolveTo({ ok: true });
  clearAbilitySlotDie = jasmine.createSpy('clearAbilitySlotDie').and.resolveTo({ ok: true });
}

class AbilityCatalogServiceStub {
  readonly abilities = signal([
    {
      ability_id: 'heavy_strike',
      type: 'active',
      display_name: 'Heavy Strike',
      short_desc: 'Hit one target hard.',
      icon_key: 'heavy_strike',
      tags: [],
      default_params: {},
      order: 1,
      speed: 4,
      dice_cost: 2,
      default_target: 'enemy_front',
    },
    {
      ability_id: 'guard',
      type: 'active',
      display_name: 'Guard',
      short_desc: 'Raise defense.',
      icon_key: 'guard',
      tags: [],
      default_params: {},
      order: 2,
      speed: 3,
      dice_cost: 1,
      default_target: 'self',
    },
    {
      ability_id: 'thick_hide',
      type: 'passive',
      display_name: 'Thick Hide',
      short_desc: 'Passive defense bonus.',
      icon_key: 'thick_hide',
      tags: [],
      default_params: {},
      order: 3,
      speed: undefined,
      dice_cost: undefined,
      default_target: null,
    },
  ] as any[]);
  readonly error = signal<string | null>(null);
  readonly abilityMap = signal(
    new Map(this.abilities().map((ability) => [ability.ability_id, ability])),
  );
  load = jasmine.createSpy('load').and.resolveTo();
}

describe('UnitDetailsPageComponent', () => {
  async function createComponent() {
    await TestBed.configureTestingModule({
      imports: [UnitDetailsPageComponent],
      providers: [
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: UnitService, useClass: UnitServiceStub },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
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
    return fixture;
  }

  it('loads promotion options on startup and can rename a unit', async () => {
    const fixture = await createComponent();

    fixture.componentInstance.renameValue = 'Rex';
    await fixture.componentInstance.renameUnit();

    const unitService = TestBed.inject(UnitService) as unknown as UnitServiceStub;
    const abilityCatalog = TestBed.inject(AbilityCatalogService) as unknown as AbilityCatalogServiceStub;
    expect(unitService.renameUnit).toHaveBeenCalledWith('u1', 'Rex');
    expect(abilityCatalog.load).toHaveBeenCalled();
    expect(fixture.componentInstance.message()).toBe('Unit renamed.');
  });

  it('builds tabbed stats, learned abilities, and academy handoff', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const host: HTMLElement = fixture.nativeElement;

    expect(component.activeTab()).toBe('stats');
    expect(component.tierRomanNumeral()).toBe('I');
    expect(host.textContent).toContain('Tier');
    expect(host.textContent).toContain('I');
    expect(host.textContent).toContain('3/3 (Goblin Bruiser)');
    expect(host.textContent).toContain('Attack');
    expect(host.textContent).toContain('Defense');
    expect(host.textContent).toContain('Promotions are now handled in the Academy.');
    expect(fixture.nativeElement.querySelector('.unit-portrait')?.getAttribute('src')).toContain(
      '/assets/ui/portraits/goblin_bruiser.png',
    );

    component.setActiveTab('abilities');
    expect(component.learnedActiveAbilities().map((ability) => ability.abilityId)).toEqual(['guard', 'heavy_strike']);
    expect(component.learnedPassiveAbilities().map((ability) => ability.abilityId)).toEqual(['thick_hide']);
  });

  it('builds slot editors from learned active abilities and filters picker dice to free or current-slot dice', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;

    const slotsByAbilityId = new Map(
      component.configurableAbilitySlots().map((slotGroup) => [slotGroup.abilityId, slotGroup]),
    );

    expect(slotsByAbilityId.get('heavy_strike')).toEqual(
      jasmine.objectContaining({
        abilityId: 'heavy_strike',
        displayName: 'Heavy Strike',
        diceCost: 2,
        copyCount: 2,
      }),
    );
    expect(slotsByAbilityId.get('guard')).toEqual(
      jasmine.objectContaining({
        abilityId: 'guard',
        displayName: 'Guard',
        diceCost: 1,
        copyCount: 0,
      }),
    );
    expect(slotsByAbilityId.get('heavy_strike')?.slots[0].die?.id).toBe('d1');
    expect(slotsByAbilityId.get('heavy_strike')?.slots[1].die).toBeNull();
    expect(slotsByAbilityId.get('guard')?.slots[0].die).toBeNull();

    component.openDicePicker('heavy_strike', 'Heavy Strike', 0);
    expect(component.pickerAvailableDice().map((die) => die.id)).toEqual(['d1', 'd2']);
  });

  it('edits and saves the combat loadout through unit service', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const unitService = TestBed.inject(UnitService) as unknown as UnitServiceStub;

    expect(component.canAddAbilityToLoadout('guard')).toBeTrue();
    component.addAbilityToLoadout('guard');
    expect(component.pendingEquippedAbilityIds()).toEqual(['heavy_strike', 'heavy_strike', 'guard']);
    expect(component.totalEquippedSpeed()).toBe(11);

    component.removeAbilityFromLoadout('heavy_strike');
    expect(component.pendingEquippedAbilityIds()).toEqual(['heavy_strike', 'guard']);

    await component.saveLoadout();
    expect(unitService.replaceEquippedAbilities).toHaveBeenCalledWith('u1', ['heavy_strike', 'guard']);
  });

  it('moves loadout abilities through the tap-first reorder controls', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;

    component.addAbilityToLoadout('guard');
    expect(component.pendingEquippedAbilityIds()).toEqual(['heavy_strike', 'heavy_strike', 'guard']);

    component.moveAbilityWithinLoadout(2, -1);
    expect(component.pendingEquippedAbilityIds()).toEqual(['heavy_strike', 'guard', 'heavy_strike']);

    component.moveAbilityWithinLoadout(1, 1);
    expect(component.pendingEquippedAbilityIds()).toEqual(['heavy_strike', 'heavy_strike', 'guard']);

    component.moveAbilityWithinLoadout(0, -1);
    expect(component.pendingEquippedAbilityIds()).toEqual(['heavy_strike', 'heavy_strike', 'guard']);
  });

  it('adds and reorders loadout bars through drop events', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;

    component.handleLoadoutDrop({
      previousContainer: { data: component.learnedActiveAbilities() },
      container: { data: component.loadoutBars() },
      previousIndex: 0,
      currentIndex: 1,
      item: { data: component.learnedActiveAbilities()[0] },
    } as any);
    expect(component.pendingEquippedAbilityIds()).toEqual(['heavy_strike', 'guard', 'heavy_strike']);

    const sharedContainer = { data: component.loadoutBars() };
    component.handleLoadoutDrop({
      previousContainer: sharedContainer,
      container: sharedContainer,
      previousIndex: 2,
      currentIndex: 0,
      item: { data: component.loadoutBars()[2] },
    } as any);
    expect(component.pendingEquippedAbilityIds()).toEqual(['heavy_strike', 'heavy_strike', 'guard']);
  });

  it('allows dice editing for learned active abilities even when they are not in the current loadout', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const unitService = TestBed.inject(UnitService) as unknown as UnitServiceStub;

    component.openDicePicker('guard', 'Guard', 0);
    expect(component.pickerState()).toEqual({
      abilityId: 'guard',
      abilityName: 'Guard',
      slotIndex: 0,
    });

    await component.applyDiceSelection('d2');
    expect(unitService.assignAbilitySlotDie).toHaveBeenCalledWith('u1', 'guard', 0, 'd2');
  });

  it('assigns and clears ability-slot dice through unit service', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const unitService = TestBed.inject(UnitService) as unknown as UnitServiceStub;

    component.openDicePicker('heavy_strike', 'Heavy Strike', 1);
    await component.applyDiceSelection('d2');
    expect(unitService.assignAbilitySlotDie).toHaveBeenCalledWith('u1', 'heavy_strike', 1, 'd2');

    component.openDicePicker('heavy_strike', 'Heavy Strike', 0);
    await component.applyDiceSelection(null);
    expect(unitService.clearAbilitySlotDie).toHaveBeenCalledWith('u1', 'heavy_strike', 0);
  });

  it('shows a lock message and blocks mutations for units locked by the active run', async () => {
    const fixture = await createComponent();
    const sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    const unitService = TestBed.inject(UnitService) as unknown as UnitServiceStub;

    sessionService.units.set(
      sessionService.units().map((unit) => (unit.id === 'u1' ? { ...unit, locked: true } : unit)) as any[],
    );
    fixture.detectChanges();

    fixture.componentInstance.renameValue = 'Rex';
    await fixture.componentInstance.renameUnit();
    fixture.componentInstance.addAbilityToLoadout('guard');
    await fixture.componentInstance.saveLoadout();
    fixture.componentInstance.openDicePicker('guard', 'Guard', 0);

    const host: HTMLElement = fixture.nativeElement;
    expect(host.textContent).toContain('This unit is locked by the active run and cannot be modified until the run ends.');
    expect(fixture.componentInstance.unitLocked()).toBeTrue();
    expect(unitService.renameUnit).not.toHaveBeenCalledWith('u1', 'Rex');
    expect(unitService.replaceEquippedAbilities).not.toHaveBeenCalled();
    expect(fixture.componentInstance.pickerState()).toBeNull();
  });

  it('treats active-squad units as locked during an active run even before the locked flag refreshes', async () => {
    const fixture = await createComponent();
    const sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;

    sessionService.hasActiveRun.set(true);
    sessionService.activeSquad.set({ id: 's1', unit_ids: ['u1'] } as any);
    fixture.detectChanges();

    expect(fixture.componentInstance.unitLocked()).toBeTrue();
  });

  it('maps bannerbearer unit slugs to the copied portrait asset name', async () => {
    await TestBed.resetTestingModule();
    await TestBed.configureTestingModule({
      imports: [UnitDetailsPageComponent],
      providers: [
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: UnitService, useClass: UnitServiceStub },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: convertToParamMap({ unitId: 'u5' }),
              queryParamMap: convertToParamMap({}),
            },
          },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(UnitDetailsPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    expect(fixture.componentInstance.unitPortraitUrl()).toBe('/assets/ui/portraits/goblin_banner.png');
  });
});
