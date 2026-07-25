import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { UnitRecord } from '../../core/models/api.models';
import { SessionService } from '../../core/services/session/session.service';
import { SquadService } from '../../core/services/squad/squad.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { UnitBarComponent } from '../../shared/ui/unit-bar/unit-bar.component';
import { formatTier, formatUnitKinLabel } from '../../shared/utils/unit-formatters';

@Component({
  selector: 'app-warband-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, PageFrameComponent, RouterLink, FormsModule, UnitBarComponent],
  templateUrl: './warband-page.component.html',
  styleUrl: './warband-page.component.scss',
})
export class WarbandPageComponent {
  private readonly sessionService = inject(SessionService);
  private readonly squadService = inject(SquadService);
  private readonly router = inject(Router);

  readonly profile = this.sessionService.profile;
  readonly squads = this.sessionService.squads;
  readonly units = this.sessionService.units;
  readonly activeRun = computed(() => this.sessionService.profileData()?.active_run ?? null);
  readonly activeSquad = this.sessionService.activeSquad;
  readonly selectedUnitType = signal<string | null>(null);
  readonly selectedKin = signal<string | null>(null);
  readonly excludedUnitTiers = signal<number[]>([]);
  readonly selectedLevelMin = signal<number | null>(null);
  readonly selectedLevelMax = signal<number | null>(null);
  readonly selectedUnitSort = signal<'name-asc' | 'level-desc' | 'tier-desc'>('level-desc');
  readonly isSaving = signal(false);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly availableUnitTypes = computed(() =>
    [...new Set(this.units().map((unit) => this.unitTypeLabel(unit)).filter((label) => label.length > 0))].sort((a, b) =>
      a.localeCompare(b),
    ),
  );
  readonly availableUnitTiers = computed(() =>
    [...new Set(this.units().map((unit) => unit.tier).filter((tier): tier is number => typeof tier === 'number' && tier > 0))].sort(
      (a, b) => a - b,
    ),
  );
  readonly availableKinLabels = computed(() =>
    [...new Set(this.units().map((unit) => this.kinLabel(unit)).filter((label) => label.length > 0))].sort((a, b) =>
      a.localeCompare(b),
    ),
  );
  readonly selectedUnitTiers = computed(() => {
    const excludedTiers = new Set(this.excludedUnitTiers());
    return this.availableUnitTiers().filter((tier) => !excludedTiers.has(tier));
  });
  readonly availableUnitLevels = computed(() => {
    const maxLevel = Math.max(
      1,
      ...this.units().map((unit) => (typeof unit.level === 'number' && unit.level > 0 ? unit.level : 1)),
    );

    return Array.from({ length: maxLevel }, (_, index) => index + 1);
  });
  readonly filteredUnits = computed(() => {
    const selectedType = this.selectedUnitType();
    const selectedKin = this.selectedKin();
    const excludedTiers = new Set(this.excludedUnitTiers());
    const selectedLevelMin = this.selectedLevelMin();
    const selectedLevelMax = this.selectedLevelMax();
    const filtered = this.units().filter((unit) => {
      if (selectedType && this.unitTypeLabel(unit) !== selectedType) {
        return false;
      }

      if (selectedKin && this.kinLabel(unit) !== selectedKin) {
        return false;
      }

      if (excludedTiers.has(unit.tier ?? 0)) {
        return false;
      }

      const level = typeof unit.level === 'number' && unit.level > 0 ? unit.level : 1;
      if (selectedLevelMin !== null && level < selectedLevelMin) {
        return false;
      }

      if (selectedLevelMax !== null && level > selectedLevelMax) {
        return false;
      }

      return true;
    });

    return [...filtered].sort((left, right) => {
      switch (this.selectedUnitSort()) {
        case 'name-asc':
          return left.name.localeCompare(right.name);
        case 'tier-desc':
          return (right.tier ?? 0) - (left.tier ?? 0) || right.name.localeCompare(left.name);
        case 'level-desc':
        default:
          return (right.level ?? 0) - (left.level ?? 0) || left.name.localeCompare(right.name);
      }
    });
  });
  readonly sortedSquads = computed(() =>
    [...this.squads()].sort((left, right) => {
      if (left.is_active === right.is_active) {
        return left.name.localeCompare(right.name);
      }

      return left.is_active ? -1 : 1;
    }),
  );
  isSquadLocked(teamId: string): boolean {
    return !!this.activeRun() && this.activeSquad()?.id === teamId;
  }

