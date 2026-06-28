import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { FocusLayoutComponent } from './focus-layout.component';

@Component({
  standalone: true,
  imports: [FocusLayoutComponent],
  template: `
    <dg-focus-layout panelWidth="20rem">
      <div focusLayoutPrimary>Primary content</div>
      <aside focusLayoutPanel>Panel content</aside>
    </dg-focus-layout>
  `,
})
class HostComponent {}

describe('FocusLayoutComponent', () => {
  it('renders primary and panel slots', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const host = fixture.nativeElement as HTMLElement;
    expect(host.textContent).toContain('Primary content');
    expect(host.textContent).toContain('Panel content');
    expect(host.querySelector('.focus-layout')).not.toBeNull();
  });
});
