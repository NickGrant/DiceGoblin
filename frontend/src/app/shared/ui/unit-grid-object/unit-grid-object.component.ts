import { NgTemplateOutlet } from '@angular/common';
import { Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { UnitRecord } from '../../../core/models/api.models';
import { GridObjectComponent } from '../grid-object/grid-object.component';

export type UnitGridObjectProgressBar = {
  percent: number;
  title: string;
  leftLabel: string;
  rightLabel?: string;
  tone?: 'hp-healthy' | 'hp-critical' | 'xp';
  celebrationLabel?: string | null;
  showLabels?: boolean;
};

@Component({
  selector: 'dg-unit-grid-object',
  standalone: true,
  imports: [NgTemplateOutlet, RouterLink],
  templateUrl: './unit-grid-object.component.html',
  styleUrl: './unit-grid-object.component.scss',
})
export class UnitGridObjectComponent extends GridObjectComponent<UnitRecord> {
  readonly tag = input('');
  readonly progressBar = input<UnitGridObjectProgressBar | null>(null);

  formatTier(tier: number | null | undefined): string | null {
    switch (tier) {
      case 1:
        return 'I';
      case 2:
        return 'II';
      case 3:
        return 'III';
      case 4:
        return 'IV';
      case 5:
        return 'V';
      default:
        return tier ? `${tier}` : null;
    }
  }

  progressWidth(progressBar: UnitGridObjectProgressBar | null): number {
    return Math.max(0, Math.min(100, progressBar?.percent ?? 0));
  }
}
