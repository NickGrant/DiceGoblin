import { Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';

export interface DgBreadcrumb {
  label: string;
  route?: string | readonly (string | number)[];
}

@Component({
  selector: 'dg-page-frame',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './dg-page-frame.component.html',
  styleUrl: './dg-page-frame.component.scss',
})
export class DgPageFrameComponent {
  readonly breadcrumbs = input<readonly DgBreadcrumb[]>([]);
  readonly eyebrow = input('');
  readonly title = input('');
  readonly subtitle = input<string | null>(null);
  readonly showHeader = input(true);
  readonly hasBreadcrumbs = computed(() => this.breadcrumbs().length > 0);
}
