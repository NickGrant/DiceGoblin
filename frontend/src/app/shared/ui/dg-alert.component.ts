import { NgClass } from '@angular/common';
import { Component, input } from '@angular/core';

@Component({
  selector: 'dg-alert',
  standalone: true,
  imports: [NgClass],
  host: {
    style: 'display: block;',
  },
  template: `
    <div
      class="dg-alert"
      [ngClass]="{
        'dg-alert--error': tone() === 'error',
        'dg-alert--success': tone() === 'success'
      }"
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
