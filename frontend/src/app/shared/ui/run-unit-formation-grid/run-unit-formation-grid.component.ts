import { Component, input } from '@angular/core';
import { UnitRecord } from '../../../core/models/api.models';
import { UnitBarComponent } from '../unit-bar/unit-bar.component';

export type RunUnitFormationCell = {
  cell: string;
  unitId: string | null;
  entry: {
    unit_instance_id: string;
    currentHp: number;
    maxHp: number;
    defeated: boolean;
    unit: {
      name?: string;
      unit_type_name?: string;
      unit_type_slug?: string;
      level?: number;
      tier?: number;
      xp?: number;
      xp_to_next_level?: number;
      is_mastered?: boolean;
      current_hp?: number;
      max_hp?: number;
      locked?: boolean;
    } | UnitRecord | null;
  } | null;
};

@Component({
  selector: 'dg-run-unit-formation-grid',
  standalone: true,
  imports: [UnitBarComponent],
  templateUrl: './run-unit-formation-grid.component.html',
  styleUrl: './run-unit-formation-grid.component.scss',
})
export class RunUnitFormationGridComponent {
  readonly cells = input.required<readonly RunUnitFormationCell[]>();

  displayUnit(entry: NonNullable<RunUnitFormationCell['entry']>): UnitRecord {
    return {
      id: entry.unit_instance_id,
      name: entry.unit?.name || `Unit ${entry.unit_instance_id}`,
      level: entry.unit?.level ?? 1,
      unit_type_name: entry.unit?.unit_type_name,
      unit_type_slug: entry.unit?.unit_type_slug,
      tier: entry.unit?.tier,
      xp: entry.unit?.xp,
      xp_to_next_level: entry.unit?.xp_to_next_level,
      is_mastered: entry.unit?.is_mastered,
      current_hp: entry.currentHp,
      max_hp: entry.maxHp,
      locked: entry.unit?.locked,
    };
  }
}
