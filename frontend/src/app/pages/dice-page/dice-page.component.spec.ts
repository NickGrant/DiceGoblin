import { signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { RouterLink, provideRouter } from '@angular/router';
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
    { id: 'd1', sell_value: 12 },
    { id: 'd2', sell_value: 8 },
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
    expect(component.dice().map((die) => die.id)).toEqual(['d1', 'd2']);
  });

  it('shows a unit link for equipped dice and keeps sell for unequipped dice', async () => {
    const fixture = await createComponent();
    const host: HTMLElement = fixture.nativeElement;

    const sellButtons = Array.from(host.querySelectorAll('button')).map((button) => button.textContent?.trim() ?? '');
    expect(sellButtons.some((label) => label.startsWith('Sell'))).toBeTrue();
    expect(sellButtons).not.toContain('Working...');

    const unitLinkDebug = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((element) => element.nativeElement.textContent?.includes('View Fang'));

    expect(unitLinkDebug).toBeDefined();
    expect(unitLinkDebug!.injector.get(RouterLink).href).toContain('/warband/units/u1');

    const actionLabels = host.textContent ?? '';
    expect(actionLabels).toContain('View Fang');
    expect(actionLabels).toContain('Sell');
  });

  it('does not expose legacy equip controls in inventory mode', async () => {
    const fixture = await createComponent();
    const host: HTMLElement = fixture.nativeElement;

    expect(host.textContent).not.toContain('Unequip');
    expect(host.textContent).not.toContain('Equip');
  });
});
