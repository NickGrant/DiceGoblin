import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SessionService } from '../../core/services/session/session.service';
import { SquadService } from '../../core/services/squad/squad.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { HorizontalRailDirective } from '../../shared/ui/horizontal-rail/horizontal-rail.directive';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { UnitGridObjectComponent } from '../../shared/ui/unit-grid-object/unit-grid-object.component';

@Component({
  selector: 'app-warband-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, HorizontalRailDirective, PageFrameComponent, RouterLink, UnitGridObjectComponent],
  templateUrl: './warband-page.component.html',
  styleUrl: './warband-page.component.scss',
})
export class WarbandPageComponent {
  private readonly sessionService = inject(SessionService);
  private readonly squadService = inject(SquadService);

  readonly profile = this.sessionService.profile;
  readonly squads = this.sessionService.squads;
  readonly units = this.sessionService.units;
  readonly activeRun = computed(() => this.sessionService.profileData()?.active_run ?? null);
  readonly activeSquad = this.sessionService.activeSquad;
  readonly selectedUnitTypes = signal<string[]>([]);
  readonly isSaving = signal(false);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly availableUnitTypes = computed(() =>
    [...new Set(this.units().map((unit) => this.unitTypeLabel(unit)).filter((label) => label.length > 0))].sort((a, b) =>
      a.localeCompare(b),
    ),
  );
  readonly filteredUnits = computed(() => {
    const selected = this.selectedUnitTypes();
    if (!selected.length) {
      return this.units();
    }

    return this.units().filter((unit) => selected.includes(this.unitTypeLabel(unit)));
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

  isUnitTypeSelected(unitType: string): boolean {
    return this.selectedUnitTypes().includes(unitType);
  }

  toggleUnitType(unitType: string): void {
    this.selectedUnitTypes.update((selected) =>
      selected.includes(unitType) ? selected.filter((value) => value !== unitType) : [...selected, unitType],
    );
  }

  clearUnitTypeFilters(): void {
    this.selectedUnitTypes.set([]);
  }

  private unitTypeLabel(unit: { unit_type_name?: string; unit_type_slug?: string }): string {
    return (unit.unit_type_name || unit.unit_type_slug || 'Unknown').trim();
  }
}

