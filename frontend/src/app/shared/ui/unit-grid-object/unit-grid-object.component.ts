import { NgTemplateOutlet } from '@angular/common';
import { Component, input } from '@angular/core';
import { FontAwesomeModule } from '@fortawesome/angular-fontawesome';
import { faHandFist, faHeart, faShieldHalved } from '@fortawesome/free-solid-svg-icons';
import { RouterLink } from '@angular/router';
import { UnitRecord } from '../../../core/models/api.models';
import { GridObjectComponent } from '../grid-object/grid-object.component';
import { resolveUnitImageUrl } from '../unit-art/unit-art';

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
  imports: [FontAwesomeModule, NgTemplateOutlet, RouterLink],
  templateUrl: './unit-grid-object.component.html',
  styleUrl: './unit-grid-object.component.scss',
})
export class UnitGridObjectComponent extends GridObjectComponent<UnitRecord> {
  readonly faAttack = faHandFist;
  readonly faDefense = faShieldHalved;
  readonly faHealth = faHeart;

  readonly tag = input('');
  readonly progressBar = input<UnitGridObjectProgressBar | null>(null);
  readonly linkEnabled = input(true);
  readonly subtitle = input<string | null>(null);
  readonly surfaceTone = input<'default' | 'enemy'>('default');
  readonly showLockBadge = input(true);
  readonly fillHeight = input(true);

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

  defaultSubtitle(): string {
    const typeLabel = this.object().unit_type_name || this.object().unit_type_slug || 'Unit';
    return typeof this.object().level === 'number' && this.object().level > 0
      ? `${typeLabel} Lv. ${this.object().level}`
      : typeLabel;
  }

  cardArtUrl(): string | null {
    return resolveUnitImageUrl(this.object().unit_type_slug)
      ?? resolveUnitImageUrl(this.object().unit_type_name);
  }

  portraitUrl(): string | null {
    return resolveUnitImageUrl(this.object().unit_type_slug);
  }

  statValue(value: number | null | undefined): string {
    return typeof value === 'number' ? `${value}` : '-';
  }
}
