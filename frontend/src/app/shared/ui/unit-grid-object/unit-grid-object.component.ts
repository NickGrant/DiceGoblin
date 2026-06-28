import { NgTemplateOutlet } from '@angular/common';
import { Component, input } from '@angular/core';
import { FontAwesomeModule } from '@fortawesome/angular-fontawesome';
import { faHandFist, faHeart, faShieldHalved } from '@fortawesome/free-solid-svg-icons';
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
    const nameSlug = this.normalizeCardArtSlug(this.object().unit_type_name);
    if (nameSlug) {
      return `/assets/ui/cardboard-units/${nameSlug}.png`;
    }

    const slug = this.normalizeCardArtSlug(this.object().unit_type_slug);
    if (slug) {
      return `/assets/ui/cardboard-units/${slug}.png`;
    }

    return this.portraitUrl();
  }

  portraitUrl(): string | null {
    const slug = this.normalizePortraitSlug(this.object().unit_type_slug);
    return slug ? `/assets/ui/portraits/${slug}.png` : null;
  }

  statValue(value: number | null | undefined): string {
    return typeof value === 'number' ? `${value}` : '-';
  }

  private normalizePortraitSlug(value: string | null | undefined): string | null {
    const normalized = (value ?? '').trim().toLowerCase().replace(/-/g, '_');
    if (!normalized.length) {
      return null;
    }

    const goblinRoleMatch = normalized.match(/^(frontline|backline|support|control)_([a-z0-9_]+)_t\d+$/);
    if (goblinRoleMatch) {
      return `goblin_${goblinRoleMatch[2]}`;
    }

    return normalized;
  }

  private normalizeCardArtSlug(value: string | null | undefined): string | null {
    const normalized = (value ?? '').trim().toLowerCase();
    if (!normalized.length) {
      return null;
    }

    const knownSlugMap: Record<string, string> = {
      frontline_bruiser_t1: 'bruiser',
      frontline_bruiser_t2: 'enforcer',
      frontline_pit_fighter_t2: 'pit-fighter',
      frontline_guardian_t1: 'guardian',
      frontline_guardian_t2: 'bulwark',
      frontline_shieldbreaker_t2: 'shieldbreaker',
      backline_marksman_t1: 'marksman',
      backline_marksman_t2: 'deadeye',
      backline_trapper_t2: 'trapper',
      support_banner_t1: 'bannerbearer',
      support_banner_t2: 'warcaller',
      support_mascot_t2: 'mascot',
      control_saboteur_t1: 'saboteur',
      control_saboteur_t2: 'trickshot',
      control_plaguehand_t2: 'plaguehand',
    };

    if (knownSlugMap[normalized]) {
      return knownSlugMap[normalized];
    }

    return normalized
      .replace(/^goblin\s+/, '')
      .replace(/\s+/g, '-')
      .replace(/banner\b/g, 'bannerbearer');
  }
}
