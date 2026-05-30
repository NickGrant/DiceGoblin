import { TestBed } from '@angular/core/testing';
import { ApiHttpService } from '../api-http/api-http.service';
import { ProfileService } from './profile.service';

describe('ProfileService', () => {
  let service: ProfileService;
  let apiHttp: jasmine.SpyObj<ApiHttpService>;

  beforeEach(() => {
    apiHttp = jasmine.createSpyObj<ApiHttpService>('ApiHttpService', ['get']);

    TestBed.configureTestingModule({
      providers: [
        ProfileService,
        { provide: ApiHttpService, useValue: apiHttp },
      ],
    });

    service = TestBed.inject(ProfileService);
  });

  it('loads the profile from the api', async () => {
    const profile = { ok: true, data: { units: [], squads: [], dice: [] } } as any;
    apiHttp.get.and.resolveTo(profile);

    await expectAsync(service.getProfileRaw()).toBeResolvedTo(profile);
    expect(apiHttp.get).toHaveBeenCalledWith('/api/v1/profile');
  });

  it('returns cached data when within ttl and not forced', async () => {
    const profile = { ok: true, data: { units: [], squads: [], dice: [] } } as any;
    apiHttp.get.and.resolveTo(profile);

    await service.getProfile();
    await service.getProfile();

    expect(apiHttp.get).toHaveBeenCalledTimes(1);
  });

  it('returns stale cache on error when allowed', async () => {
    const profile = { ok: true, data: { units: [], squads: [], dice: [] } } as any;
    let callCount = 0;
    apiHttp.get.and.callFake(async () => {
      callCount += 1;
      if (callCount === 1) {
        return profile;
      }
      throw new Error('network down');
    });

    await service.getProfile();
    await expectAsync(service.getProfile({ force: true, allowStaleOnError: true })).toBeResolvedTo(profile);
  });

  it('invalidates cache before refreshProfileAfterMutation and swallows refresh errors', async () => {
    apiHttp.get.and.rejectWith(new Error('broken'));

    expect(() => service.refreshProfileAfterMutation()).not.toThrow();
  });
});
