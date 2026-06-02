import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { DicePickerModalComponent } from './dice-picker-modal.component';

@Component({
  standalone: true,
  imports: [DicePickerModalComponent],
  template: `
    <dg-dice-picker-modal
      [open]="true"
      slotLabel="Heavy Strike · Slot 1"
      [dice]="dice"
      selectedDiceId="d1"
      (selected)="onSelected($event)"
      (dismissed)="dismissed = true"
    />
  `,
})
class HostComponent {
  readonly dice = [
    { id: 'd1', rarity: 'rare', sides: 8, affixes: [] },
    { id: 'd2', rarity: 'common', sides: 4, affixes: [] },
  ];
  selectedValue: string | null | undefined;
  dismissed = false;

  onSelected(value: string | null): void {
    this.selectedValue = value;
  }
}

describe('DicePickerModalComponent', () => {
  it('emits no-choice and die selections', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const buttons = Array.from(fixture.nativeElement.querySelectorAll('button')) as HTMLButtonElement[];
    buttons.find((button) => button.textContent?.includes('No Choice'))?.click();
    fixture.detectChanges();
    expect(fixture.componentInstance.selectedValue).toBeNull();

    buttons.find((button) => button.textContent?.includes('Choose'))?.click();
    fixture.detectChanges();
    expect(fixture.componentInstance.selectedValue).toBe('d2');
  });
});
