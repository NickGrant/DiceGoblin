import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
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
  readonly academyUnlocked = signal(false);
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
    expect(compiled.textContent).toContain('Your neighbors surely have some good stuff...');
    expect(compiled.textContent).not.toContain('Academy');
    expect(compiled.textContent).not.toContain('Unlock in Shop');
    expect(fixture.componentInstance.primaryRoute()).toBe('/regions');
    expect(fixture.componentInstance.primaryLabel()).toBe('Start Run');
  });

  it('shows continue-run copy when an active run exists', () => {
    sessionService.hasActiveRun.set(true);
    sessionService.academyUnlocked.set(true);
    sessionService.profile.set({
      activeRunId: 'run-7',
      activeSquadName: 'Alpha Squad',
      unitCount: 4,
      squadCount: 2,
    });

    const fixture = TestBed.createComponent(HomePageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const compiled = fixture.nativeElement as HTMLElement;
    const primaryImage = compiled.querySelector('.dg-media-card--feature .dg-media-card__media') as HTMLImageElement;

    expect(compiled.textContent).toContain('Continue Run');
    expect(compiled.textContent).toContain('Back to "work".');
    expect(compiled.textContent).toContain('Academy');
    expect(component.primaryRoute()).toBe('/run/map');
    expect(component.primaryLabel()).toBe('Continue Run');
    expect(primaryImage.getAttribute('src')).toContain('home_continue_run.jpg');
  });
});
