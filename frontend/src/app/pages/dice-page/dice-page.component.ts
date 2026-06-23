import { TitleCasePipe } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { DiceRecord } from '../../core/models/api.models';
import { DiceService } from '../../core/services/dice/dice.service';
import { SessionService } from '../../core/services/session/session.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import {
  buildDiceRarityOptions,
  buildDiceSizeOptions,
  DiceEquipFilter,
  DiceSortOption,
  filterAndSortDice,
} from '../../shared/ui/dice-display/dice-display.utils';
import { DiceGridObjectComponent } from '../../shared/ui/dice-grid-object/dice-grid-object.component';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { ObjectGridComponent } from '../../shared/ui/object-grid/object-grid.component';

@Component({
  selector: 'app-dice-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, PageFrameComponent, FormsModule, ObjectGridComponent, RouterLink, TitleCasePipe],
  templateUrl: './dice-page.component.html',
  styleUrl: './dice-page.component.scss',
})
export class DicePageComponent {
  private readonly diceService = inject(DiceService);
  private readonly sessionService = inject(SessionService);

  readonly profileData = this.sessionService.profileData;
  readonly dice = computed(() => this.sessionService.dice());
  readonly busyDiceId = signal<string | null>(null);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly diceObjectComponent = DiceGridObjectComponent;
  readonly selectedSize = signal<number | null>(null);
  readonly selectedRarity = signal<string | null>(null);
  readonly selectedEquipFilter = signal<DiceEquipFilter>('all');
  readonly selectedSort = signal<DiceSortOption>('size-asc');
  readonly sizeOptions = computed(() => buildDiceSizeOptions(this.dice()));
  readonly rarityOptions = computed(() => buildDiceRarityOptions(this.dice()));
  readonly filteredDice = computed(() =>
    filterAndSortDice(this.dice(), {
      selectedSize: this.selectedSize(),
      selectedRarity: this.selectedRarity(),
      equipFilter: this.selectedEquipFilter(),
      sort: this.selectedSort(),
      isEquipped: (diceId) => this.isEquippedAnywhere(diceId),
    }),
  );

  isEquippedAnywhere(diceId: string): boolean {
    return this.sessionService
      .units()
      .some((unit) => (unit.ability_dice ?? []).some((die) => die.dice_instance_id === diceId));
  }

  equippedUnit(diceId: string): { id: string; name: string } | null {
    const unit = this.sessionService
      .units()
      .find((entry) => (entry.ability_dice ?? []).some((die) => die.dice_instance_id === diceId));
    return unit ? { id: unit.id, name: unit.name } : null;
  }

  async sellDice(die: DiceRecord): Promise<void> {
    this.busyDiceId.set(die.id);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.diceService.sellDice(die.id);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set(`Sold die for ${response.data.sell_value}.`);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to sell die.');
    } finally {
      this.busyDiceId.set(null);
    }
  }

  updateSize(value: string): void {
    this.selectedSize.set(value ? Number(value) : null);
  }

  updateRarity(value: string): void {
    this.selectedRarity.set(value || null);
  }

  updateEquipFilter(value: DiceEquipFilter): void {
    this.selectedEquipFilter.set(value);
  }

  updateSort(value: DiceSortOption): void {
    this.selectedSort.set(value);
  }
}


