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

  readonly unitTypeLabel = computed(() => this.unit().unit_type_name || this.unit().unit_type_slug || 'Unit');
  readonly levelLabel = computed(() => `Lv ${this.unit().level || 1}`);
  readonly imageUrl = computed(() =>
    resolveUnitThumbnailUrl(this.unit().unit_type_slug)
    ?? resolveUnitThumbnailUrl(this.unit().unit_type_name)
    ?? resolvePrototypeUnitSpriteUrl(this.unit()),
  );
}
