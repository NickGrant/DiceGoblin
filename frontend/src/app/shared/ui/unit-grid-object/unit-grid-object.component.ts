import { NgTemplateOutlet } from '@angular/common';
import { Component, input } from '@angular/core';
import { FontAwesomeModule } from '@fortawesome/angular-fontawesome';
import { faBrain, faBullseye, faHandFist, faHeart, faShieldHalved } from '@fortawesome/free-solid-svg-icons';
import { RouterLink } from '@angular/router';
import { UnitRecord } from '../../../core/models/api.models';
import { formatTier, formatUnitKinLabel } from '../../utils/unit-formatters';
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
  readonly faPrecision = faBullseye;
  readonly faResolve = faBrain;

  readonly tag = input('');
  readonly progressBar = input<UnitGridObjectProgressBar | null>(null);
  readonly linkEnabled = input(true);
  readonly subtitle = input<string | null>(null);
  readonly surfaceTone = input<'default' | 'enemy'>('default');
  readonly showLockBadge = input(true);
  readonly fillHeight = input(true);

  formatTier(tier: number | null | undefined): string | null {
    return formatTier(tier);
  }

  tierIndicatorClass(tier: number | null | undefined): string {
    const tierNumber = Math.max(1, Math.min(5, Math.round(tier ?? 1)));
    return `unit-grid-object__tier-chip dg-tier-indicator dg-tier-indicator--${tierNumber}`;
  }

  progressWidth(progressBar: UnitGridObjectProgressBar | null): number {
    return Math.max(0, Math.min(100, progressBar?.percent ?? 0));
  }

  defaultSubtitle(): string {
    const typeLabel = this.object().unit_type_name || this.object().unit_type_slug || 'Unit';
    const kinLabel = formatUnitKinLabel(this.object());
    const identityLabel = `${typeLabel} - ${kinLabel}`;
    return typeof this.object().level === 'number' && this.object().level > 0
      ? `${identityLabel} Lv. ${this.object().level}`
      : identityLabel;
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
