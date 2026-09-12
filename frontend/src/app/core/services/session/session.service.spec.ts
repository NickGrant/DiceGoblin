import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { ApiHttpService } from '../api-http/api-http.service';
import { ProfileService } from '../profile/profile.service';
import { SessionService } from './session.service';

describe('SessionService', () => {
  let service: SessionService;
  let apiHttp: jasmine.SpyObj<ApiHttpService>;
  let profileService: jasmine.SpyObj<ProfileService>;
  let router: jasmine.SpyObj<Router>;
  let registeredAuthRecovery: {
    refreshSession: (failingPath: string) => Promise<boolean>;
    handleSessionExpired: () => Promise<void>;
  };

  const authenticatedSession = {
    ok: true,
    data: {
      authenticated: true,
      csrf_token: 'csrf',
      user: { id: 4, display_name: 'Nick' },
    },
  } as any;

  beforeEach(() => {
    apiHttp = jasmine.createSpyObj<ApiHttpService>('ApiHttpService', ['get', 'post', 'registerAuthRecovery']);
    apiHttp.registerAuthRecovery.and.callFake((handlers) => {
      registeredAuthRecovery = handlers;
    });
    profileService = jasmine.createSpyObj<ProfileService>('ProfileService', ['getProfile', 'invalidateProfileCache']);
    router = jasmine.createSpyObj<Router>('Router', ['navigateByUrl']);
    router.navigateByUrl.and.resolveTo(true);

    TestBed.configureTestingModule({
      providers: [
        SessionService,
        { provide: ApiHttpService, useValue: apiHttp },
        { provide: ProfileService, useValue: profileService },
        { provide: Router, useValue: router },
      ],
    });

    service = TestBed.inject(SessionService);
  });

  it('hydrates only shell identity for authenticated users', async () => {
    apiHttp.get.and.resolveTo(authenticatedSession);

    await service.refresh();

    expect(service.session().displayName).toBe('Nick');
    expect(service.profile().activeRunId).toBeNull();
    expect(profileService.getProfile).not.toHaveBeenCalled();
  });

  it('resets to defaults for unauthenticated sessions', async () => {
    apiHttp.get.and.resolveTo({ ok: false } as any);

    await service.refresh();

    expect(service.session().isAuthenticated).toBeFalse();
    expect(service.profile().activeRunId).toBeNull();
  });

  it('treats ok session responses with authenticated false as anonymous', async () => {
    apiHttp.get.and.resolveTo({ ok: true, data: { authenticated: false } } as any);

    await service.refresh();

    expect(service.session().isAuthenticated).toBeFalse();
    expect(service.session().displayName).toBe('Visitor');
    expect(profileService.getProfile).not.toHaveBeenCalled();
  });

  it('shares the same in-flight initialize request across concurrent callers', async () => {
    let resolveSession: ((value: unknown) => void) | null = null;
    apiHttp.get.and.returnValue(
      new Promise((resolve) => {
        resolveSession = resolve;
      }) as any,
    );

    const first = service.initialize();
    const second = service.initialize();

    expect(apiHttp.get).toHaveBeenCalledTimes(1);
    resolveSession!(authenticatedSession);
    await Promise.all([first, second]);

    expect(apiHttp.get).toHaveBeenCalledTimes(1);
    expect(profileService.getProfile).not.toHaveBeenCalled();
  });

  it('stores a readable error when refresh throws', async () => {
    apiHttp.get.and.rejectWith(new Error('down'));

    await service.refresh();

    expect(service.error()).toBe('down');
    expect(service.isLoading()).toBeFalse();
  });

  it('logs out locally even if backend logout fails', async () => {
    apiHttp.post.and.rejectWith(new Error('nope'));

    await service.logout();

    expect(service.session().displayName).toBe('Visitor');
    expect(service.profile().softCurrency).toBe(0);
    expect(router.navigateByUrl).toHaveBeenCalledWith('/login');
  });

  it('logs in using auth-only refresh and enters /game', async () => {
    apiHttp.post.and.resolveTo({ ok: true, data: { authenticated: true } } as any);
    apiHttp.get.and.resolveTo(authenticatedSession);

    await service.loginWithLocalCredentials('player@example.test', 'secret-pass');

    expect(apiHttp.post).toHaveBeenCalledWith(
      '/api/v1/auth/local/login',
      { email: 'player@example.test', password: 'secret-pass' },
      { skipAuthRecovery: true },
    );
    expect(service.session().isAuthenticated).toBeTrue();
    expect(profileService.getProfile).not.toHaveBeenCalled();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/game');
  });

  it('registers using auth-only refresh and enters /game', async () => {
    apiHttp.post.and.resolveTo({ ok: true, data: { authenticated: true } } as any);
    apiHttp.get.and.resolveTo(authenticatedSession);

    await service.registerWithLocalCredentials('player@example.test', 'secret-pass', 'Nick');

    expect(apiHttp.post).toHaveBeenCalledWith(
      '/api/v1/auth/local/register',
      { email: 'player@example.test', password: 'secret-pass', display_name: 'Nick' },
      { skipAuthRecovery: true },
    );
    expect(profileService.getProfile).not.toHaveBeenCalled();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/game');
  });

  it('requests a local password reset token', async () => {
    apiHttp.post.and.resolveTo({
      ok: true,
      data: {
        message: 'If that account exists, a password reset is available.',
        reset_token: 'token',
      },
    } as any);

    const response = await service.requestPasswordReset('player@example.test');

    expect(apiHttp.post).toHaveBeenCalledWith(
      '/api/v1/auth/local/password-reset/request',
      { email: 'player@example.test' },
      { skipAuthRecovery: true },
    );
    expect(response.reset_token).toBe('token');
  });

  it('confirms a password reset using auth-only refresh and enters /game', async () => {
    apiHttp.post.and.resolveTo({ ok: true, data: { authenticated: true } } as any);
    apiHttp.get.and.resolveTo(authenticatedSession);

    await service.confirmPasswordReset('token', 'new-password');

    expect(apiHttp.post).toHaveBeenCalledWith(
      '/api/v1/auth/local/password-reset/confirm',
      { token: 'token', password: 'new-password' },
      { skipAuthRecovery: true },
    );
    expect(profileService.getProfile).not.toHaveBeenCalled();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/game');
  });

  it('registers auth recovery and silently checks only the session endpoint', async () => {
    apiHttp.get.and.resolveTo(authenticatedSession);

    const recovered = await registeredAuthRecovery.refreshSession('/api/v1/game/bootstrap');

    expect(recovered).toBeTrue();
    expect(apiHttp.get).toHaveBeenCalledWith('/api/v1/session', { skipAuthRecovery: true });
    expect(profileService.getProfile).not.toHaveBeenCalled();
  });

  it('clears session state when auth recovery confirms expiry', async () => {
    apiHttp.get.and.resolveTo({ ok: true, data: { authenticated: false } } as any);

    const recovered = await registeredAuthRecovery.refreshSession('/api/v1/game/bootstrap');

    expect(recovered).toBeFalse();
    await registeredAuthRecovery.handleSessionExpired();
    expect(service.session().isAuthenticated).toBeFalse();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/login');
  });
});
