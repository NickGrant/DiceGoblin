import { Directive, input } from '@angular/core';

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
  readonly tone = input<'' | 'primary' | 'teal' | 'muted'>('', { alias: 'dgCommandBtn' });
}
