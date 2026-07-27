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
    dice: [{ id: 'd1' }],
    feature_unlocks: [],
    regions: [
      {
        id: '1',
        slug: 'the_farm',
        name: 'The Farm',
        recommended_level: 1,
        energy_cost: 3,
        is_enabled: true,
        is_unlocked: true,
        is_completed: false,
      },
      {
        id: '2',
        slug: 'mountains',
        name: 'Mountains',
        recommended_level: 2,
        energy_cost: 5,
        is_enabled: true,
        is_unlocked: false,
        is_completed: false,
      },
    ],
  });
  readonly activeSquad = signal<any>({
    id: 's1',
    name: 'Alpha Squad',
    is_active: true,
    unit_ids: ['u1', 'u2'],
    formation: [],
  });
  readonly squadUnitCap = signal(4);
  readonly units = signal<any[]>([
    {
      id: 'u1',
      name: 'Fang',
      unit_type_name: 'Bruiser',
      unit_type_slug: 'frontline_bruiser_t1',
      level: 3,
      tier: 1,
      max_hp: 10,
    },
    {
      id: 'u2',
      name: 'Moss',
      unit_type_name: 'Guardian',
      unit_type_slug: 'frontline_guardian_t1',
      level: 2,
      tier: 1,
      max_hp: 12,
    },
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

    expect(compiled.textContent).toContain('Start the run');
    expect(compiled.textContent).toContain('Each biome shapes the danger and rewards.');
    expect(compiled.textContent).toContain('Shop Locked');
    expect(compiled.textContent).toContain('Defeat The Farm to free the Tooth Collector.');
    expect(compiled.textContent).not.toContain('Academy');
    expect(fixture.componentInstance.primaryRoute()).toBe('/regions');
    expect(fixture.componentInstance.nextProgressionAction()).toEqual({
      eyebrow: 'Next Region',
      title: 'Clear The Farm',
      body: 'Recommended level 1; costs 3 energy.',
      route: '/regions',
      cta: 'Choose Region',
    });
    expect(compiled.textContent).toContain('2/4 squad slots filled');
    expect(compiled.textContent).toContain('0/2 regions cleared');
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
      dice: [{ id: 'd1' }],
      feature_unlocks: ['shop', 'academy'],
      regions: [
        {
          id: '1',
          slug: 'the_farm',
          name: 'The Farm',
          recommended_level: 1,
          energy_cost: 3,
          is_enabled: true,
          is_unlocked: true,
          is_completed: true,
        },
        {
          id: '2',
          slug: 'mountains',
          name: 'Mountains',
          recommended_level: 2,
          energy_cost: 5,
          is_enabled: true,
          is_unlocked: true,
          is_completed: false,
        },
      ],
    });

    const fixture = TestBed.createComponent(HomePageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const compiled = fixture.nativeElement as HTMLElement;
    const primaryImage = compiled.querySelector(
      '.home-proto__mission-card img',
    ) as HTMLImageElement;

    expect(compiled.textContent).toContain('Resume the push');
    expect(compiled.textContent).toContain('The Farm');
    expect(compiled.textContent).toContain('Shop');
    expect(compiled.textContent).toContain('Academy');
    expect(compiled.textContent).toContain('Current Squad');
    expect(component.primaryRoute()).toBe('/run/map');
    expect(component.nextProgressionAction().title).toBe('Continue The Farm');
    expect(component.nextProgressionAction().route).toBe('/run/map');
    expect(primaryImage.getAttribute('src')).toContain('home_continue_run.jpg');
  });

  it('prioritizes squad assignment when the active squad is empty', () => {
    sessionService.activeSquad.set({
      id: 's1',
      name: 'Alpha Squad',
      is_active: true,
      unit_ids: [],
      formation: [],
    });

    const fixture = TestBed.createComponent(HomePageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const compiled = fixture.nativeElement as HTMLElement;

    expect(component.nextProgressionAction().title).toBe('Assign raiders before launching');
    expect(component.nextProgressionAction().route).toBe('/warband');
    expect(compiled.textContent).toContain('0/4 squad slots filled');
  });

  it('prioritizes academy progression when a squad unit can promote', () => {
    sessionService.shopUnlocked.set(true);
    sessionService.academyUnlocked.set(true);
    sessionService.units.update((units) =>
      units.map((unit) => (unit.id === 'u1' ? { ...unit, promotion_eligible: true } : unit)),
    );

    const fixture = TestBed.createComponent(HomePageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;

    expect(component.nextProgressionAction().eyebrow).toBe('Promotion Ready');
    expect(component.nextProgressionAction().title).toBe('Fang can advance');
    expect(component.nextProgressionAction().route).toBe('/academy');
  });

  it('uses only the current backend objective for the home guidance action', () => {
    sessionService.profileData.update((profile) => ({
      ...profile,
      objectives: [
        {
          id: 'equip-first-die',
          title: 'Equip a die',
          description: 'Attach at least one die to a raider ability before pushing deeper.',
          status: 'complete',
          priority: 100,
          progress_current: 1,
          progress_target: 1,
          route: '/dice',
          meta: {},
        },
        {
          id: 'claim-first-victory',
          title: 'Claim a battle victory',
          description: 'Resolve and claim one victorious combat reward to grow the warband.',
          status: 'active',
          priority: 45,
          progress_current: 0,
          progress_target: 1,
          route: '/run/map',
          meta: {},
        },
      ],
    }));

    const fixture = TestBed.createComponent(HomePageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const compiled = fixture.nativeElement as HTMLElement;

    expect(component.nextProgressionAction()).toEqual({
      eyebrow: 'Current Objective',
      title: 'Claim a battle victory',
      body: '0/1 - Resolve and claim one victorious combat reward to grow the warband.',
      route: '/run/map',
      cta: 'Open Map',
    });
    expect(compiled.textContent).not.toContain('Equip a die');
    expect(compiled.textContent).not.toContain('Done');
    expect(compiled.textContent).toContain('Claim a battle victory');
    expect(compiled.querySelector('.home-proto__objective-list')).toBeNull();
  });
});
