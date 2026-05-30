import { TestBed } from '@angular/core/testing';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';
import { SquadService } from './squad.service';

describe('SquadService', () => {
  let service: SquadService;
  let apiHttp: jasmine.SpyObj<ApiHttpService>;
  let sessionService: jasmine.SpyObj<SessionService>;

  beforeEach(() => {
    apiHttp = jasmine.createSpyObj<ApiHttpService>('ApiHttpService', ['postWithCsrf', 'putWithCsrf', 'deleteWithCsrf']);
    sessionService = jasmine.createSpyObj<SessionService>('SessionService', ['refreshProfile']);
    sessionService.refreshProfile.and.resolveTo();

    TestBed.configureTestingModule({
      providers: [
        SquadService,
        { provide: ApiHttpService, useValue: apiHttp },
        { provide: SessionService, useValue: sessionService },
      ],
    });

    service = TestBed.inject(SquadService);
  });

  it('creates a team and refreshes the profile', async () => {
    apiHttp.postWithCsrf.and.resolveTo({ ok: true } as any);

    await service.createTeam('Alpha', true);

    expect(apiHttp.postWithCsrf).toHaveBeenCalledWith('/api/v1/teams', {
      name: 'Alpha',
      make_active: true,
    });
    expect(sessionService.refreshProfile).toHaveBeenCalledWith({ force: true });
  });

  it('updates a team and refreshes the profile', async () => {
    apiHttp.putWithCsrf.and.resolveTo({ ok: true } as any);
    const payload = { unit_ids: ['1'], formation: [] } as any;

    await service.updateTeam('5', payload);

    expect(apiHttp.putWithCsrf).toHaveBeenCalledWith('/api/v1/teams/5', payload);
  });

  it('deletes a team and refreshes the profile', async () => {
    apiHttp.deleteWithCsrf.and.resolveTo({ ok: true } as any);

    await service.deleteTeam('6');

    expect(apiHttp.deleteWithCsrf).toHaveBeenCalledWith('/api/v1/teams/6', {});
  });
});
