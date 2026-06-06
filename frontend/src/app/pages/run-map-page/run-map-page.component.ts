import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { CurrentRunData, CurrentRunNode } from '../../core/models/api.models';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DgPageFrameComponent } from '../../shared/ui/dg-page-frame/dg-page-frame.component';
import { RunUnitFormationGridComponent } from '../../shared/ui/run-unit-formation-grid/run-unit-formation-grid.component';

const FORMATION_CELLS = ['A1', 'A2', 'A3', 'B1', 'B2', 'B3', 'C1', 'C2', 'C3'] as const;

@Component({
  selector: 'app-run-map-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, DgPageFrameComponent, RunUnitFormationGridComponent],
  templateUrl: './run-map-page.component.html',
  styleUrl: './run-map-page.component.scss',
})
export class RunMapPageComponent {
  private static readonly ENCOUNTER_ICON_MAP: Record<string, string> = {
    combat: '/assets/ui/icons/icon_encounter_combat.png',
    loot: '/assets/ui/icons/icon_encounter_loot.png',
    rest: '/assets/ui/icons/icon_encounter_rest.png',
    boss: '/assets/ui/icons/icon_encounter_boss.png',
    exit: '/assets/ui/icons/icon_home.png',
  };

  private readonly router = inject(Router);
  private readonly runService = inject(RunService);
  private readonly sessionService = inject(SessionService);

  readonly runData = signal<CurrentRunData | null>(null);
  readonly loading = signal(true);
  readonly working = signal(false);
  readonly error = signal<string | null>(null);

  readonly nodes = computed(() => this.runData()?.map?.nodes ?? []);
  readonly edges = computed(() => this.runData()?.map?.edges ?? []);
  readonly run = computed(() => this.runData()?.run ?? null);
  readonly activeSquad = this.sessionService.activeSquad;
  readonly mapWidth = computed(() => {
    const maxIndex = this.nodes().reduce((highest, node) => Math.max(highest, node.node_index), 0);
    return Math.max(1200, 240 + maxIndex * 140);
  });
  readonly legendEntries = computed(() => [
    { type: 'combat', label: 'Combat', icon: this.iconForNodeType('combat') },
    { type: 'loot', label: 'Loot', icon: this.iconForNodeType('loot') },
    { type: 'rest', label: 'Rest', icon: this.iconForNodeType('rest') },
    { type: 'boss', label: 'Boss', icon: this.iconForNodeType('boss') },
    { type: 'exit', label: 'Exit', icon: this.iconForNodeType('exit') },
  ]);
  readonly runUnits = computed(() => {
    const unitsById = new Map(this.sessionService.units().map((unit) => [unit.id, unit]));
    return (this.runData()?.run_unit_state ?? []).map((state) => ({
      ...state,
      unit: unitsById.get(state.unit_instance_id) ?? null,
      currentHp: state.current_hp ?? state.hp ?? 0,
      maxHp: unitsById.get(state.unit_instance_id)?.max_hp ?? state.current_hp ?? state.hp ?? 0,
      defeated: state.is_defeated || (state.current_hp ?? state.hp ?? 0) <= 0,
    }));
  });
  readonly runUnitById = computed(
    () => new Map(this.runUnits().map((entry) => [entry.unit_instance_id, entry])),
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
        entry: unitId ? this.runUnitById().get(unitId) ?? null : null,
      };
    });
  });

  constructor() {
    void this.load();
  }

  async load(): Promise<void> {
    this.loading.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.getCurrentRun();
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.runData.set(response.data);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load current run.');
    } finally {
      this.loading.set(false);
    }
  }

  nodeX(node: CurrentRunNode): number {
    return 120 + node.node_index * 140;
  }

  nodeY(node: CurrentRunNode): number {
    const offset = node.node_index % 2 === 0 ? 90 : 190;
    return offset;
  }

  nodeById(nodeId: string): CurrentRunNode | undefined {
    return this.nodes().find((node) => node.id === nodeId);
  }

  edgeX(nodeId: string): number {
    const node = this.nodeById(nodeId);
    return node ? this.nodeX(node) : 0;
  }

  edgeY(nodeId: string): number {
    const node = this.nodeById(nodeId);
    return node ? this.nodeY(node) : 0;
  }

  iconForNode(node: CurrentRunNode): string {
    return this.iconForNodeType(node.node_type);
  }

  iconForNodeType(nodeType: string): string {
    return RunMapPageComponent.ENCOUNTER_ICON_MAP[nodeType] ?? '/assets/ui/icons/icon_encounter_locked.png';
  }

  nodeTypeLabel(nodeType: string): string {
    return nodeType.charAt(0).toUpperCase() + nodeType.slice(1);
  }

  async openNode(node: CurrentRunNode): Promise<void> {
    if (node.status !== 'available' || !this.run()) {
      return;
    }

    if (node.node_type === 'rest') {
      await this.router.navigate(['/run/rest', node.id]);
      return;
    }

    if (node.node_type === 'exit') {
      await this.finishRun();
      return;
    }

    await this.router.navigate(['/run/node', node.id]);
  }

  async abandonRun(): Promise<void> {
    if (!this.run()) {
      return;
    }
    this.working.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.abandonRun(this.run()!.run_id);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      await this.router.navigateByUrl('/run/summary');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to abandon run.');
    } finally {
      this.working.set(false);
    }
  }

  async finishRun(): Promise<void> {
    if (!this.run()) {
      return;
    }
    this.working.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.exitRun(this.run()!.run_id);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      await this.router.navigateByUrl('/run/summary');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to exit run.');
    } finally {
      this.working.set(false);
    }
  }
}

