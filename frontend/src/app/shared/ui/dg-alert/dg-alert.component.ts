import { Component, input } from '@angular/core';

@Component({
  selector: 'dg-alert',
  standalone: true,
  host: {
    '[attr.data-dg-alert]': 'tone()',
    '[attr.role]': 'tone() === "error" ? "alert" : "status"',
    '[attr.aria-live]': 'tone() === "error" ? "assertive" : "polite"',
  },
  styles: [
    `
      :host {
        display: block;
        padding: var(--dg-space-5) var(--dg-space-6);
        border: 1px solid var(--dg-border-default);
        border-radius: var(--dg-radius-lg);
        background: var(--dg-bg-card);
        color: var(--dg-text-primary);
      }

      :host([data-dg-alert='error']) {
        border-color: var(--dg-status-danger);
        background: color-mix(in srgb, var(--dg-status-danger) 12%, var(--dg-bg-card));
      }

      :host([data-dg-alert='success']) {
        border-color: var(--dg-status-success);
        background: color-mix(in srgb, var(--dg-status-success) 12%, var(--dg-bg-card));
      }
    `,
  ],
  template: `<ng-content />`,
})
export class DgAlertComponent {
  readonly tone = input<'info' | 'error' | 'success'>('info');
}
