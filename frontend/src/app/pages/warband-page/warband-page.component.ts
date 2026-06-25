import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { UnitRecord } from '../../core/models/api.models';
import { SessionService } from '../../core/services/session/session.service';
import { SquadService } from '../../core/services/squad/squad.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { UnitGridObjectComponent } from '../../shared/ui/unit-grid-object/unit-grid-object.component';

@Component({
  selector: 'app-warband-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, PageFrameComponent, RouterLink, UnitGridObjectComponent, FormsModule],
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
  readonly selectedUnitSort = signal<'name-asc' | 'level-desc' | 'tier-desc'>('level-desc');
  readonly hoveredUnitId = signal<string | null>(null);
  readonly isSaving = signal(false);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly availableUnitTypes = computed(() =>
    [...new Set(this.units().map((unit) => this.unitTypeLabel(unit)).filter((label) => label.length > 0))].sort((a, b) =>
      a.localeCompare(b),
    ),
  );
  readonly filteredUnits = computed(() => {
    const selectedType = this.selectedUnitType();
    const filtered = selectedType
      ? this.units().filter((unit) => this.unitTypeLabel(unit) === selectedType)
      : this.units();

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
  readonly inspectedUnit = computed<UnitRecord | null>(() => {
    const filteredUnits = this.filteredUnits();
    if (!filteredUnits.length) {
      return null;
    }

    const hoveredUnitId = this.hoveredUnitId();
    if (hoveredUnitId) {
      const hoveredUnit = filteredUnits.find((unit) => unit.id === hoveredUnitId);
      if (hoveredUnit) {
        return hoveredUnit;
      }
    }

    return filteredUnits[0] ?? null;
  });

  isSquadLocked(teamId: string): boolean {
    return !!this.activeRun() && this.activeSquad()?.id === teamId;
  }

  squadLockMessage(teamId: string): string | null {
    return this.isSquadLocked(teamId) ? 'Locked while this squad is committed to the active run.' : null;
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

  updateUnitSort(value: 'name-asc' | 'level-desc' | 'tier-desc'): void {
    this.selectedUnitSort.set(value);
  }

  clearUnitFilters(): void {
    this.selectedUnitType.set(null);
    this.selectedUnitSort.set('level-desc');
  }

  previewUnit(unitId: string): void {
    this.hoveredUnitId.set(unitId);
  }

  isInspectingUnit(unitId: string): boolean {
    return this.inspectedUnit()?.id === unitId;
  }

  async openUnit(unitId: string): Promise<void> {
    await this.router.navigate(['/warband/units', unitId]);
  }

  unitCardArtUrl(unit: UnitRecord): string | null {
    const nameSlug = this.normalizeCardArtSlug(unit.unit_type_name);
    if (nameSlug) {
      return `/assets/ui/cardboard-units/${nameSlug}.png`;
    }

    const slug = this.normalizeCardArtSlug(unit.unit_type_slug);
    if (slug) {
      return `/assets/ui/cardboard-units/${slug}.png`;
    }

    return null;
  }

  private unitTypeLabel(unit: { unit_type_name?: string; unit_type_slug?: string }): string {
    return (unit.unit_type_name || unit.unit_type_slug || 'Unknown').trim();
  }

  private normalizeCardArtSlug(value: string | null | undefined): string | null {
    const normalized = (value ?? '').trim().toLowerCase();
    if (!normalized.length) {
      return null;
    }

    const knownSlugMap: Record<string, string> = {
      frontline_bruiser_t1: 'bruiser',
      frontline_bruiser_t2: 'enforcer',
      frontline_pit_fighter_t2: 'pit-fighter',
      frontline_guardian_t1: 'guardian',
      frontline_guardian_t2: 'bulwark',
      frontline_shieldbreaker_t2: 'shieldbreaker',
      backline_marksman_t1: 'marksman',
      backline_marksman_t2: 'deadeye',
      backline_trapper_t2: 'trapper',
      support_banner_t1: 'bannerbearer',
      support_banner_t2: 'warcaller',
      support_mascot_t2: 'mascot',
      control_saboteur_t1: 'saboteur',
      control_saboteur_t2: 'trickshot',
      control_plaguehand_t2: 'plaguehand',
    };

    if (knownSlugMap[normalized]) {
      return knownSlugMap[normalized];
    }

    return normalized
      .replace(/^goblin\s+/, '')
      .replace(/\s+/g, '-')
      .replace(/banner\b/g, 'bannerbearer');
  }
}

