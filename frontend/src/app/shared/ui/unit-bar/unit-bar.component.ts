import { Component, computed, input } from '@angular/core';
import { FontAwesomeModule } from '@fortawesome/angular-fontawesome';
import { UnitRecord } from '../../../core/models/api.models';
import { formatSpliceVariantLabel, formatTier } from '../../utils/unit-formatters';
import { UnitThumbnailComponent } from '../unit-thumbnail/unit-thumbnail.component';
import { resolveUnitRoleIcon } from '../category-icons/category-icons';

@Component({
  selector: 'dg-unit-bar',
  standalone: true,
  imports: [FontAwesomeModule, UnitThumbnailComponent],
  templateUrl: './unit-bar.component.html',
  styleUrl: './unit-bar.component.scss',
})
export class UnitBarComponent {
  readonly unit = input.required<UnitRecord>();
  readonly currentHp = input<number | null>(null);
  readonly maxHp = input<number | null>(null);
  readonly positionLabel = input<string | null>(null);
  readonly selected = input(false);
  readonly defeated = input(false);
  readonly compact = input(false);

  readonly resolvedCurrentHp = computed(() => this.currentHp() ?? this.unit().current_hp ?? this.unit().max_hp ?? 0);
  readonly resolvedMaxHp = computed(() => this.maxHp() ?? this.unit().max_hp ?? this.resolvedCurrentHp());
  readonly hpPercent = computed(() => this.percent(this.resolvedCurrentHp(), this.resolvedMaxHp()));
  readonly hpLabel = computed(() => `${Math.max(0, this.resolvedCurrentHp())}/${Math.max(0, this.resolvedMaxHp())} HP`);
  readonly xpToNext = computed(() => Math.max(0, this.unit().xp_to_next_level ?? 0));
  readonly xpPercent = computed(() => {
    if (this.unit().is_mastered || this.xpToNext() <= 0) {
      return 100;
    }

    const currentXp = Math.max(0, this.unit().xp ?? 0);
    return this.percent(currentXp, currentXp + this.xpToNext());
  });
  readonly xpLabel = computed(() => this.unit().is_mastered ? 'Mastered' : `${this.xpToNext()} XP to next`);
  readonly tierNumber = computed(() => Math.max(1, Math.min(5, Math.round(this.unit().tier ?? 1))));
  readonly tierLabel = computed(() => `Tier ${formatTier(this.tierNumber()) ?? this.tierNumber()}`);
  readonly tierIndicatorClass = computed(
    () => `unit-bar__tier-icon dg-tier-indicator dg-tier-indicator--${this.tierNumber()}`,
  );
  readonly levelLabel = computed(() => `Level ${this.unit().level || 1}`);
  readonly roleIcon = computed(() => resolveUnitRoleIcon(this.unit().unit_type_slug ?? this.unit().unit_type_name));
  readonly spliceLabel = computed(() => formatSpliceVariantLabel(this.unit().splice_variant_name, this.unit().splice_variant_slug));
  readonly statStrip = computed(() => [
    { label: 'ATK', value: this.statValue(this.unit().total_attack) },
    { label: 'DEF', value: this.statValue(this.unit().total_defense) },
    { label: 'PRC', value: this.statValue(this.unit().total_precision) },
    { label: 'RES', value: this.statValue(this.unit().total_resolve) },
  ]);

  private percent(value: number, total: number): number {
    if (total <= 0) {
      return 0;
    }

    return Math.max(0, Math.min(100, Math.round((value / total) * 100)));
  }

  private statValue(value: number | null | undefined): string {
    return typeof value === 'number' ? `${value}` : '-';
  }
}
