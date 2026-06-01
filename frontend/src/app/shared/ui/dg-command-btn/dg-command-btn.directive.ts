import { Directive, ElementRef, Renderer2, effect, inject, input } from '@angular/core';

@Directive({
  selector: 'a[dgCommandBtn],button[dgCommandBtn]',
  standalone: true,
  host: {
    class: 'dg-command-btn',
    '[class.dg-command-btn--primary]': 'tone() === "primary"',
    '[class.dg-command-btn--teal]': 'tone() === "teal"',
    '[class.dg-command-btn--muted]': 'tone() === "muted"',
  },
})
export class DgCommandBtnDirective {
  private readonly elementRef = inject(ElementRef<HTMLElement>);
  private readonly renderer = inject(Renderer2);
  private readonly costContainer: HTMLSpanElement;
  private readonly costValue: Text;
  private readonly costIcon: HTMLImageElement;

  readonly tone = input<'' | 'primary' | 'teal' | 'muted'>('', { alias: 'dgCommandBtn' });
  readonly teethCost = input<number | string | null>(null);
  readonly teethCot = input<number | string | null>(null);

  constructor() {
    this.costContainer = this.renderer.createElement('span');
    this.renderer.addClass(this.costContainer, 'dg-command-btn__cost');

    const separator = this.renderer.createText(' - ');
    this.renderer.appendChild(this.costContainer, separator);

    this.costValue = this.renderer.createText('');
    this.renderer.appendChild(this.costContainer, this.costValue);

    const spacer = this.renderer.createText(' ');
    this.renderer.appendChild(this.costContainer, spacer);

    this.costIcon = this.renderer.createElement('img');
    this.renderer.addClass(this.costIcon, 'dg-command-btn__cost-icon');
    this.renderer.setAttribute(this.costIcon, 'src', '/assets/ui/icons/tooth_16.png');
    this.renderer.setAttribute(this.costIcon, 'alt', 'Teeth');
    this.renderer.setAttribute(this.costIcon, 'aria-hidden', 'true');
    this.renderer.appendChild(this.costContainer, this.costIcon);

    effect(() => {
      const resolvedCost = this.teethCost() ?? this.teethCot();
      const host = this.elementRef.nativeElement;
      const hasCost = resolvedCost !== null && resolvedCost !== undefined && `${resolvedCost}`.trim() !== '';

      if (hasCost) {
        this.costValue.textContent = `${resolvedCost}`;
        if (!host.contains(this.costContainer)) {
          this.renderer.appendChild(host, this.costContainer);
        }
        return;
      }

      if (host.contains(this.costContainer)) {
        this.renderer.removeChild(host, this.costContainer);
      }
    });
  }
}
