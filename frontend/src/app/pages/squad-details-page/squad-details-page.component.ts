import { NgFor, NgIf } from '@angular/common';
import { Component, computed, effect, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { TeamFormationCell } from '../../core/models/api.models';
import { SessionService } from '../../core/services/session/session.service';
import { SquadService } from '../../core/services/squad/squad.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DgPageFrameComponent } from '../../shared/ui/dg-page-frame/dg-page-frame.component';

const FORMATION_CELLS = ['A1', 'A2', 'A3', 'B1', 'B2', 'B3', 'C1', 'C2', 'C3'];

@Component({
  selector: 'app-squad-details-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, DgPageFrameComponent, FormsModule, NgFor, NgIf],
  templateUrl: './squad-details-page.component.html',
  styleUrl: './squad-details-page.component.scss',
})
export class SquadDetailsPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly sessionService = inject(SessionService);
  private readonly squadService = inject(SquadService);
  private readonly hydratedSquadId = signal<string | null>(null);

  readonly squadId = this.route.snapshot.paramMap.get('squadId') ?? '';
  readonly squad = computed(() => this.sessionService.squads().find((team) => team.id === this.squadId) ?? null);
  readonly availableUnits = this.sessionService.units;
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);

  name = this.squad()?.name ?? '';
  selectedUnitIds = new Set(this.squad()?.unit_ids ?? []);
  formationAssignments = new Map<string, string | null>(
    FORMATION_CELLS.map((cell) => [
      cell,
      this.squad()?.formation.find((entry) => entry.cell === cell)?.unit_instance_id ?? null,
    ]),
  );

  constructor() {
    effect(() => {
      const squad = this.squad();
      if (!squad || this.hydratedSquadId() === squad.id) {
        return;
      }

      this.name = squad.name;
      this.selectedUnitIds = new Set(squad.unit_ids);
      this.formationAssignments = new Map(
        FORMATION_CELLS.map((cell) => [
          cell,
          squad.formation.find((entry) => entry.cell === cell)?.unit_instance_id ?? null,
        ]),
      );
      this.hydratedSquadId.set(squad.id);
    });
  }

  toggleUnit(unitId: string): void {
    if (this.selectedUnitIds.has(unitId)) {
      this.selectedUnitIds.delete(unitId);
      this.clearFormationAssignmentsForUnit(unitId);
    } else {
      this.selectedUnitIds.add(unitId);
    }
  }

  setCell(cell: string, value: string): void {
    const nextValue = value && this.selectedUnitIds.has(value) ? value : null;
    this.formationAssignments.set(cell, nextValue);
  }

  formationForSave(): TeamFormationCell[] {
    return FORMATION_CELLS.map((cell) => ({
      cell,
      unit_instance_id: this.formationAssignments.get(cell) ?? null,
    }));
  }

  async save(): Promise<void> {
    if (!this.squad()) {
      return;
    }

    this.saving.set(true);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.squadService.updateTeam(this.squadId, {
        name: this.name.trim() || this.squad()!.name,
        unit_ids: Array.from(this.selectedUnitIds),
        formation: this.formationForSave(),
      });
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set('Squad saved.');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to save squad.');
    } finally {
      this.saving.set(false);
    }
  }

  async activate(): Promise<void> {
    this.saving.set(true);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.squadService.activateTeam(this.squadId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set('Squad activated.');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to activate squad.');
    } finally {
      this.saving.set(false);
    }
  }

  private clearFormationAssignmentsForUnit(unitId: string): void {
    this.formationAssignments.forEach((assignedUnitId, cell) => {
      if (assignedUnitId === unitId) {
        this.formationAssignments.set(cell, null);
      }
    });
  }

  protected readonly formationCells = FORMATION_CELLS;
}

