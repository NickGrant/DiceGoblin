import { Component, input } from '@angular/core';

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
    } | null;
  } | null;
};

@Component({
  selector: 'dg-run-unit-formation-grid',
  standalone: true,
  templateUrl: './run-unit-formation-grid.component.html',
  styleUrl: './run-unit-formation-grid.component.scss',
})
export class RunUnitFormationGridComponent {
  readonly cells = input.required<readonly RunUnitFormationCell[]>();
}
