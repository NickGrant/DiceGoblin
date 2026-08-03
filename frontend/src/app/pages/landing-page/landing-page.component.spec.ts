import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { SessionService } from '../../core/services/session/session.service';
import { LandingPageComponent } from './landing-page.component';

describe('LandingPageComponent', () => {
  let sessionService: jasmine.SpyObj<SessionService>;

  beforeEach(() => {
    sessionService = jasmine.createSpyObj<SessionService>('SessionService', [
      'loginWithLocalCredentials',
      'registerWithLocalCredentials',
      'requestPasswordReset',
      'confirmPasswordReset',
    ]);
    sessionService.loginWithLocalCredentials.and.resolveTo();
    sessionService.registerWithLocalCredentials.and.resolveTo();
    sessionService.requestPasswordReset.and.resolveTo({
      message: 'If that account exists, a password reset is available.',
      reset_token: 'dev-token',
    });
    sessionService.confirmPasswordReset.and.resolveTo();
  });

  it('creates a login url for discord auth', async () => {
    await TestBed.configureTestingModule({
      imports: [LandingPageComponent],
      providers: [provideRouter([]), { provide: SessionService, useValue: sessionService }],
    }).compileComponents();

    const fixture = TestBed.createComponent(LandingPageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    expect(component.discordLoginUrl).toContain('/auth/discord/start');
  });

  it('renders a public guide link', async () => {
    await TestBed.configureTestingModule({
      imports: [LandingPageComponent],
      providers: [provideRouter([]), { provide: SessionService, useValue: sessionService }],
    }).compileComponents();

    const fixture = TestBed.createComponent(LandingPageComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const text = fixture.nativeElement.textContent as string;
    const brand = compiled.querySelector('.landing-nav__brand') as HTMLAnchorElement;
    const heading = compiled.querySelector('h1') as HTMLElement;
    const guideLink = compiled.querySelector('.landing-guide-link') as HTMLAnchorElement;
    const whim = compiled.querySelector('.landing-hero__character') as HTMLImageElement;

    expect(brand.textContent).toContain('Dice Goblins');
    expect(heading.textContent).toContain('Dice');
    expect(heading.textContent).toContain('Goblins');
    expect(guideLink.textContent).toContain('How to Play');
    expect(whim.getAttribute('src')).toContain('/assets/ui/units/animated/whim/base/frame_0.png');
    expect(text).toContain('Dice Goblins');
    expect(text).toContain('A chaos roguelite');
    expect(text).toContain('Goblin Lineages');
    expect(text).toContain('How to Play');
  });

  it('dismisses the portrait rotation reminder', async () => {
    await TestBed.configureTestingModule({
      imports: [LandingPageComponent],
      providers: [provideRouter([]), { provide: SessionService, useValue: sessionService }],
    }).compileComponents();

    const fixture = TestBed.createComponent(LandingPageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    expect(component.rotationReminderDismissed()).toBeFalse();

    component.dismissRotationReminder();

    expect(component.rotationReminderDismissed()).toBeTrue();
  });

  it('submits local sign in credentials', async () => {
    await TestBed.configureTestingModule({
      imports: [LandingPageComponent],
      providers: [provideRouter([]), { provide: SessionService, useValue: sessionService }],
    }).compileComponents();

    const fixture = TestBed.createComponent(LandingPageComponent);
    const component = fixture.componentInstance;
    component.email = 'player@example.test';
    component.password = 'secret-pass';

    await component.submitLocalAuth();

    expect(sessionService.loginWithLocalCredentials).toHaveBeenCalledWith(
      'player@example.test',
      'secret-pass',
    );
  });

  it('submits local registration credentials', async () => {
    await TestBed.configureTestingModule({
      imports: [LandingPageComponent],
      providers: [provideRouter([]), { provide: SessionService, useValue: sessionService }],
    }).compileComponents();

    const fixture = TestBed.createComponent(LandingPageComponent);
    const component = fixture.componentInstance;
    component.setAuthMode('register');
    component.email = 'player@example.test';
    component.password = 'secret-pass';
    component.displayName = 'Player';

    await component.submitLocalAuth();

    expect(sessionService.registerWithLocalCredentials).toHaveBeenCalledWith(
      'player@example.test',
      'secret-pass',
      'Player',
    );
  });

  it('requests a password reset and enters reset mode when a dev token is returned', async () => {
    await TestBed.configureTestingModule({
      imports: [LandingPageComponent],
      providers: [provideRouter([]), { provide: SessionService, useValue: sessionService }],
    }).compileComponents();

    const fixture = TestBed.createComponent(LandingPageComponent);
    const component = fixture.componentInstance;
    component.setAuthMode('forgot');
    component.email = 'player@example.test';

    await component.submitLocalAuth();

    expect(sessionService.requestPasswordReset).toHaveBeenCalledWith('player@example.test');
    expect(component.authMode()).toBe('reset');
    expect(component.resetToken).toBe('dev-token');
  });

  it('confirms a password reset token', async () => {
    await TestBed.configureTestingModule({
      imports: [LandingPageComponent],
      providers: [provideRouter([]), { provide: SessionService, useValue: sessionService }],
    }).compileComponents();

    const fixture = TestBed.createComponent(LandingPageComponent);
    const component = fixture.componentInstance;
    component.setAuthMode('reset');
    component.resetToken = 'dev-token';
    component.password = 'new-password';

    await component.submitLocalAuth();

    expect(sessionService.confirmPasswordReset).toHaveBeenCalledWith('dev-token', 'new-password');
  });
});
