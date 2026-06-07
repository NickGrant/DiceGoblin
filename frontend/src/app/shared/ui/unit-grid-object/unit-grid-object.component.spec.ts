import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { UnitGridObjectComponent } from './unit-grid-object.component';

@Component({
  standalone: true,
  imports: [UnitGridObjectComponent],
  template: `
    <dg-unit-grid-object [object]="unit" />
  `,
})
class HostComponent {
  readonly unit = {
    id: 'u1',
    name: 'Fang',
    unit_type_name: 'Goblin',
    level: 3,
    tier: 2,
    locked: false,
  };
}

describe('UnitGridObjectComponent', () => {
  it('renders unit details', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
      providers: [provideRouter([])],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Fang');
    expect(compiled.textContent).toContain('Goblin');
    expect(compiled.textContent).toContain('Level 3');
    expect(compiled.textContent).toContain('Tier 2');
    expect(compiled.querySelector('a')?.getAttribute('href')).toContain('/warband/units/u1');
  });

  it('renders a lock badge for run-locked units', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
      providers: [provideRouter([])],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.componentInstance.unit.locked = true;
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Locked In Run');
  });
});
