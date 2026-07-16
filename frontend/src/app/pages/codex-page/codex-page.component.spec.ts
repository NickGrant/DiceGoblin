import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { SessionService } from '../../core/services/session/session.service';
import { CodexPageComponent } from './codex-page.component';

class SessionServiceStub {
  readonly session = signal({
    isAuthenticated: true,
    displayName: 'Commander',
    userId: 5,
    csrfToken: 'token',
  });
  readonly hasActiveRun = signal(false);
  readonly profileData = signal<any>({
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
  readonly initialize = jasmine.createSpy('initialize').and.resolveTo();
}

describe('CodexPageComponent', () => {
  let sessionService: SessionServiceStub;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CodexPageComponent],
      providers: [
        provideRouter([]),
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();
    sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
  });

  it('renders codex progress and vertical category navigation', () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Codex');
    expect(text).toContain('Features');
    expect(text).toContain('Units');
    expect(text).toContain('Affixes');
    expect(text).toContain('Enemies');
    expect(text).toContain('Lore');
    expect(text).toContain('Feature Unlocks');
    expect(text).not.toContain('Map Glossary');
  });

  it('keeps each progress label and value in the same heading row', () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    fixture.detectChanges();

    const heading = fixture.nativeElement.querySelector('.stat-head') as HTMLElement;
    expect(heading).not.toBeNull();
    expect(heading.querySelector('span')?.textContent).toContain('Feature unlocks');
    expect(heading.querySelector('strong')?.textContent).toContain('2/10');
  });

  it('shows locked feature unlocks with a fontawesome lock icon', () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Permanent account upgrades');
    expect(fixture.nativeElement.querySelector('.lock fa-icon')).not.toBeNull();
  });

  it('shows all units as a locked and unlocked hierarchy on a separate category', () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    const component = fixture.componentInstance as any;
    component.setActiveCategory('units');
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    const unitEntries = fixture.nativeElement.querySelectorAll('.unit-entry');
    expect(unitEntries.length).toBe(18);
    expect(text).toContain('Bannerbearer');
    expect(text).toContain('A support specialist that reinforces nearby allies');
    expect(text).toContain('???');
    expect(text).not.toContain('Unknown Class');
    expect(fixture.nativeElement.querySelector('.role-icon')).not.toBeNull();
  });

  it('switches categories and shows discovered affixes', () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    const component = fixture.componentInstance as any;
    component.setActiveCategory('affixes');
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Affix Archive');
    expect(text).toContain('Bulwark');
    expect(text).toContain('Execute');
    expect(text).toContain('Unknown Affix');
  });

  it('shows unlocked enemies and silhouettes locked enemy sprites', () => {
    sessionService.profileData.update((profile) => ({
      ...profile,
      regions: profile.regions.map((region: any) => (
        region.slug === 'mountains' ? { ...region, is_completed: false } : region
      )),
    }));

    const fixture = TestBed.createComponent(CodexPageComponent);
    const component = fixture.componentInstance as any;
    component.setActiveCategory('enemies');
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Enemy Record');
    expect(text).toContain('Unknown Enemy');
    expect(fixture.nativeElement.querySelector('.silhouette')).not.toBeNull();
  });

  it('shows lore entries only on the lore category', () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    const component = fixture.componentInstance as any;
    component.setActiveCategory('lore');
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Lore Archive');
    expect(text).toContain('Camp Intro');
    expect(text).toContain('Mountain Warning');
  });

  it('initializes session state on init', () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    fixture.detectChanges();

    expect(sessionService.initialize).toHaveBeenCalled();
  });
});
