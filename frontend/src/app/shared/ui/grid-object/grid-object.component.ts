import { Directive, TemplateRef, input } from '@angular/core';

@Directive()
export abstract class GridObjectComponent<TObject> {
  readonly object = input.required<TObject>();
  readonly footerTemplate = input<TemplateRef<unknown> | null>(null);
  readonly footerContext = input<unknown>(null);
}
