import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { DgProgressComponent } from './dg-progress.component';

@Component({
  standalone: true,
  imports: [DgProgressComponent],
  template: `<dg-progress [value]="45" [max]="50" ariaLabel="HP" />`,
})
class HostComponent {}

describe('DgProgressComponent', () => {
  it('clamps progress values and exposes progressbar metadata', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const progress = fixture.nativeElement.querySelector('dg-progress') as HTMLElement;
    const fill = progress.querySelector('span') as HTMLElement;

    expect(progress.getAttribute('role')).toBe('progressbar');
    expect(progress.getAttribute('aria-valuenow')).toBe('45');
    expect(progress.textContent).toContain('45/50');
    expect(fill.style.getPropertyValue('--dg-progress-value')).toBe('90%');
  });
});