  async createSquad(): Promise<void> {
    this.error.set(null);
    this.message.set(null);
    this.isSaving.set(true);

    try {
      const response = await this.squadService.createTeam(`New Squad ${this.squads().length + 1}`, false);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set('Squad created.');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to create squad.');
    } finally {
      this.isSaving.set(false);
    }
  }

  async activateSquad(teamId: string): Promise<void> {
    if (this.activeRun()) {
      return;
    }

    this.error.set(null);
    this.message.set(null);
    this.isSaving.set(true);

    try {
      const response = await this.squadService.activateTeam(teamId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set('Active squad updated.');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to activate squad.');
    } finally {
      this.isSaving.set(false);
    }
  }

  updateUnitType(value: string): void {
    this.selectedUnitType.set(value || null);
  }

  updateKin(value: string): void {
    this.selectedKin.set(value || null);
  }

  toggleUnitTier(tier: number): void {
    const excludedTiers = new Set(this.excludedUnitTiers());
    if (excludedTiers.has(tier)) {
      excludedTiers.delete(tier);
    } else {
      excludedTiers.add(tier);
    }

    this.excludedUnitTiers.set([...excludedTiers].sort((a, b) => a - b));
  }

  tierFilterLabel(tier: number): string {
    return formatTier(tier) ?? `${tier}`;
  }

  tierFilterClass(tier: number): string {
    const tierNumber = Math.max(1, Math.min(5, Math.round(tier)));
    const selectedClass = this.isUnitTierSelected(tier) ? '' : ' dg-tier-indicator--muted';
    return `warband-tier-filter__mark dg-tier-indicator dg-tier-indicator--${tierNumber}${selectedClass}`;
  }

  isUnitTierSelected(tier: number): boolean {
    return !this.excludedUnitTiers().includes(tier);
  }

  updateLevelMin(value: string): void {
    const nextValue = value ? Number(value) : null;
    const currentMax = this.selectedLevelMax();
    this.selectedLevelMin.set(nextValue);

    if (nextValue !== null && currentMax !== null && nextValue > currentMax) {
      this.selectedLevelMax.set(nextValue);
    }
  }

  updateLevelMax(value: string): void {
    const nextValue = value ? Number(value) : null;
    const currentMin = this.selectedLevelMin();
    this.selectedLevelMax.set(nextValue);

    if (nextValue !== null && currentMin !== null && nextValue < currentMin) {
      this.selectedLevelMin.set(nextValue);
    }
  }

  updateUnitSort(value: 'name-asc' | 'level-desc' | 'tier-desc'): void {
    this.selectedUnitSort.set(value);
  }

  clearUnitFilters(): void {
    this.selectedUnitType.set(null);
    this.selectedKin.set(null);
    this.excludedUnitTiers.set([]);
    this.selectedLevelMin.set(null);
    this.selectedLevelMax.set(null);
    this.selectedUnitSort.set('level-desc');
  }

  async openUnit(unitId: string): Promise<void> {
    await this.router.navigate(['/warband/units', unitId]);
  }

  unitPositionLabel(unit: UnitRecord): string | null {
    const activeSquad = this.activeSquad();
    const assignment = activeSquad?.formation?.find((entry) => entry.unit_instance_id === unit.id);
    return assignment ? `Slot ${assignment.cell}` : null;
  }

  private unitTypeLabel(unit: { unit_type_name?: string; unit_type_slug?: string }): string {
    return (unit.unit_type_name || unit.unit_type_slug || 'Unknown').trim();
  }

  private kinLabel(unit: Pick<UnitRecord, 'kin_name' | 'kin_slug' | 'splice_variant_name' | 'splice_variant_slug'>): string {
    return formatUnitKinLabel(unit);
  }
}

