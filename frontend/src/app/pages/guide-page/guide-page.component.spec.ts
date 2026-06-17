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

  it('renders the public guide sections', () => {
    const fixture = TestBed.createComponent(GuidePageComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('How Dice Goblins Works');
    expect(text).toContain('Available Unlocks');
    expect(text).toContain('Units');
    expect(text).toContain('How Promotion Works');
    expect(text).toContain('How Runs Work');
    expect(text).toContain('Sign In');
  });

  it('initializes session state on init', () => {
    const fixture = TestBed.createComponent(GuidePageComponent);
    fixture.detectChanges();

    expect(sessionService.initialize).toHaveBeenCalled();
  });

  it('shows a return action for authenticated players with an active run', () => {
    sessionService.session.set({
      isAuthenticated: true,
      displayName: 'Commander',
      userId: 5,
      csrfToken: 'token',
    });
    sessionService.hasActiveRun.set(true);

    const fixture = TestBed.createComponent(GuidePageComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Return to Run');
    expect(text).not.toContain('Sign In');
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
    });

    const fixture = TestBed.createComponent(GuidePageComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const acquiredCards = compiled.querySelectorAll('.guide-card--acquired');

    expect(compiled.textContent).toContain('Acquired unlocks are stamped below.');
    expect(acquiredCards.length).toBe(3);
    expect(compiled.textContent).toContain('Acquired');
  });
});
