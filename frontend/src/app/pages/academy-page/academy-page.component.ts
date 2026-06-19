import { Component, computed, effect, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { AcademyUnitUnlockItem, PromotionOptionRecord, UnitRecord } from '../../core/models/api.models';
import { AcademyService } from '../../core/services/academy/academy.service';
import { SessionService } from '../../core/services/session/session.service';
import { UnitService } from '../../core/services/unit/unit.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DgPageFrameComponent } from '../../shared/ui/dg-page-frame/dg-page-frame.component';

@Component({
  selector: 'app-academy-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, DgPageFrameComponent, FormsModule],
  templateUrl: './academy-page.component.html',
  styleUrl: './academy-page.component.scss',
})
export class AcademyPageComponent {
  private static readonly UNIT_UNLOCK_DESCRIPTIONS: Record<string, string> = {
    frontline_bruiser_t1: 'A durable frontliner built to absorb hits and keep pressure on the enemy line.',
    frontline_guardian_t1: 'A shield-first defender that trades damage for stronger protection and staying power.',
    backline_marksman_t1: 'A ranged damage dealer that thrives from the back row with steady offensive pressure.',
    support_banner_t1: 'A support specialist that reinforces nearby allies and helps the squad endure longer fights.',
    control_saboteur_t1: 'A disruptive skirmisher focused on interference, control, and breaking enemy momentum.',
  };

  private readonly route = inject(ActivatedRoute);
  private readonly academyService = inject(AcademyService);
  private readonly sessionService = inject(SessionService);
  private readonly unitService = inject(UnitService);

  readonly units = this.sessionService.units;
  readonly profile = this.sessionService.profile;
  readonly activeSquad = this.sessionService.activeSquad;
  readonly hasActiveRun = this.sessionService.hasActiveRun;
  readonly selectedUnitId = signal(this.route.snapshot.queryParamMap.get('unitId') ?? '');
  readonly promotionOptions = signal<PromotionOptionRecord[]>([]);
  readonly unitUnlockCatalog = signal<AcademyUnitUnlockItem[]>([]);
  readonly selectedSecondaries = signal<string[]>([]);
  readonly selectedDestination = signal<string>('');
  readonly busy = signal(false);
  readonly unlockingUnitTypeSlug = signal<string | null>(null);
  readonly loadingOptions = signal(false);
  readonly loadingUnlocks = signal(false);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);

  readonly selectedUnit = computed<UnitRecord | null>(
    () => this.units().find((entry) => entry.id === this.selectedUnitId()) ?? null,
  );
  readonly unitLocked = computed(() => {
    const unit = this.selectedUnit();
    if (!unit) {
      return false;
    }

    return !!unit.locked || (this.hasActiveRun() && !!this.activeSquad()?.unit_ids?.includes(unit.id));
  });
  readonly promotableUnits = computed(() => {
    const units = this.units();
    return units
      .filter((unit) => {
        if (!unit.promotion_eligible) {
          return false;
        }

        const candidateCount = units.filter((candidate) => {
          if (candidate.id === unit.id) {
            return false;
          }

          return (
            candidate.unit_type_id === unit.unit_type_id &&
            candidate.tier === unit.tier &&
            !!candidate.promotion_eligible
          );
        }).length;

        return candidateCount >= 2;
      })
      .slice()
      .sort((left, right) => left.name.localeCompare(right.name));
  });
  readonly eligiblePromotionCandidates = computed(() => {
    const unit = this.selectedUnit();
    if (!unit) {
      return [];
    }

    return this.units().filter((candidate) => {
      if (candidate.id === unit.id) {
        return false;
      }

      return (
        candidate.unit_type_id === unit.unit_type_id &&
        candidate.tier === unit.tier &&
        !!candidate.promotion_eligible
      );
    });
  });
  readonly availableUnitUnlocks = computed(() => this.unitUnlockCatalog().filter((entry) => !entry.is_unlocked));

  constructor() {
    void this.loadUnitUnlockCatalog();

    effect(() => {
      const units = this.promotableUnits();
      if (!units.length) {
        this.selectedUnitId.set('');
        return;
      }

      const selectedId = this.selectedUnitId();
      if (selectedId && units.some((unit) => unit.id === selectedId)) {
        return;
      }

      this.selectedUnitId.set('');
    });

    effect(() => {
      const unitId = this.selectedUnitId();
      this.selectedSecondaries.set([]);
      this.selectedDestination.set('');
      this.promotionOptions.set([]);

      if (!unitId) {
        return;
      }

      void this.loadPromotionOptions(unitId);
    });
  }

  async loadPromotionOptions(unitId: string): Promise<void> {
    this.loadingOptions.set(true);
    this.error.set(null);
    try {
      const response = await this.unitService.getPromotionOptions(unitId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      const options = response.data.options ?? [];
      this.promotionOptions.set(options);
      this.selectedDestination.set('');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load promotion options.');
    } finally {
      this.loadingOptions.set(false);
    }
  }

  async loadUnitUnlockCatalog(): Promise<void> {
    this.loadingUnlocks.set(true);
    this.error.set(null);
    try {
      const response = await this.academyService.getCatalog();
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.unitUnlockCatalog.set(response.data.unit_unlocks ?? []);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load unit unlocks.');
    } finally {
      this.loadingUnlocks.set(false);
    }
  }

  toggleSecondary(unitId: string): void {
    if (this.unitLocked()) {
      return;
    }

    const next = new Set(this.selectedSecondaries());
    if (next.has(unitId)) {
      next.delete(unitId);
    } else if (next.size < 2) {
      next.add(unitId);
    }
    this.selectedSecondaries.set(Array.from(next));
  }

  promotionOptionLabel(option: PromotionOptionRecord): string {
    if (option.mode === 'sideways') {
      if (option.target_unit_type_name === option.branch_unit_type_name) {
        return `${option.target_unit_type_name} - sideways`;
      }

      return `${option.target_unit_type_name} - sideways via ${option.branch_unit_type_name}`;
    }

    return `${option.target_unit_type_name} - chain`;
  }

  async promoteUnit(): Promise<void> {
    const unit = this.selectedUnit();
    if (!unit || this.unitLocked()) {
      return;
    }

    if (this.selectedSecondaries().length !== 2) {
      this.error.set('Choose two units to consume.');
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.unitService.promoteUnit(
        unit.id,
        [this.selectedSecondaries()[0], this.selectedSecondaries()[1]],
        this.selectedDestination() || undefined,
      );
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.selectedSecondaries.set([]);
      this.message.set('Promotion complete.');
      await this.loadPromotionOptions(unit.id);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to promote unit.');
    } finally {
      this.busy.set(false);
    }
  }

  async unlockUnitType(unitTypeSlug: string): Promise<void> {
    this.unlockingUnitTypeSlug.set(unitTypeSlug);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.academyService.unlockUnitType(unitTypeSlug);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.message.set('Unit type unlocked.');
      await this.loadUnitUnlockCatalog();
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to unlock unit type.');
    } finally {
      this.unlockingUnitTypeSlug.set(null);
    }
  }

  roleLabel(value: string | null | undefined): string {
    const normalized = (value ?? '').trim().toLowerCase();
    return normalized.length ? normalized.charAt(0).toUpperCase() + normalized.slice(1) : 'Unit';
  }

  unitUnlockDescription(unitTypeSlug: string): string {
    return AcademyPageComponent.UNIT_UNLOCK_DESCRIPTIONS[unitTypeSlug] ?? 'Unlock this unit type for future recruitment opportunities.';
  }

  toRomanNumeral(value: number | null | undefined): string {
    const normalized = Math.max(1, Math.floor(value || 1));
    const numerals: Array<{ value: number; symbol: string }> = [
      { value: 1000, symbol: 'M' },
      { value: 900, symbol: 'CM' },
      { value: 500, symbol: 'D' },
      { value: 400, symbol: 'CD' },
      { value: 100, symbol: 'C' },
      { value: 90, symbol: 'XC' },
      { value: 50, symbol: 'L' },
      { value: 40, symbol: 'XL' },
      { value: 10, symbol: 'X' },
      { value: 9, symbol: 'IX' },
      { value: 5, symbol: 'V' },
      { value: 4, symbol: 'IV' },
      { value: 1, symbol: 'I' },
    ];

    let remaining = normalized;
    let result = '';
    for (const numeral of numerals) {
      while (remaining >= numeral.value) {
        result += numeral.symbol;
        remaining -= numeral.value;
      }
    }

    return result;
  }
}
