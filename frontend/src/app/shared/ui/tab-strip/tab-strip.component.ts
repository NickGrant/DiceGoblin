import {
  AfterViewInit,
  Component,
  Directive,
  ElementRef,
  OnDestroy,
  QueryList,
  ViewChildren,
  inject,
  input,
  output,
} from '@angular/core';
import { FocusKeyManager, FocusableOption } from '@angular/cdk/a11y';
import { Subject, takeUntil } from 'rxjs';

export type TabStripItem = {
  id: string;
  label: string;
  kicker?: string;
  ariaLabel?: string;
  disabled?: boolean;
};

@Directive({
  selector: 'button[dgTabOption]',
  standalone: true,
  exportAs: 'dgTabOption',
})
export class DgTabOptionDirective implements FocusableOption {
  private readonly elementRef = inject<ElementRef<HTMLButtonElement>>(ElementRef);

  disabled = false;
  label = '';

  focus(): void {
    this.elementRef.nativeElement.focus();
  }

  getLabel(): string {
    return this.label;
  }
}

@Component({
  selector: 'dg-tab-strip',
  standalone: true,
  imports: [DgTabOptionDirective],
  templateUrl: './tab-strip.component.html',
  styleUrl: './tab-strip.component.scss',
  host: {
    '[attr.data-dg-tabs]': '""',
  },
})
export class TabStripComponent implements AfterViewInit, OnDestroy {
  readonly items = input<ReadonlyArray<TabStripItem>>([]);
  readonly activeId = input<string>('');
  readonly ariaLabel = input('Tabs');

  readonly selected = output<string>();

  @ViewChildren(DgTabOptionDirective) private readonly tabOptions?: QueryList<DgTabOptionDirective>;

  private readonly destroyed$ = new Subject<void>();
  private keyManager?: FocusKeyManager<DgTabOptionDirective>;

  isActive(id: string): boolean {
    return this.activeId() === id;
  }

  tabIndexFor(item: TabStripItem): number {
    if (item.disabled) {
      return -1;
    }

    return this.isActive(item.id) ? 0 : -1;
  }

  select(item: TabStripItem): void {
    if (item.disabled) {
      return;
    }

    this.selected.emit(item.id);
  }

  handleKeydown(event: KeyboardEvent): void {
    this.syncOptions();
    this.keyManager?.onKeydown(event);

    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    const activeItem = this.items()[this.keyManager?.activeItemIndex ?? -1];
    if (activeItem) {
      event.preventDefault();
      this.select(activeItem);
    }
  }

  ngAfterViewInit(): void {
    this.syncOptions();
    this.keyManager = new FocusKeyManager(this.tabOptions ?? new QueryList<DgTabOptionDirective>())
      .withHorizontalOrientation('ltr')
      .withWrap()
      .withHomeAndEnd();

    this.tabOptions?.changes.pipe(takeUntil(this.destroyed$)).subscribe(() => {
      this.syncOptions();
    });
  }

  ngOnDestroy(): void {
    this.destroyed$.next();
    this.destroyed$.complete();
  }

  private syncOptions(): void {
    const options = this.tabOptions?.toArray() ?? [];
    const items = this.items();

    options.forEach((option, index) => {
      const item = items[index];
      option.disabled = item?.disabled ?? false;
      option.label = item?.ariaLabel || item?.label || '';
    });

    const activeIndex = items.findIndex((item) => this.isActive(item.id));
    if (activeIndex >= 0) {
      this.keyManager?.setActiveItem(activeIndex);
    }
  }
}
