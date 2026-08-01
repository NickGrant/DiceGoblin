import { signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { Router, RouterLink, provideRouter } from '@angular/router';
import { DicePageComponent } from './dice-page.component';
import { DiceService } from '../../core/services/dice/dice.service';
import { SessionService } from '../../core/services/session/session.service';

class DiceServiceStub {
  sellDice = jasmine.createSpy('sellDice').and.resolveTo({ ok: true, data: { sell_value: 12 } });
  salvageDice = jasmine.createSpy('salvageDice').and.resolveTo({
    ok: true,
    data: { dice_id: 'd2', raw_chaos_awarded: 3, currency_raw_chaos: 10 },
  });
}

class SessionServiceStub {
  readonly wrongMachineUnlocked = signal(true);
  readonly profileData = signal<any>({
    dice: [],
    currency: { soft: 0, hard: 0, raw_chaos: 7 },
    items: [
      {
        item_id: '1',
        item_slug: 'field_poultice',
        name: 'Field Poultice',
        description: 'A paste that closes small wounds.',
        category: 'consumable',
        quantity: 2,
        rarity: 'common',
        source_region_slug: 'mountains',
        source_region_name: 'Mountains',
        source_family_slug: null,
        icon_key: 'item_field_poultice',
        lore_key: 'healing_consumable',
        is_visible_before_discovery: false,
        is_spendable: true,
        is_primary_progression: false,
        meta: { effect: 'heal_run_unit_hp', amount: 10 },
      },
      {
        item_id: '2',
        item_slug: 'travel_ration',
        name: 'Travel Ration',
        description: 'Food with suspicious endurance.',
        category: 'consumable',
        quantity: 1,
        rarity: 'common',
        source_region_slug: null,
        source_region_name: null,
        source_family_slug: null,
        icon_key: 'item_travel_ration',
        lore_key: 'energy_consumable',
        is_visible_before_discovery: false,
        is_spendable: true,
        is_primary_progression: false,
        meta: { effect: 'restore_energy', amount: 10 },
      },
    ],
  });
  readonly units = signal([
    {
      id: 'u1',
      name: 'Fang',
      ability_dice: [{ ability_id: 'heavy_strike', slot_index: 0, dice_instance_id: 'd1' }],
    },
  ] as any[]);
  readonly dice = signal([
    { id: 'd1', sell_value: 12, sides: 8, rarity: 'rare', affixes: [{ name: 'Bulwark', description: 'Increase defense by 1.' }] },
    { id: 'd2', sell_value: 8, sides: 4, rarity: 'common' },
    { id: 'd3', sell_value: 15, sides: 10, rarity: 'epic', affixes: [{ name: 'Oracle', description: 'Peek the next result.' }] },
  ] as any[]);
}

describe('DicePageComponent', () => {
  async function createComponent(): Promise<ComponentFixture<DicePageComponent>> {
    await TestBed.configureTestingModule({
      imports: [DicePageComponent],
      providers: [
        provideRouter([]),
        { provide: DiceService, useClass: DiceServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(DicePageComponent);
    fixture.detectChanges();
    return fixture;
  }

  it('reports ability-slot dice as equipped', async () => {
    const fixture = await createComponent();

    const component = fixture.componentInstance;
    expect(component.isEquippedAnywhere('d1')).toBeTrue();
    expect(component.equippedUnit('d1')).toEqual({ id: 'u1', name: 'Fang' });
    expect(component.equippedUnit('d2')).toBeNull();
    expect(component.dice().map((die) => die.id)).toEqual(['d1', 'd2', 'd3']);
  });

  it('filters by equip state, size, and rarity and sorts by rarity', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;

    component.updateEquipFilter('unequipped');
    expect(component.filteredDice().map((die) => die.id)).toEqual(['d2', 'd3']);

    component.updateSize('10');
    expect(component.filteredDice().map((die) => die.id)).toEqual(['d3']);

    component.updateSize('');
    component.updateRarity('common');
    expect(component.filteredDice().map((die) => die.id)).toEqual(['d2']);

    component.updateRarity('');
    component.updateEquipFilter('all');
    component.updateSort('rarity-desc');
    expect(component.filteredDice().map((die) => die.id)).toEqual(['d3', 'd1', 'd2']);
  });

  it('paginates filtered dice and resets to the first page when filters change', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    sessionService.dice.set(Array.from({ length: 13 }, (_, index) => ({
      id: `d${index + 1}`,
      sell_value: 5,
      sides: 6,
      rarity: index === 12 ? 'rare' : 'common',
    })));
    fixture.detectChanges();

    expect(component.totalPages()).toBe(2);
    expect(component.currentPage()).toBe(1);
    expect(component.pagedDice()).toHaveSize(12);
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Page 1 / 2');

    component.goToNextPage();
    fixture.detectChanges();

    expect(component.currentPage()).toBe(2);
    expect(component.pagedDice().map((die) => die.id)).toEqual(['d13']);
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Page 2 / 2');

    component.updateRarity('common');
    fixture.detectChanges();

    expect(component.currentPage()).toBe(1);
    expect(component.filteredDice()).toHaveSize(12);
    expect(component.pagedDice()).toHaveSize(12);
  });

  it('shows a unit link for equipped dice and keeps sell and salvage for unequipped dice', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const host: HTMLElement = fixture.nativeElement;

    const inspectBodyText = () => host.querySelector('.dice-page__inspect')?.textContent ?? '';

    component.previewDice('d1');
    fixture.detectChanges();

    const unitLinkDebug = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((element) => element.nativeElement.textContent?.includes('View Fang'));

    expect(unitLinkDebug).toBeDefined();
    expect(unitLinkDebug!.injector.get(RouterLink).href).toContain('/warband/units/u1');
    expect(inspectBodyText()).toContain('View Fang');

    component.previewDice('d2');
    fixture.detectChanges();

    expect(inspectBodyText()).toContain('Sell');
    expect(inspectBodyText()).toContain('Salvage');
    expect(inspectBodyText()).not.toContain('Working...');
  });

  it('does not duplicate the Raw Chaos balance in the dice inventory header', async () => {
    const fixture = await createComponent();
    const host: HTMLElement = fixture.nativeElement;

    expect(host.textContent).not.toContain('Raw Chaos 7');
  });

  it('hides Raw Chaos and salvage before the Wrong Machine is unlocked', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    sessionService.wrongMachineUnlocked.set(false);
    fixture.detectChanges();

    component.previewDice('d2');
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const inspectText = host.querySelector('.dice-page__inspect')?.textContent ?? '';

    expect(host.textContent).not.toContain('Raw Chaos 7');
    expect(inspectText).toContain('Sell');
    expect(inspectText).not.toContain('Salvage');

    component.openSalvageConfirm(component.dice().find((die) => die.id === 'd2')!);

    expect(component.pendingSalvageDice()).toBeNull();
  });

  it('uses the first filtered die as the default inspect target and supports hover preview', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const host: HTMLElement = fixture.nativeElement;
    const selectedLabel = () =>
      (host.querySelector('.dice-page__tile.is-selected') as HTMLButtonElement | null)?.getAttribute('aria-label') ?? '';
    const inspectBodyText = () => host.querySelector('.dice-page__inspect')?.textContent ?? '';

    expect(component.inspectedDice()?.id).toBe('d2');
    expect(selectedLabel()).toContain('d4');
    expect(inspectBodyText()).toContain('d4');

    const tiles = Array.from(host.querySelectorAll('.dice-page__tile')) as HTMLButtonElement[];
    const epicTile = tiles.find((tile) => tile.getAttribute('aria-label')?.includes('Oracle d10 d10 Epic'));
    expect(epicTile).toBeDefined();

    epicTile!.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
    fixture.detectChanges();

    expect(component.inspectedDice()?.id).toBe('d3');
    expect(selectedLabel()).toContain('Oracle d10');
    expect(inspectBodyText()).toContain('Oracle d10');
  });

  it('opens a sell confirmation for unequipped dice', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;

    await component.activateDice(component.dice().find((die) => die.id === 'd2')!);

    expect(component.pendingSellDice()?.id).toBe('d2');
  });

  it('opens a salvage confirmation and calls the salvage service', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const diceService = TestBed.inject(DiceService) as unknown as DiceServiceStub;

    component.openSalvageConfirm(component.dice().find((die) => die.id === 'd2')!);
    fixture.detectChanges();

    expect(component.pendingSalvageDice()?.id).toBe('d2');

    await component.confirmSalvageDice();
    fixture.detectChanges();

    expect(diceService.salvageDice).toHaveBeenCalledWith('d2');
    expect(component.pendingSalvageDice()).toBeNull();
    expect(component.message()).toBe('Salvaged die for 3 Raw Chaos.');
  });

  it('navigates directly for equipped dice clicks', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const router = TestBed.inject(Router);
    spyOn(router, 'navigate').and.resolveTo(true);

    await component.activateDice(component.dice().find((die) => die.id === 'd1')!);

    expect(router.navigate).toHaveBeenCalledWith(['/warband/units', 'u1']);
  });

  it('does not expose legacy equip controls in inventory mode', async () => {
    const fixture = await createComponent();
    const host: HTMLElement = fixture.nativeElement;
    const buttonLabels = Array.from(host.querySelectorAll('button')).map((button) => button.textContent?.trim() ?? '');

    expect(buttonLabels).not.toContain('Unequip');
    expect(buttonLabels).not.toContain('Equip');
  });

  it('shows owned consumables on the inventory screen', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const host: HTMLElement = fixture.nativeElement;

    component.showConsumableInventory();
    fixture.detectChanges();

    expect(host.textContent).toContain('Showing 2 of 2 consumables.');
    expect(host.textContent).toContain('Field Poultice');
    expect(host.textContent).toContain('Heals 10 life');
    expect(host.textContent).toContain('Travel Ration');
    expect(host.textContent).toContain('Restores 10 energy');
    expect(component.inspectedConsumable()?.item_slug).toBe('field_poultice');
  });

  it('paginates consumables separately from dice', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    sessionService.profileData.update((profile) => ({
      ...profile,
      items: Array.from({ length: 9 }, (_, index) => ({
        item_id: String(index + 1),
        item_slug: `supply_${index + 1}`,
        name: `Supply ${index + 1}`,
        description: 'A test supply.',
        category: 'consumable',
        quantity: 1,
        rarity: 'common',
        source_region_slug: null,
        source_region_name: null,
        source_family_slug: null,
        icon_key: null,
        lore_key: null,
        is_visible_before_discovery: false,
        is_spendable: true,
        is_primary_progression: false,
        meta: {},
      })),
    }));

    component.showConsumableInventory();
    fixture.detectChanges();

    expect(component.consumableTotalPages()).toBe(2);
    expect(component.pagedConsumables()).toHaveSize(8);

    component.goToNextConsumablePage();
    fixture.detectChanges();

    expect(component.consumableCurrentPage()).toBe(2);
    expect(component.pagedConsumables().map((item) => item.item_slug)).toEqual(['supply_9']);
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Page 2 / 2');
  });
});
