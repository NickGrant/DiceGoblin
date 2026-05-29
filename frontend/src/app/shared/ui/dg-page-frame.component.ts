import { NgIf } from '@angular/common';
import { Component, input } from '@angular/core';

@Component({
  selector: 'dg-page-frame',
  standalone: true,
  imports: [NgIf],
  host: {
    style: 'display: block;',
  },
  template: `
    <section class="dg-page-frame route-frame">
      <div class="dg-title-bar p-3 p-md-4" *ngIf="showHeader()">
        <p class="route-frame__eyebrow dg-stencil" *ngIf="eyebrow()">{{ eyebrow() }}</p>
        <h1 class="mb-2">{{ title() }}</h1>
        <p class="mb-0" *ngIf="subtitle() as subtitle">{{ subtitle }}</p>
      </div>

      <div class="p-3 p-md-4">
        <ng-content />
      </div>
    </section>
  `,
})
export class DgPageFrameComponent {
  readonly eyebrow = input('');
  readonly title = input('');
  readonly subtitle = input<string | null>(null);
  readonly showHeader = input(true);
}
