import { TitleCasePipe } from '@angular/common';
import { Component, computed, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { DiceRecord } from '../../../core/models/api.models';
import {
  buildDiceRarityOptions,
  buildDiceSizeOptions,
  DiceSortOption,
  filterAndSortDice,
} from '../dice-display/dice-display.utils';
import { DgCommandBtnDirective } from '../dg-command-btn/dg-command-btn.directive';
import { DiceGridObjectComponent } from '../dice-grid-object/dice-grid-object.component';
import { ObjectGridComponent } from '../object-grid/object-grid.component';

@Component({
  selector: 'dg-dice-picker-modal',
  standalone: true,
  imports: [DgCommandBtnDirective, FormsModule, ObjectGridComponent, TitleCasePipe],
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
  readonly selectedSize = signal<number | null>(null);
  readonly selectedRarity = signal<string | null>(null);
  readonly selectedSort = signal<DiceSortOption>('size-asc');
  readonly sizeOptions = computed(() => buildDiceSizeOptions(this.dice()));
  readonly rarityOptions = computed(() => buildDiceRarityOptions(this.dice()));
  readonly filteredDice = computed(() =>
    filterAndSortDice(this.dice(), {
      selectedSize: this.selectedSize(),
      selectedRarity: this.selectedRarity(),
      sort: this.selectedSort(),
    }),
  );
  readonly selectedDie = computed(
    () => this.dice().find((die) => die.id === this.selectedDiceId()) ?? null,
  );

  close(): void {
    this.dismissed.emit();
  }

  choose(diceId: string | null): void {
    this.selected.emit(diceId);
  }

  updateSize(value: string): void {
    this.selectedSize.set(value ? Number(value) : null);
  }

  updateRarity(value: string): void {
    this.selectedRarity.set(value || null);
  }

  updateSort(value: DiceSortOption): void {
    this.selectedSort.set(value);
  }
}
