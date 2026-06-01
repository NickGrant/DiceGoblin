import { Component, input } from '@angular/core';

@Component({
  selector: 'dg-alert',
  standalone: true,
  host: {
    style: 'display: block;',
  },
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
