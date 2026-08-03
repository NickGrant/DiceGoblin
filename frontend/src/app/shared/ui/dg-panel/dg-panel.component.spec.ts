import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { DgPanelComponent } from './dg-panel.component';

@Component({
  standalone: true,
  imports: [DgPanelComponent],
  template: `
    <dg-panel variant="surface" heading="Loot" actionLabel="Inspect" (action)="clicked = true">
      <p>Content</p>
    </dg-panel>
  `,
})
class HostComponent {
  clicked = false;
}

describe('DgPanelComponent', () => {
  it('renders projected content and emits header actions', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const panel = fixture.nativeElement.querySelector('dg-panel') as HTMLElement;
    const action = fixture.nativeElement.querySelector('button') as HTMLButtonElement;

    expect(panel.getAttribute('data-dg-panel')).toBe('surface');
    expect(panel.textContent).toContain('Loot');
    expect(panel.textContent).toContain('Content');

    action.click();
    expect(fixture.componentInstance.clicked).toBeTrue();
  });
});
