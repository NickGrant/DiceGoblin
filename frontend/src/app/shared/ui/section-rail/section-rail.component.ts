import { Component, input, output } from '@angular/core';

export type DgSectionRailItem = {
  id: string;
  label: string;
  summary?: string;
  href?: string;
  ariaLabel?: string;
  disabled?: boolean;
};

export type DgSectionRailSelection = {
  item: DgSectionRailItem;
  event: MouseEvent;
};

@Component({
  selector: 'dg-section-rail',
  standalone: true,
  templateUrl: './section-rail.component.html',
  styleUrl: './section-rail.component.scss',
  host: {
    '[attr.data-dg-section-rail]': '""',
  },
})
export class SectionRailComponent {
  readonly kicker = input('');
  readonly heading = input('');
  readonly iconSrc = input('');
  readonly iconAlt = input('');
  readonly ariaLabel = input('Sections');
  readonly items = input<ReadonlyArray<DgSectionRailItem>>([]);
  readonly activeId = input<string | null>(null);

  readonly selected = output<DgSectionRailSelection>();

  isActive(item: DgSectionRailItem): boolean {
    return this.activeId() === item.id;
  }

  select(item: DgSectionRailItem, event: MouseEvent): void {
    if (item.disabled) {
      event.preventDefault();
      return;
    }

    this.selected.emit({ item, event });
  }
}
