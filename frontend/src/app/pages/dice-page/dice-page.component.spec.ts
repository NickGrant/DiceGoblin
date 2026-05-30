import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap } from '@angular/router';
import { DicePageComponent } from './dice-page.component';
import { DiceService } from '../../core/services/dice/dice.service';
import { SessionService } from '../../core/services/session/session.service';

class DiceServiceStub {
  sellDice = jasmine.createSpy('sellDice').and.resolveTo({ ok: true, data: { sell_value: 12 } });
  equipDice = jasmine.createSpy('equipDice').and.resolveTo({ ok: true });
  unequipDice = jasmine.createSpy('unequipDice').and.resolveTo({ ok: true });
}

class SessionServiceStub {
  readonly profileData = signal({ dice: [] });
  readonly units = signal([
    {
      id: 'u1',
      name: 'Fang',
      equipped_dice: [{ dice_instance_id: 'd1' }],
    },
  ] as any[]);
  readonly dice = signal([{ id: 'd1' }, { id: 'd2' }] as any[]);
}

describe('DicePageComponent', () => {
  it('selects a unit from query params and reports equipped dice state', async () => {
    await TestBed.configureTestingModule({
      imports: [DicePageComponent],
      providers: [
        { provide: DiceService, useClass: DiceServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              queryParamMap: convertToParamMap({ unitId: 'u1', mode: 'equip' }),
            },
          },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(DicePageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    expect(component.selectedUnit()?.name).toBe('Fang');
    expect(component.isEquippedAnywhere('d1')).toBeTrue();
    expect(component.isEquippedToSelectedUnit('d1')).toBeTrue();
  });
});
