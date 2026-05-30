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
  readonly profileData = signal<any>({
    active_run: null,
    region_unlocks: [
      { region_id: '1', region_slug: 'the_farm' },
      { region_id: '2', region_slug: 'mountains' },
    ],
  });
}

describe('RegionsPageComponent', () => {
  let router: Router;
  let runService: RunServiceStub;

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
  });

  it('computes unlocked regions from profile data', () => {
    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    expect(component.unlockedRegionCount()).toBe(2);
    expect(component.regions().find((region) => region.slug === 'mountains')?.isUnlocked).toBeTrue();
    expect(component.regions().find((region) => region.slug === 'swamps')?.isUnlocked).toBeFalse();
  });

  it('starts a run and routes to the map for unlocked regions', async () => {
    spyOn(router, 'navigateByUrl').and.resolveTo(true);
    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();

    await fixture.componentInstance.startRegionRun('2', 'mountains');

    expect(runService.createRun).toHaveBeenCalledWith(2);
    expect(router.navigateByUrl).toHaveBeenCalledWith('/run/map');
  });
});
