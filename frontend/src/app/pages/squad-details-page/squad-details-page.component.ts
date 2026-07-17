import { CdkDragDrop, DragDropModule } from '@angular/cdk/drag-drop';
import { Component, computed, effect, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { TeamFormationCell, UnitRecord } from '../../core/models/api.models';
import { SessionService } from '../../core/services/session/session.service';
import { SquadService } from '../../core/services/squad/squad.service';
import { FORMATION_CELLS } from '../../shared/formation/formation';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { UnitThumbnailComponent } from '../../shared/ui/unit-thumbnail/unit-thumbnail.component';
const AVAILABLE_DROP_ID = 'available-drop';
const CELL_DROP_PREFIX = 'formation-cell-';

type DropTarget =
  | { type: 'available' }
  | { type: 'cell'; cell: string };

@Component({
  selector: 'app-squad-details-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, PageFrameComponent, DragDropModule, FormsModule, UnitThumbnailComponent],
  templateUrl: './squad-details-page.component.html',
  styleUrl: './squad-details-page.component.scss',
})
export class SquadDetailsPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly sessionService = inject(SessionService);
  private readonly squadService = inject(SquadService);
  private readonly hydratedSquadId = signal<string | null>(null);
  private readonly formationRevision = signal(0);

  readonly squadId = this.route.snapshot.paramMap.get('squadId') ?? '';
  readonly squad = computed(() => this.sessionService.squads().find((team) => team.id === this.squadId) ?? null);
  readonly activeRun = computed(() => this.sessionService.profileData()?.active_run ?? null);
  readonly activeSquad = this.sessionService.activeSquad;
  readonly availableUnits = this.sessionService.units;
  readonly squadUnitCap = this.sessionService.squadUnitCap;
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly selectedUnitId = signal<string | null>(null);
  readonly squadLocked = computed(() => !!this.activeRun() && this.activeSquad()?.id === this.squadId);
  readonly selectedUnit = computed(() => {
    const unitId = this.selectedUnitId();
    return unitId ? this.unitById(unitId) : null;
  });
  readonly selectedUnitAssignedCell = computed(() => {
    const unit = this.selectedUnit();
    return unit ? this.findAssignedCellForUnit(unit.id) : null;
  });
  readonly selectedUnitCount = computed(() => {
    this.formationRevision();
    return this.selectedUnitIdsForSave().length;
  });
  readonly squadOverCap = computed(() => this.selectedUnitCount() > this.squadUnitCap());

  name = this.squad()?.name ?? '';
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
      this.formationAssignments = new Map(
        FORMATION_CELLS.map((cell) => [
          cell,
          squad.formation.find((entry) => entry.cell === cell)?.unit_instance_id ?? null,
        ]),
      );
      this.bumpFormationRevision();
      this.hydratedSquadId.set(squad.id);
    });
  }

  dropUnit(event: CdkDragDrop<unknown>, target: DropTarget): void {
    if (this.squadLocked()) {
      return;
    }

    this.error.set(null);

    const unitId = String(event.item.data ?? '');
    const unit = this.unitById(unitId);
    if (!unit || unit.locked) {
      return;
    }

    const source = this.targetFromDropListId(event.previousContainer.id);
    if (!source || this.isSameTarget(source, target)) {
      return;
    }

    if (target.type === 'available') {
      this.removeUnitFromSquad(unitId);
      return;
    }

    const sourceIsAvailable = source.type === 'available';
    const previousCell = this.findAssignedCellForUnit(unitId);
    const targetOccupiedByUnitId = this.formationAssignments.get(target.cell) ?? null;
    const wouldAddNewMember =
      sourceIsAvailable
      && !previousCell
      && !targetOccupiedByUnitId
      && this.selectedUnitCount() >= this.squadUnitCap();

    if (wouldAddNewMember) {
      this.error.set(`This squad is capped at ${this.squadUnitCap()} units.`);
      return;
    }

    this.placeUnitInCell(unitId, target.cell, source.type === 'cell' ? source.cell : null);
  }

  toggleUnitSelection(unitId: string): void {
    if (this.squadLocked()) {
      return;
    }

    const unit = this.unitById(unitId);
    if (!unit || unit.locked) {
      return;
    }

    this.error.set(null);
    this.selectedUnitId.update((selectedUnitId) => selectedUnitId === unitId ? null : unitId);
  }

  assignSelectedUnitToCell(cell: string): void {
    if (this.squadLocked()) {
      return;
    }

    const unit = this.selectedUnit();
    if (!unit) {
      this.error.set('Select a unit first, then tap a formation slot.');
      return;
    }

    this.error.set(null);

    const previousCell = this.findAssignedCellForUnit(unit.id);
    const targetOccupiedByUnitId = this.formationAssignments.get(cell) ?? null;
    const wouldAddNewMember =
      !previousCell
      && !targetOccupiedByUnitId
      && this.selectedUnitCount() >= this.squadUnitCap();

    if (wouldAddNewMember) {
      this.error.set(`This squad is capped at ${this.squadUnitCap()} units.`);
      return;
    }

    this.placeUnitInCell(unit.id, cell, previousCell);
  }

  formationCellActionLabel(cell: string): string {
    if (this.selectedUnitAssignedCell() === cell) {
      return 'Selected';
    }

    return this.selectedUnit() ? 'Tap to Place' : 'Tap to Select Unit';
  }

  removeSelectedUnitFromFormation(): void {
    if (this.squadLocked()) {
      return;
    }

    const unit = this.selectedUnit();
    if (!unit) {
      this.error.set('Select a formation unit first, then remove it from the squad.');
      return;
    }

    const assignedCell = this.findAssignedCellForUnit(unit.id);
    if (!assignedCell) {
      this.error.set('Selected unit is already in the available pool.');
      return;
    }

    this.error.set(null);
    this.removeUnitFromSquad(unit.id);
  }

  isUnitSelected(unitId: string): boolean {
    return this.selectedUnitId() === unitId;
  }

  unitById(unitId: string): UnitRecord | null {
    return this.availableUnits().find((unit) => unit.id === unitId) ?? null;
  }

  availablePoolUnits(): UnitRecord[] {
    return this.availableUnits().filter((unit) => !this.findAssignedCellForUnit(unit.id));
  }

  unitForCell(cell: string): UnitRecord | null {
    const unitId = this.formationAssignments.get(cell) ?? null;
    return unitId ? this.unitById(unitId) : null;
  }

  formationForSave(): TeamFormationCell[] {
    return FORMATION_CELLS.map((cell) => ({
      cell,
      unit_instance_id: this.formationAssignments.get(cell) ?? null,
    }));
  }

  selectedUnitIdsForSave(): string[] {
    return Array.from(
      new Set(
        FORMATION_CELLS
          .map((cell) => this.formationAssignments.get(cell) ?? null)
          .filter((unitId): unitId is string => !!unitId),
      ),
    );
  }

  dropListIdForCell(cell: string): string {
    return `${CELL_DROP_PREFIX}${cell}`;
  }

  async save(): Promise<void> {
    if (!this.squad() || this.squadLocked()) {
      return;
    }

    if (this.squadOverCap()) {
      this.error.set(`This squad exceeds your ${this.squadUnitCap()}-unit cap. Remove units before saving.`);
      return;
    }

    this.saving.set(true);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.squadService.updateTeam(this.squadId, {
        name: this.name.trim() || this.squad()!.name,
        unit_ids: this.selectedUnitIdsForSave(),
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

  private removeUnitFromSquad(unitId: string): void {
    this.clearFormationAssignmentsForUnit(unitId);
    if (this.selectedUnitId() === unitId) {
      this.selectedUnitId.set(null);
    }
    this.bumpFormationRevision();
  }

  private placeUnitInCell(unitId: string, targetCell: string, sourceCell: string | null): void {
    const previousCell = this.findAssignedCellForUnit(unitId);
    const displacedUnitId = this.formationAssignments.get(targetCell) ?? null;

    if (previousCell === targetCell) {
      return;
    }

    if (previousCell) {
      this.formationAssignments.set(previousCell, null);
    }

    this.clearFormationAssignmentsForUnit(unitId, targetCell);
    this.formationAssignments.set(targetCell, unitId);

    if (displacedUnitId && displacedUnitId !== unitId && sourceCell && sourceCell !== targetCell) {
      this.formationAssignments.set(sourceCell, displacedUnitId);
    }

    this.selectedUnitId.set(unitId);
    this.bumpFormationRevision();
  }

  private findAssignedCellForUnit(unitId: string): string | null {
    for (const [cell, assignedUnitId] of this.formationAssignments.entries()) {
      if (assignedUnitId === unitId) {
        return cell;
      }
    }

    return null;
  }

  private targetFromDropListId(id: string): DropTarget | null {
    if (id === AVAILABLE_DROP_ID) {
      return { type: 'available' };
    }

    if (id.startsWith(CELL_DROP_PREFIX)) {
      return { type: 'cell', cell: id.slice(CELL_DROP_PREFIX.length) };
    }

    return null;
  }

  private isSameTarget(source: DropTarget, target: DropTarget): boolean {
    return source.type === target.type && source.type !== 'cell'
      ? true
      : source.type === 'cell' && target.type === 'cell' && source.cell === target.cell;
  }

  private clearFormationAssignmentsForUnit(unitId: string, exceptCell?: string): void {
    this.formationAssignments.forEach((assignedUnitId, cell) => {
      if (assignedUnitId === unitId && cell !== exceptCell) {
        this.formationAssignments.set(cell, null);
      }
    });
  }

  private bumpFormationRevision(): void {
    this.formationRevision.update((value) => value + 1);
  }

  protected readonly availableDropId = AVAILABLE_DROP_ID;
  protected readonly formationCells = FORMATION_CELLS;
}
