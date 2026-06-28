import { Component, input, output } from '@angular/core';

export type TabStripItem = {
  id: string;
  label: string;
  kicker?: string;
  ariaLabel?: string;
  disabled?: boolean;
};

@Component({
  selector: 'dg-tab-strip',
  standalone: true,
  templateUrl: './tab-strip.component.html',
  styleUrl: './tab-strip.component.scss',
})
export class TabStripComponent {
  readonly items = input<ReadonlyArray<TabStripItem>>([]);
  readonly activeId = input<string>('');
  readonly ariaLabel = input('Tabs');

  readonly selected = output<string>();

  isActive(id: string): boolean {
    return this.activeId() === id;
  }

  select(item: TabStripItem): void {
    if (item.disabled) {
      return;
    }

    this.selected.emit(item.id);
  }
}
