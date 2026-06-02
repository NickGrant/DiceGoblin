import { TitleCasePipe } from '@angular/common';
import { Component, computed, input, output } from '@angular/core';
import { DiceRecord } from '../../../core/models/api.models';
import { DgCommandBtnDirective } from '../dg-command-btn/dg-command-btn.directive';
import { DiceGridObjectComponent } from '../dice-grid-object/dice-grid-object.component';
import { ObjectGridComponent } from '../object-grid/object-grid.component';

@Component({
  selector: 'dg-dice-picker-modal',
  standalone: true,
  imports: [DgCommandBtnDirective, ObjectGridComponent, TitleCasePipe],
  templateUrl: './dice-picker-modal.component.html',
  styleUrl: './dice-picker-modal.component.scss',
})
export class DicePickerModalComponent {
  readonly open = input(false);
  readonly dice = input<readonly DiceRecord[]>([]);
  readonly slotLabel = input('Slot');
  readonly selectedDiceId = input<string | null>(null);
  readonly busy = input(false);

  readonly dismissed = output<void>();
  readonly selected = output<string | null>();

  readonly diceObjectComponent = DiceGridObjectComponent;
  readonly selectedDie = computed(
    () => this.dice().find((die) => die.id === this.selectedDiceId()) ?? null,
  );

  close(): void {
    this.dismissed.emit();
  }

  choose(diceId: string | null): void {
    this.selected.emit(diceId);
  }
}
