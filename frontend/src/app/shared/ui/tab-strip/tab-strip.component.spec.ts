import { Component, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { TabStripComponent, TabStripItem } from './tab-strip.component';

@Component({
  standalone: true,
  imports: [TabStripComponent],
  template: `
    <dg-tab-strip
      [items]="items"
      [activeId]="activeId()"
      ariaLabel="Example tabs"
      (selected)="activeId.set($event)"
    />
  `,
})
class HostComponent {
  readonly items: ReadonlyArray<TabStripItem> = [
    { id: 'overview', label: 'Overview', kicker: 'Guide' },
    { id: 'roster', label: 'Roster' },
  ];
  readonly activeId = signal('overview');
}

describe('TabStripComponent', () => {
  it('renders items and updates selection when clicked', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const buttons = fixture.nativeElement.querySelectorAll('button[role="tab"]') as NodeListOf<HTMLButtonElement>;
    expect(buttons.length).toBe(2);
    expect(buttons[0].getAttribute('aria-selected')).toBe('true');
    expect(buttons[0].hasAttribute('data-active')).toBeTrue();

    buttons[1].click();
    fixture.detectChanges();

    expect(buttons[1].getAttribute('aria-selected')).toBe('true');
    expect(buttons[1].hasAttribute('data-active')).toBeTrue();
  });
});
