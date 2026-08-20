import { Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import type { PageFrameBreadcrumb } from '../page-frame/page-frame.component';

export type PageHeroMode = 'normal' | 'tall';

@Component({
  selector: 'page-hero',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './page-hero.component.html',
  styleUrl: './page-hero.component.scss',
})
export class PageHeroComponent {
  readonly breadcrumbs = input<readonly PageFrameBreadcrumb[]>([]);
  readonly title = input('');
  readonly subtitle = input<string | null>(null);
  readonly mode = input<PageHeroMode>('normal');
  readonly backgroundUrl = input<string | null>(null);

  readonly backgroundStyle = computed(() => {
    const url = this.backgroundUrl();
    return url ? `url('${url}')` : null;
  });
}
