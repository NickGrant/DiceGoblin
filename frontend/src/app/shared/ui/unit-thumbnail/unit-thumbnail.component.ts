import { Component, computed, input } from '@angular/core';
import { UnitRecord } from '../../../core/models/api.models';
import { resolvePrototypeUnitSpriteUrl } from '../prototype-art/prototype-art';
import { resolveUnitThumbnailUrl } from '../unit-art/unit-art';

@Component({
  selector: 'dg-unit-thumbnail',
  standalone: true,
  templateUrl: './unit-thumbnail.component.html',
  styleUrl: './unit-thumbnail.component.scss',
})
export class UnitThumbnailComponent {
  readonly unit = input.required<UnitRecord>();
  readonly selected = input(false);
  readonly locked = input(false);
  readonly compact = input(false);
  readonly showCopy = input(true);
  readonly showHp = input(false);
  readonly currentHp = input<number | null>(null);
  readonly maxHp = input<number | null>(null);

  readonly unitTypeLabel = computed(() => this.unit().unit_type_name || this.unit().unit_type_slug || 'Unit');
  readonly levelLabel = computed(() => `Lv ${this.unit().level || 1}`);
  readonly resolvedCurrentHp = computed(() => this.currentHp() ?? this.unit().current_hp ?? this.unit().max_hp ?? 0);
  readonly resolvedMaxHp = computed(() => this.maxHp() ?? this.unit().max_hp ?? this.resolvedCurrentHp());
  readonly hpPercent = computed(() => {
    const maxHp = this.resolvedMaxHp();
    if (maxHp <= 0) {
      return 0;
    }

    return Math.max(0, Math.min(100, Math.round((this.resolvedCurrentHp() / maxHp) * 100)));
  });
  readonly hpLabel = computed(() => `${Math.max(0, this.resolvedCurrentHp())}/${Math.max(0, this.resolvedMaxHp())} HP`);
  readonly imageUrl = computed(() =>
    resolveUnitThumbnailUrl(this.unit().unit_type_slug)
    ?? resolveUnitThumbnailUrl(this.unit().unit_type_name)
    ?? resolvePrototypeUnitSpriteUrl(this.unit()),
  );
}
