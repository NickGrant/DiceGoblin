import { FocusMonitor } from '@angular/cdk/a11y';
import { BooleanInput, coerceBooleanProperty } from '@angular/cdk/coercion';
import { Directive, ElementRef, OnDestroy, inject, input } from '@angular/core';

export type DgChipTone = 'neutral' | 'trait' | 'ability' | 'damage' | 'buff' | 'status' | 'pending';

@Directive({
  selector: '[dgChip]',
  standalone: true,
  host: {
    '[attr.data-dg-chip]': 'tone()',
    '[attr.data-active]': 'active() ? "" : null',
  },
})
export class DgChipDirective implements OnDestroy {
  private readonly elementRef = inject<ElementRef<HTMLElement>>(ElementRef);
  private readonly focusMonitor = inject(FocusMonitor);

  readonly tone = input<DgChipTone>('neutral', { alias: 'dgChip' });
  readonly active = input(false, { transform: coerceBooleanProperty });

  static ngAcceptInputType_active: BooleanInput;

  constructor() {
    this.focusMonitor.monitor(this.elementRef, true);
  }

  ngOnDestroy(): void {
    this.focusMonitor.stopMonitoring(this.elementRef);
  }
}
