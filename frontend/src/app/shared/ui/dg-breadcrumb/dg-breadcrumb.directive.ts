import { Directive, input } from '@angular/core';

@Directive({
  selector: 'nav[dgBreadcrumb]',
  standalone: true,
  host: {
    '[attr.aria-label]': 'ariaLabel()',
    '[attr.data-dg-breadcrumb]': '""',
  },
})
export class DgBreadcrumbDirective {
  readonly ariaLabel = input('Breadcrumb');
}
