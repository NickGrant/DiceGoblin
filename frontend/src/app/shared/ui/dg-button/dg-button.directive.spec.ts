import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { DgButtonDirective } from './dg-button.directive';

@Component({
  standalone: true,
  imports: [DgButtonDirective],
  template: `
    <button dgButton="primary" size="lg">Raid</button>
    <a dgButton="ghost" disabled href="/danger">Locked</a>
  `,
})
class HostComponent {}

describe('DgButtonDirective', () => {
  it('binds button variants through host attributes without wrapper markup', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const button = fixture.nativeElement.querySelector('button') as HTMLButtonElement;
    const anchor = fixture.nativeElement.querySelector('a') as HTMLAnchorElement;

    expect(button.getAttribute('data-dg-button')).toBe('primary');
    expect(button.getAttribute('data-size')).toBe('lg');
    expect(anchor.getAttribute('data-dg-button')).toBe('ghost');
    expect(anchor.getAttribute('aria-disabled')).toBe('true');
    expect(anchor.getAttribute('tabindex')).toBe('-1');
  });
});
