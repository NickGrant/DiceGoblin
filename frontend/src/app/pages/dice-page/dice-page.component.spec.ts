import { signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { RouterLink, provideRouter } from '@angular/router';
import { DiceService } from '../../core/services/dice/dice.service';
import { SessionService } from '../../core/services/session/session.service';
import { DicePageComponent } from './dice-page.component';

class DiceServiceStub {
  sellDice = jasmine.createSpy('sellDice').and.resolveTo({ ok: true, data: { sell_value: 12 } });
  salvageDice = jasmine.createSpy('salvageDice').and.resolveTo({
    ok: true,
    data: { dice_id: 'd2', raw_chaos_awarded: 3, currency_raw_chaos: 10 },
  });
}

class SessionServiceStub {
  readonly wrongMachineUnlocked = signal(true);
  readonly units = signal([
    {
      id: 'u1',
      name: 'Fang',
      ability_dice: [{ ability_id: 'heavy_strike', slot_index: 0, dice_instance_id: 'd1' }],
      equipped_dice: [],
    },
  ] as any[]);
  readonly dice = signal([
    {
      id: 'd1',
      display_name: 'Bone D8',
      sell_value: 12,
      value: 8,
      sides: 8,
      rarity: 'rare',
      affixes: [{ name: 'Bulwark', description: 'Increase defense by 1.', value: 1 }],
    },
    { id: 'd2', display_name: 'Cardboard D4', sell_value: 8, value: 4, sides: 4, rarity: 'common', affixes: [] },
    {
      id: 'd3',
      display_name: 'Gemstone D10',
      sell_value: 15,
      value: 10,
      sides: 10,
      rarity: 'legendary',
      affixes: [{ name: 'Oracle', description: 'Peek the next result.', value: 2 }],
    },
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

  it('renders the Figma dice inventory shell with a selected detail panel', async () => {
    const fixture = await createComponent();
    const host: HTMLElement = fixture.nativeElement;

    expect(host.textContent).toContain('Dice Inventory');
    expect(host.textContent).toContain('3 dice collected');
    expect(host.querySelectorAll('.dice-card')).toHaveSize(3);
    expect(host.textContent).toContain('Showing 3 of 3 dice');
    expect(host.querySelector('.dice-inspector')?.textContent).toContain('Gemstone D10');
    expect(host.textContent).toContain('Search dice');
    expect(host.textContent).not.toContain('Consumables');
  });

  it('filters dice by search text, material, and die size', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;

    component.updateSearch('bone');
    expect(component.filteredDice().map((die) => die.id)).toEqual(['d1']);

    component.updateSearch('');
    component.updateMaterial('cardboard');
    expect(component.filteredDice().map((die) => die.id)).toEqual(['d2']);

    component.updateMaterial('all');
    component.selectSize(10);
    expect(component.filteredDice().map((die) => die.id)).toEqual(['d3']);
  });

  it('paginates dice eight at a time and resets pagination when filters change', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    sessionService.dice.set(
      Array.from({ length: 9 }, (_, index) => ({
        id: `d${index + 1}`,
        display_name: `Cardboard D6 ${index + 1}`,
        sell_value: 6,
        value: 6,
        sides: 6,
        rarity: index === 8 ? 'rare' : 'common',
        affixes: [],
      })),
    );
    fixture.detectChanges();

    expect(component.totalPages()).toBe(2);
    expect(component.currentPage()).toBe(1);
    expect(component.pagedDice()).toHaveSize(8);
    expect((fixture.nativeElement as HTMLElement).querySelectorAll('.dice-card')).toHaveSize(8);
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Page 1 / 2');

    component.goToNextPage();
    fixture.detectChanges();

    expect(component.currentPage()).toBe(2);
    expect(component.pagedDice()).toHaveSize(1);
    expect((fixture.nativeElement as HTMLElement).querySelectorAll('.dice-card')).toHaveSize(1);
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Page 2 / 2');

    component.updateSearch('Cardboard D6 1');
    fixture.detectChanges();

    expect(component.currentPage()).toBe(1);
    expect(component.filteredDice().length).toBeGreaterThan(0);
  });

  it('uses selected dice for inspection and falls back when filters exclude it', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;

    component.inspectDice(component.dice().find((die) => die.id === 'd1')!);
    fixture.detectChanges();

    expect(component.inspectedDice()?.id).toBe('d1');
    expect((fixture.nativeElement as HTMLElement).querySelector('.dice-inspector')?.textContent).toContain('Bone D8');

    component.updateSearch('cardboard');
    fixture.detectChanges();

    expect(component.inspectedDice()?.id).toBe('d2');
    expect((fixture.nativeElement as HTMLElement).querySelector('.dice-inspector')?.textContent).toContain('Cardboard D4');
  });

  it('reports ability-slot and equipped dice as equipped', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;

    sessionService.units.update((units) => [
      ...units,
      { id: 'u2', name: 'Mog', ability_dice: [], equipped_dice: [{ dice_instance_id: 'd2', slot_index: 0 }] },
    ]);

    expect(component.isEquippedAnywhere('d1')).toBeTrue();
    expect(component.isEquippedAnywhere('d2')).toBeTrue();
    expect(component.equippedUnit('d1')).toEqual({ id: 'u1', name: 'Fang' });
    expect(component.equippedUnit('d2')).toEqual({ id: 'u2', name: 'Mog' });
  });

  it('links equipped dice to the owning unit', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const host: HTMLElement = fixture.nativeElement;

    component.inspectDice(component.dice().find((die) => die.id === 'd1')!);
    fixture.detectChanges();

    const unitLinkDebug = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((element) => element.nativeElement.textContent?.includes('View Unit'));

    expect(host.querySelector('.dice-inspector')?.textContent).toContain('Currently Equipped by');
    expect(unitLinkDebug).toBeDefined();
    expect(unitLinkDebug!.injector.get(RouterLink).href).toContain('/warband/units/u1');
  });

  it('opens sell and salvage confirmations and calls the service', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const diceService = TestBed.inject(DiceService) as unknown as DiceServiceStub;
    const die = component.dice().find((entry) => entry.id === 'd2')!;

    component.openSellConfirm(die);
    fixture.detectChanges();

    expect(component.pendingSellDice()?.id).toBe('d2');

    await component.confirmSellDice();
    fixture.detectChanges();

    expect(diceService.sellDice).toHaveBeenCalledWith('d2');
    expect(component.pendingSellDice()).toBeNull();

    component.openSalvageConfirm(die);
    fixture.detectChanges();

    await component.confirmSalvageDice();
    fixture.detectChanges();

    expect(diceService.salvageDice).toHaveBeenCalledWith('d2');
    expect(component.message()).toBe('Salvaged die for 3 Raw Chaos.');
  });

  it('hides salvage before the Wrong Machine is unlocked', async () => {
    const fixture = await createComponent();
    const component = fixture.componentInstance;
    const sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    const die = component.dice().find((entry) => entry.id === 'd2')!;

    sessionService.wrongMachineUnlocked.set(false);
    fixture.detectChanges();

    expect((fixture.nativeElement as HTMLElement).querySelector('.dice-inspector')?.textContent).not.toContain('Salvage');

    component.openSalvageConfirm(die);

    expect(component.pendingSalvageDice()).toBeNull();
  });
});
