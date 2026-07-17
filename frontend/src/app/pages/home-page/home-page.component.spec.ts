import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { RouterLink, provideRouter } from '@angular/router';
import { HomePageComponent } from './home-page.component';
import { SessionService } from '../../core/services/session/session.service';

type ProfileState = {
  activeRunId: string | null;
  activeSquadName: string | null;
  unitCount: number;
  squadCount: number;
};

class SessionServiceStub {
  readonly profile = signal<ProfileState>({
    activeRunId: null,
    activeSquadName: 'Alpha Squad',
    unitCount: 4,
    squadCount: 2,
  });

  readonly hasActiveRun = signal(false);
  readonly shopUnlocked = signal(false);
  readonly academyUnlocked = signal(false);
  readonly profileData = signal<any>({
    active_run: null,
  });
  readonly activeSquad = signal<any>({
    id: 's1',
    name: 'Alpha Squad',
    is_active: true,
    unit_ids: ['u1', 'u2'],
    formation: [],
  });
  readonly units = signal<any[]>([
    { id: 'u1', name: 'Fang', unit_type_name: 'Bruiser', unit_type_slug: 'frontline_bruiser_t1', level: 3, tier: 1, max_hp: 10 },
    { id: 'u2', name: 'Moss', unit_type_name: 'Guardian', unit_type_slug: 'frontline_guardian_t1', level: 2, tier: 1, max_hp: 12 },
  ]);
}

describe('HomePageComponent', () => {
  let sessionService: SessionServiceStub;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [HomePageComponent],
      providers: [
        provideRouter([]),
        {
          provide: SessionService,
          useClass: SessionServiceStub,
        },
      ],
    }).compileComponents();

    sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
  });

  it('shows start-run copy when there is no active run', () => {
    const fixture = TestBed.createComponent(HomePageComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;

    expect(compiled.textContent).toContain('Start Run');
    expect(compiled.textContent).toContain('Pick a biome, commit the squad, and chase loot before the route turns ugly.');
    expect(compiled.textContent).toContain('Shop Locked');
    expect(compiled.textContent).toContain('Defeat The Farm to free the Tooth Collector.');
    expect(compiled.textContent).not.toContain('Academy');
    expect(fixture.componentInstance.primaryRoute()).toBe('/regions');
    expect(fixture.componentInstance.primaryLabel()).toBe('Start Run');
  });

  it('links current squad units to their details pages', () => {
    const fixture = TestBed.createComponent(HomePageComponent);
    fixture.detectChanges();

    const unitLink = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((debugElement) => debugElement.nativeElement.textContent?.includes('Fang'));

    expect(unitLink).toBeDefined();
    expect(unitLink!.injector.get(RouterLink).href).toContain('/warband/units/u1');
  });

  it('shows continue-run copy when an active run exists', () => {
    sessionService.hasActiveRun.set(true);
    sessionService.shopUnlocked.set(true);
    sessionService.academyUnlocked.set(true);
    sessionService.profile.set({
      activeRunId: 'run-7',
      activeSquadName: 'Alpha Squad',
      unitCount: 4,
      squadCount: 2,
    });
    sessionService.profileData.set({
      active_run: {
        run_id: 'run-7',
        region_name: 'The Farm',
        seed: 'abc123',
      },
    });

    const fixture = TestBed.createComponent(HomePageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const compiled = fixture.nativeElement as HTMLElement;
    const primaryImage = compiled.querySelector(
      '.home-proto__mission-card img',
    ) as HTMLImageElement;

    expect(compiled.textContent).toContain('Continue Run');
    expect(compiled.textContent).toContain('The crew is already out there. Jump back into the route, keep the formation alive, and get paid.');
    expect(compiled.textContent).toContain('Shop');
    expect(compiled.textContent).toContain('Academy');
    expect(compiled.textContent).toContain('Current Squad');
    expect(component.primaryRoute()).toBe('/run/map');
    expect(component.primaryLabel()).toBe('Continue Run');
    expect(primaryImage.getAttribute('src')).toContain('home_continue_run.jpg');
  });
});
