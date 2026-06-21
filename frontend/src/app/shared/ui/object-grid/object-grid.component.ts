import { NgClass, NgComponentOutlet, NgTemplateOutlet } from '@angular/common';
import { Component, TemplateRef, Type, computed, effect, input, signal } from '@angular/core';
import { DgAlertComponent } from '../dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../dg-command-btn/dg-command-btn.directive';

@Component({
  selector: 'dg-object-grid',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, NgClass, NgComponentOutlet, NgTemplateOutlet],
  templateUrl: './object-grid.component.html',
  styleUrl: './object-grid.component.scss',
})
export class ObjectGridComponent {
  readonly objects = input.required<readonly unknown[]>();
  readonly objectComponent = input.required<Type<unknown>>();
  readonly objectInputs = input<((object: unknown) => Record<string, unknown>) | null>(null);
  readonly footerTemplate = input<unknown | null>(null);
  readonly leadingTemplate = input<TemplateRef<unknown> | null>(null);
  readonly leadingContext = input<unknown>(null);
  readonly emptyMessage = input('Nothing available.');
  readonly pageSize = input(6);
  readonly columnClasses = input('object-grid__col--halves');

  readonly currentPage = signal(1);
  readonly objectsPerPage = computed(() => {
    const reservedSlots = this.leadingTemplate() ? 1 : 0;
    return Math.max(1, this.pageSize() - reservedSlots);
  });
  readonly hasLeadingTile = computed(() => this.leadingTemplate() !== null);
  readonly hasVisibleContent = computed(() => this.objects().length > 0 || this.hasLeadingTile());
  readonly totalPages = computed(() => {
    const size = this.objectsPerPage();
    return Math.max(1, Math.ceil(this.objects().length / size));
  });
  readonly pagedObjects = computed(() => {
    const size = this.objectsPerPage();
    const start = (this.currentPage() - 1) * size;
    return this.objects().slice(start, start + size);
  });

  constructor() {
    effect(() => {
      const page = this.currentPage();
      const totalPages = this.totalPages();
      if (page > totalPages) {
        this.currentPage.set(totalPages);
      }
    });
  }

  buildInputs(object: unknown): Record<string, unknown> {
    return {
      object,
      footerTemplate: this.footerTemplate(),
      footerContext: object,
      ...(this.objectInputs()?.(object) ?? {}),
    };
  }

  previousPage(): void {
    this.currentPage.update((page) => Math.max(1, page - 1));
  }

  nextPage(): void {
    this.currentPage.update((page) => Math.min(this.totalPages(), page + 1));
  }

  trackByObject = (_index: number, object: unknown): unknown =>
    typeof object === 'object' && object !== null && 'id' in object
      ? (object as { id: unknown }).id
      : object;
}
