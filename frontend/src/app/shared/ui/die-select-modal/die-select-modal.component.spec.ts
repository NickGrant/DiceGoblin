import { Component, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { DiceRecord } from '../../../core/models/api.models';
import { DieSelectModalComponent } from './die-select-modal.component';

@Component({
  standalone: true,
  imports: [DieSelectModalComponent],
  template: `
    <dg-die-select-modal
      [open]="true"
      [dice]="dice"
      [equippedDiceIds]="equippedDiceIds"
      [lockedFilters]="lockedFilters()"
      [hiddenFilters]="hiddenFilters"
      (selected)="selected.set($event)"
    />
  `,
})
class DieSelectModalHostComponent {
  dice: DiceRecord[] = [
    { id: 'd1', display_name: 'Bone D6', rarity: 'rare', sides: 6 },
    { id: 'd2', display_name: 'Clay D8', rarity: 'common', sides: 8 },
    { id: 'd3', display_name: 'Metal D12', rarity: 'epic', sides: 12 },
  ];
  equippedDiceIds = ['d1'];
  hiddenFilters = ['status'] as const;
  lockedFilters = signal({ status: 'unequipped' as const });
  selected = signal<DiceRecord | null>(null);
}

describe('DieSelectModalComponent', () => {
  let fixture: ComponentFixture<DieSelectModalHostComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DieSelectModalHostComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(DieSelectModalHostComponent);
    fixture.detectChanges();
  });

  it('applies hidden locked status filters', () => {
    const text = fixture.nativeElement.textContent as string;

    expect(text).not.toContain('Bone D6');
    expect(text).toContain('Clay D8');
    expect(text).toContain('Metal D12');
    expect(fixture.debugElement.query(By.css('select[aria-label="Status"]'))).toBeNull();
  });

  it('emits the confirmed selected die', () => {
    const cards = fixture.debugElement.queryAll(By.css('.die-card:not(.die-card--empty)'));
    cards[0].nativeElement.click();
    fixture.detectChanges();

    const selectButton = fixture.debugElement.query(By.css('.select-modal__actions button:last-child'));
    selectButton.nativeElement.click();

    expect(fixture.componentInstance.selected()?.id).toBe('d2');
  });
});
