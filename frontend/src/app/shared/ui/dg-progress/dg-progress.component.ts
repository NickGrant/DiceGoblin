import { Component, computed, input } from '@angular/core';

export type DgProgressSize = 'standard' | 'compact';

@Component({
  selector: 'dg-progress',
  standalone: true,
  templateUrl: './dg-progress.component.html',
  styleUrl: './dg-progress.component.scss',
  host: {
    role: 'progressbar',
    '[attr.data-dg-progress]': 'size()',
    '[attr.aria-valuemin]': '0',
    '[attr.aria-valuemax]': 'max()',
    '[attr.aria-valuenow]': 'clampedValue()',
    '[attr.aria-label]': 'ariaLabel()',
  },
})
export class DgProgressComponent {
  readonly value = input(0);
  readonly max = input(100);
  readonly size = input<DgProgressSize>('standard');
  readonly ariaLabel = input('Progress');
  readonly showValue = input(true);

  readonly clampedValue = computed(() => Math.min(Math.max(this.value(), 0), Math.max(this.max(), 0)));
  readonly percent = computed(() => {
    const max = this.max();
    return max > 0 ? (this.clampedValue() / max) * 100 : 0;
  });
  readonly percentStyle = computed(() => `${this.percent()}%`);
}
