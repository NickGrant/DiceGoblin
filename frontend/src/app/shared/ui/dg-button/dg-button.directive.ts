import { FocusMonitor } from '@angular/cdk/a11y';
import { BooleanInput, coerceBooleanProperty } from '@angular/cdk/coercion';
import { Directive, ElementRef, OnDestroy, inject, input } from '@angular/core';

export type DgButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'action' | 'special';
export type DgButtonSize = 'sm' | 'md' | 'lg';

@Directive({
  selector: 'button[dgButton],a[dgButton]',
  standalone: true,
  host: {
    '[attr.data-dg-button]': 'variant()',
    '[attr.data-size]': 'size()',
    '[attr.data-full-width]': 'fullWidth() ? "" : null',
    '[attr.aria-disabled]': 'isAnchorDisabled',
    '[attr.tabindex]': 'isAnchorDisabled ? -1 : null',
    '(click)': 'handleClick($event)',
  },
})
export class DgButtonDirective implements OnDestroy {
  private readonly elementRef = inject<ElementRef<HTMLElement>>(ElementRef);
  private readonly focusMonitor = inject(FocusMonitor);

  readonly variant = input<DgButtonVariant>('primary', { alias: 'dgButton' });
  readonly size = input<DgButtonSize>('md');
  readonly disabled = input(false, { transform: coerceBooleanProperty });
  readonly fullWidth = input(false, { transform: coerceBooleanProperty });

  static ngAcceptInputType_disabled: BooleanInput;
  static ngAcceptInputType_fullWidth: BooleanInput;

  constructor() {
    this.focusMonitor.monitor(this.elementRef, true);
  }

  get isAnchorDisabled(): 'true' | null {
    const host = this.elementRef.nativeElement;
    return host.tagName.toLowerCase() === 'a' && this.disabled() ? 'true' : null;
  }

  handleClick(event: Event): void {
    if (!this.disabled()) {
      return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
  }

  ngOnDestroy(): void {
    this.focusMonitor.stopMonitoring(this.elementRef);
  }
}
