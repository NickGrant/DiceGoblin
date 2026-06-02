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
});
