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

  it('hydrates session and profile state for authenticated users', async () => {
    apiHttp.get.and.resolveTo({
      ok: true,
      data: {
        authenticated: true,
        csrf_token: 'csrf',
        user: { id: 4, display_name: 'Nick' },
      },
    } as any);
    profileService.getProfile.and.resolveTo({
      ok: true,
      data: {
        energy: { current: 5, max: 10 },
        currency: { soft: 40 },
        active_run: { run_id: 12 },
        squad_unit_cap: 4,
        feature_unlocks: [],
        unit_type_unlocks: ['support_banner_t1'],
        lineage_unlocks: [{ lineage_slug: 'basic_goblin', kin_slug: 'basic_goblin', name: 'Basic Goblin', description: '', is_default: true, is_implicit: true, unlocked_at: null }],
        squads: [{ id: '1', name: 'Alpha', is_active: true }],
        units: [{ id: 'u1' }],
        dice: [{ id: 'd1' }],
      },
    } as any);

    await service.refresh();

    expect(service.session().displayName).toBe('Nick');
    expect(service.profile().activeRunId).toBe('12');
    expect(service.profile().activeSquadName).toBe('Alpha');
    expect(service.squadUnitCap()).toBe(4);
    expect(service.unitTypeUnlocks()).toEqual(['support_banner_t1']);
    expect(service.lineageUnlocks().map((lineage) => lineage.lineage_slug)).toEqual(['basic_goblin']);
    expect(service.hasActiveRun()).toBeTrue();
  });

  it('resets to defaults for unauthenticated sessions', async () => {
    apiHttp.get.and.resolveTo({ ok: false } as any);

    await service.refresh();

    expect(service.session().isAuthenticated).toBeFalse();
    expect(service.profile().activeRunId).toBeNull();
  });

  it('treats ok session responses with authenticated false as anonymous', async () => {
    apiHttp.get.and.resolveTo({
      ok: true,
      data: {
        authenticated: false,
      },
    } as any);

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
    profileService.getProfile.and.resolveTo({ ok: true, data: { squads: [], units: [], dice: [], energy: { current: 0, max: 0 }, currency: { soft: 0 }, feature_unlocks: [], unit_type_unlocks: [], active_run: null } } as any);

    const first = service.initialize();
    const second = service.initialize();

    expect(apiHttp.get).toHaveBeenCalledTimes(1);

    resolveSession!({
      ok: true,
      data: {
        authenticated: true,
        csrf_token: 'csrf',
        user: { id: 4, display_name: 'Nick' },
      },
    });

    await Promise.all([first, second]);

    expect(apiHttp.get).toHaveBeenCalledTimes(1);
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

  it('logs in with local credentials and refreshes session state', async () => {
    apiHttp.post.and.resolveTo({ ok: true, data: { authenticated: true } } as any);
    apiHttp.get.and.resolveTo({
      ok: true,
      data: {
        authenticated: true,
        csrf_token: 'csrf',
        user: { id: 4, display_name: 'Nick' },
      },
    } as any);
    profileService.getProfile.and.resolveTo({
      ok: true,
      data: {
        energy: { current: 0, max: 10 },
        currency: { soft: 0 },
        active_run: null,
        squad_unit_cap: 4,
        feature_unlocks: [],
        unit_type_unlocks: [],
        squads: [],
        units: [],
        dice: [],
      },
    } as any);

    await service.loginWithLocalCredentials('player@example.test', 'secret-pass');

    expect(apiHttp.post).toHaveBeenCalledWith(
      '/api/v1/auth/local/login',
      { email: 'player@example.test', password: 'secret-pass' },
      { skipAuthRecovery: true },
    );
    expect(service.session().isAuthenticated).toBeTrue();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/');
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

  it('confirms a local password reset and refreshes session state', async () => {
    apiHttp.post.and.resolveTo({ ok: true, data: { authenticated: true } } as any);
    apiHttp.get.and.resolveTo({
      ok: true,
      data: {
        authenticated: true,
        csrf_token: 'csrf',
        user: { id: 4, display_name: 'Nick' },
      },
    } as any);
    profileService.getProfile.and.resolveTo({
      ok: true,
      data: {
        energy: { current: 0, max: 10 },
        currency: { soft: 0 },
        active_run: null,
        squad_unit_cap: 4,
        feature_unlocks: [],
        unit_type_unlocks: [],
        squads: [],
        units: [],
        dice: [],
      },
    } as any);

    await service.confirmPasswordReset('token', 'new-password');

    expect(apiHttp.post).toHaveBeenCalledWith(
      '/api/v1/auth/local/password-reset/confirm',
      { token: 'token', password: 'new-password' },
      { skipAuthRecovery: true },
    );
    expect(service.session().isAuthenticated).toBeTrue();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/');
  });

  it('registers auth recovery and can silently confirm a still-authenticated session', async () => {
    apiHttp.get.and.resolveTo({
      ok: true,
      data: {
        authenticated: true,
        csrf_token: 'csrf',
        user: { id: 4, display_name: 'Nick' },
      },
    } as any);
    profileService.getProfile.and.resolveTo({
      ok: true,
      data: {
        energy: { current: 5, max: 10 },
        currency: { soft: 40 },
        active_run: null,
        squad_unit_cap: 4,
        feature_unlocks: [],
        unit_type_unlocks: [],
        squads: [],
        units: [],
        dice: [],
      },
    } as any);

    const recovered = await registeredAuthRecovery.refreshSession('/api/v1/profile');

    expect(recovered).toBeTrue();
    expect(apiHttp.get).toHaveBeenCalledWith('/api/v1/session', { skipAuthRecovery: true });
  });

  it('clears session state when auth recovery confirms expiry', async () => {
    apiHttp.get.and.resolveTo({
      ok: true,
      data: {
        authenticated: false,
      },
    } as any);

    const recovered = await registeredAuthRecovery.refreshSession('/api/v1/profile');

    expect(recovered).toBeFalse();
    await registeredAuthRecovery.handleSessionExpired();
    expect(service.session().isAuthenticated).toBeFalse();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/login');
  });
});
