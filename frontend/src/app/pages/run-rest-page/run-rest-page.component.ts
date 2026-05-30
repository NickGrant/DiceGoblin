import { NgFor, NgIf } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TeamFormationCell, RestOpenData } from '../../core/models/api.models';
import { RestService } from '../../core/services/rest/rest.service';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DgPageFrameComponent } from '../../shared/ui/dg-page-frame/dg-page-frame.component';

const REST_FORMATION_CELLS = ['A1', 'A2', 'A3', 'B1', 'B2', 'B3', 'C1', 'C2', 'C3'];

@Component({
  selector: 'app-run-rest-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, DgPageFrameComponent, FormsModule, NgFor, NgIf, RouterLink],
  templateUrl: './run-rest-page.component.html',
  styleUrl: './run-rest-page.component.scss',
})
export class RunRestPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly runService = inject(RunService);
  private readonly restService = inject(RestService);
  private readonly sessionService = inject(SessionService);

  readonly nodeId = this.route.snapshot.paramMap.get('nodeId') ?? '';
  readonly runId = signal<string | null>(null);
  readonly restData = signal<RestOpenData | null>(null);
  readonly loading = signal(true);
  readonly busy = signal(false);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  selectedUnitIds = new Set<string>();
  formationAssignments = new Map<string, string | null>();

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
    this.selectedUnitIds = new Set(response.data.unit_ids);
    this.formationAssignments = new Map(
      REST_FORMATION_CELLS.map((cell) => [
        cell,
        response.data.formation.find((entry) => entry.cell === cell)?.unit_instance_id ?? null,
      ]),
    );
  }

  toggleUnit(unitId: string): void {
    if (this.selectedUnitIds.has(unitId)) {
      this.selectedUnitIds.delete(unitId);
    } else {
      this.selectedUnitIds.add(unitId);
    }
  }

  setCell(cell: string, value: string): void {
    this.formationAssignments.set(cell, value || null);
  }

  formationForSave(): TeamFormationCell[] {
    return REST_FORMATION_CELLS.map((cell) => ({
      cell,
      unit_instance_id: this.formationAssignments.get(cell) ?? null,
    }));
  }

  async saveRestState(): Promise<void> {
    if (!this.runId()) {
      return;
    }
    this.busy.set(true);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.runService.updateRestState(this.runId()!, this.nodeId, {
        unit_ids: Array.from(this.selectedUnitIds),
        formation: this.formationForSave(),
      });
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.restData.set(response.data);
      this.message.set('Rest setup saved.');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to save rest state.');
    } finally {
      this.busy.set(false);
    }
  }

  async buyRestItem(itemType: 'basic_unit' | 'basic_dice'): Promise<void> {
    if (!this.runId()) {
      return;
    }
    this.busy.set(true);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.restService.purchaseStoreItem(this.runId()!, this.nodeId, itemType);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set('Rest purchase complete.');
      await this.sessionService.refreshProfile({ force: true });
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to purchase rest item.');
    } finally {
      this.busy.set(false);
    }
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
      this.error.set(error instanceof Error ? error.message : 'Unable to finalize rest.');
    } finally {
      this.busy.set(false);
    }
  }

  protected readonly units = this.sessionService.units;
  protected readonly cells = REST_FORMATION_CELLS;
}

