import { signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { Router, RouterLink, provideRouter } from '@angular/router';
import { DicePageComponent } from './dice-page.component';
import { DiceService } from '../../core/services/dice/dice.service';
import { SessionService } from '../../core/services/session/session.service';

class DiceServiceStub {
  sellDice = jasmine.createSpy('sellDice').and.resolveTo({ ok: true, data: { sell_value: 12 } });
}

class SessionServiceStub {
  readonly profileData = signal({ dice: [] });
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

  it('shows a unit link for equipped dice and keeps sell for unequipped dice', async () => {
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
    expect(inspectBodyText()).not.toContain('Working...');
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
});
