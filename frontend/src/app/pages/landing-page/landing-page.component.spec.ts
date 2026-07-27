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
    const logo = compiled.querySelector('.landing-logo') as HTMLImageElement;
    const heading = compiled.querySelector('h1') as HTMLElement;
    const guideLink = compiled.querySelector('.landing-guide-link') as HTMLAnchorElement;

    expect(logo.getAttribute('src')).toContain('/assets/ui/branding/hero-logo.png');
    expect(heading.classList).toContain('visually-hidden');
    expect(guideLink.textContent).toContain('How to Play');
    expect(text).toContain('Dice Goblins');
    expect(text).toContain('Turn-based run battles');
    expect(text).toContain('How to Play');
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
