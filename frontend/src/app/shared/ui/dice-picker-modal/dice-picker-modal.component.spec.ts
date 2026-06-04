import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { DicePickerModalComponent } from './dice-picker-modal.component';
import { ObjectGridComponent } from '../object-grid/object-grid.component';

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
    { id: 'd3', rarity: 'epic', sides: 10, affixes: [] },
    { id: 'd4', rarity: 'common', sides: 6, affixes: [] },
    { id: 'd5', rarity: 'uncommon', sides: 4, affixes: [] },
    { id: 'd6', rarity: 'rare', sides: 6, affixes: [] },
    { id: 'd7', rarity: 'legendary', sides: 10, affixes: [] },
    { id: 'd8', rarity: 'common', sides: 8, affixes: [] },
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
    buttons.find((button) => button.textContent?.includes('None'))?.click();
    fixture.detectChanges();
    expect(fixture.componentInstance.selectedValue).toBeNull();

    buttons.find((button) => button.textContent?.includes('Choose'))?.click();
    fixture.detectChanges();
    expect(fixture.componentInstance.selectedValue).toBe('d2');
  });

  it('filters by size and rarity and sorts displayed dice', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const modal = fixture.debugElement.query(By.directive(DicePickerModalComponent)).componentInstance as DicePickerModalComponent;

    expect(modal.filteredDice().map((die) => die.id)).toEqual(['d2', 'd5', 'd4', 'd6', 'd8', 'd1', 'd3', 'd7']);

    modal.updateSize('8');
    fixture.detectChanges();
    expect(modal.filteredDice().map((die) => die.id)).toEqual(['d8', 'd1']);

    modal.updateSize('');
    modal.updateRarity('epic');
    fixture.detectChanges();
    expect(modal.filteredDice().map((die) => die.id)).toEqual(['d3']);

    modal.updateRarity('');
    modal.updateSort('rarity-desc');
    fixture.detectChanges();
    expect(modal.filteredDice().map((die) => die.id)).toEqual(['d7', 'd3', 'd6', 'd1', 'd5', 'd2', 'd4', 'd8']);
  });

  it('keeps the none tile visible while paging dice in groups of seven', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const grid = fixture.debugElement.query(By.directive(ObjectGridComponent)).componentInstance as ObjectGridComponent;
    const host: HTMLElement = fixture.nativeElement;

    expect(grid.objectsPerPage()).toBe(7);
    expect(grid.totalPages()).toBe(2);
    expect(grid.pagedObjects().length).toBe(7);
    expect(host.textContent).toContain('None');

    grid.nextPage();
    fixture.detectChanges();

    expect(grid.currentPage()).toBe(2);
    expect((grid.pagedObjects() as Array<{ id: string }>).map((die) => die.id)).toEqual(['d7']);
    expect(host.textContent).toContain('None');
  });
});
