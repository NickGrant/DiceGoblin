import { NgFor, NgIf } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { DiceRecord } from '../core/models/api.models';
import { DiceService } from '../core/services/dice.service';
import { SessionService } from '../core/services/session.service';

@Component({
  selector: 'app-dice-page',
  standalone: true,
  imports: [NgFor, NgIf],
  templateUrl: './dice-page.component.html',
  styleUrl: './dice-page.component.scss',
})
export class DicePageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly diceService = inject(DiceService);
  private readonly sessionService = inject(SessionService);

  readonly profileData = this.sessionService.profileData;
  readonly unitId = computed(() => this.route.snapshot.queryParamMap.get('unitId'));
  readonly mode = computed(() => this.route.snapshot.queryParamMap.get('mode') ?? 'inventory');
  readonly runId = computed(() => this.route.snapshot.queryParamMap.get('runId') ?? undefined);
  readonly nodeId = computed(() => this.route.snapshot.queryParamMap.get('nodeId') ?? undefined);
  readonly selectedUnit = computed(
    () => this.sessionService.units().find((unit) => unit.id === this.unitId()) ?? null,
  );
  readonly dice = computed(() => this.sessionService.dice());
  readonly busyDiceId = signal<string | null>(null);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);

  isEquippedAnywhere(diceId: string): boolean {
    return this.sessionService
      .units()
      .some((unit) => (unit.equipped_dice ?? []).some((die) => die.dice_instance_id === diceId));
  }

  isEquippedToSelectedUnit(diceId: string): boolean {
    return (this.selectedUnit()?.equipped_dice ?? []).some((die) => die.dice_instance_id === diceId);
  }

  equippedUnitName(diceId: string): string | null {
    const unit = this.sessionService
      .units()
      .find((entry) => (entry.equipped_dice ?? []).some((die) => die.dice_instance_id === diceId));
    return unit?.name ?? null;
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

  async toggleEquip(die: DiceRecord): Promise<void> {
    const selectedUnit = this.selectedUnit();
    if (!selectedUnit) {
      return;
    }

    const wasEquipped = this.isEquippedToSelectedUnit(die.id);
    this.busyDiceId.set(die.id);
    this.error.set(null);
    this.message.set(null);
    const context = {
      runId: this.runId(),
      nodeId: this.nodeId(),
    };

    try {
      const response = wasEquipped
        ? await this.diceService.unequipDice(selectedUnit.id, die.id, context)
        : await this.diceService.equipDice(selectedUnit.id, die.id, context);

      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.message.set(wasEquipped ? 'Die unequipped.' : 'Die equipped.');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to update equipment.');
    } finally {
      this.busyDiceId.set(null);
    }
  }
}
