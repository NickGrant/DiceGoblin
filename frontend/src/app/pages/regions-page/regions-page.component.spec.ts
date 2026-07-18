import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { RegionsPageComponent } from './regions-page.component';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';

class RunServiceStub {
  createRun = jasmine.createSpy('createRun').and.resolveTo({ ok: true });
}

class SessionServiceStub {
  readonly hasActiveRun = signal(false);
  readonly session = signal({
    isAuthenticated: true,
    displayName: 'Ravin',
    userId: '42',
    csrfToken: 'token',
  });
  readonly profileData = signal<any>({
    active_run: null,
    seen_dialogues: [],
    regions: [
      { id: '1', slug: 'the_farm', name: 'The Farm', theme: 'farm', recommended_level: 1, energy_cost: 3, is_enabled: true, is_unlocked: true, is_completed: true, unlocked_at: '2026-06-01T00:00:00Z' },
      { id: '2', slug: 'mountains', name: 'Mountains', theme: 'mountain', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: true, is_completed: false, unlocked_at: '2026-06-02T00:00:00Z' },
      { id: '3', slug: 'swamps', name: 'Swamps', theme: 'swamp', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: false, is_completed: false, unlocked_at: null },
    ],
    region_unlocks: [
      { region_id: '1', region_slug: 'the_farm', unlocked_at: '2026-06-01T00:00:00Z' },
      { region_id: '2', region_slug: 'mountains', unlocked_at: '2026-06-02T00:00:00Z' },
    ],
  });
  refreshProfile = jasmine.createSpy('refreshProfile').and.resolveTo(undefined);
}

describe('RegionsPageComponent', () => {
  let router: Router;
  let runService: RunServiceStub;
  let sessionService: SessionServiceStub;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [RegionsPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    router = TestBed.inject(Router);
    runService = TestBed.inject(RunService) as unknown as RunServiceStub;
    sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
  });

  it('computes unlocked regions from profile data', () => {
    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    expect(component.regions().filter((region) => region.is_unlocked).length).toBe(2);
    expect(component.regions().find((region) => region.slug === 'mountains')?.is_unlocked).toBeTrue();
    expect(component.regions().find((region) => region.slug === 'swamps')?.is_unlocked).toBeFalse();
  });

  it('starts a run and routes to the map for unlocked regions', async () => {
    spyOn(router, 'navigateByUrl').and.resolveTo(true);
    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();

    await fixture.componentInstance.startRegionRun('2', 'mountains');

    expect(runService.createRun).toHaveBeenCalledWith(2);
    expect(router.navigateByUrl).toHaveBeenCalledWith('/run/map');
  });

  it('shows continue run only for the active region tile', async () => {
    const sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    sessionService.hasActiveRun.set(true);
    sessionService.profileData.set({
      active_run: { region_id: '2' },
      seen_dialogues: [],
      regions: [
        { id: '1', slug: 'the_farm', name: 'The Farm', theme: 'farm', recommended_level: 1, energy_cost: 3, is_enabled: true, is_unlocked: true, is_completed: true, unlocked_at: '2026-06-01T00:00:00Z' },
        { id: '2', slug: 'mountains', name: 'Mountains', theme: 'mountain', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: true, is_completed: false, unlocked_at: '2026-06-02T00:00:00Z' },
        { id: '3', slug: 'swamps', name: 'Swamps', theme: 'swamp', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: false, is_completed: false, unlocked_at: null },
      ],
      region_unlocks: [
        { region_id: '1', region_slug: 'the_farm', unlocked_at: '2026-06-01T00:00:00Z' },
        { region_id: '2', region_slug: 'mountains', unlocked_at: '2026-06-02T00:00:00Z' },
      ],
    });

    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const farm = component.regions().find((region) => region.slug === 'the_farm')!;
    const mountains = component.regions().find((region) => region.slug === 'mountains')!;

    expect(component.regionActionLabel(farm)).toBe('Start Run');
    expect(component.regionActionDisabled(farm)).toBeTrue();
    expect(component.regionActionLabel(mountains)).toBe('Continue Run');
    expect(component.regionActionDisabled(mountains)).toBeFalse();
  });

  it('defaults inspection to the first region and uses hover preview', () => {
    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    expect(component.inspectedRegion()?.slug).toBe('the_farm');

    component.previewRegion('mountains');
    expect(component.inspectedRegion()?.slug).toBe('mountains');
  });

  it('opens a confirm state before starting a new run', async () => {
    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const mountains = component.regions().find((region) => region.slug === 'mountains')!;

    await component.activateRegion(mountains);

    expect(component.pendingRegion()?.slug).toBe('mountains');
  });

  it('marks locked region art with lock styling classes', () => {
    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();
    fixture.componentInstance.previewRegion('swamps');
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const lockedTileBadge = host.querySelector('.region-page__tile.is-locked .region-page__tile-badge');
    const lockedInspectImage = host.querySelector('.region-page__inspect-image.is-locked');

    expect(lockedTileBadge?.classList.contains('is-locked')).toBeTrue();
    expect(lockedInspectImage).not.toBeNull();
  });
});
