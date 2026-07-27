import { Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';

export interface PageFrameBreadcrumb {
  label: string;
  route?: string | readonly (string | number)[];
}

export type PageFrameHeaderVariant = 'red' | 'guide' | 'home';

@Component({
  selector: 'page-frame',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './page-frame.component.html',
  styleUrl: './page-frame.component.scss',
  host: {
    class: 'dg-motion-surface-enter'
  }
})
export class PageFrameComponent {
  readonly breadcrumbs = input<readonly PageFrameBreadcrumb[]>([]);
  readonly title = input('');
  readonly subtitle = input<string | null>(null);
  readonly showHeader = input(true);
  readonly headerVariant = input<PageFrameHeaderVariant>('red');
  readonly normalizedBreadcrumbs = computed<readonly PageFrameBreadcrumb[]>(() => {
    const inputCrumbs = [...this.breadcrumbs()];
    const first = inputCrumbs[0];

    if (first && first.route === undefined && first.label.trim().toLowerCase() === 'home') {
      return inputCrumbs;
    }

    let homeLabel = 'HQ';
    let remaining = inputCrumbs;

    if (first && this.isHomeRoute(first.route)) {
      homeLabel = first.label || 'HQ';
      remaining = inputCrumbs.slice(1);
    }

    return [{ label: homeLabel, route: '/home' }, ...remaining];
  });

  private isHomeRoute(route?: string | readonly (string | number)[]): boolean {
    if (typeof route === 'string') {
      return route === '/home';
    }

    if (Array.isArray(route)) {
      return route.join('/') === '/home' || route.join('/') === 'home';
    }

    return false;
  }
}
