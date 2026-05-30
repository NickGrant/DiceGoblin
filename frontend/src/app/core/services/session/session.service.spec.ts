import { TestBed } from '@angular/core/testing';
import { ApiHttpService } from '../api-http/api-http.service';
import { ProfileService } from '../profile/profile.service';
import { SessionService } from './session.service';

describe('SessionService', () => {
  let service: SessionService;
  let apiHttp: jasmine.SpyObj<ApiHttpService>;
  let profileService: jasmine.SpyObj<ProfileService>;

  beforeEach(() => {
    apiHttp = jasmine.createSpyObj<ApiHttpService>('ApiHttpService', ['get', 'post']);
    profileService = jasmine.createSpyObj<ProfileService>('ProfileService', ['getProfile', 'invalidateProfileCache']);

    TestBed.configureTestingModule({
      providers: [
        SessionService,
        { provide: ApiHttpService, useValue: apiHttp },
        { provide: ProfileService, useValue: profileService },
      ],
    });

    service = TestBed.inject(SessionService);
  });

  it('hydrates session and profile state for authenticated users', async () => {
    apiHttp.get.and.resolveTo({
      ok: true,
      data: {
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
        squads: [{ id: '1', name: 'Alpha', is_active: true }],
        units: [{ id: 'u1' }],
        dice: [{ id: 'd1' }],
      },
    } as any);

    await service.refresh();

    expect(service.session().displayName).toBe('Nick');
    expect(service.profile().activeRunId).toBe('12');
    expect(service.profile().activeSquadName).toBe('Alpha');
    expect(service.hasActiveRun()).toBeTrue();
  });

  it('resets to defaults for unauthenticated sessions', async () => {
    apiHttp.get.and.resolveTo({ ok: false } as any);

    await service.refresh();

    expect(service.session().isAuthenticated).toBeFalse();
    expect(service.profile().activeRunId).toBeNull();
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
  });
});
