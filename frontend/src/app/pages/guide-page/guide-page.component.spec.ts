import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { SessionService } from '../../core/services/session/session.service';
import { GuidePageComponent } from './guide-page.component';

class SessionServiceStub {
  readonly session = signal<{
    isAuthenticated: boolean;
    displayName: string;
    userId: number | null;
    csrfToken: string | null;
  }>({
    isAuthenticated: false,
    displayName: 'Visitor',
    userId: null,
    csrfToken: null,
  });
  readonly hasActiveRun = signal(false);
  readonly profileData = signal<any>(null);
  readonly initialize = jasmine.createSpy('initialize').and.resolveTo();
}

describe('GuidePageComponent', () => {
  let sessionService: SessionServiceStub;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [GuidePageComponent],
      providers: [
        provideRouter([]),
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();
    sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
  });

  it('renders the field guide codex shell and overview chapter by default', () => {
    const fixture = TestBed.createComponent(GuidePageComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Field Guide');
    expect(text).toContain('Overview');
    expect(text).toContain('Warband');
    expect(text).toContain('Dice');
    expect(text).toContain('Expeditions');
    expect(text).toContain('Permanent unlocks');
    expect(text).toContain('Run node reference');
  });

  it('switches to the warband chapter and shows the current promotion paths', () => {
    const fixture = TestBed.createComponent(GuidePageComponent);
    const component = fixture.componentInstance as any;
    component.setActiveChapter('warband');
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Current unit codex');
    expect(text).toContain('Enforcer or Pit Fighter');
    expect(text).toContain('Warcaller or Mascot');
    expect(text).toContain('Trickshot or Plaguehand');
    expect(text).toContain('Tier 3 promotions still expect the authored rare region item.');
  });

  it('switches to the dice chapter and shows dice materials and sizes', () => {
    const fixture = TestBed.createComponent(GuidePageComponent);
    const component = fixture.componentInstance as any;
    component.setActiveChapter('dice');
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Dice rarity ladder');
    expect(text).toContain('Common');
    expect(text).toContain('Legendary');
    expect(text).toContain('d20');
    expect(text).toContain('Common terms you will see on dice');
  });

  it('initializes session state on init', () => {
    const fixture = TestBed.createComponent(GuidePageComponent);
    fixture.detectChanges();

    expect(sessionService.initialize).toHaveBeenCalled();
  });

  it('highlights acquired feature and unit unlocks for authenticated players', () => {
    sessionService.session.set({
      isAuthenticated: true,
      displayName: 'Commander',
      userId: 5,
      csrfToken: 'token',
    });
    sessionService.profileData.set({
      feature_unlocks: ['academy', 'sell_bonus'],
      unit_type_unlocks: ['support_banner_t1'],
      regions: [],
    });

    const fixture = TestBed.createComponent(GuidePageComponent);
    const component = fixture.componentInstance as any;
    component.setActiveChapter('overview');
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelectorAll('.guide-tile--acquired').length).toBe(2);

    component.setActiveChapter('warband');
    fixture.detectChanges();

    expect(compiled.querySelectorAll('.guide-unit-tile--acquired').length).toBe(1);
    expect(compiled.textContent).toContain('Acquired');
  });

  it('reveals biome units only for authenticated players who have completed the matching biome', () => {
    sessionService.session.set({
      isAuthenticated: true,
      displayName: 'Commander',
      userId: 5,
      csrfToken: 'token',
    });
    sessionService.profileData.set({
      feature_unlocks: [],
      unit_type_unlocks: [],
      regions: [
        { id: '1', slug: 'the_farm', name: 'The Farm', theme: 'farm', recommended_level: 1, energy_cost: 3, is_enabled: true, is_unlocked: true, is_completed: true, unlocked_at: '2026-06-01T00:00:00Z' },
        { id: '2', slug: 'mountains', name: 'Mountains', theme: 'mountain', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: true, is_completed: true, unlocked_at: '2026-06-02T00:00:00Z' },
        { id: '3', slug: 'swamps', name: 'Swamps', theme: 'swamp', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: true, is_completed: false, unlocked_at: '2026-06-03T00:00:00Z' },
      ],
      region_unlocks: [
        { region_id: '1', region_slug: 'the_farm', region_name: 'The Farm', unlocked_at: '2026-06-01T00:00:00Z' },
        { region_id: '2', region_slug: 'mountains', region_name: 'Mountains', unlocked_at: '2026-06-02T00:00:00Z' },
        { region_id: '3', region_slug: 'swamps', region_name: 'Swamps', unlocked_at: '2026-06-03T00:00:00Z' },
      ],
    });

    const fixture = TestBed.createComponent(GuidePageComponent);
    const component = fixture.componentInstance as any;
    component.setActiveChapter('warband');
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Biome encounter units you have uncovered');
    expect(compiled.textContent).toContain('Kobold Skirmisher');
    expect(compiled.textContent).toContain('Kobold Warchief');
    expect(compiled.textContent).toContain('Biome: Mountains');
  });

  it('keeps the units section hidden when the player has not completed a matching biome', () => {
    sessionService.session.set({
      isAuthenticated: true,
      displayName: 'Commander',
      userId: 5,
      csrfToken: 'token',
    });
    sessionService.profileData.set({
      feature_unlocks: [],
      unit_type_unlocks: [],
      regions: [
        { id: '1', slug: 'the_farm', name: 'The Farm', theme: 'farm', recommended_level: 1, energy_cost: 3, is_enabled: true, is_unlocked: true, is_completed: true, unlocked_at: '2026-06-01T00:00:00Z' },
        { id: '2', slug: 'mountains', name: 'Mountains', theme: 'mountain', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: true, is_completed: false, unlocked_at: '2026-06-02T00:00:00Z' },
        { id: '3', slug: 'swamps', name: 'Swamps', theme: 'swamp', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: false, is_completed: false, unlocked_at: null },
      ],
      region_unlocks: [
        { region_id: '1', region_slug: 'the_farm', region_name: 'The Farm', unlocked_at: '2026-06-01T00:00:00Z' },
        { region_id: '2', region_slug: 'mountains', region_name: 'Mountains', unlocked_at: '2026-06-02T00:00:00Z' },
      ],
    });

    const fixture = TestBed.createComponent(GuidePageComponent);
    const component = fixture.componentInstance as any;
    component.setActiveChapter('warband');
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).not.toContain('Biome encounter units you have uncovered');
    expect(compiled.textContent).not.toContain('Kobold Skirmisher');
  });
});
