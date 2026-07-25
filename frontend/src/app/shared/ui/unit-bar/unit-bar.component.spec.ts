import { ComponentFixture, TestBed } from '@angular/core/testing';
import { UnitBarComponent } from './unit-bar.component';

describe('UnitBarComponent', () => {
  let fixture: ComponentFixture<UnitBarComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [UnitBarComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(UnitBarComponent);
    fixture.componentRef.setInput('unit', {
      id: 'u1',
      name: 'Fang',
      level: 4,
      tier: 2,
      unit_type_slug: 'frontline_bruiser_t1',
      unit_type_name: 'Bruiser',
      splice_variant_slug: 'rat_splice',
      splice_variant_name: 'Rat-Spliced',
      current_hp: 12,
      max_hp: 20,
      total_attack: 7,
      total_defense: 5,
      total_precision: 6,
      total_resolve: 4,
      xp: 30,
      xp_to_next_level: 70,
    });
  });

  it('renders moderate unit information with hp and xp progress bars', () => {
    fixture.componentRef.setInput('positionLabel', 'Slot B2');
    fixture.detectChanges();

    const host = fixture.nativeElement as HTMLElement;
    const fills = host.querySelectorAll('.unit-bar__progress-fill') as NodeListOf<HTMLElement>;
    const tierIcon = host.querySelector('.unit-bar__tier-icon') as HTMLElement;
    const roleIcon = host.querySelector('.unit-bar__role-icon');
    expect(host.textContent).toContain('Fang');
    expect(host.textContent).toContain('Level 4');
    expect(host.textContent).toContain('Tier II');
    expect(host.textContent).toContain('Rat Kin');
    expect(host.textContent).toContain('Slot B2');
    expect(host.textContent).toContain('12/20 HP');
    expect(host.textContent).toContain('70 XP to next');
    expect(host.textContent).toContain('PRC');
    expect(host.textContent).toContain('RES');
    expect(host.textContent).toContain('6');
    expect(host.textContent).toContain('4');
    expect(tierIcon.classList.contains('dg-tier-indicator--2')).toBeTrue();
    expect(tierIcon.getAttribute('aria-label')).toBe('Tier II');
    expect(roleIcon).not.toBeNull();
    expect(fills[0].style.width).toBe('60%');
    expect(fills[1].style.width).toBe('30%');
  });
});
