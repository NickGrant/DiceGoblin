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

  it('renders the public guide experience by default', () => {
    const fixture = TestBed.createComponent(GuidePageComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Guide');
    expect(text).toContain('Public Guide');
    expect(text).toContain('Core Loop');
    expect(text).toContain('Starter classes and why they matter');
    expect(text).toContain('Rarity tells you ceiling. Affixes tell you intent.');
    expect(text).toContain('Read the map before greed takes over');
  });

  it('renders the codex view when the variant is switched', () => {
    sessionService.session.set({
      isAuthenticated: true,
      displayName: 'Commander',
      userId: 5,
      csrfToken: 'token',
    });
    sessionService.profileData.set({
      feature_unlocks: ['academy', 'sell_bonus'],
      unit_type_unlocks: ['support_banner_t1'],
      seen_dialogues: ['camp_intro', 'mountain_warning'],
      dice: [
        { id: 'd1', affixes: [{ name: 'Bulwark', affix_slug: 'bulwark', value: 1 }] },
        { id: 'd2', affixes: [{ name: 'Execute', affix_slug: 'execute', value: 1 }] },
      ],
      regions: [
        { id: '1', slug: 'the_farm', name: 'The Farm', theme: 'farm', recommended_level: 1, energy_cost: 3, is_enabled: true, is_unlocked: true, is_completed: true, unlocked_at: '2026-06-01T00:00:00Z' },
        { id: '2', slug: 'mountains', name: 'Mountains', theme: 'mountain', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: true, is_completed: true, unlocked_at: '2026-06-02T00:00:00Z' },
      ],
      region_unlocks: [
        { region_id: '1', region_slug: 'the_farm', region_name: 'The Farm', unlocked_at: '2026-06-01T00:00:00Z' },
        { region_id: '2', region_slug: 'mountains', region_name: 'Mountains', unlocked_at: '2026-06-02T00:00:00Z' },
      ],
      active_run: { region_name: 'Mountains' },
    });

    const fixture = TestBed.createComponent(GuidePageComponent);
    const component = fixture.componentInstance as any;
    component.setVariant('codex');
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Codex');
    expect(text).toContain('Authenticated Codex');
    expect(text).toContain('Feature unlocks');
    expect(text).toContain('Seen affixes');
    expect(text).toContain('Defeated enemy types');
    expect(text).toContain('Lore Archive');
    expect(text).toContain('Camp Intro');
    expect(text).toContain('Mountain Warning');
    expect(text).toContain('Kobold Skirmisher');
  });

  it('initializes session state on init', () => {
    const fixture = TestBed.createComponent(GuidePageComponent);
    fixture.detectChanges();

    expect(sessionService.initialize).toHaveBeenCalled();
  });

  it('marks known feature unlocks and unit unlocks in codex mode', () => {
    sessionService.session.set({
      isAuthenticated: true,
      displayName: 'Commander',
      userId: 5,
      csrfToken: 'token',
    });
    sessionService.profileData.set({
      feature_unlocks: ['academy', 'sell_bonus'],
      unit_type_unlocks: ['support_banner_t1'],
      seen_dialogues: [],
      dice: [],
      regions: [],
      region_unlocks: [],
      active_run: null,
    });

    const fixture = TestBed.createComponent(GuidePageComponent);
    const component = fixture.componentInstance as any;
    component.setVariant('codex');
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Academy');
    expect(text).toContain('Seen');
    expect(text).toContain('Bannerbearer');
    expect(text).toContain('Unlocked');
  });

  it('shows a locked lore empty state when no dialogue has been seen', () => {
    sessionService.session.set({
      isAuthenticated: true,
      displayName: 'Commander',
      userId: 5,
      csrfToken: 'token',
    });
    sessionService.profileData.set({
      feature_unlocks: [],
      unit_type_unlocks: [],
      seen_dialogues: [],
      dice: [],
      regions: [],
      region_unlocks: [],
      active_run: null,
    });

    const fixture = TestBed.createComponent(GuidePageComponent);
    const component = fixture.componentInstance as any;
    component.setVariant('codex');
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('No lore pages unlocked yet');
    expect(text).not.toContain('Camp Intro');
  });
});
