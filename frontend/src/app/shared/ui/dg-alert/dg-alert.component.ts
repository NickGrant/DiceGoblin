import { Component, input } from '@angular/core';

@Component({
  selector: 'dg-alert',
  standalone: true,
  host: {
    style: 'display: block;',
  },
  styles: [
    `
      .dg-alert {
        padding: 0.9rem 1rem;
        border: 2px solid rgba(35, 39, 42, 0.2);
        background: rgba(243, 239, 224, 0.9);
      }

      .dg-alert--error {
        border-color: rgba(185, 28, 28, 0.45);
        background: rgba(185, 28, 28, 0.12);
      }

      .dg-alert--success {
        border-color: rgba(0, 111, 122, 0.4);
        background: rgba(0, 111, 122, 0.1);
      }
    `,
  ],
  template: `
    <div
      class="dg-alert"
      [class.dg-alert--error]="tone() === 'error'"
      [class.dg-alert--success]="tone() === 'success'"
      [attr.role]="tone() === 'error' ? 'alert' : 'status'"
      [attr.aria-live]="tone() === 'error' ? 'assertive' : 'polite'"
    >
      <ng-content />
    </div>
  `,
})
export class DgAlertComponent {
  readonly tone = input<'info' | 'error' | 'success'>('info');
}
