import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { RestOpenData } from '../../core/models/api.models';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { RunUnitFormationGridComponent } from '../../shared/ui/run-unit-formation-grid/run-unit-formation-grid.component';

const FORMATION_CELLS = ['A1', 'A2', 'A3', 'B1', 'B2', 'B3', 'C1', 'C2', 'C3'] as const;

@Component({
  selector: 'app-run-rest-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, PageFrameComponent, RunUnitFormationGridComponent],
  templateUrl: './run-rest-page.component.html',
  styleUrl: './run-rest-page.component.scss',
})
export class RunRestPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly runService = inject(RunService);
  private readonly sessionService = inject(SessionService);

  readonly nodeId = this.route.snapshot.paramMap.get('nodeId') ?? '';
  readonly runId = signal<string | null>(null);
  readonly restData = signal<RestOpenData | null>(null);
  readonly loading = signal(true);
  readonly busy = signal(false);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly activeSquad = this.sessionService.activeSquad;
  readonly restingUnits = computed(() => {
    const unitsById = new Map(this.sessionService.units().map((unit) => [unit.id, unit]));
    return (this.restData()?.run_unit_state ?? []).map((state) => ({
      ...state,
      unit: unitsById.get(state.unit_instance_id) ?? null,
      currentHp: state.current_hp ?? state.hp ?? 0,
      maxHp: unitsById.get(state.unit_instance_id)?.max_hp ?? state.current_hp ?? state.hp ?? 0,
      defeated: state.is_defeated || (state.current_hp ?? state.hp ?? 0) <= 0,
    }));
  });
  readonly restingUnitById = computed(
    () => new Map(this.restingUnits().map((entry) => [entry.unit_instance_id, entry])),
  );
  readonly formationGrid = computed(() => {
    const formationAssignments = new Map(
      (this.activeSquad()?.formation ?? []).map((entry) => [entry.cell, entry.unit_instance_id]),
    );

    return FORMATION_CELLS.map((cell) => {
      const unitId = formationAssignments.get(cell) ?? null;
      return {
        cell,
        unitId,
        entry: unitId ? this.restingUnitById().get(unitId) ?? null : null,
      };
    });
  });

  constructor() {
    void this.bootstrap();
  }

  async bootstrap(): Promise<void> {
    try {
      const current = await this.runService.getCurrentRun();
      if (!current.ok || !current.data.run) {
        this.error.set(current.ok ? 'No active run.' : current.error.message);
        return;
      }
      this.runId.set(current.data.run.run_id);
      await this.loadRest();
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to open rest.');
    } finally {
      this.loading.set(false);
    }
  }

  async loadRest(): Promise<void> {
    if (!this.runId()) {
      return;
    }

    const response = await this.runService.openRest(this.runId()!, this.nodeId);
    if (!response.ok) {
      this.error.set(response.error.message);
      return;
    }
    this.restData.set(response.data);
  }

  async finalizeRest(): Promise<void> {
    if (!this.runId()) {
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.runService.finalizeRest(this.runId()!, this.nodeId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      await this.router.navigateByUrl('/run/map');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to rest.');
    } finally {
      this.busy.set(false);
    }
  }
}
