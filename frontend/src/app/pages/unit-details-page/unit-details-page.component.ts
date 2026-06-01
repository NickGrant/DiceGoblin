import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { DiceService } from '../../core/services/dice/dice.service';
import { SessionService } from '../../core/services/session/session.service';
import { UnitService } from '../../core/services/unit/unit.service';
import { PromotionOptionRecord, UnitRecord } from '../../core/models/api.models';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DgPageFrameComponent } from '../../shared/ui/dg-page-frame/dg-page-frame.component';

@Component({
  selector: 'app-unit-details-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, DgPageFrameComponent, FormsModule, RouterLink],
  templateUrl: './unit-details-page.component.html',
  styleUrl: './unit-details-page.component.scss',
})
export class UnitDetailsPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly sessionService = inject(SessionService);
  private readonly unitService = inject(UnitService);
  private readonly diceService = inject(DiceService);

  readonly unitId = this.route.snapshot.paramMap.get('unitId') ?? '';
  readonly runId = this.route.snapshot.queryParamMap.get('runId') ?? undefined;
  readonly nodeId = this.route.snapshot.queryParamMap.get('nodeId') ?? undefined;
  readonly unit = computed<UnitRecord | null>(
    () => this.sessionService.units().find((entry) => entry.id === this.unitId) ?? null,
  );
  readonly units = this.sessionService.units;
  readonly promotionOptions = signal<PromotionOptionRecord[]>([]);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly busy = signal(false);
  readonly selectedSecondaries = signal<string[]>([]);
  readonly selectedDestination = signal<string>('');

  renameValue = '';

  constructor() {
    this.renameValue = this.unit()?.name ?? '';
    void this.loadPromotionOptions();
  }

  async loadPromotionOptions(): Promise<void> {
    if (!this.unit()) {
      return;
    }

    try {
      const response = await this.unitService.getPromotionOptions(this.unitId);
      if (response.ok) {
        this.promotionOptions.set(response.data.options);
        this.selectedDestination.set(response.data.options[0]?.target_unit_type_id ?? '');
      }
    } catch {
      // Keep details screen usable even if promotion lookup fails.
    }
  }

  toggleSecondary(unitId: string): void {
    const next = new Set(this.selectedSecondaries());
    if (next.has(unitId)) {
      next.delete(unitId);
    } else if (next.size < 2) {
      next.add(unitId);
    }
    this.selectedSecondaries.set(Array.from(next));
  }

  async renameUnit(): Promise<void> {
    if (!this.renameValue.trim()) {
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.unitService.renameUnit(this.unitId, this.renameValue.trim());
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set('Unit renamed.');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to rename unit.');
    } finally {
      this.busy.set(false);
    }
  }

  async promoteUnit(): Promise<void> {
    if (this.selectedSecondaries().length !== 2) {
      this.error.set('Choose two units to consume.');
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.unitService.promoteUnit(
        this.unitId,
        [this.selectedSecondaries()[0], this.selectedSecondaries()[1]],
        this.selectedDestination() || undefined,
        { runId: this.runId, nodeId: this.nodeId },
      );
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.selectedSecondaries.set([]);
      this.message.set('Promotion complete.');
      await this.loadPromotionOptions();
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to promote unit.');
    } finally {
      this.busy.set(false);
    }
  }

  async unequipDice(diceId: string): Promise<void> {
    this.busy.set(true);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.diceService.unequipDice(this.unitId, diceId, {
        runId: this.runId,
        nodeId: this.nodeId,
      });
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set('Die unequipped.');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to unequip die.');
    } finally {
      this.busy.set(false);
    }
  }
}

