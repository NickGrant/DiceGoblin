import { Component, input } from '@angular/core';

@Component({
  selector: 'dg-page-frame',
  standalone: true,
  host: {
    style: 'display: block;',
  },
  template: `
    <section class="dg-page-frame route-frame">
      @if (showHeader()) {
      <div class="dg-title-bar p-3 p-md-4">
        @if (eyebrow()) {
        <p class="route-frame__eyebrow dg-stencil">{{ eyebrow() }}</p>
        }
        <h1 class="mb-2">{{ title() }}</h1>
        @if (subtitle(); as subtitle) {
        <p class="mb-0">{{ subtitle }}</p>
        }
      </div>
      }

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
