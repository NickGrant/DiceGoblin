import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { UnitGridObjectComponent } from './unit-grid-object.component';

@Component({
  standalone: true,
  imports: [UnitGridObjectComponent],
  template: `
    <dg-unit-grid-object
      [object]="unit"
      [progressBar]="progressBar"
      [linkEnabled]="linkEnabled"
      [subtitle]="subtitle"
      [surfaceTone]="surfaceTone"
      [showLockBadge]="showLockBadge"
      [fillHeight]="fillHeight"
    />
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
  readonly progressBar = {
    percent: 75,
    title: 'XP 90 of 120, gained 30',
    leftLabel: 'XP 90/120',
    tone: 'xp' as const,
    celebrationLabel: 'Level Up',
  };
  linkEnabled = true;
  subtitle: string | null = null;
  surfaceTone: 'default' | 'enemy' = 'default';
  showLockBadge = true;
  fillHeight = true;
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
    expect(compiled.textContent).toContain('II');
    expect(compiled.textContent).toContain('Goblin');
    expect(compiled.textContent).toContain('Level 3');
    expect(compiled.textContent).not.toContain('Tier 2');
    expect(compiled.textContent).not.toContain('Unit Record');
    expect(compiled.textContent).toContain('XP 90/120');
    expect(compiled.textContent).toContain('Level Up');
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

  it('can render as a non-link enemy surface with custom subtitle', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
      providers: [provideRouter([])],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.componentInstance.linkEnabled = false;
    fixture.componentInstance.subtitle = 'Enemy Unit';
    fixture.componentInstance.surfaceTone = 'enemy';
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('a')).toBeNull();
    expect(compiled.querySelector('article.unit-grid-object--enemy')).not.toBeNull();
    expect(compiled.textContent).toContain('Enemy Unit');
  });

  it('can hide the lock badge and omit full-height stretching', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
      providers: [provideRouter([])],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.componentInstance.unit.locked = true;
    fixture.componentInstance.showLockBadge = false;
    fixture.componentInstance.fillHeight = false;
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const card = compiled.querySelector('.unit-grid-object');
    expect(compiled.textContent).not.toContain('Locked In Run');
    expect(card?.classList.contains('h-100')).toBeFalse();
  });
});
