import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { DiceRecord } from '../../core/models/api.models';
import { DiceService } from '../../core/services/dice/dice.service';
import { SessionService } from '../../core/services/session/session.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DiceGridObjectComponent } from '../../shared/ui/dice-grid-object/dice-grid-object.component';
import { DgPageFrameComponent } from '../../shared/ui/dg-page-frame/dg-page-frame.component';
import { ObjectGridComponent } from '../../shared/ui/object-grid/object-grid.component';

@Component({
  selector: 'app-dice-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, DgPageFrameComponent, ObjectGridComponent, RouterLink],
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
}


