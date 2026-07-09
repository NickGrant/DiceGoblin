import { Component, input } from '@angular/core';
import { formatTier } from '../../utils/unit-formatters';

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

  formatTier(tier: number | null | undefined): string | null {
    return formatTier(tier);
  }

  hpPercent(currentHp: number, maxHp: number): number {
    if (maxHp <= 0) {
      return 0;
    }

    return Math.max(0, Math.min(100, (currentHp / maxHp) * 100));
  }

  hpBarClass(currentHp: number, maxHp: number): string {
    return this.hpPercent(currentHp, maxHp) <= 25 ? 'run-unit-grid__hp-fill--critical' : 'run-unit-grid__hp-fill--healthy';
  }
}
